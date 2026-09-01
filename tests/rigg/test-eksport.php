<?php
// Røyk-test for eksport-kommandoen (webapp-sporets fase 1, W7):
// formatet i samlab-webapp/docs/eksportformat.md valideres mot en
// seedet rigg - konvolutten, samlingene, kryssreferansene og
// hemmelighetsvernet. Kjøres tidlig i suiten, mens seed-koblingene
// fortsatt finnes (test-e4 rydder dem senere).
// Kjøres med: wp eval-file test-eksport.php

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

$samlab_cmd  = new Samlab_CLI_Command();
$samlab_data = $samlab_cmd->eksport_data();

// --- Konvolutten ---
sjekk( 'format-feltet er satt', 'samlab-eksport' === ( $samlab_data['format'] ?? '' ) );
sjekk( 'format_versjon er 1', 1 === ( $samlab_data['format_versjon'] ?? 0 ) );
sjekk( 'plugin_versjon matcher konstanten', SAMLAB_VERSION === ( $samlab_data['plugin_versjon'] ?? '' ) );
sjekk( 'eksportert er RFC3339 UTC', 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $samlab_data['eksportert'] ?? '' ) );

$samlab_samlinger = array( 'innstillinger', 'brukere', 'bedrifter', 'behov', 'koblinger', 'arrangementer', 'handbok', 'innlegg', 'kommentarer', 'reaksjoner', 'stemmer', 'ubesvart', 'medier' );
$samlab_alle_med  = true;
foreach ( $samlab_samlinger as $samlab_s ) {
	$samlab_alle_med = $samlab_alle_med && isset( $samlab_data[ $samlab_s ] ) && is_array( $samlab_data[ $samlab_s ] );
}
sjekk( 'alle samlinger finnes som arrays', $samlab_alle_med );

// --- Seed-innholdet er med ---
sjekk( 'bedrifter fra seed er med', count( $samlab_data['bedrifter'] ) >= 4 );
sjekk( 'behov fra seed er med', count( $samlab_data['behov'] ) >= 5 );
sjekk( 'koblinger fra seed er med', count( $samlab_data['koblinger'] ) >= 6 );
sjekk( 'innlegg fra seed er med', count( $samlab_data['innlegg'] ) >= 5 );
sjekk( 'brukere med samlab-roller er med', count( $samlab_data['brukere'] ) >= 5 );

// --- Hemmelighetsvernet ---
$samlab_json = wp_json_encode( $samlab_data );
sjekk( 'ingen passordfelter i eksporten', false === strpos( $samlab_json, 'user_pass' ) );
sjekk( 'ingen nøkler i eksporten', false === strpos( $samlab_json, 'nokkel' ) );
$samlab_bruker_felter = array_keys( $samlab_data['brukere'][0] );
sjekk( 'brukere har kun de avtalte feltene', array() === array_diff( $samlab_bruker_felter, array( 'wp_id', 'login', 'epost', 'visningsnavn', 'roller', 'registrert', 'ukesbrev_reservert' ) ) );

// --- Kryssreferanser: kontaktperson og koblingsparter peker på
// brukere/bedrifter som faktisk er i eksporten ---
$samlab_bruker_ids  = array_column( $samlab_data['brukere'], 'wp_id' );
$samlab_bedrift_ids = array_column( $samlab_data['bedrifter'], 'wp_id' );
$samlab_kontakt_ok  = true;
foreach ( $samlab_data['bedrifter'] as $samlab_b ) {
	if ( $samlab_b['kontaktperson'] && ! in_array( $samlab_b['kontaktperson'], $samlab_bruker_ids, true ) ) {
		$samlab_kontakt_ok = false;
	}
}
sjekk( 'kontaktpersoner refererer eksporterte brukere', $samlab_kontakt_ok );

$samlab_parter_ok = true;
foreach ( $samlab_data['koblinger'] as $samlab_k ) {
	foreach ( array( 'part_a', 'part_b' ) as $samlab_p ) {
		$samlab_liste = 'bedrift' === $samlab_k[ $samlab_p ]['type'] ? $samlab_bedrift_ids : $samlab_bruker_ids;
		if ( $samlab_k[ $samlab_p ]['wp_id'] && ! in_array( $samlab_k[ $samlab_p ]['wp_id'], $samlab_liste, true ) ) {
			$samlab_parter_ok = false;
		}
	}
}
sjekk( 'koblingsparter refererer eksporterte objekter', $samlab_parter_ok );

