# Yii3 administrácia a katalóg — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Používateľ vybral lokálny Claude Code / Opus 5.5; koordinátor vykoná nezávislé review. Nespúšťať ďalších agentov ani automatický push.

**Goal:** Spustiť samostatné lokálne ERP UI vo vzhľade newadmin a overiť read-only katalógovú hranicu bez vytvorenia druhého produktového kmeňa.

**Architecture:** Yii3 modulárna server-rendered aplikácia. Katalóg závisí na porte, ktorý má syntetický a HTTP adapter. Reálne eOil API, identity a skladové zápisy sú nasledujúce balíky.

**Tech Stack:** PHP 8.4 v Dockeri, Yii3 web template, Limitless v4/Bootstrap 5/Phosphor, Codeception/PHPUnit z template, PSR-18 HTTP klient s timeoutom.

**Spec:** [2026-09-27-foundation-design.md](../specs/2026-09-27-foundation-design.md)

**Stav 27. 9. 2026:** Tasky 1–4 implementované Claude Code (`claude-opus-5-5`), bez commitu; čaká sa na nezávislé review koordinátora. Výsledky, odchýlky od plánu a otvorené body: [foundation-validation.md](../../development/foundation-validation.md).

## Global Constraints

- ProductHasPack a User zostávajú autoritatívne v eOil; žiadny druhý produktový kmeň, priame DB pripojenie ani lokálne heslá User.
- PHP 8.4; server-rendered Yii3; bez Yii2, SPA, brokeru alebo queue.
- Vývojový server iba `127.0.0.1:8088`; fixture UI nie je produkčne prístupné.
- Ukážkové údaje sú syntetické a viditeľne označené. Reálne API ešte nie je implementované.
- MRP referencie sú reťazce; `.01` sa nesmie stratiť.
- Pôvodný eOil projekt je iba zdroj na čítanie; existujúce ERP docs/research sa neprepisujú.
- Celý implementačný balík ostáva v novej vetve `codex/yii3-foundation`; pracovný adresár a autor zmien sú uvedené v odovzdaní.

## Review Focus

1. HTML v názve alebo q: zobraziť text bez vykonania kódu; test v Task 3.
2. HTTP chyba/timeout alebo poškodený JSON: viditeľná chyba, nie nulový katalóg; test v Task 2 a 3.
3. Model/SKU identita: kladné ProductHasPack ID a zachovaná `.01`; test v Task 2.
4. Produkčná konfigurácia s fixture/bez identity adaptera: odmietnutý prístup, bez fallback login bypassu; test v Task 1.
5. Malý displej, diakritika a navigácia späť: čitateľný zoznam a zachované q/page; browser kontrola v Task 3.

## Task 1: Spustiteľný Yii3 základ a admin layout

**Files:**
- Importovať z overeného upstream template: `composer.json`, `config/`, `src/bootstrap.php`, `src/Environment.php`, `src/Shared/`, `src/Web/Shared/`, `src/Web/NotFound/`, `src/Web/HomePage/`, `public/index.php`, `yii`, `tests/`, `codeception.yml`, `LICENSE.md`.
- Vytvoriť/upraviť: `docker/php/Dockerfile`, `compose.yaml`, `.env.example`, `.gitignore`, `composer.lock`, `src/Web/Health/Action.php`, `config/common/routes.php`, `src/Web/Shared/Layout/Main/layout.php`, `src/Web/Shared/Layout/Main/MainAsset.php`.
- Assets: `assets/limitless/`, `assets/erp/custom.css`, `assets/erp/navigation.css`, `docs/development/theme-provenance.json`.
- Test: `tests/Web/FoundationCest.php`, `tests/Unit/DevelopmentAccessPolicyTest.php`.

**Interfaces:** GET `/health`, GET `/`; spoločný layout s navigáciou Prehľad/Katalóg; test/dev konfigurácia s explicitným fixture flagom.

- [x] Skontrolovať čistotu vetvy, prečítať AGENTS/CLAUDE/spec a vytvoriť feature vetvu bez resetovania analýzy. Overený template je lokálne v `.local/yii3-upstream` na SHA `19f5fdf9ddb784818d6f09653f53e63bc4e7dd63`; ak chýba, stiahnuť tento snapshot z oficiálneho repozitára. Pôvod a licenciu zachovať.
- [x] Zostaviť PHP 8.4 runtime s Composerom a rozšíreniami vyžadovanými uzamknutými závislosťami. Compose službu nazvať `app`, pracovný adresár `/app`, mapovanie `127.0.0.1:8088:8080`. Server musí v kontajneri počúvať na `0.0.0.0:8080`; host port zostáva loopback.
- [x] Nainštalovať závislosti, uložiť composer.lock a overiť `composer validate --strict`. Vývojové logy/vendor/runtime/secrets a publikované assets pridať do ignore; `.env.example` ostáva verzovaná.
- [x] Pridať test očakávaných stránok ešte pred nahradením upstream demo obsahu:

