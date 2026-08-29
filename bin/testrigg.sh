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
	WP core install --url=http://127.0.0.1:8890 --title="Samlab testrigg" \
		--admin_user=admin --admin_password=admin \
		--admin_email=test@example.com --skip-email
fi
# wp-cli kan avlede en sti-prefikset URL fra katalogen - lås den.
WP option update siteurl 'http://127.0.0.1:8890' > /dev/null
WP option update home 'http://127.0.0.1:8890' > /dev/null

ln -sfn "$REPO/samlab" "$WPDIR/wp-content/plugins/samlab"
WP plugin activate samlab
WP plugin list --fields=name,status,version

# Testbrukere som røyk-testene i tests/rigg/ forventer.
WP user get testmod > /dev/null 2>&1 || \
	WP user create testmod testmod@example.com \
		--role=samlab_moderator --user_pass="$(head -c 24 /dev/urandom | base64 | tr -dc a-zA-Z0-9)" > /dev/null
WP user get testmedlem > /dev/null 2>&1 || \
	WP user create testmedlem testmedlem@example.com \
		--role=samlab_member --user_pass="$(head -c 24 /dev/urandom | base64 | tr -dc a-zA-Z0-9)" > /dev/null

# Pene permalenker (portal-rutene forutsetter det) og router for php -S.
WP rewrite structure '/%postname%/'
WP rewrite flush
cat > "$WPDIR/router.php" <<'PHP'
<?php
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( '/' !== $path && file_exists( __DIR__ . $path ) ) {
	return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
PHP

echo "Testrigg klar i $WPDIR"
echo "Kjør kommandoer med: php $RIG/wp-cli.phar --allow-root --path=$WPDIR <kommando>"
echo "Seed demodata:       php $RIG/wp-cli.phar --allow-root --path=$WPDIR samlab seed"
echo "Start server:        php -S 127.0.0.1:8890 -t $WPDIR $WPDIR/router.php"
echo "Portal:              http://127.0.0.1:8890/portal/  (admin / admin)"
