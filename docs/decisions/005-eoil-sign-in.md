# ADR-005 — Prihlásenie do ERP cez eOil (M2)

Stav: **návrh implementovaný a overený lokálne**, 27. 9. 2026; čaká na nezávislé review. Nasadenie nie je schválené.

User zostáva v eOil (ADR-001). ERP sa prihlasuje cez existujúcu session eOil podmnožinou **OAuth 2.0 Authorization Code s PKCE S256** pre jedného dôverného klienta. eOil vydá jednorazový kód (60 s) na presne registrovaný callback; ERP ho server-to-server vymení s client secretom za **krátkodobý prístupový token (JWT HS256, 10 min)** s vlastným podpisovým kľúčom a audience `eoil-erp-api`. Token drží iba server ERP v session; katalógové API ho overuje a pri každej požiadavke znovu číta `User.active` a povolené role (`erpAllowedRoles`, predvolene nikto).

Zamietnuté: prihlasovanie menom a heslom cez ERP (ERP by videlo heslá), token v URL (história, logy), statický servisný token (obchádza práva používateľa), znovupoužitie tokenu AI agenta (iný účel a secret), úplný OAuth server (DB tabuľky a kľúče neprimerané pre jedného klienta), priamy prístup ERP do DB eOil.

Dôsledky: zablokovanie alebo odobratie roly platí od najbližšej požiadavky; ERP session končí najneskôr s tokenom; jednorazové kódy sú v cache eOil (pri viacerých serveroch treba zdieľané úložisko); single logout nie je. Produkcia ERP ostáva odmietnutá, kým nie je HTTPS nasadenie a review. Podrobnosti a sekvencia: [návrh M2](../superpowers/specs/2026-09-27-m2-eoil-integration-design.md), výsledky: [m2-validation.md](../development/m2-validation.md).
