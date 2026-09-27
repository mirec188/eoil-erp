# Výskumné nástroje

Pomocné read-only extraktory, nie migračný importér ani produktový skeleton ERP.

## Aktuálne lokálne prostredie

- Firebird 3.0.14 v `mrp-crossover-fb`, porty na hostiteľovi viazané iba na localhost.
- Výskumné súbory v `/var/lib/firebird/data/research-20260927/`; všetkých päť má nastavený databázový read-only režim.
- PHP klient v lokálnom Docker image `eoil-php-fb:8.2` s PDO Firebird; nie deklarácia finálnej PHP verzie ERP.
- Lokálne Python wrappers čítajú credential z `/Users/mirec/mrp-crossover-local/firebird.env`, odovzdajú ho cez prostredie procesu, nie ako text príkazu. Výstup nesmie obsahovať credential. Prístup Docker administrátora však umožňuje čítať prostredie kontajnera.
- Wrappery sú zámerne viazané na toto výskumné prostredie. Na inom stroji treba nakonfigurovať cesty/network/image; heslo nikdy nevkladať do repozitára.

## Opakovanie

Z koreňa repozitára, pri bežiacom kontajneri a existujúcej read-only kópii:

```sh
mkdir -p .local
python3 tools/research/run-local-profile.py --database DATA0003 --output .local/DATA0003.json
python3 tools/research/run-local-query.py tools/research/usage-queries.json .local/usage.json
python3 tools/research/run-local-query.py tools/research/deep-queries.json .local/deep.json
```

Rovnako sa profilujú DATA0001, DATA0002, DATASPOL, DATALOCK. Raw profily vrátane rozsahov dátumov môžu obsahovať citlivé metadáta, preto patria iba do `.local/`.

PHP entrypointy `firebird-profile.php` a `firebird-query.php` prijímajú `MRP_DSN`, `MRP_USER` (lokálny default SYSDBA), `MRP_PASSWORD`. Ihneď overia MON$READ_ONLY; ak databáza nie je read-only, skončia. Textový filter SELECT v query helperi nie je bezpečnostný SQL parser. Skutočnou ochranou pred zápisom je režim databázy a ručná kontrola dotazov. Neprijímať dotazy od nedôveryhodného používateľa.

Dotazy v `usage-queries.json` a `deep-queries.json` sú agregácie a schéma. Alias `ean_duplicates` označuje duplicity SKKAR.KOD; nepotvrdzuje syntaktickú platnosť EAN/GTIN. Hodnoty numerických flagov ostávajú kódy, kým ich význam nie je overený.

`export-evidence.py` vyžaduje päť profilov, oba query výsledky a lokálny `.local/logic.json` s vybranými zdrojmi procedúr. Exportuje iba mená/hash logiky, schému a ručne skontrolované agregáty. Celé telá procedúr ostávajú mimo Gitu. Reprodukovateľný SELECT pre 13 vybraných objektov je v `logic-queries.json`:

```sh
python3 tools/research/run-local-query.py tools/research/logic-queries.json .local/logic.json
python3 tools/research/export-evidence.py
```

Exportér nie je všeobecný anonymizátor: pri rozšírení query setu je povinná kontrola výstupu pred commitom. Pred exportom treba mať aktuálny lokálny source manifest; jeho hash je identita originálu, nie read-only kópie so zmenenou hlavičkou.

Zámerne nespúšťame zapisujúce procedúry, opravy dát, zariadenia, email ani produkčné API. Na workflow testy je potrebná ďalšia oddelená kópia.
