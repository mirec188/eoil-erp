# M2 — výsledky overenia (prihlásenie cez eOil a katalóg z eOil)

Dátum: 27. 9. 2026. Implementátor: Claude Code, `claude-opus-5-5`. Zadanie: `docs/development/claude-m2-handoff.md` (koordinátor). Návrh: [spec](../superpowers/specs/2026-09-27-m2-eoil-integration-design.md), [plán](../superpowers/plans/2026-09-27-m2-eoil-integration.md), [ADR-005](../decisions/005-eoil-sign-in.md). Bez commitu, pushu a nasadenia.

## Kde sú zmeny

| Strana | Vetva | Adresár | Východisko |
|---|---|---|---|
| ERP | `codex/eoil-integration` | worktree `/Users/mirec/Sites/localhost/eoil-erp-m2` | `03d262e` |
| eOil | `codex/erp-api-identity` | worktree `/Users/mirec/Sites/localhost/eoil-erp-api-identity` | `release/8` `de5a5c2` |

Hlavný checkout ERP som vrátil na `codex/yii3-foundation` (ten istý commit), pretože session na pozadí smie zapisovať iba do izolovaného worktree a vetva nemôže byť súčasne v dvoch checkoutoch. Necommitnuté súbory koordinátora v hlavnom checkoute (`claude-m2-handoff.md`, `claude-sessions.md`) ostali nedotknuté. Pôvodný checkout eOil (`release/8`) nebol prepnutý ani zmenený.

## Čo je čo

| Kategória | Obsah |
|---|---|
| **Implementované a overené lokálne proti reálnej lokálnej DB eOil** | prihlásenie ERP cez existujúcu session eOil (authorize → kód → výmena → session ERP), čítanie skutočných balení cez API, detail, stránkovanie, vyhľadávanie MRP čísla s `.01`, odhlásenie s CSRF |
| **Overené testami eOil nad `eoil_test` (rollback)** | zablokovaný používateľ, používateľ bez roly, odobratie roly a zablokovanie počas session (okamžite 403), replay kódu, zlý PKCE verifier, zlý client secret, neregistrované redirect URI, cudzí/expirovaný/podvrhnutý token vrátane tokenu agenta, mapovanie `.01`/`.00` |
| **Overené iba proti mocku eOil v ERP Web testoch** | správanie ERP pri 403/401 počas session, nedostupnom token endpointe, timeoute a neplatnom JSON katalógu, výzva „prihláste sa do eOil“ |
| **Neoverené / mimo M2** | HTTPS nasadenie, produkčné tajomstvá, viac serverov eOil (zdieľaná cache kódov), single logout, zmena loginu eOil, nezávislé bezpečnostné review |

## Testy a kontroly

ERP (worktree, `docker compose run --rm --no-deps app …`, PHP 8.4.26):

| Príkaz | Výsledok |
|---|---|
| `vendor/bin/codecept run` | **OK (249 tests, 806 assertions)** — Unit 196, Web 50, Functional 2, Console 1 |
| `vendor/bin/psalm --no-progress` | `No errors found!` |
| `vendor/bin/php-cs-fixer fix --dry-run` | `Found 0 of 103 files that can be fixed` |
| `vendor/bin/composer-dependency-analyser` | `No composer issues found` |
| `composer validate --strict` | valid |
| `php -l` | 105 súborov bez chyby |

Nové/zmenené ERP testy: `Unit/Auth/AuthSessionTest` (18), `Unit/Auth/EoilTokenClientTest` (19), `Unit/Catalog/CatalogSettingsTest` (30, vrátane nastavení prihlásenia), `Unit/Catalog/HttpCatalogGatewayTest` (38, 401/403, žiadna požiadavka bez prihlásenia), `Web/SignInCest` (14), `Web/CatalogHttpSourceCest` (6, po prihlásení), `Unit/DevelopmentAccessPolicyTest` (5).

eOil (worktree, MAMP PHP 8.2.0, DB `eoil_test`):

