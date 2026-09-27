# eOil ERP — analýza a návrh

Stav k **27. 9. 2026**: prvá reverzná analýza lokálnej kópie MRP K/S a návrh hraníc nového systému. Repo zatiaľ obsahuje dokumentáciu a nástroje na čítanie dát. Migračný import ešte neexistuje. Na základe následného zadania sa pripravuje prvý Yii3 implementačný balík.

**Potvrdená hranica:** `ProductHasPack`, `User` a dohodnuté kmeňové údaje zostávajú v eOil. ERP je samostatná aplikácia, napojená cez API. Konkrétne vlastníctvo ostatných entít je uvedené v návrhu; slovo „a pod.“ sa nepovažuje za hotovú špecifikáciu.

## Čítať v tomto poradí

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

## Čo už vieme

- Hlavná databáza má 387 tabuliek, 190 s dátami. Profilované sú aj ďalšie štyri databázy.
- Skontrolovaný vzorec zostatku sedí pri všetkých **75 831** kombináciách karta–sklad: počiatočný stav + platné účtované pohyby. Historické pohyby po ročnom prevode sa nesmú započítať znovu.
- Nové riešenie musí pokryť aj ponuky, opravné doklady, inventúry, úhrady, fiškálne operácie a prílohy. Presný rozsah musí potvrdiť prevádzka.
- SQL dokazuje existenciu dát a niektoré pravidlá. **Nedokazuje kompletný pracovný postup používateľov ani všetky funkcie aplikácie.** Prvá etapa nie je úplná špecifikácia na implementáciu.

[Opakovanie meraní](tools/research/README.md) · [Inventár všetkých tabuliek](docs/research/evidence/table-inventory.csv) · [Manifest zdrojov](docs/research/evidence/manifest.json)

[Vykonané overenie a jeho limity](docs/research/06-validation.md): 98 analytických dotazov, 11 Mermaid diagramov, kontrola zdrojových hashov a odkazov.

Dokumentácia obsahuje interné agregované prevádzkové údaje. Do Gitu nepatria databázy, osobné údaje, heslá, originálne doklady ani snímky zákazníckych údajov. Viditeľnosť vzdialeného repozitára zatiaľ nebola overená.

## Začatie vývoja

Používateľ vybral Yii3, vzhľad newadmin a lokálny Claude Code / Opus 5.5. [Návrh prvého balíka](docs/superpowers/specs/2026-09-27-foundation-design.md), [implementačný plán](docs/superpowers/plans/2026-09-27-foundation.md) a [zadanie pre Claude Code](docs/development/claude-first-slice.md). [ADR-004](docs/decisions/004-yii3-and-newadmin-theme.md) nahrádza otvorený výber frameworku.
