# Rozsah a metóda

**Prvý analytický baseline, 27. 9. 2026.** Cieľom je zistiť, čo MRP reálne eviduje, ktoré pravidlá sú skryté v databáze a čo musí mať rozhodnutie o náhrade podložené. Nejde o certifikáciu úplnosti zdrojovej zálohy ani finálny zoznam všetkých UI funkcií.

## Zdroje a ich váha

| ID | Zdroj | Čo dokazuje / nedokazuje |
|---|---|---|
| U1 | Zadanie vlastníka | eOil drží ProductHasPack a User; samostatná aplikácia; API; automatizovaná migrácia; jednoduchá infraštruktúra |
| E1 | [Manifest](evidence/manifest.json), [inventár](evidence/table-inventory.csv) | Presná identita dodaných súborov a počty vo výskumnej kópii; nie živý stav produkcie |
| E2 | [usage.json](evidence/usage.json) | Agregáty dokladov, dátumov, väzieb, skladov a číselníkov; každý výsledok má SQL |
| E3 | [deep.json](evidence/deep.json) | Ročné stavy, úhrady, prílohy, kontrola zostatkov a vybrané nastavenia |
| E4 | [Schéma](evidence/DATA0003-columns.csv), [FK](evidence/DATA0003-foreign_keys.csv), [kľúče](evidence/DATA0003-keys.csv) | Fyzické typy a deklarované väzby; nie vždy vyplnené alebo využívané |
| E5 | [Register logiky](evidence/reviewed-logic.csv) a [inventár objektov](evidence/DATA0003-objects.csv) | Prečítaných 13 vybraných triggerov/procedúr; ďalšie ostávajú na audit |
| E6 | Zdroj eOil na revízii v manifeste | AR modely a aplikačné služby; neprofilovala sa aktuálna eOil databáza |
| E7 | Predchádzajúce lokálne spustenie MRP | MRP 6.71(010), otvorenie firmy 3, skladových kariet a vydaných faktúr; nie kompletný UI walkthrough |

Výskumné databázy vznikli z dodaných súborov v samostatnom adresári `research-20260927`. Všetkých päť má Firebird `MON$READ_ONLY=1`; oba PHP nástroje odmietajú zapisovateľnú databázu. Aplikačná pracovná kópia MRP je oddelená. Zmena režimu read-only mení hlavičku výskumnej kópie, preto jej hash nemá byť totožný s originálom. Manifest obsahuje hash originálu.

## Pokrytie databáz

| Databáza | Tabuľky / neprázdne | Úloha a obmedzenie |
|---|---:|---|
| DATA0003 | 387 / 190 | Hlavný predmet analýzy, firma v UI označená rokom 2025; obsahuje aj 2026 a historické dáta |
| DATA0001 | 387 / 194 | Staršia evidencia; nie automaticky nezávislá firma. Prekryv s DATA0003 treba zmerať pred zlúčením |
| DATA0002 | 372 / 152 | V lokálnom zozname testovacia firma; vylúčenie z migrácie musí byť explicitné |
| DATASPOL | 15 / 8 | Spoločné konfigurácie/registre; obsah nastavení a prihlasovacích údajov sa nepublikuje |
| DATALOCK | 5 / 3 | Pomocná databáza; obsah sa nesmie automaticky považovať za obchodné dáta |

`table-inventory.csv` zahŕňa zjednotenie názvov tabuliek zo všetkých zdrojov. `ABSENT` znamená chýbajúcu tabuľku, `0` existujúcu prázdnu tabuľku. `domain_hint` je navigačné zaradenie podľa názvu; nepotvrdzuje presný význam. Nezaradené tabuľky ostávajú otvorené. Stĺpce, väzby a databázové objekty sú kompletne exportované pre DATA0003; sémantická analýza úplná nie je.

## Interpretácia

- „Aktuálne dáta“ v tejto analýze znamenajú dátumy dokladov v rokoch 2025–2026. Dátum dokladu nemusí byť čas skutočnej obsluhy funkcie.
- Počet riadkov ≠ počet používateľských úkonov; log fiškalizácie ≠ počet tržieb; riadok pohybu ≠ riadok objednávky.
- Nevyplnené FK nemožno nahradiť automatickým párovaním podľa podobného textu.
- `CHAR` polia môžu obsahovať prázdny reťazec. Relevantné počty referencií používajú `TRIM(...)<>''`, nie iba `IS NOT NULL`.
- Model User v eOil dokazuje uloženie zákazníckych/fakturačných údajov; ešte neurčuje kompletný model právnickej osoby, dodávateľa ani oprávnení ERP.

Na dokončenie baseline chýba pozorovanie reprezentatívnych pracovných postupov, potvrdenie významu číselníkov a aktuálnych nastavení, súbežná kópia eOil pre krížové párovanie a inventár externých zariadení/exportov. Tieto medzery sú vedené ako [otvorené otázky](05-open-questions.md), nie doplnené odhadom.
