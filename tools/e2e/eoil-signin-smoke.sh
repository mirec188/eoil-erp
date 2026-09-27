#!/usr/bin/env bash
# Local end-to-end smoke test: ERP (Docker) ⇄ eOil (MAMP worktree codex/erp-api-identity).
#
# Signs in to eOil with an existing LOCAL account through the ERP sign-in (authorize → eOil login →
# automatic return → callback → code exchange), reads the catalog through the eOil API and, optionally,
# checks the automatic renewal after the access token expired. Prints only HTTP status codes, paths
# without query strings, counts and PASS/FAIL — never the password, cookies, codes, tokens or personal data.
#
# Required environment (not stored anywhere by this script):
#   EOIL_E2E_EMAIL, EOIL_E2E_PASSWORD   local eOil account with the Admin role
# Optional:
#   ERP_URL   (default http://127.0.0.1:8089)
#   EOIL_URL  (default http://localhost:8888/eoil-erp-api-identity/eoil-yii2)
#   EOIL_E2E_MRP      an MRP card number with a replacement suffix (e.g. ".01") present in the local
#                     eOil DB; checks that it is found and shown unchanged (value is not printed)
#   EOIL_E2E_RENEWAL_WAIT  seconds until the ERP token is expired (eOil TTL − 15 s + 2 s, e.g. 47 for a
#                     60 s TTL set in backend/runtime/erp_access_token_ttl); enables the renewal checks
set -euo pipefail