// --- Domenedata: samtykke, statuslogg og utfall ---
$samlab_samtykke_ok = true;
$samlab_logg_ok     = true;
foreach ( $samlab_data['koblinger'] as $samlab_k ) {
	$samlab_samtykke_ok = $samlab_samtykke_ok
		&& in_array( $samlab_k['samtykke_a'], array( 'venter', 'ja', 'nei' ), true )
		&& in_array( $samlab_k['samtykke_b'], array( 'venter', 'ja', 'nei' ), true );
	foreach ( $samlab_k['statuslogg'] as $samlab_rad ) {
		if ( ! isset( $samlab_rad['status'], $samlab_rad['user_wp_id'], $samlab_rad['tid'] ) ) {
			$samlab_logg_ok = false;
		}
	}
}
sjekk( 'samtykkeverdiene er gyldige', $samlab_samtykke_ok );
sjekk( 'statusloggene har status/aktør/tid', $samlab_logg_ok );

$samlab_utfall = array_values( array_filter( array_column( $samlab_data['koblinger'], 'utfall' ) ) );
sjekk( 'seedens fulgt opp-kobling har utfall med type og tid', array() !== $samlab_utfall && '' !== $samlab_utfall[0]['type'] && '' !== $samlab_utfall[0]['tid'] );
$samlab_uten_belop = true;
foreach ( $samlab_utfall as $samlab_u ) {
	if ( isset( $samlab_u['belop'] ) || isset( $samlab_u['amount'] ) ) {
		$samlab_uten_belop = false;
	}
}
sjekk( 'utfall har aldri beløpsfelt', $samlab_uten_belop );

// --- Ubesvart-køen er anonym også i eksport ---
// Testen kjører med assistent-modulen AV; køens funksjoner er rene
// option-operasjoner og kan lastes direkte.
if ( ! function_exists( 'samlab_ubesvart_registrer' ) ) {
	require_once SAMLAB_PLUGIN_DIR . 'includes/assistent/ubesvart.php';
}
samlab_ubesvart_registrer( 'Eksporttest: finnes det sykkelparkering?' );
$samlab_data2   = $samlab_cmd->eksport_data();
$samlab_ub      = end( $samlab_data2['ubesvart'] );
sjekk( 'ubesvart-rad er med etter registrering', false !== strpos( (string) $samlab_ub['sporsmal'], 'sykkelparkering' ) );
sjekk( 'ubesvart-raden har aldri bruker-ID', ! isset( $samlab_ub['user_id'] ) && ! isset( $samlab_ub['user_wp_id'] ) );
samlab_ubesvart_fjern( $samlab_ub['sporsmal'] );

// --- Medier og avstemning ---
sjekk( 'medieregisteret har fil og url', array() === array_filter( $samlab_data['medier'], fn( $m ) => '' === $m['fil'] || '' === $m['url'] ) );
$samlab_avstemninger = array_values( array_filter( array_column( $samlab_data['innlegg'], 'avstemning' ) ) );
sjekk( 'seed-avstemningen er med og har alternativer', array() !== $samlab_avstemninger && count( $samlab_avstemninger[0]['alternativer'] ) >= 2 );

// --- Kommentarene peker på eksporterte innlegg ---
$samlab_innlegg_ids = array_column( $samlab_data['innlegg'], 'id' );
$samlab_komm_ok     = true;
foreach ( $samlab_data['kommentarer'] as $samlab_kom ) {
	if ( ! in_array( $samlab_kom['innlegg_id'], $samlab_innlegg_ids, true ) ) {
		$samlab_komm_ok = false;
	}
}
sjekk( 'kommentarer refererer eksporterte innlegg', $samlab_komm_ok );

// --- JSON-gyldighet hele veien ---
sjekk( 'eksporten er gyldig JSON', false !== $samlab_json && null !== json_decode( $samlab_json, true ) );

exit( $fail );
