# Otvorené otázky a potrebné dôkazy

Toto je pracovný backlog, nie požiadavka odpovedať naraz na celý dotazník. Najbližšie treba prejsť reálny predaj, uzávierku a príjem tovaru s obsluhou a porovnať ich s databázou.

| ID | Otázka / dôkaz | Prečo blokuje návrh | Kto / spôsob |
|---|---|---|---|
| Q-01 | Presný model Elcom, verzia softvéru, pripojenie, SDK/protokol, terminál a jeho poskytovateľ | Fiškalizácia, overenie neistého výsledku, platforma adaptera | Zariadenie + technická dokumentácia; značka Elcom je známa |
| Q-02 | Vzor dennej a mesačnej uzávierky a balíka pre účtovníčku; hotovosť/karty/refundy/vklady | Používateľ potvrdil proces; chýbajú polia, sumové väzby, formát a opravy | Obsluha + účtovníčka, anonymizovaný vzor alebo lokálna kontrola |
| Q-03 | Ktoré sklady sú živé a prečo existujú záporné stavy; ako sa riešia opravy | Prevádzková politika, aktívny rozsah, migrácia opening | Obsluha + porovnanie konkrétnych príkladov |
| Q-04 | Efektívne nastavenie ocenenia, spoločná/oddelená cena, dodatočné náklady | Finančná správnosť skladu | MRP nastavenia + procedúry + referenčný príjem/výdaj |
| Q-05 | Úplný read-only eOil snapshot na rovnakej hranici ako MRP | Presná mapa ProductHasPack/ExternalId type 14, duplicity a chýbajúce karty | Schválená kópia, profil oboma smermi |
| Q-06 | Kto smie vytvárať produkt, balenie, partnera; User vs firma vs dodávateľ | Canonical API a autorita kmeňových údajov | Vlastník produktu + dnešné eOil postupy |
| Q-07 | Autorita cien, zliav, jednotiek a DPH; prepočet balení | Zamedzenie nových rozdielov a presná fakturácia | Používateľ + eOil/MRP cenové nastavenia |
| Q-08 | Ako objednávka z e-shopu súvisí s ponukou/objednávkou v MRP; čiastočné plnenia | Dnes sú mnohé priame FK prázdne; automatický import by hádal | Reálny anonymizovaný prípad end-to-end |
| Q-09 | Význam OSPOH, zápočtov, kusovej evidencie a neznámych kódov | Potenciálne nevypustené agendy | UI walkthrough a cielený audit logiky |
| Q-10 | Prekryv DATA0001 a DATA0003 a história potrebná v novom UI | Deduplikácia, archív verzus plný replay | Párovací profil + požadované reporty |
| Q-11 | Tlačiarne, etikety, skenery, emaily, importy/exporty, API a automatické joby | Funkcie mimo SQL sa nemusia prejaviť vlastnou tabuľkou | Inventár pracovísk a integračných nastavení |
| Q-12 | Výpadkový režim a paralelná práca, počty používateľov, čas obnovy | Cache, zamykanie, výkon, lokálny adapter | Prevádzkové scenáre a meranie |
| Q-13 | Roly a schvaľovanie opráv, uzávierok, zliav a párovania | Autorizácia a audit nového ERP | Matica rolí overená obsluhou |

## Už zodpovedané

- ProductHasPack a User zostávajú v eOil; ERP je samostatný projekt a integruje sa cez API.
- Messaging/queues majú byť zavedené iba s konkrétnym dôvodom.
- MRP neslúži len na sklad a faktúry: používateľ potvrdil prepojenie pokladne a fiškálneho modulu, bloček k prijatej hotovosti, výdajku pri tovare a denné aj mesačné podklady účtovníčke.
- Značka fiškálneho zariadenia je Elcom; presný model nie je potvrdený.

Kompletný audit nesmie byť označený za hotový, kým významné neprázdne agendy, externé toky a otvorené rozhodnutia nemajú potvrdené spracovanie alebo zdôvodnené vylúčenie.
