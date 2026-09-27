# Funkčná špecifikácia — baseline 0.1

Toto je register požiadaviek odvodených z dát a zadania. „Navrhnuté“ neznamená schválenú náhradu všetkých MRP variantov. Referencie BR/RG sú v [regresnom katalógu](../research/04-rules-and-regression.md); zdroje E1–E7 v [metóde](../research/01-scope-and-method.md).

| ID | Požiadavka | Pôvod / stav | Overenie |
|---|---|---|---|
| FR-01 | Každá operatívna skladová položka odkazuje na kanonický eOil ProductHasPack. ERP nespravuje nezávislý produktový kmeň. | Používateľ, potvrdené | RG-01, RG-14, RG-16 |
| FR-02 | Používateľská identita zostáva v eOil; ERP vyhodnocuje vlastné oprávnenia na citlivé úkony. | Používateľ + návrh práv | Matica rolí, zakázané operácie aj cez API |
| FR-03 | Príjem, výdaj, vnútorná spotreba, reklamný výdaj a preskladnenie majú vlastný dôvod a audit. | E2 movement_types | BR-02, RG-03, RG-08 |
| FR-04 | Stav fyzický, rezervovaný a disponibilný sú odlíšené; záporné stavy majú schválenú politiku. | E2 + návrh | RG-05; rozhodnutie Q-03 |
| FR-05 | Inventúra uchová pôvodný stav, spočítané množstvo, schválenie a rozdielový pohyb. | E1 inventúry + návrh | RG-09 |
| FR-06 | Existuje proces ponuky a nadväzujúcej objednávky; stavy a kopírovanie riadkov sa potvrdia na MRP. | E2/E5 | RG-07 |
| FR-07 | Nákupná objednávka, fyzický príjem a prijatá faktúra sú samostatné, prepojiteľné doklady. | E1/E4 | Čiastočný príjem a rozdiel v cene bez druhého príjmu |
| FR-08 | Fakturácia podporí reálne používané typy dokladov, číselné rady, dane, zaokrúhlenie a opravy. | E1/E2; podrobnosti otvorené | RG-06, referenčné doklady so sadzbami 0/5/19/20/23 |
| FR-09 | Úhrady podporia čiastočné a viacnásobné platby, alokáciu, menu a zostatok. | E1, BR-08 | Súlad doklad–úhrady–otvorený zostatok |
| FR-10 | Predaj cez pokladňu prepojí výdaj, platbu, pokladničný a fiškálny doklad. | Používateľ potvrdené; E2 | RG-10, RG-11 |
| FR-11 | Úhrada existujúcej faktúry nevytvorí nový výdaj zásob. | E2 UF + návrh | RG-12 |
| FR-12 | Denné a mesačné uzávierky poskytnú podklady účtovníčke vrátane opráv a rozdielov. | Používateľ potvrdené | RG-13; vzor reálneho výstupu |
| FR-13 | Neznámy výsledok platby/fiškalizácie je viditeľný stav s kontrolovaným dokončením. | Návrh z integračného rizika | RG-11, RG-15; device spike |
| FR-14 | Historické doklady a prílohy sú vyhľadateľné, prepojené a nemenia sa zmenou master dát. | E1/E3 + návrh | RG-14, RG-17 |
| FR-15 | Otvorené objednávky, pohľadávky a záväzky sa prenesú s pôvodnou identitou a zostávajúcim účinkom. | Migračný cieľ | RG-18 |
| FR-16 | Každý importovaný objekt má mapu pôvodu a audit migračného behu. Opakovanie nevytvorí duplikát. | Migračný cieľ | Dva identické behy, prerušenie a pokračovanie |
| FR-17 | Existujúci e-shop dostáva dostupnosť a stav plnenia z dohodnutého jediného vlastníka. | Návrh | Súbežná rezervácia/predaj, storno a zmena objednávky |
| FR-18 | Opravy, storno, uzávierky a ručné párovanie majú identitu autora, čas a dôvod. | E1 audit + návrh | Audit v aplikačnom aj API toku |

## Rozsah, ktorý zatiaľ nie je uzavretý

- Detailná kalkulácia skladovej ceny, dodatočné obstarávacie náklady a režim pri zápornom stave.
- Cenové hladiny, akcie, zľavy a autorita predajnej ceny medzi eOil a ERP.
- Význam aktuálnych ostatných pohľadávok, zápočtov a kusových údajov.
- Tlačové zostavy, etikety, čítačky, exporty a presný formát odovzdávania účtovníčke.
- Pracovný deň pri výpadku siete/zariadenia a kompetencie obsluhy na rekonciliáciu.

## Nefunkčné podmienky

Presná desatinná aritmetika; auditovateľné opravy; idempotentné mutácie; transakčná ochrana zásob; dohľadateľnosť historických referencií; obnoviteľná DB a prílohy. SLO, objem súbežných predajov, prípustná latencia a čas obnovy sa najprv zmerajú alebo dohodnú. Neuvádzame vymyslené percentá dostupnosti či výkonové limity.

Každý budúci implementačný balík má uviesť: ktoré FR realizuje, ktoré RG overuje, aké otvorené rozhodnutia uzatvára a aký má dopad na migráciu. Funkcie z MRP sa vypúšťajú vedomým rozhodnutím s dôvodom, nie preto, že ešte nemajú diagram.