| Príkaz | Výsledok |
|---|---|
| `codecept run unit -c common/codeception.yml services/erp` | **OK (14 tests, 52 assertions)** |
| `codecept run unit -c common/codeception.yml integration/ErpProductPackReaderDbTest` | **OK (7 tests, 311 assertions)** |
| `codecept run functional -c backend/codeception.yml ErpAuthFlowCest` | **OK (13 tests, 87 assertions)** |
| `codecept run functional -c backend/codeception.yml` (celá sada) | 18 testov, 1 chyba: **existujúci** `LoginCest` (`Unknown column 'username'`), nesúvisí s M2 |
| `codecept run unit -c common/codeception.yml` (celá sada, pred a po zmene) | rovnaký výsledok bez aj s M2: 15 existujúcich zlyhaní (CartZeroQuantity 2, OrderCreateEmptyCart 4, PriceImport* 9) a pád na pamäti v `PricelistExportServiceDbTest` (XLSX); s M2 +7 úspešných testov, žiadna regresia |
| `php -l` zmenených súborov | bez chyby |

Po behoch v `eoil_test` nezostal žiadny testovací používateľ, rola ani syntetické MRP (overené počtom riadkov = 0). `openspec validate --strict` sa nedal spustiť — CLI `openspec` nie je nainštalované.

## Lokálny end-to-end tok

`tools/e2e/eoil-signin-smoke.sh` proti ERP `127.0.0.1:8089` (Docker) a eOil worktree v MAMP (`localhost:8888`, lokálna DB `eoil`, nie produkcia), s existujúcim lokálnym administrátorským účtom (údaje sa nevypisovali): **21/21 PASS, exit 0**.

Overené kroky: presmerovanie hosťa v ERP (302) → authorize s PKCE → výzva eOil pre neprihláseného (200, bez kódu) → prihlásenie formulárom eOil → kód na registrovaný callback → výmena kódu a prihlásenie v ERP → **replay callbacku odmietnutý (400)** → katalóg 25 riadkov, bez ukážkového odznaku, s odhlásením → detail 200, strana 2 200, neexistujúci detail 404 → **vyhľadanie reálneho MRP čísla s príponou `.0x`: 1 riadok, číslo zobrazené nezmenené** → odhlásenie bez CSRF 422, s CSRF 302 → katalóg znova vyžaduje prihlásenie.

Kontrola logov po E2E: log ERP kontajnera (107 riadkov) a `backend/runtime/logs/app.log` eOil (54 riadkov) obsahujú **0** výskytov client secret, podpisového kľúča, JWT, jednorazového kódu a hlavičky `Bearer`. Log ERP zaznamenal iba `eOil user <id> signed in to the ERP.`

Konfigurácia lokálneho behu: tajomstvá vygenerované `openssl rand` priamo do git-ignorovaných `backend/runtime/erp_*` (600) a git-ignorovaného `.env` ERP worktree; povolená rola `Admin`; žiadna zmena DB, hesiel ani rolí.

## Odchýlky a rozhodnutia

1. **ERP worktree namiesto prepnutia hlavného checkoutu** — vynútené izoláciou session na pozadí (pozri vyššie). M1 beží ďalej na `127.0.0.1:8088`, M2 na `127.0.0.1:8089` (`COMPOSE_PROJECT_NAME`, `ERP_HTTP_PORT`).
2. **`CATALOG_API_BASE_URL`/`CATALOG_API_TOKEN` nahradené** `EOIL_API_BASE_URL` a tokenom prihláseného používateľa; statický servisný token by obchádzal práva používateľa.
3. **HTTP bez TLS** povolené aj v `APP_ENV=dev`, ale iba pre `localhost`, `127.0.0.1`, `::1`, `host.docker.internal` (MAMP z kontajnera). Produkcia vyžaduje HTTPS a je aj tak odmietnutá.
4. **Produkcia ostáva odmietnutá** aj s prihlásením — tok nie je nasadený ani posúdený. Texty politiky to hovoria pravdivo.
5. **Limit `unit` zvýšený z 20 na 64 znakov** — názvy `Entity` v eOil majú až 29 znakov; pôvodný limit by zhodil celú stránku.
6. **Zobrazené údaje o používateľovi**: iba ID, meno a povolené role; e-mail ani iné osobné údaje API nevydáva.
7. **Login eOil sa nemenil**: po prihlásení v eOil treba v ERP/eOil pokračovať ručne („Už som prihlásený, pokračovať do ERP“).
8. Mock eOil v ERP testoch bol rozšírený o authorize/token a scenáre; ostáva jasne označený ako mock.

