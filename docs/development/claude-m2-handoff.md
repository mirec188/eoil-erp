# M2 — lokálne napojenie katalógu a identity eOil

Používateľ po odovzdaní základu `03d262e` požiadal pokračovať cez uloženú Claude Code CLI session s Remote Control pre mobil. Model `claude-opus-5-5`, effort `high` pre analýzu identity/integrácie; pri konkrétnych opravách minimálne `medium`.

## Výsledok pracovného balíka

Lokálne overiteľný tok prihlásenia cez existujúcu identitu eOil a čítania skutočných balení cez API. `ProductHasPack` a `User` zostávajú autoritatívne v eOil, ERP nemá priamy DB prístup ani druhú databázu hesiel. Existujúci ukážkový režim zostáva jasne označený. MRP číslo je externá referencia pre párovanie/migráciu, nie povinná nová identita ERP.

## Postup

1. Prečítaj AGENTS/CLAUDE, existujúcu analýzu vlastníctva, ADR a návrh API. Prvý balík je dokončený; neopakuj jeho implementáciu. Toto zadanie rozširuje rozsah pôvodného CLAUDE.md na M2.
2. Preskúmaj aktuálny Yii2 eOil: ProductHasPack, User, ExternalId typ 14, existujúce API, prihlásenie/session a kontrolu administrátorských práv. Hľadaj možnosti využitia existujúceho bezpečného mechanizmu; domnienky označ. Neexportuj osobné údaje ani tajomstvá do promptu, reportu či Gitu.
3. V ERP zapíš stručný návrh a plán M2 vrátane Mermaid sekvencie prihlásenia a čítania katalógu, hraníc dôvery, zániku session, zablokovaného používateľa, nedostatočných práv a chybových stavov. Zvoľ najjednoduchší primeraný mechanizmus podložený kódom; nevytváraj vlastný kryptografický protokol. Nepridávaj broker/queue. Používateľ už poveril postupným vývojom; rutinné kroky nevyžadujú opakované schvaľovanie. Otázku polož iba pri skutočne chýbajúcom obchodnom pravidle alebo prístupe.
4. Implementuj najmenší ucelený lokálny tok. ERP začni na novej vetve `codex/eoil-integration` z aktuálneho overeného HEAD, ak neexistuje. Zmeny eOil rob iba v samostatnom git worktree s vetvou `codex/erp-api-identity` z aktuálneho eOil HEAD; pôvodný checkout nemeň ani neprepínaj. Pre eOil čítaj jeho AGENTS/OpenSpec a relevantné pokyny. V pracovnom prostredí nie si sám: zachovaj cudzie zmeny.
5. Lokálne pripojenie MAMP/Docker over bez používania produkcie. ERP cez HTTP API, DB číta iba eOil. Existujúce lokálne nastavenia môžeš použiť bez vypisovania hodnôt; nič nekopíruj do verzovaných súborov. Na testy práv použij izolované testovacie dáta. Žiadne schémové/data migrácie reálnej DB ani zmeny hesiel používateľov.
6. Testuj najmä odmietnutie neprihláseného/neoprávneného/zablokovaného používateľa, ochranu session/CSRF/redirectu podľa vybraného toku, logout, revokáciu, HTTP timeout/neplatnú odpoveď a korektné mapovanie balení vrátane `.01`. Prístupy musia byť explicitné; žiadny login bypass ani otvorenie produkčného režimu len pre demo.
7. Dokonči overenie oboch strán, dokumentuj skutočne overené vs. mock/otvorené body a návod na lokálne spustenie. Výsledok odovzdaj na nezávislé review. Ak chýba kritický prístup, dokonči nezávislé časti a pomenuj konkrétny blokátor.

## Hranice a odovzdanie

Žiadne produkčné/test server deploye, produkčné požiadavky, fiškalizácia, platby, skladové zápisy, migrácia MRP, odosielanie správ ani ďalší agenti. Nevytváraj commit/push/merge; to po review spraví koordinátor. Nevypínaj ochrany ani Remote Control. Pracuj v tej istej uloženej session; používateľ ju môže sledovať a smerovať z mobilu. Na záver uveď vetvy/pracovné adresáre, diff, presné výsledky testov, lokálnu URL a limity.
