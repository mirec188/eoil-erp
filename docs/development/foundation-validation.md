# Prvý balík — výsledky overenia

Dátum: 27. 9. 2026. Implementátor: Claude Code, model `claude-opus-5-5`, vetva `codex/yii3-foundation`, pracovný adresár `/Users/mirec/Sites/localhost/eoil-erp`. Implementácia aj opravy review vznikli v uloženej Claude CLI session; koordinátor následne vykonal nezávislé overenie opísané nižšie.

## Čo je čo

| Kategória | Obsah |
|---|---|
| **Implementované a overené lokálne** | Yii3 aplikácia (PHP 8.4, Docker), layout newadmin, `/health`, katalóg (zoznam, vyhľadávanie, stránkovanie, detail, stavy 400/404/503), politika prístupu, bezpečnostné hlavičky |
| **Ukážkové údaje** | `FixtureCatalogGateway`: 32 syntetických položiek (ID 101–132), všetky s názvom „Ukážk…“. Žiadne dáta z eOil, MRP ani zákazníckych exportov. V UI označené „Ukážkové údaje“ na každej stránke |
| **Overené iba na testovom transporte / mocku** | `HttpCatalogGateway` podľa **návrhu** kontraktu. eOil endpoint neexistuje; žiadne živé napojenie nebolo skúšané ani tvrdené |
| **Plánované** | M2 eOil API + identita User a práva, M3 sklady, M4 migrácia |

## Prostredie

Docker 29.2.1, Compose v5.1.0 (macOS). Image `eoil-erp-php:dev` z `php:8.4-cli-bookworm` → PHP 8.4.26, Composer 2.10.3, rozšírenia `intl`, `opcache` (+ štandardné `curl`, `mbstring`, `dom`…). Uzamknuté: yiisoft/yii-http 1.1.1, yii-runner-http 3.2.1, router 4.0.2, router-fastroute 4.0.3, view 12.2.4, yii-view-renderer 7.4.1, assets 5.1.2, html 4.2.0, di 1.4.1, config 1.6.2, guzzlehttp/guzzle 7.15.5, guzzlehttp/psr7 2.13.1, httpsoft/http-message 1.1.6; dev: codeception 5.3.6, phpunit 11.5.56, psalm 6.18.1. `composer.lock`: 71 + 100 dev balíkov.

## Príkazy a výsledky

Všetky v jednorazovom kontajneri (`docker compose run --rm --no-deps app …`):

| Príkaz | Výsledok |
|---|---|
| `vendor/bin/codecept run` | **OK (189 tests, 592 assertions)** — Console 1, Functional 2, Unit 151, Web 35 (po review koordinátora; pôvodne 177/553) |
| `composer validate --strict` | `./composer.json is valid` |
| `php -l` na `src`, `config`, `public`, `tests` (bez generovaných), `yii` | 77 súborov bez chyby |
| `vendor/bin/psalm --no-progress` (errorLevel 1 z template) | `No errors found!` |
| `vendor/bin/php-cs-fixer fix --dry-run` (PER-CS) | `Found 0 of 75 files that can be fixed` |
| `vendor/bin/composer-dependency-analyser` | `No composer issues found` |

Testy podľa súboru:

