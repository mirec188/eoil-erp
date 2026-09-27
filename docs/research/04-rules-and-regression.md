# Pravidlá a regresný katalóg

Rozlišujeme test **zhody so zdrojom** a test **navrhovaného správania ERP**. Zmeny politiky (napríklad zákaz záporného skladu) musia byť schválené ako zmena procesu, nie prezentované ako oprava migrácie.

## Rekonštruované pravidlá

| ID | Pravidlo a dôkaz | Rozsah istoty |
|---|---|---|
| BR-01 | `SKKAR_ROCNIPREVOD_NEW_S` presúva pohyby T do O a mení počiatočné stavy | Prečítaný kód + rozdelenie dát podľa rokov; nie kompletná rekonštrukcia všetkých uzávierok |
| BR-02 | Stav = POCPOCETMJ + súčet príjem/výdaj, iba JEPLATNY=T a JEUCETPOL=T | Nezávislý SELECT, 75 831 riadkov, 0 rozdielov; E3 `balance_hypothesis` |
| BR-03 | `SET_SKKAR_CELKEM` agreguje stav/rezervácie zo skladov | Prečítaná procedúra; neoverené všetky cache polia |
| BR-04 | `SKPOHPOL_PRUMCENA` vetví ocenenie podľa nastavenia a pracuje s váženým priemerom | Prečítaný kód; efektívny režim a všetky vetvy zatiaľ nepotvrdené |
| BR-05 | Trigger zmeny SKPOHPOL pracuje aj s nadväznými pohybmi a podmienenou FIFO logikou | Na migráciu nestačí preniesť jednoduché CRUD operácie |
| BR-06 | `SKPOH_BU0_ZPRACOVANI` obmedzuje návrat stavov T/O do F/R | Životný cyklus nie je ľubovoľné prepísanie statusu |
| BR-07 | `OBJPR_REFRESH_NABIDKA` kontroluje ponukové položky a nastavuje hlavičku | Ponuku preveriť aj na úrovni riadkov |
| BR-08 | `STAV_UHR_FAKVY` rozlišuje domácu a cudziu menu pri zostatku faktúry | Faktúra, úhrada a kurzové hodnoty sa nesmú zliať do jedného čísla |
| BR-09 | Predaj prepája výdaj, bloček a pohyb hotovosti; denné/mesačné uzávierky pre účtovníčku | Potvrdené používateľom; presné časovanie a sumové mostíky treba zmerať |

[Register](evidence/reviewed-logic.csv) obsahuje mená a SHA256 prečítaných zdrojov. Celé proprietárne telá sa neukladajú do Gitu. Z 513 triggerov a 299 procedúr je toto iba cielený prvý výber.

## Regresné scenáre pre ďalšiu etapu

| ID | Scenár | Očakávaný dôkaz / akceptácia |
|---|---|---|
| RG-01 | Dvojité spustenie importu tej istej karty s desatinným číslom | Jedna mapa na ProductHasPack; pôvodné ID aj presné číslo zachované |
| RG-02 | Rovnaký čiarový kód na rôznych kartách | Konflikt v karanténe; žiadne tiché zlúčenie |
| RG-03 | Opening + T pohyby v aktuálnej kópii | 75 831 porovnaní; aktuálna baseline nula rozdielov; uložiť aj toleranciu a presnosť |
| RG-04 | Historický O pohyb spolu s opening | História je dohľadateľná, nový skladový efekt sa nevytvorí |
| RG-05 | Záporný stav a následný príjem | Migrácia zachová podpísané množstvo; ocenenie porovnať s MRP po potvrdení nastavení |
| RG-06 | Faktúra s viacerými výdajkami | Jedno započítanie každého výdaja; zachované väzby a súčty |
| RG-07 | Ponuka → objednávka → čiastočné plnenie | Pozorovať v izolovanom MRP; potvrdiť prechody, množstvá a doklady pred implementáciou |
| RG-08 | Preskladnenie medzi dvoma skladmi | Dva opačné účinky, spoločná referencia; chyby jednej strany nezanechajú polovičný prevod |
| RG-09 | Inventúra so známym rozdielom | Archivovaný spočítaný stav a schválený korekčný pohyb; MRP pravidlá dátumu treba overiť |
| RG-10 | Hotovostný predaj a vratka | Prepojený predaj, výdaj/vrátenie, platba, pokladňa a fiškálny doklad; uzávierkový mostík |
| RG-11 | Prerušené spojenie po fiškalizácii | Stav neznámy, overenie podľa pôvodnej identity; žiadny druhý blok alebo výdaj |
| RG-12 | Hotovostná úhrada už vydanej faktúry | Pridá úhradu/pokladničný a fiškálny záznam; nevydá tovar druhýkrát |
| RG-13 | Denná a mesačná uzávierka | Sumy podľa platieb, opráv a fiškálnych stavov; porovnanie s reálnym podkladom účtovníčky |
| RG-14 | Zmena mena/adresy/SKU názvu v eOil | Nový doklad použije aktuálne dáta; starý doklad zachová pôvodnú snímku |
| RG-15 | Opakované API volanie po timeoute | Rovnaký kľúč vráti pôvodný výsledok; iné telo s rovnakým kľúčom je konflikt |
| RG-16 | API eOil nedostupné | ERP nerozmnoží identity; nové založenie čaká; pravidlá predaja z cache treba potvrdiť |
| RG-17 | Príloha a historický doklad | Zhodná kontrolná suma súboru, správna väzba, riadený prístup |
| RG-18 | Otvorená faktúra/objednávka pri cutover | Počiatočný záväzok a zostávajúce plnenie bez nového vystavenia či fiškalizácie histórie |

RG-03 bol vykonaný ako read-only porovnanie. Ostatné sú pripravené scenáre; ich existenciu v dokumente netreba zamieňať za úspešný test. Zápisové scenáre MRP sa vykonajú na ďalšej obetovateľnej kópii, s odpojenými fiškálnymi a platobnými zariadeniami aj emailom.
