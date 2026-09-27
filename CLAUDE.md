# eOil ERP — pokyny pre Claude Code

Najprv si prečítaj `AGENTS.md`. Platí aj pri implementácii.

## Aktuálne zadanie

Používateľ 27. 9. 2026 požiadal začať postupný vývoj popri dopĺňaní analýzy. Vybral **Yii3**, rovnaký CSS/JS template ako existujúci `newadmin` a implementáciu cez lokálny **Claude Code / Opus 5.5**. Nezamieňaj požiadavku na začatie vývoja s požiadavkou dokončiť celé ERP naraz.

Prvý balík:

- Spec: `docs/superpowers/specs/2026-09-27-foundation-design.md`
- Plán: `docs/superpowers/plans/2026-09-27-foundation.md`
- Handoff: `docs/development/claude-first-slice.md`

Implementuj prvý balík po malých overiteľných krokoch. Nepridávaj fakturáciu, skladové zápisy, fiškalizáciu, produkčné SSO ani kompletnú migráciu bez príslušného pracovného balíka. Chýbajúci kontrakt neodhaduj ako potvrdené existujúce API.

## Práca v prostredí

- Zapisuj do tohto repozitára. V pôvodnom `/Users/mirec/Sites/localhost/eoil-refacto` čítaj iba potrebné template súbory; nemeň jeho kód ani konfiguráciu.
- Nie si jediný pracovník v zdieľanom prostredí. Pred úpravou skontroluj stav; nevracaj cudzie zmeny a prispôsob sa im.
- `.local/` obsahuje súkromné analytické dáta a lokálne nástroje. Nie je to produktový zdrojový kód. Nečítaj MRP raw dáta, credentials ani zákaznícke doklady na úlohu, ktorá ich nepotrebuje.
- Nepoužívaj `--dangerously-skip-permissions`, nevypínaj ochrany a nespúšťaj ďalších agentov bez konkrétnej potreby a poverenia.
- Nevytváraj commit/push/deploy automaticky v odovzdanom implementačnom behu. Odovzdaj diff, výsledky testov, URL a otvorené body na nezávislé overenie. Integráciu spravuje koordinátor.
- Rozlišuj existujúce, implementované, overené a plánované. Pri ukážkových dátach to musí byť viditeľné aj v UI.

## Technické zásady

PHP 8.4 v izolovanom Docker prostredí pre prvý balík. Yii3 web template, server-rendered PHP views, Limitless v4/Bootstrap 5/Phosphor z newadmin. Žiadne prenesené Yii2 controllery, ActiveRecord alebo `yii.js`. Žiadne React/Vue SPA, queue ani broker v prvom balíku.

ProductHasPack/User sú autorita eOil; ERP má API klienta a referencie. Nikdy priamy zápis do eOil DB. Peňažné a množstvové hodnoty neskôr ako presné decimals, identifikátory MRP ako neprerušované reťazce vrátane `.01`.

Nevyhlasuj úlohu za hotovú na základe samotného vytvorenia súborov: aplikácia musí bežať, testy prejsť a výsledok v prehliadači byť skontrolovaný.
