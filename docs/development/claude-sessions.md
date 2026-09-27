# Claude Code sessions

Používateľ chce pokračovať v rovnakých lokálnych CLI sessions. Implementačné behy preto ukladajú históriu do štandardného úložiska Claude Code; `--no-session-persistence` sa smie používať iba na malý diagnostický probe bez práce na projekte.

| Úloha | Session ID | Model | Effort | Pracovný adresár |
|---|---|---|---|---|
| Prvý Yii3 balík | `6f935d48-fe27-4cac-87bc-237ef7baec89` | `claude-opus-5-5` | xhigh pri úvodnej implementácii, medium pri opravách review | `/Users/mirec/Sites/localhost/eoil-erp` |

Po skončení aktuálneho behu pokračovať:

```sh
cd /Users/mirec/Sites/localhost/eoil-erp
claude --resume 6f935d48-fe27-4cac-87bc-237ef7baec89 --model claude-opus-5-5 --effort medium
```

Nespúšťať dve zapisujúce pokračovania tej istej session súčasne. Menšie opravy/konkrétne testy: minimálne `medium`; zložité doménové alebo integračné úlohy: `high`/`xhigh`. Vyšší režim iba ak ho nainštalované CLI a model skutočne podporujú. Model sa nemení implicitným fallbackom.

História CLI je lokálna, nie obsah tohto repozitára. Tento register slúži na dohľadanie session; neobsahuje prihlasovacie údaje ani surové výpisy nástrojov.

Prvý implementačný beh skončil úspešne; následné opravy review prebehli cez `--resume` v tej istej session, nie v odbočenej alebo jednorazovej histórii. Koordinátor zaznamenáva nezávislé overenie do `foundation-validation.md`.

## Pokračovanie M2 s Remote Control

27. 9. 2026 používateľ poveril pokračovaním podľa `claude-m2-handoff.md` a požiadal Remote Control pre mobil. Tá istá session `6f935d48-fe27-4cac-87bc-237ef7baec89` bola obnovená ako natívna background session (`claude --bg --resume … --remote-control …`), model `claude-opus-5-5`, effort `high`. Stav `working`, model v aktuálnych odpovediach a aktívne Remote Control boli overené. M2 týmto nie je vyhlásené za dokončené.

Lokálne otvorenie práve bežiacej session:

```sh
claude attach 6f935d48
```

Stav: `claude agents --json`. Výstup: `claude logs 6f935d48`. Kým beží, nepoužívať ďalšie `--resume` na paralelné zapisujúce pokračovanie. Na mobile otvoriť túto Remote Control session cez Claude / Code v rovnakom účte. Mac a proces Claude musia zostať bežať.