## Otvorené body

- Nezávislé bezpečnostné review toku (OAuth podmnožina je vlastná implementácia koncových bodov, nie knižnica).
- Pred produkciou: HTTPS, produkčné tajomstvá mimo súborov, `cookie_secure=1`, zdieľaná cache alebo tabuľka pre jednorazové kódy pri viacerých serveroch eOil, audit prihlásení v ERP DB.
- Single logout a návrat z loginu eOil; rozhodnúť, ktoré role eOil majú mať prístup do ERP (lokálne iba `Admin`).
- Vo worktree eOil sú git-ignorované kópie lokálnych konfigurácií a symlink `vendor`; frontend worktree preto používa `baseUrl` pôvodného checkoutu (login cez `index.php/auth/login`).
- `openspec validate --strict` nespustené (chýba CLI); spec delta je napísaná podľa `openspec/AGENTS.md`.
- Prehľadávanie názvov závisí od kolácie DB (`utf8_general_ci`); výkon `COUNT` pri veľkých výsledkoch nemeraný.

## Nezávislé overenie a rozhodnutie pred uložením M2

Koordinátor 27. 9. 2026 zopakoval ERP suite (249 testov / 806 assertions) a `composer validate --strict`: úspech. Zopakoval aj eOil testy `services/erp` (14/52), `ErpProductPackReaderDbTest` (7/311) a `ErpAuthFlowCest` (13/87) nad izolovanou `eoil_test`: všetko úspešné. Celú historickú regresnú sadu eOil znovu nespúšťal; vyššie uvedené existujúce zlyhania zostávajú hlásením implementátora.

Používateľ výslovne potvrdil prístup **iba pre Admin**. Poveril uložením M2 a pokračovaním bezpečnostným review, návratom po prihlásení a obnovou session. eOil vetva `codex/erp-api-identity` sa **teraz nemerguje do release**. Tieto následné úpravy nie sú dokončené týmto základným commitom; produkcia zostáva odmietnutá.

## M2.1 — cielené security review a dokončenie prihlásenia (27. 9. 2026)

Zadanie: `docs/development/m2-followup-handoff.md`. Implementátor: Claude Code, `claude-opus-5-5`, effort high. Zmeny vznikli v tých istých worktree nad základnými commitmi (`eoil-erp-m2`: `728b9cb`, `eoil-erp-api-identity`: `be8d1886`). Návrh pred implementáciou: sekcia M2.1 v [spec](../superpowers/specs/2026-09-27-m2-eoil-integration-design.md). Tento oddiel nahrádza otvorené body vyššie o návrate z loginu, rolách, OpenSpec a `index.php` login URL.

### Nálezy security review

