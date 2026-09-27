# Lokálne spustenie — eOil ERP (prvý balík a M2)

Lokálny vývojový základ. Predvolene **ukážkové údaje** bez prihlásenia; s `CATALOG_SOURCE=http` prihlásenie cez eOil a údaje z eOil (M2, iba lokálne). Nie je to nasaditeľná produkčná administrácia: `APP_ENV=prod` odmietne prístup.

## Predpoklady

- macOS s Docker Desktop (overené: Docker 29.2.1, Compose v5.1.0). PHP ani Composer na hoste netreba.
- Voľný port `127.0.0.1:8088`.

Runtime: `docker/php/Dockerfile` — oficiálny `php:8.4-cli-bookworm` (overené PHP 8.4.26) + `intl`, `opcache`, Composer 2. Build context je iba `docker/php/`, takže sa do Docker daemonu neposiela repozitár ani `.local/`. Kód sa pripája ako volume `./:/app`.

## Prvé spustenie

```sh
cd /Users/mirec/Sites/localhost/eoil-erp
docker compose build
docker compose run --rm --no-deps app composer install --no-interaction
docker compose up -d --wait
```

Otvoriť <http://127.0.0.1:8088/> (katalóg: <http://127.0.0.1:8088/catalog>, stav: <http://127.0.0.1:8088/health> → `{"status":"ok"}`).

Server je PHP built-in server (`php -S 0.0.0.0:8080 -t public public/index.php`, rovnaký mechanizmus ako upstream `./yii serve`, 4 workery) v kontajneri; Compose ho publikuje iba na `127.0.0.1:8088`. Zastavenie: `docker compose down`.

## Konfigurácia

Predvolené hodnoty sú v `compose.yaml` (`APP_ENV=dev`, `CATALOG_SOURCE=fixture`). Zmeny patria do git-ignorovaného `.env` vedľa `compose.yaml` (vzor: `.env.example`), napr. overenie produkčnej politiky:

```sh
APP_ENV=prod APP_DEBUG=false docker compose up -d   # každá cesta vrátane /health vráti 503 „Prístup odmietnutý“
curl -i http://127.0.0.1:8088/catalog
docker compose up -d --wait                         # späť na dev
```

V produkčnej konfigurácii kontajner zámerne nikdy nie je `healthy` (aj `/health` je odmietnutý), preto tam nepoužiť `--wait`. Bez nastaveného `CATALOG_SOURCE` aplikácia zlyhá bezpečne s HTTP 500 (s `APP_DEBUG=true` uvidíte dôvod).

