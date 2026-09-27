# Katalóg balení — API kontrakt eOil → ERP

Stav (M2, 27. 9. 2026): **implementované v eOil na vetve `codex/erp-api-identity`** (worktree, nenasadené), overené testami eOil nad `eoil_test` a lokálnym end-to-end tokom ERP ↔ MAMP proti lokálnej DB eOil. Nie je to produkčné API: produkcia nie je nasadená ani bezpečnostne posúdená. Prihlásenie, ktoré vydáva prístupový token, opisuje [návrh M2](../superpowers/specs/2026-09-27-m2-eoil-integration-design.md). Web testy ERP naďalej používajú mock (`tests/Support/MockCatalogApi/router.php`).

Autorita: `ProductHasPack` zostáva v eOil. ERP iba číta; kontrakt neobsahuje zápisové operácie.

## Požiadavky

| | Kolekcia | Detail |
|---|---|---|
| Metóda a cesta | `GET /erp-api/v1/product-packs?q={q}&page={page}&pageSize=25` | `GET /erp-api/v1/product-packs/{id}` |
| Hlavičky | `Accept: application/json`, `Authorization: Bearer {prístupový token prihláseného používateľa}` | rovnaké |
| Parametre | `q` 0–200 znakov (UTF-8), `page` 1–10000, `pageSize` vždy 25 | `id` kladné celé číslo |

Kódovanie query je RFC 3986 (`q=olej%20%26%20filter&page=1&pageSize=25`). Token ide iba v hlavičke, nikdy v URL. Adaptér neposiela iné metódy ako GET, neopakuje požiadavky a nesleduje presmerovania.

Sémantika `q` v eOil: podreťazec spojenia výrobca + produkt + viskozita podľa kolácie DB (`utf8_general_ci`: bez ohľadu na veľkosť písmen a väčšinu diakritiky), **alebo** presná zhoda MRP čísla po normalizácii `MrpCislo::bare` (odstráni `.00`, zachová `.01`, `.02`). `987654.01` ≠ `987654`; `987653.00` = `987653`. Znaky `%` a `_` sa hľadajú doslova. Ukážkový zdroj ERP používa zjednodušenú verziu (bez `.00` pravidla).

## Odpovede

Kolekcia, HTTP 200, `Content-Type: application/json`:

```json
{
  "items": [
    {"id": 101, "name": "Ukážkový olej", "packLabel": "1 l", "unit": "l", "active": true, "mrpNumbers": ["901.01"]},
    {"id": 108, "name": "Ukážková sada", "packLabel": "1 sada", "unit": null, "active": false, "mrpNumbers": []}
  ],
  "total": 2,
  "page": 1,
  "pageSize": 25
}
```

Detail, HTTP 200: jeden objekt položky v rovnakom tvare (`{"id": 101, ...}`). HTTP 404 detailu = balenie neexistuje.

Položka:

| Pole | Typ | Pravidlo |
|---|---|---|
| `id` | integer | ID ProductHasPack, > 0; pri detaile sa musí rovnať požadovanému |
| `name` | string | neprázdny, max. 500 znakov; ERP ho vždy escapuje. eOil: `ProductHasPack::getName(true)`; balenie bez produktu (chyba dát, 2 v `eoil_test`) = „Balenie bez produktu v eOil“ |
| `packLabel` | string | neprázdny, max. 100 znakov. eOil: `Pack::getName()`, bez balenia `?` |
| `unit` | string \| null | kľúč musí existovať; `null` = neuvedená; max. 64 znakov (názvy `Entity` majú až 29) |
| `active` | boolean | eOil: `ProductHasPack.active = 1`; `0` aj `NULL` = neaktívne |
| `mrpNumbers` | array of string | zoznam (aj prázdny); reťazce bez okrajových medzier, max. 64 znakov; **nikdy čísla**. eOil: `ExternalId` typ 14, `MrpCislo::bare`, prázdne hodnoty vynechané; párovanie je 1:1, takže najviac jedna hodnota |

Extra polia sa ignorujú a nezobrazujú. Odpoveď nesmie obsahovať heslá, zákaznícke údaje ani stav autentifikácie ERP.

## Chyby

Prázdne `items` je platný výsledok (UI: „Žiadny produkt nezodpovedá vyhľadávaniu“). Všetko nasledujúce je **chyba integrácie**, nikdy nie prázdny katalóg — UI vráti HTTP 503 „Katalóg sa nepodarilo načítať“ s odkazom na opakovanie toho istého GET:

- 404 kolekcie, 3xx, 5xx a akýkoľvek iný stav okrem 200, 401, 403 (a 404 detailu);
- transportná chyba alebo timeout (predvolene 5 s, `CATALOG_API_TIMEOUT`, max. 30 s; connect timeout max. 3 s);
- iný `Content-Type` ako `application/json`, telo > 2 MB (číta sa najviac 2 MB + 1 bajt, telo sa streamuje), chyba čítania streamu, neplatný JSON (hĺbka max. 16);
- chýbajúce pole, nesprávny typ (napr. `"id": "101"`, `mrpNumbers: 901.01`), neplatná hodnota;
- `page`/`pageSize` iné ako v požiadavke; počet `items` iný ako `min(25, max(0, total − (page − 1) · 25))` — napr. prázdne `items`, hoci `total` tvrdí, že na strane položky sú.

Osobitne: **401** (`invalid_token`, `unauthorized`) = prihlásenie už neplatí → ERP zruší svoju session a pošle používateľa na prihlásenie s návratom na tú istú stránku. **403** (`forbidden`) = účet je zablokovaný alebo stratil rolu → ERP zruší session a zobrazí „Prístup zamietnutý“. eOil kontroluje `User.active` a rolu pri každej požiadavke.

Správa výnimky `CatalogUnavailable` je zložená iba z nášho textu a stavového kódu; neobsahuje token, hlavičky, surové telo ani pôvodnú výnimku klienta (nie je reťazená). Log obsahuje jeden riadok `warning`, napr. `Catalog list unavailable: Catalog API returned unexpected HTTP status 500.`

## Konfigurácia ERP

| Premenná | Význam |
|---|---|
| `CATALOG_SOURCE=http` | zapne adaptér a povinné prihlásenie cez eOil (inak `fixture`); bez predvolenej hodnoty |
| `EOIL_API_BASE_URL` | povinné; báza backendu eOil zo strany servera ERP (API aj token endpoint). HTTPS; HTTP iba `APP_ENV=dev` pre `localhost`/`127.0.0.1`/`host.docker.internal` a `APP_ENV=test` pre loopback |
| `EOIL_AUTHORIZE_URL`, `EOIL_CLIENT_ID`, `EOIL_CLIENT_SECRET`, `ERP_REDIRECT_URI` | prihlásenie, pozri `.env.example` a [lokálne spustenie](local-setup.md) |
| `CATALOG_API_TIMEOUT` | sekundy, (0, 30], predvolene 5 |

Statický `CATALOG_API_TOKEN` z prvého balíka bol odstránený: API sa volá výhradne tokenom prihláseného používateľa. Neexistuje predvolená URL žiadneho reálneho eOil systému ani čítanie lokálnych credential súborov.

## Otvorené

- Triedenie (zatiaľ podľa ID) a výkon `COUNT` pri veľkých výsledkoch; lokalizácia názvov.
- Správanie pri zmazanom balení: dnes 404 (riadok neexistuje), neaktívne balenie ostáva s `active=false`.
- Verzovanie (`/v1`) a spätná kompatibilita pri pridaní polí.
- Produkčné nasadenie: HTTPS, tajomstvá, zdieľaná cache jednorazových kódov pri viacerých serveroch.
