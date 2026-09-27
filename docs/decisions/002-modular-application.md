# ADR-002 — Modulárna aplikácia, jedna DB, API

Stav: **navrhnuté**, 27. 9. 2026.

ERP bude samostatná PHP aplikácia s modulmi skladu, dokladov, platieb, pokladne a integrácií. Vlastná databáza poskytne transakčnú konzistenciu lokálnych účinkov. Medzi eOil a ERP bude API s idempotenciou, verzovaním a overením výsledku.

Alternatívy: rozšírenie základného eOil projektu odporuje požadovanému oddeleniu; microservices a povinný broker zatiaľ nemajú doložený prevádzkový prínos. Jednoduchý background worker môže mať opodstatnenie pri exporte, migrácii alebo rekonciliácii zariadení bez distribuovanej infraštruktúry.

Dôsledky: treba vymedziť moduly a ownership, spoločný audit a transakcie. Žiadne priame cross-DB zápisy do eOil. PHP framework a SQL databáza ešte nie sú vybrané; aktuálny stav Yii3 a alternatív sa overí v P3.