| # | Závažnosť | Nález a dôkaz | Oprava | Regresný test |
|---|---|---|---|---|
| F1 | stredná | `ErpAuthCodeStore::consume` ignoroval výsledok `cache->delete()`; `FileCache::deleteValue` vracia výsledok `@unlink`. Pri zlyhaní mazania by sa vydal grant a záznam by ostal pre ďalšiu výmenu toho istého kódu (druhý token). | Grant sa použije iba pri úspešnom zmazaní (fail closed); pri nedostupnom zámku tiež odmietnutie. Najviac jedna úspešná výmena. | `ErpSecurityTest::testCodeIsRefusedWhenItCannotBeDeleted` (mutácia na pôvodnú logiku → test zlyhá), `…WhenTheLockIsNotAvailable` |
| F2 | nízka | Yii log targety pri chybe/varovaní vypisujú `$_POST`; `code` a `code_verifier` token requestu nie sú maskované (Basic client secret a `Authorization` sú maskované predvoleným `maskVars`; `REDIRECT_HTTP_AUTHORIZATION` backend nevytvára — overené sondou, ktorá vypísala iba prítomnosť kľúčov a bola hneď zmazaná). | Po načítaní sa hodnoty odstránia z `$_POST` (lokálne v akcii, bez zmeny globálnej konfigurácie logov). | `ErpSecurityTest::testOneTimeValuesAreRemovedFromPost`, `ErpAuthFlowCest::tokenRequestRemovesOneTimeValuesFromPost` |
| F3 | stredná (dev/test) | Yii debug modul, zapnutý lokálne v `backend/config/main-local.php` (šablóna `environments/dev`; produkčná šablóna ho nemá), ukladal do `backend/runtime/debug/*.data` Basic hlavičku s client secretom (3 súbory) a Bearer JWT (17 súborov) — zistené počtom zhôd, bez výpisu. | ERP koncové body vypnú debug log target pre svoju požiadavku (rovnako ako debug modul pre vlastné stránky). Lokálne debug dáta zmazané, lokálny client secret aj podpisový kľúč **rotované**. | `ErpSecurityTest::testDebugLogTargetIsDisabledForErpRequests`; dôkaz v MAMP: po ERP požiadavkách 0 debug záznamov, po bežnej stránke backendu 1 |
| F4 | nízka | Odpovede `erp-auth` a API nemali ochranu proti vloženiu do rámca. | `X-Frame-Options: DENY`, `Content-Security-Policy: frame-ancestors 'none'`, `nosniff`, `no-store`. | `ErpAuthFlowCest::responsesCannotBeFramedOrCached` |
| F5 | informácia | Viacero serverov eOil: `FileMutex` je lokálny zámok súboru, `FileCache` lokálna; zdieľaná cache sama nestačí na atomickú spotrebu kódu. | Opravená dokumentácia (kód, návrh, OpenSpec): mimo rozsahu je atomická spotreba v zdieľanom úložisku (DB `UPDATE … WHERE used = 0`, Redis GETDEL). Rozsah ostáva jeden server. | — (dokumentácia) |
| F6 | informácia | Návratová cesta ERP sa brala aj z ne-GET požiadaviek. | Návrat iba z GET/HEAD; ostatné metódy → `/`, nikdy sa neprehrávajú. | `RequireSignInMiddlewareTest::testPostIsNeverRenewedOrReplayed` |
| F7 | informácia | Access logy: MAMP (`LogFormat … "%r"`) zapisuje riadok požiadavky — authorize (state, PKCE challenge — nie tajomstvá) a login `?erp=<nonce>` (bez session bezcenný). ERP PHP server s workermi zapisuje iba „Accepted/Closing“ (overené testovacím `code=PROBE…`: 0 výskytov). Produkčný web server ERP by zapísal `code` z callbacku. | Bez zmeny kódu: kód je jednorazový, 60 s, viazaný na PKCE verifier v session ERP a client secret. Odporúčanie pre nasadenie: nelogovať query string `/auth/callback`. | — |
| F8 | informácia | GET `/login/start` možno vyvolať z cudzej stránky („login CSRF“): prihlási obeť iba jej vlastným účtom a prepíše rozpracovaný pokus. | Prijaté riziko, zdokumentované; state a PKCE bránia podstrčeniu cudzieho kódu. | `SignInCest::callbackWithoutMatchingStateIsRejected` |
| F9 | overené v poriadku | PKCE S256 + `hash_equals`; state 256 bit viazaný na session ERP, jednorazový; presné `redirect_uri`; lokálna návratová cesta; `session_regenerate_id(true)` pri prihlásení aj odhlásení (Yii3 `Session::regenerateId`); cookie `HttpOnly`, `SameSite=Lax`; expirácia ERP 15 s pred tokenom, JWT leeway 30 s; revokácia pri každej požiadavke; chybové odpovede bez vstupov a tajomstiev. | — | existujúce testy M2 |

