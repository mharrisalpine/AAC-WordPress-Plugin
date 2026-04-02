#!/bin/bash
set -euo pipefail

site="https://wondrous-marshallleeharris.wpcomstaging.com"
zip_path="${1:?plugin zip path required}"
user="${2:?username required}"
pass="${3:?password required}"

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
login_script="$script_dir/staging-login.sh"

cj="$(bash "$login_script" "$user" "$pass" "$site/wp-admin/plugin-install.php?tab=upload" 2>/tmp/aac-login-response.html)"
cp "$cj" /tmp/aac-plugin-upload-cookies.txt
cj="/tmp/aac-plugin-upload-cookies.txt"

upload_page="$(curl -s -b "$cj" "$site/wp-admin/plugin-install.php?tab=upload")"
nonce="$(printf '%s' "$upload_page" | perl -0777 -ne 'if(/name="_wpnonce" value="([^"]+)"/){print $1; exit}')"

if [ -z "$nonce" ]; then
	echo "Failed to find upload nonce" >&2
	exit 1
fi

resp="/tmp/aac-plugin-upload-response.html"
curl -s -L -b "$cj" -c "$cj" \
	-F "_wpnonce=$nonce" \
	-F "_wp_http_referer=/wp-admin/plugin-install.php?tab=upload" \
	-F "pluginzip=@$zip_path;type=application/zip" \
	-F "install-plugin-submit=Install Now" \
	"$site/wp-admin/update.php?action=upload-plugin" -o "$resp"

overwrite_url="$(perl -0777 -ne 'if(/href="([^"]*overwrite=update-plugin[^"]*)"/){print $1; exit}' "$resp" | sed 's/&amp;/\&/g')"
echo "OVERWRITE_URL=$overwrite_url"
if [ -n "$overwrite_url" ]; then
	if [[ "$overwrite_url" != http* ]]; then
		overwrite_url="$site/wp-admin/${overwrite_url#./}"
	fi

	curl -s -L -b "$cj" -c "$cj" "$overwrite_url" -o "$resp"
fi

rg -n "Plugin updated successfully|Plugin installed successfully|Destination folder already exists|AAC Member Portal" "$resp" -S