```php
public function foundation(\App\Tests\Support\WebTester $I): void
{
    $I->amOnPage('/');
    $I->seeResponseCodeIs(200);
    $I->see('eOil ERP');
    $I->seeLink('Katalóg', '/catalog');
    $I->amOnPage('/health');
    $I->seeResponseCodeIs(200);
    $I->see('ok');
}
```

- [x] Spustiť test, zaznamenať chýbajúce ERP UI ako očakávané zlyhanie. Pridať minimálny health action a layout. Health nesmie vypisovať env, token ani phpinfo.
- [x] Z eOil prevziať konkrétne súbory, nie celý template: `backend/web/new-template/template/html/layout_1/full/assets/css/ltr/all.min.css`; priečinok `template/assets/icons/phosphor/` vrátane fontov; `template/assets/js/bootstrap/bootstrap.bundle.min.js`; jQuery len ak ho použitý UI komponent skutočne potrebuje. Zachovať relatívne cesty fontov alebo ich deterministicky upraviť a overiť.
- [x] Z `backend/modules/newadmin/web/css/custom.css` a `backend/web/css/newadmin-nav.css` prevziať kompatibilné vizuálne úpravy. Upraviť horizontálnu navigáciu na ERP routes. Nepreniesť `OrdersAsset`, objednávkové skripty, chat, tracking ani Yii2 `yii.js`. Manifest uvádza zdrojový path, SHA revízie a SHA256 prevzatého súboru.
- [x] Testovať policy: dev+fixture je lokálna ukážka; prod+fixture odmietnuté; prod bez identity adaptera odmietnuté. Použiť explicitnú konfiguračnú policy testovanú mimo HTTP; vzdialenú bezpečnosť neopierať iba o text badge.
- [x] Spustiť smoke testy a overiť reálne asset HTTP odpovede. HTTP server musí po odovzdaní zostať dostupný alebo musí byť doložený presný príkaz na jeho spustenie.

## Task 2: Katalógový port a overený HTTP adapter

**Files:**
- Create: `src/Catalog/ProductPackView.php`, `CatalogQuery.php`, `CatalogPage.php`, `CatalogGateway.php`, `CatalogUnavailable.php`, `FixtureCatalogGateway.php`, `HttpCatalogGateway.php`.
- Create: `tests/Unit/Catalog/FixtureCatalogGatewayTest.php`, `HttpCatalogGatewayTest.php`, `ProductPackViewTest.php`.
- Modify: `config/common/di/application.php`, `.env.example`, `composer.json`/lock pre priamu HTTP závislosť.
- Document: `docs/development/catalog-api-contract.md` ako návrh, nie existujúce eOil API.

**Interfaces:**

```php
namespace App\Catalog;

interface CatalogGateway
{
    public function search(CatalogQuery $query): CatalogPage;
    public function get(int $id): ?ProductPackView;
}

// Constructor signatures; implement validation and readonly properties.
// ProductPackView(int $id, string $name, string $packLabel,
//     ?string $unit, bool $active, array $mrpNumbers)
// CatalogQuery(string $term = '', int $page = 1, int $pageSize = 25)
// CatalogPage(array $items, int $total, int $page, int $pageSize)
```

- [x] Pridať test identity a syntetického gateway pred implementáciou:

```php
$gateway = new \App\Catalog\FixtureCatalogGateway();
$page = $gateway->search(new \App\Catalog\CatalogQuery('901.01'));
self::assertCount(1, $page->items);
self::assertSame(['901.01'], $page->items[0]->mrpNumbers);
self::assertSame(101, $page->items[0]->id);
self::assertNull($gateway->get(999999));
```

