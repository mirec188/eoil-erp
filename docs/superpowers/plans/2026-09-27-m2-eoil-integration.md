# M2 — plán implementácie

Návrh: [2026-09-27-m2-eoil-integration-design.md](../specs/2026-09-27-m2-eoil-integration-design.md). Bez commit/push/deploy; review a integráciu robí koordinátor.

**Stav 27. 9. 2026:** všetky kroky implementované a overené lokálne; výsledky a otvorené body v [m2-validation.md](../../development/m2-validation.md). Čaká na nezávislé review koordinátora.

## Pracovné miesta

| Strana | Vetva | Adresár |
|---|---|---|
| ERP | `codex/eoil-integration` z `03d262e` | worktree `/Users/mirec/Sites/localhost/eoil-erp-m2` (session na pozadí vyžaduje izoláciu; hlavný checkout ostal na `codex/yii3-foundation`) |
| eOil | `codex/erp-api-identity` z `release/8` `de5a5c2` | worktree `/Users/mirec/Sites/localhost/eoil-erp-api-identity`; pôvodný checkout sa neprepína ani nemení |

## eOil

- [x] `common/services/erp/`: `ErpAccessPolicy` (active + allowlist rolí), `ErpAccessToken` (mint/verify JWT, vlastný secret a audience), `ErpAuthCodeStore` (jednorazové kódy v cache pod `FileMutex`), `ErpConfig` (client_id, presné redirect URI, secret, zatvorené predvolené hodnoty), `Pkce`, `ProductPackReader` (SELECT katalógu, MRP cez `MrpCislo::bare`).
- [x] `backend/controllers/ErpAuthController` (`GET erp-auth/authorize`, `POST erp-api/v1/auth/token`) a `ErpApiController` (`GET erp-api/v1/product-packs`, `/{id}`); JSON, CSRF iba mimo formulárov, bez presmerovaní na login pri API.
- [x] Params s bezpečnými predvolenými hodnotami (nič nepovolené), hodnoty z env alebo `backend/runtime/erp_*`.
- [x] Unit testy služieb; DB testy nad `eoil_test` v rollback transakciách: aktívny/blokovaný/bez role používateľ, mapovanie balení a `.01`, vyhľadávanie, stránkovanie.

## ERP

- [x] `src/Auth/`: nastavenia, PKCE/state, klient token endpointu (PSR-18, timeout, obmedzené telo), `AuthSession`, middleware chránených stránok.
- [x] Web: `/login`, `/login/start`, `/auth/callback`, `POST /logout`; stav prihláseného v layoute; 401/403 z API → zrušenie session.
- [x] `HttpCatalogGateway` s tokenom prihláseného používateľa; odstránenie `CATALOG_API_TOKEN`.
- [x] Mock eOil (authorize, token, product-packs, scenáre blokovania a odobratia práv) pre Web testy; Unit testy; statické kontroly.
- [x] Konfigurovateľný host port ERP, aby M1 (8088) a M2 (8089) mohli bežať súčasne.

## Overenie

- [x] Lokálny tok MAMP ↔ ERP Docker s existujúcim lokálnym účtom (bez výpisu údajov), čítanie reálnych balení z lokálnej DB cez API.
- [x] Dokumentácia spustenia, overené vs. mock, otvorené body.

## M2.1 — review a dokončenie (27. 9. 2026)

- [x] Návrh pred implementáciou v spec (sekcia M2.1).
- [x] Cielené security review oboch strán; nálezy F1–F9, opravy a regresné testy v [m2-validation.md](../../development/m2-validation.md).
- [x] Iba Admin v kóde a testoch (neaktívny Admin, Seller/Product).
- [x] Automatický návrat z loginu eOil (nonce v session, bez open redirectu).
- [x] Obnova ERP prihlásenia cez authorize/PKCE s ochranou proti slučke, bez automatiky po odhlásení.
- [x] OpenSpec zmena doplnená a zvalidovaná (`openspec validate --strict` cez npx), changelog „Čo je nové“.
- [x] Lokálne E2E s obnovou, testy a statické kontroly; lokálne tajomstvá rotované.
- [ ] Nezávislé review a uloženie koordinátorom; eOil sa nemerguje do release.
