# Prvý funkčný balík — Yii3 administrácia a hranica katalógu

## Cieľ a rozsah

Vytvoriť lokálne spustiteľnú samostatnú Yii3 aplikáciu s vizuálnym základom newadmin a čítaním katalógu cez vymeniteľný zdroj. Používateľ chce začať vývoj, pričom detailná analýza pokračuje po moduloch. Tento balík nesmie vytvoriť druhý produktový master.

Potvrdené: Yii3, samostatná appka, ProductHasPack/User v eOil, integrácia cez API, vzhľad newadmin, Claude Code/Opus 5.5. Návrh prvého rezu: prehľad a read-only katalóg s detailom, jasné ukážkové dáta a pripravený HTTP adapter. Reálne eOil ERP API ešte neexistuje ako overený kontrakt, preto jeho implementácia a autentifikácia patria do nasledujúceho balíka.

## Výsledok viditeľný používateľovi

- `http://127.0.0.1:8088/` otvorí eOil ERP s horizontálnou navigáciou a vizuálnym základom newadmin.
- Prehľad vysvetlí stav prvého balíka a ponúkne vstup do katalógu. Nezobrazuje vymyslené tržby, živé skladové sumy ani funkčné tlačidlá k neexistujúcim agendám.
- `/catalog` zobrazí názov, balenie, ID ProductHasPack, MRP referencie a stav. Vyhľadávanie `q` pracuje s názvom aj presným MRP reťazcom; stránkovanie má stabilný parameter `page`.
- `/catalog/{id}` zobrazí tie isté údaje a návrat na zoznam. Nevytvára ani neupravuje produkt.
- Ukážkový režim je na každej katalógovej stránke označený **„Ukážkové údaje“**. Dáta sú syntetické a nepoužijú reálne zákaznícke ani produktové exporty.
- Chyba vzdialeného zdroja je stav **„Katalóg sa nepodarilo načítať“**, nie prázdny zoznam. Prázdne vyhľadávanie bez výsledkov je odlišný stav.

## Technológia a rozloženie

PHP 8.4, oficiálny Yii3 web template, server-rendered PHP views, Limitless v4/Bootstrap 5/Phosphor. Zachovať upstream licenčné súbory a zaznamenať verzie/hash prevzatých aktív. Framework inicializovať podľa aktuálneho upstream kódu, nie Yii2 API.

Zdrojové oblasti:

```text
src/Web/HomePage/                      prehľad
src/Web/Catalog/                       action a templates katalógu
src/Web/Shared/Layout/Main/            Yii3 layout a asset bundle
src/Catalog/                          DTO, query, port a adaptery
assets/limitless/                      iba potrebné template assets
assets/erp/                            vlastné CSS/JS
config/                               konfigurácia prostredia a DI
tests/                                jednotkové a HTTP/browser overenie
docker/                               PHP runtime
compose.yaml                          izolovaný lokálny štart
docs/development/                     spustenie, kontrakt, výsledky
```

Presné existujúce template súbory má overený upstream snapshot v ADR-004. Zachovať existujúce `docs/`, `tools/research/` a git históriu; nevytvárať projekt prepisom celého repozitára.

## Katalógová hranica

Port `CatalogGateway` má `search(CatalogQuery $query): CatalogPage` a `get(int $id): ?ProductPackView`. Žiadne save/delete metódy.

`ProductPackView`: kladné `id` (ProductHasPack), `name`, `packLabel`, nullable `unit`, boolean `active`, `mrpNumbers` ako zoznam reťazcov. `CatalogQuery`: `term`, `page` od 1, pevné `pageSize=25`. `CatalogPage`: `items`, `total`, `page`, `pageSize`.

`FixtureCatalogGateway` poskytuje syntetické dáta, filtrovanie a stabilné poradie podľa ID. `HttpCatalogGateway` komunikuje cez PSR-18 klienta s konečným timeoutom, bez retry mutácií, bez priameho pripojenia k DB eOil. Kontrakt GET je návrh pre ďalší balík:

```json
{
  "items": [{"id": 101, "name": "Ukážkový olej", "packLabel": "1 l", "unit": "l", "active": true, "mrpNumbers": ["901.01"]}],
  "total": 1,
  "page": 1,
  "pageSize": 25
}
```

Kolekcia: `GET /erp-api/v1/product-packs?q=...&page=...&pageSize=25`; detail: `GET /erp-api/v1/product-packs/{id}`. Prázdne `items` je platný výsledok; 404 detailu znamená nenájdené. 401/403, 5xx, timeout, neplatný JSON alebo neplatné polia sú chyba integrácie. Odpoveď nesmie obsahovať heslá, zákaznícke údaje ani ERP autentifikačný stav.

Žiadny predvolený URL/token produkcie. Pri `CATALOG_SOURCE=http` musí byť explicitný base URL a token z prostredia. HTTP je dovolené iba v lokálnom test režime pre mock server; inak HTTPS. UI a logy neukazujú token, surovú odpoveď ani výnimku s hlavičkami. Kým eOil endpoint nie je vytvorený, adapter sa overuje na mock odpovediach; úspešný mock test nie je živé napojenie.

## Prístup a bezpečná hranica prvého balíka

Toto je **lokálny vývojový základ s ukážkovými dátami**, nie nasaditeľná produkčná administrácia. Compose publikuje iba `127.0.0.1:8088`. APP_ENV=prod s fixture zdrojom alebo bez budúceho autentifikačného adaptera musí skončiť odmietnutím štartu alebo prístupu. Nevytvárať trvalé obídenie loginu či lokálny register hesiel.

Prvý balík nemá skutočných prihlásených User, preto nepredstiera ich identitu. Integrácia identity/autorizácie je samostatná úloha pred napojením citlivých dát a pred vzdialeným nasadením. Názvy produktov a vstupy sa HTML escapujú. Žiadne priame čítanie ani zápis do eOil/MySQL alebo MRP.

## Akceptácia

1. Spustenie podľa README na Macu cez Docker, `GET /health` = 200 s minimálnym stavom bez secrets; `/` a katalóg sú dostupné na localhost.
2. Všetky potrebné CSS/JS/fonty sa načítajú lokálne bez 404 a UI má rovnaký vizuálny základ ako newadmin; nejde o kópiu jeho objednávkového správania.
3. Vyhľadávanie s diakritikou, prázdny výsledok, detail, neexistujúce ID, návrat a stránkovanie fungujú. `.01` zostáva v identite.
4. Chyby HTTP/JSON/timeout sa neprezentujú ako nula produktov; token nie je v UI/logoch.
5. Syntetický názov obsahujúci HTML sa zobrazí ako text. Nevznikajú JS event handlery z obsahu dát.
6. Ukážkový režim je viditeľný a nepoužiteľný v produkčnej konfigurácii. API adapter netvrdí napojenie na reálny eOil.
7. Testy a browser kontrola na desktope aj šírke 390 px majú konkrétny výsledok; opraviť console chyby a horizontálny overflow stránky.

## Ďalšie balíky

M2: skutočný read-only eOil API kontrakt, autentifikácia User a práva, pripojenie katalógu. M3: sklady, nemenný denník, príjem/výdaj a idempotencia. M4: migračný staging a porovnanie kópie MRP. Ďalšie agendy podľa analýzy; fiškálny/terminálový technický experiment prebieha včas, ale nie ako závislosť na vykreslení prvého katalógu.