### Dokončené kroky

- **Iba Admin** (potvrdené používateľom): `ErpConfig` prijme z konfigurácie iba `Admin`, iné názvy (Seller, Product, `admin`) ignoruje. Testy: `testOnlyAdminCanEverBeAllowed`, `inactiveAdminIsDenied`, `activeSellerAndProductUserIsDeniedEvenIfConfigured`. Role reálnych účtov sa nemenili (testovací používatelia iba v rollback transakciách `eoil_test`).
- **Automatický návrat z loginu eOil**: authorize pre hosťa uloží overenú URL pod jednorazovým nonce (5 min) a presmeruje na login s `?erp=`; formulár nonce zachová; po úspechu návrat na uloženú URL. Bez nonce, s cudzím nonce, s `return=` na cudziu doménu alebo pri zablokovanom účte sa login správa ako doteraz. Testy: `ErpLoginReturnCest` (7), `ErpSecurityTest` (4 testy nonce), `ErpAuthFlowCest::guestIsSentToEoilLoginWithOneTimeReturn`, `guestWithInvalidRequestIsNotSentToLogin`.
- **Obnova ERP prihlásenia**: po expirácii tokenu alebo 401 z eOil spustí ďalšia GET požiadavka raz automaticky štandardný authorize/PKCE tok s návratom na tú istú GET adresu; najviac raz za 60 s; nie po explicitnom odhlásení; POST sa neprehráva; zablokovanie/odobratie roly končí „Prístup zamietnutý“. Testy: `AuthSessionTest` (6 nových), `RequireSignInMiddlewareTest` (5), `SignInCest` (4 nové/upravené scenáre).
- **OpenSpec**: `npx --yes @fission-ai/openspec@1.13.2 validate add-erp-api-identity --strict --no-interactive` (používateľská cache npm, bez globálnej inštalácie) → `Change 'add-erp-api-identity' is valid`, exit 0 — pred aj po doplnení M2.1.
- **Changelog eOil** („Čo je nové“, sekcia Prepojenie s ERP): pridaná jedna veta podľa pravidiel `changelog-on-commit`; commit spraví koordinátor.

### Výsledky kontrol

ERP (`docker compose run --rm --no-deps app …`): **OK (263 tests, 844 assertions)** — Unit 207, Web 53, Functional 2, Console 1; Psalm `No errors found!`; PHP-CS-Fixer `0 of 104`; dependency analyser `No composer issues found`; `composer validate --strict` valid; lint 106 súborov.

eOil (MAMP PHP 8.2.0, `eoil_test`):

| Sada | Výsledok |
|---|---|
| `unit services/erp` | **OK (23 tests, 78 assertions)** |
| `unit integration/ErpProductPackReaderDbTest` | **OK (7 tests, 311 assertions)** |
| `functional backend ErpAuthFlowCest` | **OK (18 tests, 109 assertions)** |
| `functional backend` (celá) | 23 testov, 1 chyba = existujúci `LoginCest` (`Unknown column 'username'`) |
| `functional frontend ErpLoginReturnCest` | **OK (7 tests, 17 assertions)** |
| `functional frontend` (celá, pred a po) | pred: 49 testov, 15 chýb, 9 zlyhaní (šablónové About/Contact/Home/Signup/Login…); po: 56 testov, **rovnaká množina** chýb/zlyhaní + 7 nových úspešných |

Po behoch v `eoil_test` nezostal žiadny testovací používateľ ani e-mail. Nesúvisiace `common` sady (XLSX, PriceImport) sa znovu nespúšťali — zmena sa ich netýka. Konce riadkov `frontend/controllers/AuthController.php` (CRLF) sú zachované; diff je +10/−1.

### Lokálny end-to-end tok (MAMP ⇄ ERP 8089)

