#!/bin/bash
set -euo pipefail

BASE_URL='https://wondrous-marshallleeharris.wpcomstaging.com'
USER='marshallleeharris'
PASS='wutangCLAN!!8344'

TMP=$(mktemp -d)
CJ="$TMP/cookies.txt"

extract_token() {
	perl -ne 'print "$1\n" if /name="jetpack_protect_answer" value="([^"]+)"/' | head -n1
}

extract_sum() {
	perl -0777 -ne 'if (/<label[^>]*for="jetpack_protect_answer"[^>]*>(.*?)<\/label>/s) { @n = ($1 =~ /(\d+)/g); print(($n[0] + $n[1]) . "\n") if @n >= 2; }'
}

PAGE=$(curl -s -c "$CJ" "$BASE_URL/wp-login.php")
TOK=$(printf '%s' "$PAGE" | extract_token)
ANS=$(printf '%s' "$PAGE" | extract_sum)

CHAL=$(curl -s -L -b "$CJ" -c "$CJ" \
	--data-urlencode "log=$USER" \
	--data-urlencode "pwd=$PASS" \
	--data-urlencode "jetpack_protect_num=$ANS" \
	--data-urlencode "jetpack_protect_answer=$TOK" \
	--data-urlencode "rememberme=forever" \
	--data-urlencode "wp-submit=Log In" \
	--data-urlencode "redirect_to=$BASE_URL/wp-admin/plugins.php" \
	--data-urlencode "testcookie=1" \
	"$BASE_URL/wp-login.php")

TOK2=$(printf '%s' "$CHAL" | extract_token)
ANS2=$(printf '%s' "$CHAL" | extract_sum)

if [[ -n "${TOK2:-}" && -n "${ANS2:-}" ]]; then
	curl -s -L -b "$CJ" -c "$CJ" \
		-d "jetpack_protect_num=$ANS2&jetpack_protect_answer=$TOK2&jetpack_protect_process_math_form=1" \
		"$BASE_URL/login/" >/dev/null
fi

PLUGINS_PAGE="$TMP/plugins.html"
curl -s -L -b "$CJ" "$BASE_URL/wp-admin/plugins.php" > "$PLUGINS_PAGE"

ACTIVATE_REL=$(perl -ne 'print "$1\n" if /href="([^"]*plugins\.php\?action=activate&amp;plugin=paid-memberships-pro%2Fpaid-memberships-pro\.php[^"]*)"/' "$PLUGINS_PAGE" | head -n1 | perl -MHTML::Entities -pe 'decode_entities($_);')

if [[ -z "${ACTIVATE_REL:-}" ]]; then
	echo "Could not find PMPro activation link."
	exit 1
fi

RESP="$TMP/activate-pmpro.html"
curl -s -L -b "$CJ" -c "$CJ" "$BASE_URL/wp-admin/$ACTIVATE_REL" > "$RESP"

echo "Saved activation response to: $RESP"
grep -in 'No plugins were activated\|Suspected plugin\|Error details\|Fatal\|Uncaught Error\|Warning\|paid memberships pro\|pmpro' "$RESP" | sed -n '1,200p'
