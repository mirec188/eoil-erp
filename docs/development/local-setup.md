# Lokálne spustenie — eOil ERP (prvý balík)

Lokálny vývojový základ s **ukážkovými údajmi**. Nie je to nasaditeľná produkčná administrácia: nemá prihlásenie a `APP_ENV=prod` odmietne prístup.

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

`CATALOG_SOURCE=http` vyžaduje `CATALOG_API_BASE_URL` (HTTPS) a `CATALOG_API_TOKEN`; reálny eOil endpoint zatiaľ neexistuje — pozri [catalog-api-contract.md](catalog-api-contract.md).

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

`tests/bootstrap.php` vždy nastaví `APP_ENV=test` a `CATALOG_SOURCE=fixture` a zmaže `CATALOG_API_*`. Web suite (`tests/Web.suite.yml`) si v kontajneri spustí vlastné servery iba na loopbacku:

| Port | Účel |
|---|---|
| 8081 | aplikácia, test env, ukážkový katalóg (predvolený cieľ PhpBrowser) |
| 8092 | mock navrhovaného eOil API (`tests/Support/MockCatalogApi/router.php`) |
| 8082 | aplikácia s `CATALOG_SOURCE=http` na mock, syntetický token, timeout 1 s |
| 8083 | aplikácia s `APP_ENV=prod` + fixture (musí odmietnuť prístup) |

Oproti upstream `composer serve` na 8080 sú porty zmenené, aby sa testy dali spustiť aj cez `docker compose exec app …` vedľa bežiaceho dev servera.

### Kontrola v prehliadači

Voliteľný skript bez npm závislostí (Node ≥ 22 a lokálny Google Chrome) pri šírke 1366 a 390 px hlási console chyby, 4xx/failed a nelokálne požiadavky, horizontálny overflow, načítanie fontov Inter/Phosphor, mobilné menu, návrat zo detailu so zachovaním `q/page` a poradie fokusu:

```sh
node tools/browser-qa/cdp-smoke.mjs              # cieľ http://127.0.0.1:8088
```

Screenshoty ukladá do `runtime/browser-qa/` (git-ignorované).

## Štruktúra

```text
src/Catalog/                  port CatalogGateway, DTO, FixtureCatalogGateway, HttpCatalogGateway, nastavenia
src/Shared/Access/            DevelopmentAccessPolicy (dev/test áno; prod vždy nie — bez prihlásenia)
src/Web/Catalog/              list/detail action, šablóny, stavy 400/404/503
src/Web/Health/               GET /health
src/Web/Shared/               layout newadmin, asset bundles, politika prístupu a bezpečnostné hlavičky
assets/limitless/             vybrané súbory Limitless/Bootstrap/Phosphor/Inter (pôvod: theme-provenance.json)
assets/erp/                   custom.css a navigation.css (z newadmin) + ERP úpravy
config/common/di/catalog.php  výber zdroja katalógu podľa CATALOG_SOURCE
tests/                        Unit, Functional, Web (PhpBrowser), Console
tools/browser-qa/             kontrola v skutočnom prehliadači
```
