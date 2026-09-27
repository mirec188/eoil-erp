#!/usr/bin/env bash
# Local end-to-end smoke test: ERP (Docker) ⇄ eOil (MAMP worktree codex/erp-api-identity).
#
# Signs in to eOil with an existing LOCAL account, runs the ERP sign-in (authorize → callback →
# code exchange) and reads the catalog through the eOil API. Prints only HTTP status codes, counts
# and pass/fail lines — never the password, cookies, codes, tokens or personal data.
#
# Required environment (not stored anywhere by this script):
#   EOIL_E2E_EMAIL, EOIL_E2E_PASSWORD   local eOil account with an ERP role (erp_allowed_roles)
# Optional:
#   ERP_URL   (default http://127.0.0.1:8089)
#   EOIL_URL  (default http://localhost:8888/eoil-erp-api-identity/eoil-yii2)
#   EOIL_LOGIN_URL (default $EOIL_URL/frontend/web/index.php/auth/login; the worktree's copied
#             frontend config keeps the original checkout's baseUrl, so pretty URLs route elsewhere)
#   EOIL_E2E_MRP  an MRP card number with a replacement suffix (e.g. ".01") present in the local
#             eOil DB; checks that it is found and shown unchanged (value is not printed)
set -euo pipefail

ERP_URL="${ERP_URL:-http://127.0.0.1:8089}"
EOIL_URL="${EOIL_URL:-http://localhost:8888/eoil-erp-api-identity/eoil-yii2}"
EOIL_LOGIN_URL="${EOIL_LOGIN_URL:-$EOIL_URL/frontend/web/index.php/auth/login}"
: "${EOIL_E2E_EMAIL:?EOIL_E2E_EMAIL is required}"
: "${EOIL_E2E_PASSWORD:?EOIL_E2E_PASSWORD is required}"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
EOIL_JAR="$WORK/eoil.cookies"
ERP_JAR="$WORK/erp.cookies"
FAILED=0

check() { # check <label> <actual> <expected>
  if [[ "$2" == "$3" ]]; then echo "PASS  $1 ($2)"; else echo "FAIL  $1 (got $2, expected $3)"; FAILED=1; fi
}
status() { curl -s -o "$WORK/body" -w '%{http_code}' "$@"; }
location() { curl -s -o /dev/null -w '%{redirect_url}' "$@"; }

# 1. ERP refuses guests.
check "ERP /catalog without sign-in redirects" "$(status -b "$ERP_JAR" -c "$ERP_JAR" "$ERP_URL/catalog")" 302

# 2. Start the ERP sign-in while not signed in to eOil: eOil shows its own prompt, no code.
AUTHORIZE="$(location -b "$ERP_JAR" -c "$ERP_JAR" "$ERP_URL/login/start?return=%2Fcatalog")"
[[ "$AUTHORIZE" == "$EOIL_URL/backend/web/erp-auth/authorize?"* ]] && echo "PASS  ERP redirects to eOil authorize" || { echo "FAIL  unexpected authorize URL"; exit 1; }
check "eOil authorize for a guest shows the login prompt" "$(status -b "$EOIL_JAR" -c "$EOIL_JAR" "$AUTHORIZE")" 200
grep -q 'Najprv sa prihláste do eOil' "$WORK/body" && echo "PASS  prompt text present" || { echo "FAIL  prompt text missing"; FAILED=1; }