| Súbor | Testov | Pokrýva |
|---|---|---|
| `Unit/EnvironmentTest` | 15 | upstream, bez zmeny |
| `Unit/DevelopmentAccessPolicyTest` | 6 | dev/test povolené; prod odmietnutý pre každý zdroj katalógu, bez identity parametra |
| `Unit/Catalog/CatalogSettingsTest` | 22 | explicitný zdroj, HTTPS, HTTP iba test+loopback, token nie je v správe/trace/`var_dump` |
| `Unit/Catalog/ProductPackViewTest` | 38 | ID > 0, `.01`/`0904.01` ako reťazce, MRP ako číslo odmietnuté, query/page validácia, `CatalogPage` bez pretečenia a s presným počtom položiek |
| `Unit/Catalog/FixtureCatalogGatewayTest` | 9 | `901.01` → ID 101, presná MRP zhoda, diakritika, 2 strany, stabilné poradie |
| `Unit/Catalog/HttpCatalogGatewayTest` | 38 | kolekcia, detail, 404, URL `q=olej%20%26%20filter`, Bearer iba v hlavičke, 24 chybových odpovedí vrátane 401/500/timeout/poškodeného JSON a nekonzistentného počtu položiek, obmedzené čítanie tela, sanitizovaná chyba streamu, bez retry, bez tokenu a raw tela v správe |
| `Unit/Web/CatalogRequestTest` | 23 | parsovanie `q/page/id` pred volaním gateway, bezpečné návratové parametre |
| `Functional/HomePageCest` | 2 | in-process runner |
| `Web/FoundationCest` | 6 | `/`, `/health`, lokálne assety, hlavičky, prod konfigurácia → 503 na 4 cestách |
| `Web/CatalogCest` | 20 | test z plánu, HTML v názve aj `q` vrátane úniku z atribútu, diakritika, prázdny výsledok, 2. strana, 400/404, návrat so zachovaním `q/page` |
| `Web/CatalogHttpSourceCest` | 6 | aplikácia s `CATALOG_SOURCE=http` proti mocku: zoznam, detail, 404, prázdny výsledok, 503 pre 500/401/JSON/položku/timeout, token nie je v HTML |
| `Web/NotFoundHandlerCest`, `Console/YiiCest` | 3 + 1 | upstream, upravené |

### Priame HTTP overenie (curl proti `127.0.0.1:8088`)

- `/health` 200 `{"status":"ok"}`; všetkých 6 CSS/JS a 7 fontov (Phosphor, Inter 300–700) 200 s lokálnou URL.
- Port publikovaný iba ako `127.0.0.1:8088` (`lsof`: `TCP 127.0.0.1:8088 (LISTEN)`).
- `APP_ENV=prod` + fixture: `/`, `/catalog`, `/catalog/101`, `/health` → 503 „Prístup odmietnutý…“. `APP_ENV=prod` + HTTPS API → 503 (táto verzia nemá prihlásenie). Bez `CATALOG_SOURCE` → 500 (fail closed).
- HTTP režim proti mocku: 5 chybových scenárov → 503; v tele odpovedí aj v logu aplikácie 0 výskytov tokenu, `RAW-UPSTREAM` a `Authorization`. Log obsahuje iba jednoriadkové sanitizované `warning` správy.

### Prehliadač

`node tools/browser-qa/cdp-smoke.mjs` — lokálny headless Google Chrome cez DevTools protocol, 11 stránok × 1366 px a 390 px (vrátane 400/404 stránok a HTML názvu):

- 0 console chýb/varovaní, 0 zlyhaných alebo 4xx asset požiadaviek (očakávaný stav hlavného dokumentu 400/404 vyňatý), 0 nelokálnych požiadaviek;
- horizontálny overflow stránky 0 px na všetkých stránkach v oboch šírkach;
- Inter a Phosphor načítané; mobilné menu (Bootstrap collapse) sa otvorí, `aria-expanded=true`;
- zoznam → Detail → „Späť na zoznam“ zachová `?q=ukážkový&page=2`;
- Tab poradie: logo → Prehľad → Katalóg → pole q → Hľadať → Detail…, každý prvok s viditeľným fokusom.

Nájdené a opravené počas kontroly: navbar odkazy nemali viditeľný fokus; tabuľka pri 390 px skrývala MRP/Stav/Detail za vodorovným scrollom (teraz riadky pod sebou pod 768 px); „Neaktívny“ mal modrú farbu témy `secondary`. Screenshoty sú lokálne v `runtime/browser-qa/` (nie v gite).

**Nekontrolované v prehliadači:** 503 stránka HTTP režimu (overená PhpBrowser testami a curl), Safari/Firefox, čítačka obrazovky.

## Opravy po review koordinátora (27. 9. 2026)

