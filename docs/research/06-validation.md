# Overenie prvého analytického balíka

Vykonané 27. 9. 2026 na lokálnych kópiách:

- Profilovaných päť Firebird databáz; všetky hlásia `MON$READ_ONLY=1`.
- Inventár obsahuje 402 rôznych názvov tabuliek naprieč zdrojmi. Hlavná DATA0003 má 387 tabuliek, 190 s dátami, 513 triggerov, 299 procedúr a 4 funkcie.
- 77 základných a 21 hĺbkových SELECT dotazov dokončených bez SQL chyby. Dotazy a agregované výsledky sú uložené v dôkazoch.
- Prepočet 75 831 kombinácií karta–sklad: 0 rozdielov nad 0,00001; maximálny rozdiel 0,000000. Neoveruje ceny, fyzickú inventúru ani úplnosť zdrojovej kópie.
- SHA256 všetkých piatich pôvodných databáz znova porovnané s východiskovým manifestom: zhodné. Read-only výskumné kópie majú vlastnú zmenenú hlavičku.
- Výskumné Python súbory prešli kompiláciou; oba PHP súbory prešli syntax kontrolou v lokálnom klientskom image.
- Všetkých 11 Mermaid diagramov overených parserom; interné Markdown odkazy skontrolované na existenciu súborov.
- Kontrola dodávaných súborov na známe lokálne DB credentials bez zhody. Dôkazy obsahujú schému/agregáty, nie export zákazníckych riadkov alebo obsah príloh. Automatická kontrola hesiel nenahrádza kontrolu osobných údajov.

V tejto analytickej etape nebol vykonaný nový zapisujúci UI workflow, fiškálna operácia, platba, ERP import ani end-to-end test nového systému. Lokálne otvorenie MRP z predchádzajúcej etapy je označené samostatne ako E7. Väčšina RG scenárov je pripravená na vykonanie, nie označená za úspešne otestovanú.
