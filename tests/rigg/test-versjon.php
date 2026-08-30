<?php
// Røyk-test for versjonering: plugin-headeren, konstanten og
// changeloggen skal si det samme.
//
// Tallet står tre steder som ikke kan utledes av hverandre. Uten en
// test her oppdages et avvik først når en installasjon ikke får
// beskjed om at det finnes en ny versjon - oppdaterings-URL-en
// sammenligner nettopp headeren.
// Kjøres med: wp eval-file test-versjon.php

// eval-file kjører i funksjons-scope: bind til den globale sjekk() skriver til.
global $fail;
$fail = 0;
function sjekk( $navn, $ok ) {
	global $fail;
	if ( $ok ) {
		echo "OK   $navn\n";
	} else {
		echo "FEIL $navn\n";
		$fail = 1;
	}
}

$plugin_fil = WP_PLUGIN_DIR . '/samlab/samlab.php';

// Riggen symlenker repoets samlab/ inn i wp-content/plugins/, så
// realpath() peker inn i repoet: <repo>/samlab/samlab.php. Repo-roten
// er da to nivåer opp. Vi leter oss likevel oppover framfor å telle
// dirname-nivåer i blinde - det var nettopp en feiltelling som gjorde
// at denne testen ikke fant fila første gang.
$changelog = '';
$mappe     = dirname( (string) realpath( $plugin_fil ) );
for ( $samlab_n = 0; $samlab_n < 4; $samlab_n++ ) {
	if ( file_exists( $mappe . '/CHANGELOG.md' ) ) {
		$changelog = $mappe . '/CHANGELOG.md';
		break;
	}
	$foreldre = dirname( $mappe );
	if ( $foreldre === $mappe ) {
		break;
	}
	$mappe = $foreldre;
}

sjekk( 'plugin-fila finnes', file_exists( $plugin_fil ) );

if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$data   = get_plugin_data( $plugin_fil, false, false );
$header = (string) $data['Version'];

sjekk( 'headeren har en versjon', '' !== $header );
sjekk( 'SAMLAB_VERSION er definert', defined( 'SAMLAB_VERSION' ) );
sjekk( 'header og konstant er like', $header === SAMLAB_VERSION );
sjekk(
	'versjonen er semver (x.y.z)',
	1 === preg_match( '/^\d+\.\d+\.\d+$/', $header )
);

sjekk( 'CHANGELOG.md finnes', '' !== $changelog && file_exists( $changelog ) );
if ( '' !== $changelog && file_exists( $changelog ) ) {
	$tekst = (string) file_get_contents( $changelog ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Leser en fil i repoet, ikke over nettverk.

	sjekk(
		'changeloggen har et [Uutgitt]-avsnitt',
		false !== strpos( $tekst, '## [Uutgitt]' )
	);

	// Nyeste utgivelse er den første ## [x.y.z]-overskriften etter
	// [Uutgitt]. Den skal være versjonen koden faktisk har.
	preg_match_all( '/^## \[(\d+\.\d+\.\d+)\]/m', $tekst, $treff );
	$nyeste = isset( $treff[1][0] ) ? $treff[1][0] : '';
	sjekk( 'changeloggen har minst én utgivelse', '' !== $nyeste );
	sjekk(
		"nyeste utgivelse i changeloggen ($nyeste) er versjonen koden har ($header)",
		$nyeste === $header
	);

	sjekk(
		'utgivelsen har en dato på ISO-form',
		1 === preg_match( '/^## \[' . preg_quote( $nyeste, '/' ) . '\] - \d{4}-\d{2}-\d{2}$/m', $tekst )
	);

	// Ingen em-dash: CLAUDE.md krever « - ».
	sjekk( 'changeloggen bruker ingen em-dash', false === strpos( $tekst, "\xE2\x80\x94" ) );
}

echo $fail ? "\nNOEN SJEKKER FEILET\n" : "\nAlle sjekker OK\n";
exit( $fail );
