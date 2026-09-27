# Katalóg balení — návrh API kontraktu eOil → ERP

Stav: **návrh pre M2, nie existujúce eOil API.** Endpoint bude implementovaný na strane eOil v balíku M2 (spolu s autentifikáciou User a právami). Prvý balík ERP má HTTP adaptér (`src/Catalog/HttpCatalogGateway.php`), ktorý je overený **iba** proti testovému transportu (Guzzle `MockHandler`) a lokálnemu mock serveru (`tests/Support/MockCatalogApi/router.php`). Úspešný mock test nie je živé napojenie na eOil.

Autorita: `ProductHasPack` zostáva v eOil. ERP iba číta; kontrakt neobsahuje zápisové operácie.

## Požiadavky

| | Kolekcia | Detail |
|---|---|---|
| Metóda a cesta | `GET /erp-api/v1/product-packs?q={q}&page={page}&pageSize=25` | `GET /erp-api/v1/product-packs/{id}` |
| Hlavičky | `Accept: application/json`, `Authorization: Bearer {token}` | rovnaké |
| Parametre | `q` 0–200 znakov (UTF-8), `page` 1–10000, `pageSize` vždy 25 | `id` kladné celé číslo |

Kódovanie query je RFC 3986 (`q=olej%20%26%20filter&page=1&pageSize=25`). Token ide iba v hlavičke, nikdy v URL. Adaptér neposiela iné metódy ako GET, neopakuje požiadavky a nesleduje presmerovania.

Navrhovaná sémantika `q` (overená iba na ukážkovom zdroji): obsahuje sa v názve bez ohľadu na veľkosť písmen a diakritiku, **alebo** sa presne rovná niektorému MRP číslu. MRP číslo sa porovnáva ako reťazec: `901.01` ≠ `901.1` ≠ `901`, `0904.01` ≠ `904.01`. Konečnú sémantiku potvrdí M2.

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
| `name` | string | neprázdny, max. 500 znakov; ERP ho vždy escapuje |
| `packLabel` | string | neprázdny, max. 100 znakov |
| `unit` | string \| null | kľúč musí existovať; `null` = neuvedená |
| `active` | boolean | |
| `mrpNumbers` | array of string | zoznam (aj prázdny); reťazce bez okrajových medzier, max. 64 znakov; **nikdy čísla** |

Extra polia sa ignorujú a nezobrazujú. Odpoveď nesmie obsahovať heslá, zákaznícke údaje ani stav autentifikácie ERP.

## Chyby

Prázdne `items` je platný výsledok (UI: „Žiadny produkt nezodpovedá vyhľadávaniu“). Všetko nasledujúce je **chyba integrácie**, nikdy nie prázdny katalóg — UI vráti HTTP 503 „Katalóg sa nepodarilo načítať“ s odkazom na opakovanie toho istého GET:

- 401, 403, 404 kolekcie, 3xx, 5xx a akýkoľvek iný stav okrem 200 (a 404 detailu);
- transportná chyba alebo timeout (predvolene 5 s, `CATALOG_API_TIMEOUT`, max. 30 s; connect timeout max. 3 s);
- iný `Content-Type` ako `application/json`, telo > 2 MB (číta sa najviac 2 MB + 1 bajt, telo sa streamuje), chyba čítania streamu, neplatný JSON (hĺbka max. 16);
- chýbajúce pole, nesprávny typ (napr. `"id": "101"`, `mrpNumbers: 901.01`), neplatná hodnota;
- `page`/`pageSize` iné ako v požiadavke; počet `items` iný ako `min(25, max(0, total − (page − 1) · 25))` — napr. prázdne `items`, hoci `total` tvrdí, že na strane položky sú.

Správa výnimky `CatalogUnavailable` je zložená iba z nášho textu a stavového kódu; neobsahuje token, hlavičky, surové telo ani pôvodnú výnimku klienta (nie je reťazená). Log obsahuje jeden riadok `warning`, napr. `Catalog list unavailable: Catalog API returned unexpected HTTP status 500.`

## Konfigurácia ERP

| Premenná | Význam |
|---|---|
| `CATALOG_SOURCE=http` | zapne adaptér (inak `fixture`); bez predvolenej hodnoty |
| `CATALOG_API_BASE_URL` | povinné; HTTPS, bez prihlasovacích údajov, query a fragmentu. HTTP iba pri `APP_ENV=test` a loopback hoste (mock) |
| `CATALOG_API_TOKEN` | povinné; tlačiteľné ASCII bez medzier; parametre s tokenom sú označené `#[SensitiveParameter]` a `__debugInfo()` ho skrýva |
| `CATALOG_API_TIMEOUT` | sekundy, (0, 30], predvolene 5 |

Neexistuje predvolená URL žiadneho reálneho eOil systému ani čítanie lokálnych credential súborov.

## Otvorené pre M2

- Autentifikácia: statický servisný token vs. token viazaný na prihláseného User; rotácia a rozsah práv.
- Presná sémantika vyhľadávania, triedenie (zatiaľ podľa ID) a limit `total` pri veľkých výsledkoch.
- Či `packLabel`/`unit` pochádzajú priamo z ProductHasPack alebo sa skladajú; lokalizácia.
- Správanie pri zrušenom (zmazanom) balení: 404 vs. položka s `active=false`.
- Verzovanie (`/v1`) a spätná kompatibilita pri pridaní polí.
