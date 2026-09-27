# eOil ERP — analýza, návrh a prvý Yii3 balík

Repo má dve oddelené časti:

1. **Spustiteľná lokálna ukážka** — prvý Yii3 implementačný balík (nižšie).
2. **Analýza MRP a návrh** — reverzná analýza a architektúra (od časti „Čítať v tomto poradí“).

## Spustiteľná lokálna ukážka (prvý balík a M2, 27. 9. 2026)

Samostatná Yii3 aplikácia (PHP 8.4, Docker) so vzhľadom eOil newadmin a **read-only katalógom balení**. Predvolene beží nad **ukážkovými (syntetickými) údajmi** bez prihlásenia. S `CATALOG_SOURCE=http` (M2) sa prihlasuje účtom eOil a číta skutočné balenia cez API eOil — zatiaľ iba lokálne proti eOil v MAMP. Nie je to produkčná administrácia: `APP_ENV=prod` odmietne prístup a ERP nič nezapisuje do eOil ani MRP.

```sh
docker compose build
docker compose run --rm --no-deps app composer install --no-interaction
docker compose up -d --wait
# http://127.0.0.1:8088/  ·  testy: docker compose run --rm --no-deps app vendor/bin/codecept run
```

| | Stav |
|---|---|
| Yii3 základ, layout newadmin, `/health` | implementované, overené testami a v prehliadači |
| Katalóg `/catalog`, detail `/catalog/{id}` | implementované nad **ukážkovými údajmi** |
| Prihlásenie cez eOil a katalóg z eOil API (M2) | implementované na vetvách `codex/eoil-integration` (ERP) a `codex/erp-api-identity` (eOil); overené testami a **lokálnym** end-to-end tokom proti MAMP; nenasadené |
| Sklady, migrácia | plánované (M3–M4) |

[Lokálne spustenie a testy](docs/development/local-setup.md) · [Návrh API kontraktu](docs/development/catalog-api-contract.md) · [Výsledky overenia M1](docs/development/foundation-validation.md) · [Výsledky M2](docs/development/m2-validation.md) · [ADR-005 prihlásenie](docs/decisions/005-eoil-sign-in.md) · [Pôvod prevzatých súborov](docs/development/theme-provenance.json)

## Analýza MRP a návrh

Stav k **27. 9. 2026**: prvá reverzná analýza lokálnej kópie MRP K/S a návrh hraníc nového systému. Analytická časť obsahuje dokumentáciu a nástroje na čítanie dát (`tools/research/`). Migračný import ešte neexistuje.

**Potvrdená hranica:** `ProductHasPack`, `User` a dohodnuté kmeňové údaje zostávajú v eOil. ERP je samostatná aplikácia, napojená cez API. Konkrétne vlastníctvo ostatných entít je uvedené v návrhu; slovo „a pod.“ sa nepovažuje za hotovú špecifikáciu.

### Čítať v tomto poradí

1. [Zistenia a mapa používaných funkcií](docs/research/02-current-state.md)
2. [Metóda, dôkazy a limity analýzy](docs/research/01-scope-and-method.md)
3. [Dátový model MRP](docs/research/03-data-model.md)
4. [Pravidlá a regresné scenáre](docs/research/04-rules-and-regression.md)
5. [Architektúra a vlastníctvo dát](docs/architecture/01-context-and-ownership.md)
6. [Doménový model ERP](docs/architecture/02-domain-model.md)
7. [API a sekvenčné diagramy](docs/architecture/03-api-and-sequences.md)
8. [Funkčné požiadavky](docs/specifications/functional-baseline.md)
9. [Automatizovaná migrácia](docs/migration/strategy.md)
10. [Otvorené otázky](docs/research/05-open-questions.md) a [ďalšie etapy](docs/plan.md)

Rozhodnutia: [ADR-001: identity v eOil](docs/decisions/001-eoil-master-data.md), [ADR-002: modulárna aplikácia a API](docs/decisions/002-modular-application.md), [ADR-003: migrácia zostatkov a histórie](docs/decisions/003-history-and-opening.md).

### Čo už vieme

- Hlavná databáza má 387 tabuliek, 190 s dátami. Profilované sú aj ďalšie štyri databázy.
- Skontrolovaný vzorec zostatku sedí pri všetkých **75 831** kombináciách karta–sklad: počiatočný stav + platné účtované pohyby. Historické pohyby po ročnom prevode sa nesmú započítať znovu.
- Nové riešenie musí pokryť aj ponuky, opravné doklady, inventúry, úhrady, fiškálne operácie a prílohy. Presný rozsah musí potvrdiť prevádzka.
- SQL dokazuje existenciu dát a niektoré pravidlá. **Nedokazuje kompletný pracovný postup používateľov ani všetky funkcie aplikácie.** Prvá etapa nie je úplná špecifikácia na implementáciu.

[Opakovanie meraní](tools/research/README.md) · [Inventár všetkých tabuliek](docs/research/evidence/table-inventory.csv) · [Manifest zdrojov](docs/research/evidence/manifest.json)

[Vykonané overenie a jeho limity](docs/research/06-validation.md): 98 analytických dotazov, 11 Mermaid diagramov, kontrola zdrojových hashov a odkazov.

Dokumentácia obsahuje interné agregované prevádzkové údaje. Do Gitu nepatria databázy, osobné údaje, heslá, originálne doklady ani snímky zákazníckych údajov. Viditeľnosť vzdialeného repozitára zatiaľ nebola overená.

## Začatie vývoja

Používateľ vybral Yii3, vzhľad newadmin a lokálny Claude Code / Opus 5.5. [Návrh prvého balíka](docs/superpowers/specs/2026-09-27-foundation-design.md), [implementačný plán](docs/superpowers/plans/2026-09-27-foundation.md) a [zadanie pre Claude Code](docs/development/claude-first-slice.md). [ADR-004](docs/decisions/004-yii3-and-newadmin-theme.md) nahrádza otvorený výber frameworku.