- [x] Overiť zlyhanie a implementovať port/DTO/fixture: minimálne 30 syntetických položiek pre dve stránky, jedna neaktívna, jedna bez jednotky, jedna s HTML textom, jedna s diakritikou. ID 101 má MRP referenciu `901.01`. Názvy majú byť zjavne ukážkové, nie reálne exporty.
- [x] Otestovať `page=0`, záporné ID, neplatný pageSize a prázdne vyhľadávanie. Port má vyhadzovať validačnú výnimku pre neplatné vstupy; HTTP action ju neskôr mapuje na 400. Nenájdený platný detail je null.
- [x] HTTP adapter injektuje `Psr\Http\Client\ClientInterface`, request factory, base URL a secret token. Použiť priamu dependency na PSR-18 implementáciu, napríklad Guzzle; timeout/connect timeout nastaviť v DI. Pri 404 iba detail vráti null; ostatné zlyhania vytvoria `CatalogUnavailable` so sanitizovaným textom.
- [x] Použiť PSR-18 fake client alebo Guzzle MockHandler na testy: validná kolekcia, detail 404, 401, 500, poškodený JSON, chýbajúce id, mrpNumbers ako číslo, neplatná paginácia a transport timeout. JSON čítať cez JSON_THROW_ON_ERROR a validovať typy; žiadne konverzie chybnej odpovede na prázdny výsledok.
- [x] Overiť GET URL encoding pre `q=olej & filter`, stránku a stabilné pageSize=25. Bearer token ide iba do hlavičky, nikdy query/logu. Test musí použiť syntetický token a overiť, že sa nenachádza v texte vyhodenej výnimky.
- [x] DI prepína fixture/http podľa konfigurácie. Http vyžaduje explicitný URL/token; žiadny fallback na živú eOil URL ani lokálne credential súbory.
- [x] Zapísať kontrakt a príklady odpovedí. Explicitne uviesť, že endpoint bude implementovaný na strane eOil v M2 a tento balík ho overuje iba proti testovému transportu.

## Task 3: Katalógové stránky a používateľské stavy

**Files:**
- Create: `src/Web/Catalog/ListAction.php`, `DetailAction.php`, `list.php`, `detail.php`, `unavailable.php`.
- Modify: `config/common/routes.php`, layout navigácia, `assets/erp/custom.css`.
- Create: `tests/Web/CatalogCest.php`; podľa potreby HTTP testy action s injektovaným chybovým gateway.

**Interfaces:** GET `/catalog?q=&page=1`, GET `/catalog/{id}`; consumes `CatalogGateway` z Task 2. Úspech 200, neplatný query 400, neexistujúci detail 404, nedostupný zdroj 503.

- [x] Pridať browser/HTTP scenár pred implementáciou:

```php
public function preservesMrpIdentity(\App\Tests\Support\WebTester $I): void
{
    $I->amOnPage('/catalog?q=901.01&page=1');
    $I->see('Ukážkové údaje');
    $I->see('901.01');
    $I->click('Detail');
    $I->see('101');
    $I->see('901.01');
}
```

- [x] Overiť zlyhanie a implementovať list/detail s Yii3 rendererom a HTML escapingom. Query parametre validovať pred volaním gateway; limit dĺžky q=200 znakov. Form má GET metódu a stránkovanie zachováva q.
- [x] Rozlíšiť empty/error/loading podľa server-rendered toku: normálny GET nepotrebuje falošný JS loading. Error má jasnú správu a odkaz na opakovanie toho istého GET; 503 nezobrazuje raw odpoveď.
- [x] Pridať test HTML názvu aj q, diakritiky, prázdneho výsledku, druhej stránky, id=0/nečíselného ID, detailu 404 a zlyhania gateway. Návrat na zoznam zachová q/page cez bezpečné lokálne parametre, nie cez ľubovoľnú return URL.
- [x] Skontrolovať desktop a 390 px v skutočnom prehliadači: navigácia, formulár, detail, späť, stránkovanie, focus/tab, ikonové fonty, console chyby, asset 404. Tabuľka môže mať vlastný scroll, celá stránka nesmie pretiecť.

## Task 4: Odovzdanie a samostatné overenie

**Files:** README.md, `docs/development/local-setup.md`, `docs/development/foundation-validation.md`, relevantné statusy plánu.

- [x] Spustiť `composer validate --strict`, syntax kontrolu produktových PHP súborov a test suite cez Docker. Zaznamenať presné použité príkazy; ak sa upstream Codeception bootstrap upraví, uviesť dôvod a spustiteľný konečný postup.
- [x] Zaznamenať skutočný počet testov, výsledok, URL a screenshoty lokálne mimo súkromných dát. Nepísať „všetko hotové“, ak zostal neoverený HTTP alebo UI scenár.
- [x] README rozdelí „spustiteľná lokálna ukážka“ a „analýza MRP“. Analýza ostáva historicky pravdivá; aktuálny stav repo už nesmie tvrdiť, že neexistuje produktový kód, ak bol vytvorený.
- [x] Skontrolovať diff na secrets, vendor/runtime, pôvodné eOil zmeny a reálne exporty. Odovzdať koordinátorovi zoznam zmien, testy, URL a limity. Bez automatického commit/push.
- [x] Koordinátor vykonal nezávislé review a zopakoval významné testy; výsledok je pripravený na commit a push do schválenej feature vetvy. Dôkazy: `docs/development/foundation-validation.md`.

## Kontrola pokrytia

Spec akceptácia 1/2/6 → Task 1; 3/5/7 → Task 3; 4 → Task 2 a 3. Identity a hranice → Task 2. Spustiteľnosť a pravdivé odovzdanie → Task 4. Žiadna úloha tohto balíka nevyžaduje zmenu databázy eOil alebo MRP.
