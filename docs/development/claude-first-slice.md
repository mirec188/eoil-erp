# Zadanie pre lokálny Claude Code / Opus 5.5

## Spustenie

Pracovný adresár: `/Users/mirec/Sites/localhost/eoil-erp`. Model musí byť explicitne `claude-opus-5-5`; nepoužiť automatický fallback na iný model. Ak model alebo prihlásenie nefunguje, oznámiť konkrétnu chybu a nevydávať vytvorenú konfiguráciu za spusteného agenta.

```sh
cd /Users/mirec/Sites/localhost/eoil-erp
claude --model claude-opus-5-5
```

Do session odovzdať nasledujúce zadanie:

> Implementuj prvý balík podľa CLAUDE.md, AGENTS.md, docs/superpowers/specs/2026-09-27-foundation-design.md a docs/superpowers/plans/2026-09-27-foundation.md. Používateľ požiadal začať postupný vývoj Yii3 ERP s rovnakým template ako eOil newadmin. Tvojou zodpovednosťou je iba Yii3 základ, admin layout a read-only katalógová hranica uvedená v pláne. Nie si sám v pracovnom prostredí: nevracaj cudzie zmeny. Meníš iba eoil-erp, pôvodný eoil-refacto je zdroj potrebných template súborov na čítanie. Existujúce analýzy a výskumné nástroje zachovaj. Dokonči balík vrátane testov a spustenia, zaznamenaj neistoty a otvorené body. Žiadne DB alebo produkčné zásahy, reálne platby, fiškalizácia, ďalší agenti, commit, push alebo deploy. Nepýtaj si znovu súhlas s každým rutinným krokom tohto balíka. Pri zásadnom rozpore alebo chýbajúcom prístupe uveď konkrétny blokátor. Na záver odovzdaj zoznam zmien, presné testy/výsledky a lokálnu URL; neoznačuj fixture adapter za živé napojenie na eOil.

Koordinátor musí výsledok nezávisle skontrolovať. Prvý úspešný modelový probe má doložiť skutočne použitý model; samotné `claude auth status` nie je dôkaz funkčného API volania.

## Stav prípravy 27. 9. 2026

Overená verzia CLI: 2.1.263. Probe s `--model claude-opus-5-5` skončil pred inferenciou: `OAuth session expired and could not be refreshed`. CLI tiež vypísalo `unrecognized_model`; oficiálna dokumentácia však uvedené ID pozná. Po obnovení prihlásenia treba znovu overiť výsledok; ak je problém s rozpoznaním aj potom, preveriť aktualizáciu CLI. Model sa nesmie svojvoľne nahradiť.

Používateľ potvrdil, že obnoví prihlásenie. Implementačná session zatiaľ nebola spustená. Tento stav sa aktualizuje až podľa skutočného výsledku behu.
