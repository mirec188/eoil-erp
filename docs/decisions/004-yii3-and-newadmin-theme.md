# ADR-004 — Yii3 a vzhľad existujúceho newadmin

Stav: **voľba používateľa**, 27. 9. 2026. Podrobnosti prvého balíka sú implementačný návrh.

Používateľ zvolil Yii3 a rovnaký CSS/JS template ako newadmin. Vývoj bude postupný a detailná analýza bude pokračovať pri jednotlivých funkciách. Už nečakáme na kompletnú špecifikáciu celého ERP, aby vznikol základ aplikácie.

Zdrojový eOil `LimitlessAsset.php` uvádza Limitless v4.0, Bootstrap 5.2.2, jQuery 3.6.1 a Phosphor ikony. `NewAdminAsset.php` pridáva spoločné CSS; layout používa aj `backend/web/css/newadmin-nav.css`. Tieto súbory boli overené na eOil revízii `de5a5c2241503a02191a8d230078af7e071fac0b`. Konkrétne vendored súbory treba pri prevzatí zaznamenať manifestom.

Prevziať vizuálny základ a lokálne CSS/JS, adaptovať layout na Yii3. Neprenášať Yii2 AssetBundle, `OrdersAsset`, `yii.js`, obsluhu objednávok ani chat widget. ERP nepotrebuje závislosti všetkých stránok pôvodného adminu.

Oficiálny [Yii3 web template](https://github.com/yiisoft/app) podporuje klasickú server-rendered aplikáciu; overený upstream snapshot `19f5fdf9ddb784818d6f09653f53e63bc4e7dd63`. Pre tento balík navrhujeme PHP 8.4 v Dockeri; composer.lock sa vytvorí pri implementácii a bude verzovaný. Aktuálny checkout nepredstavuje potvrdený release tag, preto zaznamenáme pôvod aj skutočne nainštalované verzie.

Databázový engine prvý balík nepotrebuje: číta katalóg cez rozhranie, nič operatívne neukladá. Pri sklade odporúčame samostatnú MySQL databázu pre zhodu s existujúcou prevádzkou; konkrétnu verziu, decimal typy a locking overí následný balík. Táto voľba nie je podmienkou spustenia UI.

Claude Code je implementátor podľa výberu používateľa, koordinátor robí analýzu, zadanie, review a nezávislé overenie. Požadovaný model má oficiálne ID [`claude-opus-5-5`](https://platform.claude.com/docs/en/models/opus-5-5/overview). Dostupnosť v konkrétnej lokálnej session sa overuje výsledkom volania, nie iba nastaveným názvom.
