<?php
// Røyk-test for E6: arrangement-CPT, portalflate-lister, søk og
// ukesbrev-integrasjon. Skjemaet fra portalen verifiseres over HTTP
// i tillegg (se BACKLOG-notatet).
// Kjøres med: wp eval-file test-e6.php

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

// --- Registrering, flate og capability ---
sjekk( 'CPT samlab_arrangement er registrert', post_type_exists( 'samlab_arrangement' ) );
sjekk( 'arrangementer er en portalflate', isset( samlab_portal_views()['arrangementer'] ) );
$medlem = get_user_by( 'login', 'testmedlem' );
sjekk( 'medlem kan opprette arrangement', user_can( $medlem, 'samlab_create_arrangement' ) );

// --- Tidssanitering ---
sjekk( 'datetime-local normaliseres', '2026-09-01 18:00' === samlab_arrangement_sanitize_tid( '2026-09-01T18:00' ) );
sjekk( 'ugyldig tid avvises', '' === samlab_arrangement_sanitize_tid( 'i morgen kl 18' ) );

// --- Lister: kommende først (nærmeste øverst), tidligere for seg ---
function samlab_test_arrangement( $tittel, $start, $ekstra = array() ) {
	return wp_insert_post(
		array_merge(
			array(
				'post_type'   => 'samlab_arrangement',
				'post_title'  => $tittel,
				'post_status' => 'publish',
				'meta_input'  => array_merge( array( '_samlab_start' => $start ), $ekstra ),
			)
		)
	);
}
$brygga = get_page_by_path( 'brygga-design', OBJECT, 'samlab_bedrift' );
$om_2d  = wp_date( 'Y-m-d H:i', time() + 2 * DAY_IN_SECONDS );
$om_1d  = wp_date( 'Y-m-d H:i', time() + DAY_IN_SECONDS );
$for_2d = wp_date( 'Y-m-d H:i', time() - 2 * DAY_IN_SECONDS );

$a = samlab_test_arrangement( 'Frokostseminar om regnskap', $om_2d, array( '_samlab_sted' => 'Kantina', '_samlab_bedrift' => $brygga->ID ) );
$b = samlab_test_arrangement( 'Felleslunsj med quiz', $om_1d, array( '_samlab_slutt' => wp_date( 'Y-m-d H:i', time() + DAY_IN_SECONDS + HOUR_IN_SECONDS ) ) );
$p = samlab_test_arrangement( 'Gammel sommerfest', $for_2d );

$kommende_ids = wp_list_pluck( samlab_kommende_arrangementer(), 'ID' );
sjekk( 'kommende inneholder begge fremtidige', in_array( $a, $kommende_ids, true ) && in_array( $b, $kommende_ids, true ) );
sjekk( 'kommende har nærmeste først', array_search( $b, $kommende_ids, true ) < array_search( $a, $kommende_ids, true ) );
sjekk( 'kommende inneholder ikke tidligere', ! in_array( $p, $kommende_ids, true ) );

$tidligere_ids = wp_list_pluck( samlab_tidligere_arrangementer(), 'ID' );
sjekk( 'tidligere inneholder det gamle', in_array( $p, $tidligere_ids, true ) );
sjekk( 'tidligere inneholder ikke kommende', ! in_array( $a, $tidligere_ids, true ) );

// --- Tidsvisning ---
sjekk( 'tidsvisning er satt', '' !== samlab_arrangement_tid_visning( $a ) );
sjekk( 'slutt samme dag vises som intervall', false !== strpos( samlab_arrangement_tid_visning( $b ), ' - ' ) );

// --- Globalt søk ---
$grupper = samlab_global_search( 'Frokostseminar' );
sjekk( 'globalt søk finner arrangementet', isset( $grupper['arrangementer'] ) && $a === $grupper['arrangementer'][0]->ID );

// --- Ukesbrev-integrasjonen (E5-filteret) ---
$seksjoner = samlab_ukesbrev_seksjoner( time() - WEEK_IN_SECONDS );
$arr_seksjon = null;
foreach ( $seksjoner as $seksjon ) {
	if ( __( 'Kommende arrangementer', 'samlab' ) === $seksjon['tittel'] ) {
		$arr_seksjon = $seksjon;
	}
}
$arr_tekster = $arr_seksjon ? wp_list_pluck( $arr_seksjon['linjer'], 'tekst' ) : array();
sjekk( 'ukesbrevet har arrangement-seksjon', null !== $arr_seksjon );
sjekk( 'seksjonen nevner kommende med sted', (bool) preg_grep( '/Frokostseminar.*Kantina/', $arr_tekster ) );
sjekk( 'seksjonen har ikke tidligere arrangement', ! preg_grep( '/Gammel sommerfest/', $arr_tekster ) );

// --- Rydd ---
foreach ( array( $a, $b, $p ) as $id ) {
	wp_delete_post( $id, true );
}
exit( $fail );
