#!/bin/bash
set -euo pipefail

site="https://wondrous-marshallleeharris.wpcomstaging.com"
user="${1:?username required}"
pass="${2:?password required}"

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
login_script="$script_dir/staging-login.sh"

cj="$(bash "$login_script" "$user" "$pass" "$site/wp-admin/options-reading.php" 2>/tmp/aac-login-response.html)"

curl -s -L -b "$cj" "$site/wp-admin/options-reading.php" > /tmp/aac-options-reading.html

nonce="$(grep -o 'id="_wpnonce" name="_wpnonce" value="[^"]*' /tmp/aac-options-reading.html | head -n 1 | cut -d'"' -f6)"

curl -s -L -b "$cj" -c "$cj" \
	--data-urlencode "option_page=reading" \
	--data-urlencode "action=update" \
	--data-urlencode "_wpnonce=$nonce" \
	--data-urlencode "_wp_http_referer=/wp-admin/options-reading.php" \
	--data-urlencode "blog_public=1" \
	--data-urlencode "submit=Save Changes" \
	"$site/wp-admin/options.php" > /tmp/aac-options-save.html

rg -n "Settings saved|Settings saved.|Site visibility" /tmp/aac-options-save.html -S