`tools/e2e/eoil-signin-smoke.sh` s lokálnym Admin účtom, reálnym MRP číslom s `.0x` a dočasným TTL 60 s (`backend/runtime/erp_access_token_ttl`, po teste zmazaný; predvolených 600 s): **28/28 PASS, exit 0**:

- hosť v ERP → authorize → login eOil s nonce → formulár nonce zachová → po prihlásení eOil sám vráti authorize → callback → pôvodná stránka ERP (bez tlačidla „Už som prihlásený“);
- katalóg 25 riadkov, detail, strana 2, 404, MRP s `.0x` nezmenené; nové ERP prihlásenie pri platnej session eOil bez formulára;
- po expirácii tokenu (47 s) sa ďalšia GET obnoví sama a ostane na tej istej stránke;
- po odhlásení z eOil a expirácii obnova vyžaduje skutočný login eOil a vráti do ERP;
- odhlásenie z ERP bez CSRF 422, s CSRF 302; potom **žiadne** automatické prihlásenie (`/login`).

Kontrola po E2E: debug adresár backendu bez záznamov z ERP koncových bodov; `app.log` eOil a log ERP kontajnera bez client secretu, podpisového kľúča, Basic hlavičky, JWT, hesla aj `code_verifier` (0 zhôd).

### Čo je čo po M2.1

| | |
|---|---|
| Overené lokálne na reálnej lokálnej DB eOil | návrat z loginu, obnova pri platnej aj neplatnej session eOil, odhlásenie bez automatického návratu, katalóg a `.0x` |
| Overené testami nad `eoil_test` | iba Admin, neaktívny Admin, Seller/Product, jednorazový kód pri zlyhanom mazaní, hlavičky, `$_POST`, návrat z loginu vrátane cudzieho nonce a zablokovaného účtu |
| Iba mock (ERP Web testy) | obnova končiaca 401 hneď po obnove (ochrana proti slučke), zamietnutie počas obnovy, token endpoint nedostupný |
| Limity pred produkciou | nezávislé bezpečnostné review, HTTPS a `cookie_secure=1`, produkčné tajomstvá mimo súborov, jeden server eOil (atomická spotreba kódu v zdieľanom úložisku pri viacerých), access log ERP bez query `/auth/callback`, single logout, audit prihlásení v ERP DB |

### Lokálne súbory mimo gitu (worktree eOil)

Pre funkčný návrat v lokálnom worktree som v jeho git-ignorovaných kópiách `common/config/params-local.php` (`loginUrl`, `baseFrontendUrl`, `baseBackendUrl`, `backendBaseUrl`) a `frontend/config/main-local.php` (`baseUrl`) zmenil cestu `/eoil-refacto/eoil-yii2` na `/eoil-erp-api-identity/eoil-yii2` a doplnil chýbajúci `frontend/config/codeception-local.php`. Pôvodný checkout eOil nie je zmenený.


### Nezávislé overenie koordinátorom pred uložením M2.1

Koordinátor 27. 9. 2026 skontroloval výsledný diff oboch aplikácií a zopakoval ERP suite: **263 testov / 844 assertions**, Psalm bez chýb a `composer validate --strict` úspešne. Nad izolovanou `eoil_test` zopakoval `services/erp` **23/78**, mapovanie katalógu **7/311**, backend `ErpAuthFlowCest` **18/109** a frontend `ErpLoginReturnCest` **7/17**; všetky úspešne. Celé historické eOil sady a lokálny E2E 28/28 v tomto overení znovu nespúšťal; ich výsledky vyššie sú hlásenie Claude Code, nie ďalší nezávislý beh. Kontrola whitespace pre eOil rešpektuje existujúce CRLF v AuthController (`core.whitespace=cr-at-eol`).

Toto je cielené lokálne review a regresné overenie, nie úplný audit pre produkčné nasadenie. Prístup zostáva iba pre Admin. Uloženie smeruje do `codex/eoil-integration` a `codex/erp-api-identity`; bez merge do release a bez nasadenia.