- **Pretečenie stránkovania:** `CatalogPage([], PHP_INT_MAX, 1, 25)->pageCount()` hádzalo `TypeError` (súčet pretiekol na float). Teraz delenie so zvyškom bez pretečenia; `page` 1–10000 a `pageSize` iba 25. Počet položiek sa musí presne rovnať `min(25, max(0, total − offset))`, takže prázdna stránka pri `total`, ktorý tvrdí existujúce položky, je chyba integrácie (503), nie prázdny katalóg. Uvedený prípad dnes vedie na kontrolovanú `InvalidArgumentException`.
- **Obmedzené čítanie tela:** adaptér číta najviac `MAX_BODY_BYTES + 1` bajtov po 64 KB a pri známej veľkosti nad limit nečíta vôbec; Guzzle klient v DI má `stream => true` a `read_timeout`, takže limit skutočne obmedzuje pamäť. Výnimka streamu → `CatalogUnavailable` „body could not be read“ bez pôvodnej správy. Web testy HTTP režimu (vrátane timeoutu) prechádzajú aj so streamovaním.
- **Produkcia bezpodmienečne odmietnutá:** odstránená vetva, ktorá povoľovala produkciu pre ľubovoľný reťazec `identityAdapter`, aj príslušný parameter v middleware a DI.
- **Texty pre používateľa:** prehľad je stručný obchodný úvod (katalóg, jasná poznámka o ukážkových údajoch, tlačidlo „Otvoriť katalóg“); stav implementácie a míľniky sú iba v dokumentácii. V katalógu „ID balenia v eOil“ (na mobile „ID eOil“), jednoduchšie vysvetlenie ukážkových údajov, pätička bez technických pojmov. Web testy kontrolujú, že sa na prehľade a v katalógu nezobrazujú pojmy ako ProductHasPack, adaptér či mock.

Po opravách: celá sada 189/592 OK, Psalm bez chýb, PHP-CS-Fixer 0 súborov, dependency analyser bez nálezov, `composer validate --strict` OK, lint 77 súborov. Celý browser skript nebol znovu spustený; UI zmeny sú iba texty a finálnu kontrolu v prehliadači robí koordinátor.

## Opravená chyba bezpečnosti počas implementácie

`Yiisoft\Html\Html::encode()` je určené pre textový obsah a **neescapuje úvodzovky** (`ENT_NOQUOTES`). Hodnoty v atribútoch (`value`, `href`, `aria-label`) preto používajú `Html::encodeAttribute()`. Test `htmlInQueryIsText` chybu zachytil; pridané testy úniku z atribútu v `q`, v návratových parametroch detailu a v `aria-label` s názvom produktu. Mutačná kontrola (dočasný návrat na `encode`) vyvolala 4 zlyhania. Pravidlo platí pre všetky budúce Yii3 šablóny.

## Odchýlky od plánu a dôvody

1. **`LICENSE.md` → `LICENSES/yiisoft-app-BSD-3-Clause.md`.** Koreňový `LICENSE.md` by tvrdil, že celé ERP je BSD licencované Yii Software. Licencia upstream template je zachovaná.
2. **Docker podľa plánu, nie upstream FrankenPHP.** `docker/php/Dockerfile` + `compose.yaml`, služba `app`, `/app`, `127.0.0.1:8088:8080`, server na `0.0.0.0:8080`. Build context je iba `docker/php/`, aby sa `.local/` nikdy neposielalo do Docker daemonu. PHP built-in server je vývojový server.
3. **DI katalógu v `config/common/di/catalog.php`** namiesto úpravy `application.php` — načíta ho glob `common/di/*.php`; oddelené od upstream definícií.
4. **Porty Web testov 8081/8082/8083/8092** namiesto upstream `composer serve` na 8080, aby testy išli aj vedľa bežiaceho dev servera; pridaný helper `tests/Support/Helper/HttpResponse.php`, lebo PhpBrowser nemá overenie hlavičiek. Data providery cez `Codeception\Attribute\DataProvider` (PHPUnit atribút Codeception loader nečíta).
5. **Neplatné ID detailu (`0`, `abc`, `0101`) → 400**, nie router 404 — podľa pravidla „validačná chyba → 400, neexistujúci platný detail → 404“.
6. **`pageSize` prijíma iba 25** (spec: pevné). `page` 1–10000 (ochrana pred pretečením offsetu).
7. **Inter lokálne** namiesto Google Fonts, ktoré používa eOil `LimitlessAsset` — spec vyžaduje lokálne fonty. Z Bootstrap bundle odstránený riadok `sourceMappingURL` (mapa v template neexistuje). Obe úpravy sú v `theme-provenance.json`.
8. **Odstránený upstream demo príkaz `hello`**; `./yii` funguje bez aplikačných príkazov.
9. **`composer.json`:** upstream `bump-after-update` po `composer update` zvýšil dolné hranice na nainštalované verzie. Pridané `guzzlehttp/guzzle` (PSR-18), `psr/http-client`, `ext-intl`; `guzzlehttp/psr7` v dev pre testy.
10. **Doplnené triedy nad rámec zoznamu súborov:** `CatalogSource`, `CatalogSettings`, `InvalidCatalogConfiguration`, `InvalidCatalogQuery`, `Web/Catalog/CatalogRequest`, šablóny `invalid.php`, `not-found.php`, `_source-notice.php`, `AccessDecision`, `AccessPolicyMiddleware`, `SecurityHeadersMiddleware` (CSP bez inline skriptov), `LimitlessAsset`, mock API a `tools/browser-qa/`.