`CATALOG_SOURCE=http` číta reálne údaje z eOil a vyžaduje prihlásenie cez eOil — pozri [M2: napojenie na lokálny eOil](#m2-napojenie-na-lokálny-eoil-mamp) a [catalog-api-contract.md](catalog-api-contract.md).

## Testy a kontroly

Všetko beží v izolovanom jednorazovom kontajneri (nezávisle od bežiaceho dev servera):

```sh
docker compose run --rm --no-deps app vendor/bin/codecept run            # celá sada
docker compose run --rm --no-deps app vendor/bin/codecept run Unit       # iba unit
docker compose run --rm --no-deps app composer validate --strict
docker compose run --rm --no-deps app vendor/bin/psalm --no-progress
docker compose run --rm --no-deps app vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --dry-run
docker compose run --rm --no-deps app vendor/bin/composer-dependency-analyser --config=composer-dependency-analyser.php
```

`tests/bootstrap.php` vždy nastaví `APP_ENV=test` a `CATALOG_SOURCE=fixture` a zmaže `CATALOG_API_TIMEOUT`, `EOIL_*` a `ERP_REDIRECT_URI`. Web suite (`tests/Web.suite.yml`) si v kontajneri spustí vlastné servery iba na loopbacku:

| Port | Účel |
|---|---|
| 8081 | aplikácia, test env, ukážkový katalóg (predvolený cieľ PhpBrowser) |
| 8092 | mock eOil: authorize, token endpoint s PKCE a Basic, katalóg s tokenom, scenáre `/__mock/*` (`tests/Support/MockCatalogApi/router.php`) — nie reálny eOil |
| 8082 | aplikácia s `CATALOG_SOURCE=http` a prihlásením cez mock, syntetický client secret, timeout 1 s |
| 8083 | aplikácia s `APP_ENV=prod` + fixture (musí odmietnuť prístup) |

Oproti upstream `composer serve` na 8080 sú porty zmenené, aby sa testy dali spustiť aj cez `docker compose exec app …` vedľa bežiaceho dev servera.

### Kontrola v prehliadači

Voliteľný skript bez npm závislostí (Node ≥ 22 a lokálny Google Chrome) pri šírke 1366 a 390 px hlási console chyby, 4xx/failed a nelokálne požiadavky, horizontálny overflow, načítanie fontov Inter/Phosphor, mobilné menu, návrat zo detailu so zachovaním `q/page` a poradie fokusu:

```sh
node tools/browser-qa/cdp-smoke.mjs              # cieľ http://127.0.0.1:8088
```

Screenshoty ukladá do `runtime/browser-qa/` (git-ignorované).

## M2: napojenie na lokálny eOil (MAMP)

Lokálny vývoj proti eOil vo worktree `codex/erp-api-identity`, obsluhovanom MAMP PRO (`DocumentRoot /Users/mirec/Sites/localhost`, port 8888, MySQL 8889). Produkcia sa nepoužíva; `APP_ENV=prod` je naďalej odmietnutý.

| Časť | Kde |
|---|---|
| ERP M2 | worktree `/Users/mirec/Sites/localhost/eoil-erp-m2`, <http://127.0.0.1:8089/> (M1 môže ďalej bežať na 8088) |
| eOil | worktree `/Users/mirec/Sites/localhost/eoil-erp-api-identity`, backend <http://localhost:8888/eoil-erp-api-identity/eoil-yii2/backend/web/> |

**1. eOil worktree** — git-ignorované lokálne súbory: `vendor` ako symlink na pôvodný checkout; `*-local.php` konfigurácie, `backend/web/index.php`, `frontend/web/index.php` a `yii` skopírované z pôvodného checkoutu (nie symlink, `__DIR__` by ukazoval do pôvodného kódu). Nastavenia ERP klienta v `eoil-yii2/backend/runtime/` (git-ignorované, práva 600):

```sh
RT=/Users/mirec/Sites/localhost/eoil-erp-api-identity/eoil-yii2/backend/runtime
umask 077
openssl rand -hex 32 > $RT/erp_client_secret      # client secret (zdieľa sa s ERP .env)
openssl rand -hex 32 > $RT/erp_jwt_secret         # podpisový kľúč, iný ako client secret aj MCP secret
echo 'http://127.0.0.1:8089/auth/callback' > $RT/erp_redirect_uris
echo 'Admin' > $RT/erp_allowed_roles              # presné názvy rolí eOil; prázdne = nikto
```

**2. ERP `.env`** v `eoil-erp-m2` (git-ignorovaný; hodnotu tajomstva doplní príkaz, nevypisovať):

```sh
BASE=http://localhost:8888/eoil-erp-api-identity/eoil-yii2/backend/web
cat > .env <<ENV
COMPOSE_PROJECT_NAME=eoil-erp-m2
ERP_HTTP_PORT=8089
APP_ENV=dev
APP_DEBUG=true
CATALOG_SOURCE=http
EOIL_API_BASE_URL=http://host.docker.internal:8888/eoil-erp-api-identity/eoil-yii2/backend/web
EOIL_AUTHORIZE_URL=$BASE/erp-auth/authorize
EOIL_CLIENT_ID=eoil-erp
EOIL_CLIENT_SECRET=$(cat $RT/erp_client_secret)
ERP_REDIRECT_URI=http://127.0.0.1:8089/auth/callback
ENV
docker compose run --rm --no-deps app composer install --no-interaction
docker compose up -d --wait
```

Prehliadač používa `localhost:8888` (authorize), kontajner ERP `host.docker.internal:8888` (token a API). Callback ide na `127.0.0.1:8089`.

**3. Prihlásenie**: <http://127.0.0.1:8089/> → „Prihlásiť sa cez eOil“. Ak nie ste prihlásený v eOil, eOil zobrazí výzvu; prihláste sa lokálnym účtom s rolou zo `erp_allowed_roles` (napr. cez `…/frontend/web/index.php/auth/login`) a potom v eOil kliknite „Už som prihlásený, pokračovať do ERP“ (login eOil sa po prihlásení nevracia späť sám).

**4. Automatická kontrola** (vypisuje iba stavové kódy a PASS/FAIL, nie údaje účtu):

```sh
EOIL_E2E_EMAIL=… EOIL_E2E_PASSWORD=… [EOIL_E2E_MRP=…] ./tools/e2e/eoil-signin-smoke.sh
```

**Testy eOil** (MAMP PHP 8.2, DB `eoil_test`, zápisy iba v rollback transakciách):

```sh
cd /Users/mirec/Sites/localhost/eoil-erp-api-identity/eoil-yii2
PHP=/Applications/MAMP/bin/php/php8.2.0/bin/php
$PHP vendor/bin/codecept run unit -c common/codeception.yml services/erp
$PHP vendor/bin/codecept run unit -c common/codeception.yml integration/ErpProductPackReaderDbTest
$PHP vendor/bin/codecept run functional -c backend/codeception.yml ErpAuthFlowCest
```

## Štruktúra

```text
src/Auth/                     prihlásenie cez eOil: nastavenia, PKCE, výmena kódu, AuthSession
src/Catalog/                  port CatalogGateway, DTO, FixtureCatalogGateway, HttpCatalogGateway, nastavenia
src/Shared/Access/            DevelopmentAccessPolicy (dev/test áno; prod vždy nie — M2 nie je nasadené ani posúdené)
src/Web/Auth/                 /login, /login/start, /auth/callback, POST /logout
src/Web/Catalog/              list/detail action, šablóny, stavy 400/404/503
src/Web/Health/               GET /health
src/Web/Shared/               layout newadmin, asset bundles, politika prístupu, povinné prihlásenie, bezpečnostné hlavičky
assets/limitless/             vybrané súbory Limitless/Bootstrap/Phosphor/Inter (pôvod: theme-provenance.json)
assets/erp/                   custom.css a navigation.css (z newadmin) + ERP úpravy
config/common/di/catalog.php  výber zdroja katalógu podľa CATALOG_SOURCE
tests/                        Unit, Functional, Web (PhpBrowser), Console
tools/browser-qa/             kontrola v skutočnom prehliadači
tools/e2e/                    lokálny end-to-end tok ERP ⇄ eOil (MAMP)
```
