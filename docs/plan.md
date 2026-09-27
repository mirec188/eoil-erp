# Etapy a výstupné podmienky

**Aktualizácia 27. 9. 2026:** používateľ poveril začatím postupného vývoja cez Claude Code / Opus 5.5. Yii3 a vzhľad newadmin sú vybrané. [Prvý implementačný balík](superpowers/plans/2026-09-27-foundation.md) môže prebiehať súbežne s P1/P2; nerobí skladové ani finančné zápisy.

Bez kalendárneho odhadu, kým nie je potvrdený rozsah a dostupnosť obsluhy. Každá etapa má konkrétny artefakt a podmienku ukončenia.

| Etapa | Výstup | Stav / podmienka ukončenia |
|---|---|---|
| P0 — technický inventár | 5 profilov DB, schéma hlavnej firmy, agregáty, prvé pravidlá, diagramy a otázky | **Prvý baseline vytvorený**; sémantický audit všetkých funkcií ešte nie |
| P1 — pracovné procesy | Záznam predaja, príjmu, ponuky, faktúry, opravy, inventúry a uzávierok; roly, zariadenia, exporty | Každý proces má vstup, kroky, výsledné doklady, výnimky a dôkaz |
| P2 — identity a účtovné/sku pravidlá | eOil↔MRP crosswalk, jednotky, ceny, skladové ocenenie, typy dokladov a kódy | Aktívne položky bez nejednoznačnosti alebo explicitne schválené riešenie; potvrdený účtovný odovzdávací balík |
| P3 — architektúra a technické experimenty | Schválené ADR, Yii2/Yii3/Laravel porovnanie, DB voľba, sandbox fiškálneho/terminálového adaptera | Preukázané dokončenie a overenie neistého výsledku; realistický integračný kontrakt |
| P4 — automatizovaný migračný prototyp | Extract → staging → map → test import → reconciliation report | Opakovateľný beh, žiadne duplicity, kontrolné súčty; zatiaľ bez produkcie |
| P5 — vertikálne ERP workflow | Najprv identita + príjem + výdaj + audit, následne nákup/predaj/faktúra/pokladňa podľa rizika | Každý balík splní svoje FR/RG a má migračnú cestu |
| P6 — skúšobná prevádzka | Tieňové porovnanie, školenie a cutover rehearsal | Jeden zapisovateľ pre každý reálny skladový/fiškálny účinok; test nemá druhé reálne platby |
| P7 — prechod | Schválený finálny import, prepnutie autority, MRP archív, dohľad | Vysporiadané otvorené operácie, záloha/obnova overená, odsúhlasené zostatky a uzávierky |

Nie všetko musí čakať na dokončenie ostatných oblastí: inventár zariadení, mapovanie SKU a rekonštrukcia ocenenia môžu postupovať nezávisle. Produktová implementácia konkrétnej oblasti však čaká na potvrdené pravidlá tej oblasti. Finálny prechod vyžaduje uzavretie všetkých kritických väzieb.

## Najbližší konkrétny pracovný balík

1. Lokálne otvoriť jeden hotovostný predaj a príslušnú dennú uzávierku; zmapovať výdajku, platbu, pokladňu a fiškálne záznamy bez odosielania nových dokladov.
2. Porovnať denný a mesačný výstup používaný účtovníčkou; doplniť dátový model uzávierok a akceptačné súčty.
3. Zistiť presný model zariadenia a aktuálne skladové/cenové nastavenia MRP.
4. Získať súbežnú read-only eOil kópiu a pripraviť zoznam konfliktov ExternalId type 14. Žiadne automatické opravy párovania pred vyhodnotením.
5. Rozšíriť regresné scenáre o konkrétne lokálne referenčné prípady; do Gitu dať anonymizovaný výsledok, nie osobné údaje alebo originálne bločky.
