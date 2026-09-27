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