## Otvorené body

- **Licencia Limitless** pre novú aplikáciu: template neobsahuje licenčný súbor; predpokladá sa licencia eOil — potvrdí vlastník.
- **eOil API (M2):** autentifikácia, sémantika vyhľadávania, zrušené balenia, verzovanie — `catalog-api-contract.md`.
- **Identita a práva (M2):** produkcia je odmietnutá bezpodmienečne; politika nemá žiadny parameter ani prepínač na povolenie. M2 musí doplniť skutočné overenie identity a práv.
- Upstream `SessionMiddleware` + CSRF nastavuje `PHPSESSID` aj na stránkach bez formulára na zápis. Ponechané pre budúci login; rozhodnúť v M2.
- Základný image je pripnutý tagom `php:8.4-cli-bookworm`, nie digestom.
- Vyhľadávanie v ukážkovom zdroji (bez diakritiky, presné MRP) je návrh, nie potvrdené správanie eOil.

## Nezávislé overenie koordinátorom

27. 9. 2026, po dokončení opráv review. CLI session `6f935d48-fe27-4cac-87bc-237ef7baec89`: skutočný model `claude-opus-5-5`, úvodný beh `xhigh`, opravy cez `--resume` s `medium`; oba skončili úspešne. Žiadna súbežná zapisujúca session nezostala bežať.

- Zopakovaný `docker compose run --rm --no-deps app vendor/bin/codecept run --no-colors`: **189 testov / 592 assertions, exit 0**.
- Zopakované `composer validate --strict`, Psalm, PHP-CS-Fixer dry run a dependency analyser: všetko exit 0; bez chýb alebo navrhovaných úprav. Syntax kontrola `src/config/public/tests` bez generovaných actorov: exit 0.
- Reálny interaktívny prehliadač: úvodná stránka, katalóg, presné MRP `901.01`, detail a návrat so zachovaním `q`; druhá strana má 7 z 32 položiek a návrat zachová `page=2`; diakritika `čistič`, prázdny výsledok a zrušenie vyhľadávania fungujú.
- Názov s HTML je text, nevznikol žiadny vložený obrázok. Vstup `" onfocus=alert(1)` zostal hodnotou poľa; atribút `onfocus` nevznikol. Klávesnica: pole → Hľadať → zrušenie vyhľadávania, viditeľný fokus.
- Desktop 1280 px aj mobil 390 px; pri mobile šírka dokumentu 390 px, MRP/stav/detail viditeľné, menu sa otvára. Finálne texty po review znovu skontrolované. Console errors/warnings: 0. Rozmer prehliadača bol po QA obnovený.
- MAMP na porte 8888 neprekáža ERP na `127.0.0.1:8088`. Existujúci newadmin presmeroval prehliadač na prihlásenie; porovnanie prihláseného pôvodného UI sa preto netvrdí. Pôvod vzhľadu je doložený zdrojmi a manifestom.
- Kontrolné súčty všetkých 14 prevzatých/odvodených assetov zodpovedajú manifestu. Pôvodný eOil nemá zmeny verzovaných súborov; jeho existujúce neverzované súbory zostali zachované.
- Kontrola nových súborov: žiadne databázy, `.local`, vendor, runtime výstupy ani nájdené tajné kľúče. Lokálne logy overenia ostávajú ignorované.

Nie je to overenie živého eOil API, prihlasovania, fiškalizácie, platieb ani skladových zápisov. Tieto časti tento balík neimplementuje. Stav HTTP chýb je overený automatickými HTTP testami, nie interaktívnym browser QA.
