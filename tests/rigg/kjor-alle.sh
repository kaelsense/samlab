#!/usr/bin/env bash
# Kjører alle røyk-testene i tests/rigg/ mot en testrigg (se
# bin/testrigg.sh) og orkestrerer tilstanden de trenger:
# assistent-testene krever modulen henholdsvis AV (f1) og PÅ
# (f2-f4). Modultilstanden gjenopprettes til AV etterpå.
#
# Bruk: tests/rigg/kjor-alle.sh [riggmappe]
set -u

RIG="${1:-${TMPDIR:-/tmp}/samlab-testrigg}"
HER="$(cd "$(dirname "$0")" && pwd)"
WP() { php "$RIG/wp-cli.phar" --allow-root --path="$RIG/wp" "$@"; }

assistent() {
	if [ "$1" = "pa" ]; then
		WP eval '$s = get_option("samlab_settings", array()); $s["assistent_aktiv"] = "1"; update_option("samlab_settings", $s);'
	else
		WP eval '$s = get_option("samlab_settings", array()); unset($s["assistent_aktiv"]); update_option("samlab_settings", $s); wp_clear_scheduled_hook("samlab_assistent_kunnskap");'
	fi
}

SAMLET=0
kjor() {
	UT=$(WP eval-file "$HER/$1" 2>&1)
	RC=$?
	OKA=$(printf '%s' "$UT" | grep -c '^OK')
	FEIL=$(printf '%s' "$UT" | grep -c '^FEIL')
	printf '%-14s %3s OK  %s FEIL  exit %s\n' "$1" "$OKA" "$FEIL" "$RC"
	if [ "$RC" -ne 0 ]; then
		printf '%s\n' "$UT" | grep '^FEIL'
		SAMLET=1
	fi
}

assistent av
for t in test-b3.php test-b4.php test-b5.php test-b6.php \
	test-e1.php test-e2.php test-e3.php test-e4.php test-e5.php \
	test-e6.php test-e7.php test-e8.php test-e9.php test-g1.php \
	test-f1.php; do
	kjor "$t"
done

assistent pa
for t in test-f2.php test-f3.php test-f4.php; do
	kjor "$t"
done
assistent av

if [ "$SAMLET" -eq 0 ]; then
	echo "Alle riggtester grønne."
else
	echo "Én eller flere tester feilet."
fi
exit "$SAMLET"
