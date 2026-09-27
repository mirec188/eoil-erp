# ADR-001 — Kanonické identity v eOil

Stav: **prijaté zadanie používateľa**, 27. 9. 2026; rozsah ostatných entít otvorený.

ProductHasPack a User zostávajú v eOil. ERP je samostatná aplikácia a pre kmeňové údaje používa API. ERP projekcie a historické snímky nie sú nový master. Zamedzuje sa nezávislému zakladaniu tej istej skladovej položky vo dvoch systémoch.

Dôsledky: stabilné ID, kontrakt deaktivácie/verzie, mapovanie MRP typu 14, riadená tvorba chýbajúcich identít a výpadkový režim. Autorita Partner/Supplier/Price/Order sa musí doplniť podľa [tabuľky vlastníctva](../architecture/01-context-and-ownership.md). Starší návrh presunúť produktový master do ERP je týmto pre tento projekt prekonaný.
