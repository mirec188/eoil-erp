# ADR-003 — Otváracie stavy a historický archív

Stav: **navrhnuté**, 27. 9. 2026; vyžaduje potvrdenie požiadaviek na historické reporty.

Preferovaná prvá migrácia prenesie overené stavy ku cutover, otvorené doklady a dohľadateľný nemenný archív. Nevykoná opätovne obchodné účinky historických dokladov. Zdrojové identity a väzby zostanú zachované.

Dôvod: MRP ročné prevody už zahrnuli historické pohyby do počiatočných stavov. Kontrola 75 831 stavov sedí pri opening + platné účtované T pohyby; plný replay všetkých O/T bez hranice by bol nesprávny. Ocenenie a prekryv starších databáz zatiaľ nie sú úplne rekonštruované.

Alternatíva: plný historický replay až po preukázaní pravidiel a požadovanej hodnoty pre používateľov. Rozhodnutie nesmie obmedziť dohľadateľnosť faktúr, úhrad, príloh a uzávierok. Detaily sú v [migračnej stratégii](../migration/strategy.md).
