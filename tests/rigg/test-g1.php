<?php
// Røyk-test for G1: parts-samtykke i koblingsflyten - forespurt-
// status, samtykke-meta og samlab_kobling_svar() som eneste vei til
// godkjent (begge ja / ett nei / svar i feil status).
// Kjøres med: wp eval-file test-g1.php

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

$moderator = get_user_by( 'login', 'testmod' );
$kari      = get_user_by( 'login', 'kari.demo' );   // kontaktperson for Brygga Design (seed)
$jonas     = get_user_by( 'login', 'jonas.demo' );  // medlem (seed)
$brygga    = get_page_by_path( 'brygga-design', OBJECT, 'samlab_bedrift' );
$rydd      = array();

// 1) Statuskjeden har forespurt mellom foreslått og godkjent.
$kjede = array_keys( samlab_kobling_statuser() );
sjekk( 'forespurt ligger mellom foreslått og godkjent', array( 'foreslatt', 'forespurt', 'godkjent' ) === array_slice( $kjede, 0, 3 ) );

function g1_kobling( $brygga, $jonas ) {
	return samlab_opprett_kobling(
		array(
			'tittel'      => 'G1-test',
			'begrunnelse' => 'Samtykketest.',
			'part_a'      => array(
				'type' => 'bedrift',
				'id'   => $brygga->ID,
			),
			'part_b'      => array(
				'type' => 'bruker',
				'id'   => $jonas->ID,
			),
		)
	);
}

// 2) Svar i feil status avvises - foreslått venter ikke på svar.
$kobling = g1_kobling( $brygga, $jonas );
$rydd[]  = $kobling;
sjekk( 'samtykke er venter på fersk kobling', 'venter' === samlab_kobling_samtykke( $kobling, 'a' ) && 'venter' === samlab_kobling_samtykke( $kobling, 'b' ) );
$resultat = samlab_kobling_svar( $kobling, 'a', 'ja', $kari->ID );
sjekk( 'svar på foreslått kobling avvises', is_wp_error( $resultat ) && 'samlab_feil_status' === $resultat->get_error_code() );

// 3) Kontrollpanelets godkjenning setter forespurt, ikke godkjent.
sjekk( 'kontrollpanel-godkjenn gir forespurt', true === samlab_kontrollpanel_utfor( $kobling, 'godkjenn', $moderator->ID ) && 'forespurt' === get_post_meta( $kobling, '_samlab_status', true ) );
sjekk( 'forespurt nullstiller samtykkene', 'venter' === get_post_meta( $kobling, '_samlab_samtykke_a', true ) && 'venter' === get_post_meta( $kobling, '_samlab_samtykke_b', true ) );

// 4) Valideringsvaktene.
sjekk( 'ugyldig part avvises', is_wp_error( samlab_kobling_svar( $kobling, 'c', 'ja' ) ) );
sjekk( 'ugyldig svar avvises', is_wp_error( samlab_kobling_svar( $kobling, 'a', 'kanskje' ) ) );
sjekk( 'ikke-kobling avvises', is_wp_error( samlab_kobling_svar( $brygga->ID, 'a', 'ja' ) ) );

// 5) Begge ja: godkjent nås først ved svar nummer to.
$besvart = array();
add_action(
	'samlab_kobling_besvart',
	function ( $id, $part, $svar ) use ( &$besvart ) {
		$besvart[] = $part . ':' . $svar;
	},
	10,
	3
);
sjekk( 'part A takker ja', true === samlab_kobling_svar( $kobling, 'a', 'ja', $kari->ID ) );
sjekk( 'ett ja løfter ikke statusen', 'forespurt' === get_post_meta( $kobling, '_samlab_status', true ) );
sjekk( 'part B takker ja', true === samlab_kobling_svar( $kobling, 'b', 'ja', $jonas->ID ) );
sjekk( 'begge ja gir godkjent', 'godkjent' === get_post_meta( $kobling, '_samlab_status', true ) );
sjekk( 'besvart-action fyrte per svar', array( 'a:ja', 'b:ja' ) === $besvart );
$logg = wp_list_pluck( get_post_meta( $kobling, '_samlab_statuslogg', true ), 'status' );
sjekk( 'statusloggen fører samtykkene', array( 'foreslatt', 'forespurt', 'samtykke_ja', 'samtykke_ja', 'godkjent' ) === $logg );
sjekk( 'svar etter godkjent avvises', is_wp_error( samlab_kobling_svar( $kobling, 'a', 'nei' ) ) );

// 6) Ett nei: koblingen avvises, uansett hva den andre svarte.
$kobling2 = g1_kobling( $brygga, $jonas );
$rydd[]   = $kobling2;
samlab_sett_kobling_status( $kobling2, 'forespurt', $moderator->ID );
samlab_kobling_svar( $kobling2, 'a', 'ja', $kari->ID );
sjekk( 'part B takker nei', true === samlab_kobling_svar( $kobling2, 'b', 'nei', $jonas->ID ) );
sjekk( 'ett nei gir avvist', 'avvist' === get_post_meta( $kobling2, '_samlab_status', true ) );
sjekk( 'nei-samtykket er ført', 'nei' === samlab_kobling_samtykke( $kobling2, 'b' ) && 'ja' === samlab_kobling_samtykke( $kobling2, 'a' ) );
sjekk( 'svar etter avvist avvises', is_wp_error( samlab_kobling_svar( $kobling2, 'a', 'ja' ) ) );

// 7) Re-forespørsel starter med blanke ark.
samlab_sett_kobling_status( $kobling2, 'forespurt', $moderator->ID );
sjekk( 're-forespørsel nullstiller samtykkene', 'venter' === samlab_kobling_samtykke( $kobling2, 'a' ) && 'venter' === samlab_kobling_samtykke( $kobling2, 'b' ) );

// 8) Historikk: koblinger fra før G1 (godkjent+ uten samtykke-meta)
// regnes som samtykket.
$kobling3 = g1_kobling( $brygga, $jonas );
$rydd[]   = $kobling3;
samlab_sett_kobling_status( $kobling3, 'godkjent', $moderator->ID );
sjekk( 'historisk godkjent teller som to ja', 'ja' === samlab_kobling_samtykke( $kobling3, 'a' ) && 'ja' === samlab_kobling_samtykke( $kobling3, 'b' ) );

// 9) Manuell metaboks-overstyring til godkjent fører samtykkene som
// ja (CM har innhentet dem utenfor portalen).
$kobling4 = g1_kobling( $brygga, $jonas );
$rydd[]   = $kobling4;
samlab_sett_kobling_status( $kobling4, 'forespurt', $moderator->ID );
wp_set_current_user( 1 );
$_POST = array(
	'samlab_kobling_nonce' => wp_create_nonce( 'samlab_kobling_meta' ),
	'samlab_status'        => 'godkjent',
);
samlab_save_kobling_meta( $kobling4 );
sjekk( 'manuell godkjent fører samtykkene som ja', 'ja' === get_post_meta( $kobling4, '_samlab_samtykke_a', true ) && 'ja' === get_post_meta( $kobling4, '_samlab_samtykke_b', true ) );
$_POST = array();

foreach ( $rydd as $post_id ) {
	if ( ! is_wp_error( $post_id ) ) {
		wp_delete_post( $post_id, true );
	}
}

exit( $fail );