# 3. Sign in to eOil with its normal login form (CSRF token from the form).
status -b "$EOIL_JAR" -c "$EOIL_JAR" "$EOIL_LOGIN_URL" >/dev/null
CSRF="$(sed -n 's/.*name="_csrf-frontend" value="\([^"]*\)".*/\1/p; s/.*name="_csrf" value="\([^"]*\)".*/\1/p' "$WORK/body" | head -1)"
CSRF_PARAM="$(grep -o 'name="_csrf[^"]*"' "$WORK/body" | head -1 | sed 's/name="\(.*\)"/\1/' || true)"
[[ -n "$CSRF_PARAM" && -n "$CSRF" ]] || { echo "FAIL  eOil login form not found at EOIL_LOGIN_URL"; exit 1; }
LOGIN_STATUS="$(status -b "$EOIL_JAR" -c "$EOIL_JAR" \
  --data-urlencode "$CSRF_PARAM=$CSRF" \
  --data-urlencode "User[email]=$EOIL_E2E_EMAIL" \
  --data-urlencode "User[password]=$EOIL_E2E_PASSWORD" \
  "$EOIL_LOGIN_URL")"
check "eOil login form accepted" "$LOGIN_STATUS" 302

# 4. Same authorize request again (the ERP pending sign-in is still valid): now a code comes back.
CALLBACK="$(location -b "$EOIL_JAR" -c "$EOIL_JAR" "$AUTHORIZE")"
[[ "$CALLBACK" == "$ERP_URL/auth/callback?code="* ]] && echo "PASS  eOil issued a code to the registered callback" || { echo "FAIL  no code (is the account in erp_allowed_roles?)"; exit 1; }

# 5. Callback: the ERP exchanges the code server-to-server and signs in.
check "ERP callback completes sign-in" "$(status -b "$ERP_JAR" -c "$ERP_JAR" "$CALLBACK")" 302
check "replayed callback is refused" "$(status -b "$ERP_JAR" -c "$ERP_JAR" "$CALLBACK")" 400

# 6. Real catalog through the eOil API.
check "ERP /catalog" "$(status -b "$ERP_JAR" -c "$ERP_JAR" "$ERP_URL/catalog")" 200
ROWS="$(grep -c 'data-label="ID eOil"' "$WORK/body" || true)"
check "catalog rows on page 1" "$ROWS" 25
grep -q 'Ukážkové údaje' "$WORK/body" && { echo "FAIL  demo badge shown for eOil data"; FAILED=1; } || echo "PASS  no demo badge"
grep -q 'form method="post" action="/logout"' "$WORK/body" && echo "PASS  signed-in header with logout" || { echo "FAIL  logout form missing"; FAILED=1; }
FIRST_ID="$(grep -o 'data-label="ID eOil">[0-9]*' "$WORK/body" | head -1 | grep -o '[0-9]*$')"
check "ERP detail of first pack" "$(status -b "$ERP_JAR" -c "$ERP_JAR" "$ERP_URL/catalog/$FIRST_ID")" 200
check "ERP page 2" "$(status -b "$ERP_JAR" -c "$ERP_JAR" "$ERP_URL/catalog?page=2")" 200
check "ERP unknown detail" "$(status -b "$ERP_JAR" -c "$ERP_JAR" "$ERP_URL/catalog/999999999")" 404
if [[ -n "${EOIL_E2E_MRP:-}" ]]; then
  check "ERP search by MRP number with suffix" "$(status -b "$ERP_JAR" -c "$ERP_JAR" -G --data-urlencode "q=$EOIL_E2E_MRP" "$ERP_URL/catalog")" 200
  grep -qF ">$EOIL_E2E_MRP<" "$WORK/body" && echo "PASS  MRP number shown unchanged" || { echo "FAIL  MRP number not shown unchanged"; FAILED=1; }
  check "MRP search result rows" "$(grep -c 'data-label="ID eOil"' "$WORK/body" || true)" 1
fi

# 7. Logout needs POST + CSRF; afterwards the ERP asks for sign-in again.
status -b "$ERP_JAR" -c "$ERP_JAR" "$ERP_URL/catalog" >/dev/null
ERP_CSRF="$(sed -n 's/.*name="_csrf" value="\([^"]*\)".*/\1/p' "$WORK/body" | head -1)"
check "logout without CSRF refused" "$(status -b "$ERP_JAR" -c "$ERP_JAR" -X POST "$ERP_URL/logout")" 422
check "logout with CSRF" "$(status -b "$ERP_JAR" -c "$ERP_JAR" --data-urlencode "_csrf=$ERP_CSRF" "$ERP_URL/logout")" 302
check "ERP /catalog after logout redirects" "$(status -b "$ERP_JAR" -c "$ERP_JAR" "$ERP_URL/catalog")" 302

exit "$FAILED"
