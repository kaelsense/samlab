#!/usr/bin/env bash
# Bygger en WordPress-testrigg uten docker: wp-cli + SQLite-drop-in.
# Brukes av loop-rundene (og gjerne lokalt) til å verifisere pluginen
# mot en ekte WordPress der wp-env/docker ikke er tilgjengelig.
#
# Bruk: bin/testrigg.sh [målmappe]
# Etterpå: php <målmappe>/wp-cli.phar --allow-root --path=<målmappe>/wp <kommando>
set -euo pipefail

RIG="${1:-${TMPDIR:-/tmp}/samlab-testrigg}"
REPO="$(cd "$(dirname "$0")/.." && pwd)"
WPDIR="$RIG/wp"
mkdir -p "$WPDIR"

if [ ! -f "$RIG/wp-cli.phar" ]; then
	curl -sSL -o "$RIG/wp-cli.phar" \
		https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
fi
WP() { php "$RIG/wp-cli.phar" --allow-root --path="$WPDIR" "$@"; }

if [ ! -f "$WPDIR/wp-load.php" ]; then
	WP core download --version=latest
fi

if [ ! -d "$WPDIR/wp-content/plugins/sqlite-database-integration" ]; then
	curl -sSL -o "$RIG/sqlite.zip" \
		https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
	unzip -oq "$RIG/sqlite.zip" -d "$WPDIR/wp-content/plugins/"
fi

if [ ! -f "$WPDIR/wp-config.php" ]; then
	WP config create --dbname=samlab --dbuser=samlab --dbpass=samlab --skip-check --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
PHP
fi

# SQLite-drop-in: db.copy -> wp-content/db.php med utfylte plassholdere.
if [ ! -f "$WPDIR/wp-content/db.php" ]; then
	sed \
		-e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WPDIR/wp-content/plugins/sqlite-database-integration#" \
		-e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
		"$WPDIR/wp-content/plugins/sqlite-database-integration/db.copy" \
		> "$WPDIR/wp-content/db.php"
fi

if ! WP core is-installed 2>/dev/null; then
	WP core install --url=http://localhost:8890 --title="Samlab testrigg" \
		--admin_user=admin --admin_password=admin \
		--admin_email=test@example.com --skip-email
fi

ln -sfn "$REPO/samlab" "$WPDIR/wp-content/plugins/samlab"
WP plugin activate samlab
WP plugin list --fields=name,status,version

echo "Testrigg klar i $WPDIR"
echo "Kjør kommandoer med: php $RIG/wp-cli.phar --allow-root --path=$WPDIR <kommando>"