ERP_URL="${ERP_URL:-http://127.0.0.1:8089}"
EOIL_URL="${EOIL_URL:-http://localhost:8888/eoil-erp-api-identity/eoil-yii2}"
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
jar_for() { [[ "$1" == "$ERP_URL"* ]] && echo "$ERP_JAR" || echo "$EOIL_JAR"; }
status() { # status [curl options…] <url>; the URL must be the last argument
  local url="${*: -1}"
  curl -s -o "$WORK/body" -w '%{http_code}' -b "$(jar_for "$url")" -c "$(jar_for "$url")" "$@"
}
location() { curl -s -o /dev/null -w '%{redirect_url}' -b "$(jar_for "$1")" -c "$(jar_for "$1")" "$1"; }
# follow <url>: follows redirects across ERP/eOil with the right cookie jar; sets FINAL_CODE, FINAL_URL and
# HOPS (paths only, no query strings, so no code/state is printed).
follow() {
  local url="$1" out code next i
  HOPS=""
  for i in 1 2 3 4 5 6 7 8; do
    out="$(curl -s -o "$WORK/body" -w '%{http_code} %{redirect_url}' -b "$(jar_for "$url")" -c "$(jar_for "$url")" "$url")"
    code="${out%% *}"; next="${out#* }"
    HOPS="$HOPS ${url%%\?*}"
    if [[ "$code" != 3* || -z "$next" ]]; then FINAL_CODE="$code"; FINAL_URL="$url"; return; fi
    url="$next"
  done
  FINAL_CODE="loop"; FINAL_URL="$url"
}
login_via_form() { # login_via_form <eOil login page url>; the form keeps the ?erp= nonce
  status "$1" >/dev/null
  local csrf action host
  csrf="$(sed -n 's/.*name="_csrf" value="\([^"]*\)".*/\1/p' "$WORK/body" | head -1)"
  action="$(sed -n 's/.*<form id="loginForm" method="post" action="\([^"]*\)".*/\1/p' "$WORK/body" | head -1 | sed 's/&amp;/\&/g')"
  [[ -n "$csrf" && -n "$action" ]] || { echo "FAIL  eOil login form not found"; exit 1; }
  [[ "$action" == *"erp="* ]] && echo "PASS  login form keeps the ERP return nonce" || { echo "FAIL  login form lost the ERP nonce"; FAILED=1; }
  host="$(printf '%s' "$EOIL_URL" | sed -E 's#^(https?://[^/]+).*#\1#')"
  LOGIN_NEXT="$(curl -s -o /dev/null -w '%{redirect_url}' -b "$EOIL_JAR" -c "$EOIL_JAR" \
    --data-urlencode "_csrf=$csrf" --data-urlencode "User[email]=$EOIL_E2E_EMAIL" \
    --data-urlencode "User[password]=$EOIL_E2E_PASSWORD" "$host$action")"
}

# 1. ERP refuses guests.
check "ERP /catalog without sign-in redirects" "$(status "$ERP_URL/catalog")" 302

# 2. ERP sign-in while not signed in to eOil: eOil sends the browser to its login form with a nonce.
AUTHORIZE="$(location "$ERP_URL/login/start?return=%2Fcatalog%3Fpage%3D2")"
[[ "$AUTHORIZE" == "$EOIL_URL/backend/web/erp-auth/authorize?"* ]] && echo "PASS  ERP redirects to eOil authorize" || { echo "FAIL  unexpected authorize URL"; exit 1; }
LOGIN_PAGE="$(location "$AUTHORIZE")"
[[ "$LOGIN_PAGE" == *"/frontend/web/auth/login?erp="* ]] && echo "PASS  guest goes to the eOil login with a return nonce" || { echo "FAIL  guest not sent to the eOil login"; exit 1; }

# 3. Normal eOil login form; afterwards eOil returns to the authorize request by itself.
login_via_form "$LOGIN_PAGE"
[[ "$LOGIN_NEXT" == "$EOIL_URL/backend/web/erp-auth/authorize?"* ]] && echo "PASS  after login eOil returns to the ERP sign-in" || { echo "FAIL  no automatic return after login"; exit 1; }
follow "$LOGIN_NEXT"
check "sign-in completes on the original page" "$FINAL_CODE ${FINAL_URL%%\?*}" "200 $ERP_URL/catalog"
echo "INFO  hops:$HOPS"

# 4. Real catalog through the eOil API.
check "ERP /catalog" "$(status "$ERP_URL/catalog")" 200
check "catalog rows on page 1" "$(grep -c 'data-label="ID eOil"' "$WORK/body" || true)" 25
grep -q 'Ukážkové údaje' "$WORK/body" && { echo "FAIL  demo badge shown for eOil data"; FAILED=1; } || echo "PASS  no demo badge"
grep -q 'form method="post" action="/logout"' "$WORK/body" && echo "PASS  signed-in header with logout" || { echo "FAIL  logout form missing"; FAILED=1; }
FIRST_ID="$(grep -o 'data-label="ID eOil">[0-9]*' "$WORK/body" | head -1 | grep -o '[0-9]*$')"
check "ERP detail of first pack" "$(status "$ERP_URL/catalog/$FIRST_ID")" 200
check "ERP page 2" "$(status "$ERP_URL/catalog?page=2")" 200
check "ERP unknown detail" "$(status "$ERP_URL/catalog/999999999")" 404
if [[ -n "${EOIL_E2E_MRP:-}" ]]; then
  check "ERP search by MRP number with suffix" "$(status -G --data-urlencode "q=$EOIL_E2E_MRP" "$ERP_URL/catalog")" 200
  grep -qF ">$EOIL_E2E_MRP<" "$WORK/body" && echo "PASS  MRP number shown unchanged" || { echo "FAIL  MRP number not shown unchanged"; FAILED=1; }
  check "MRP search result rows" "$(grep -c 'data-label="ID eOil"' "$WORK/body" || true)" 1
fi

# 5. Already signed in to eOil: a new ERP sign-in needs no form at all.
follow "$ERP_URL/login/start?return=%2Fcatalog%2F$FIRST_ID"
check "sign-in with an existing eOil session" "$FINAL_CODE ${FINAL_URL%%\?*}" "200 $ERP_URL/catalog/$FIRST_ID"

if [[ -n "${EOIL_E2E_RENEWAL_WAIT:-}" ]]; then
  # 6. Token expired, eOil session valid: the next GET renews automatically and stays on the same page.
  echo "INFO  waiting ${EOIL_E2E_RENEWAL_WAIT}s for the ERP token to expire"
  sleep "$EOIL_E2E_RENEWAL_WAIT"
  NEXT="$(location "$ERP_URL/catalog?page=2")"
  [[ "$NEXT" == "$ERP_URL/login/start?"* ]] && echo "PASS  expired sign-in starts the automatic renewal" || { echo "FAIL  no automatic renewal"; FAILED=1; }
  follow "$NEXT"
  check "renewed without a form, back on the same page" "$FINAL_CODE ${FINAL_URL%%\?*}" "200 $ERP_URL/catalog"
  grep -q 'Prihlásiť sa cez eOil' "$WORK/body" && { echo "FAIL  login page shown"; FAILED=1; } || echo "PASS  no login page"

  # 7. Token expired and eOil session ended: renewal goes through the real eOil login and returns.
  status "$EOIL_URL/frontend/web/auth/logout" >/dev/null
  WAIT2=$(( EOIL_E2E_RENEWAL_WAIT > 62 ? EOIL_E2E_RENEWAL_WAIT : 62 ))
  echo "INFO  signed out of eOil, waiting ${WAIT2}s (token expiry and renewal interval)"
  sleep "$WAIT2"
  NEXT="$(location "$ERP_URL/catalog")"
  [[ "$NEXT" == "$ERP_URL/login/start?"* ]] && echo "PASS  automatic renewal started" || { echo "FAIL  no automatic renewal"; FAILED=1; }
  AUTHORIZE="$(location "$NEXT")"
  LOGIN_PAGE="$(location "$AUTHORIZE")"
  [[ "$LOGIN_PAGE" == *"/frontend/web/auth/login?erp="* ]] && echo "PASS  without eOil session the real eOil login is required" || { echo "FAIL  renewal skipped the eOil login"; FAILED=1; }
  login_via_form "$LOGIN_PAGE"
  follow "$LOGIN_NEXT"
  check "back in the ERP after the eOil login" "$FINAL_CODE ${FINAL_URL%%\?*}" "200 $ERP_URL/catalog"
fi

# 8. Logout needs POST + CSRF; afterwards no automatic sign-in although the eOil session is valid.
status "$ERP_URL/catalog" >/dev/null
ERP_CSRF="$(sed -n 's/.*name="_csrf" value="\([^"]*\)".*/\1/p' "$WORK/body" | head -1)"
check "logout without CSRF refused" "$(status -X POST "$ERP_URL/logout")" 422
check "logout with CSRF" "$(status --data-urlencode "_csrf=$ERP_CSRF" "$ERP_URL/logout")" 302
NEXT="$(location "$ERP_URL/catalog")"
check "after logout: login page, not automatic sign-in" "${NEXT%%\?*}" "$ERP_URL/login"

exit "$FAILED"
