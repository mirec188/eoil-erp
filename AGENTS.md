# Pravidlá práce na eOil ERP

- Komunikácia po slovensky alebo anglicky. Začíname reverznou analýzou; nevytvárať ERP implementáciu len preto, že existuje návrh diagramu.
- `ProductHasPack` a `User` majú autoritu v eOil. ERP pracuje s referenciami cez API; bez priameho zápisu do eOil DB. Ostatné vlastníctvo pozri `docs/architecture/01-context-and-ownership.md`.
- Rozlišovať: potvrdené používateľom, pozorované v UI, zmerané v dátach, prečítané v kóde, hypotéza, návrh. Prázdna tabuľka nie je dôkaz, že funkcia nikdy nebola potrebná.
- MRP výskum iba na oddelenej kópii v režime read-only. Testovanie zápisových workflow vyžaduje ďalšiu obetovateľnú kópiu a odpojené externé integrácie.
- Nikdy nepridávať do Gitu databázy, heslá, osobné údaje, obsah príloh, fiškálne identifikátory ani celé proprietárne procedúry. Agregované dôkazy manuálne skontrolovať.
- Ponechať pôvodné identifikátory vrátane desatinných častí MRP čísel. EAN nie je spoľahlivý unikátny kľúč.
- Každé dôležité pravidlo dokumentovať s dôkazom a regresným scenárom. Zmena návrhu musí aktualizovať príslušný ADR, diagramy a migračné dôsledky.
- Prednosť má modulárna aplikácia, transakcie v jednej ERP databáze a synchronné API. Každú queue, outbox či nový proces zdôvodniť konkrétnym zlyhaním alebo meranou potrebou.
- Žiadne produkčné zápisy, odosielanie správ, fiškalizácia, platby alebo migrácia na základe výskumného skriptu. Rollback po reálnom predaji nie je jednoduché obnovenie starej databázy.
