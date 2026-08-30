<?php
// Røyk-test for G5: rapportflaten - tallene stemmer for alle tre
// periodene (delta-målt mot kontrollerte hendelser i hver
// tidslomme), CSV-formatet, og tilgangsvaktene.
// Kjøres med: wp eval-file test-g5.php

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
$medlem    = get_user_by( 'login', 'testmedlem' );
$kari      = get_user_by( 'login', 'kari.demo' );   // kontaktperson for Brygga Design (seed)
$jonas     = get_user_by( 'login', 'jonas.demo' );  // medlem (seed)
$brygga    = get_page_by_path( 'brygga-design', OBJECT, 'samlab_bedrift' );
$rydd      = array();

function g5_logg_tilbake( $kobling_id, $slug, $dager ) {
	// Fører et statuslogg-innslag datert bakover i tid, uten
	// hendelses-sideeffekter - rapporten leser kun loggen.
	$logg   = get_post_meta( $kobling_id, '_samlab_statuslogg', true );
	$logg   = is_array( $logg ) ? $logg : array();
	$logg[] = array(
		'status'  => $slug,
		'user_id' => 0,
		'tid'     => gmdate( 'Y-m-d H:i:s', time() - $dager * DAY_IN_SECONDS ),
	);
	update_post_meta( $kobling_id, '_samlab_statuslogg', $logg );
}

// Basislinje per periode.
$basis = array();
foreach ( samlab_rapport_perioder() as $dager ) {
	$basis[ $dager ] = samlab_rapport_tall( $dager );
}
sjekk( 'periodene er 30/90/365', array( 30, 90, 365 ) === samlab_rapport_perioder() );

// 1) Kontrollerte hendelser i hver tidslomme.

// Nytt behov (nå) - teller i alle periodene.
$behov  = wp_insert_post(
	array(
		'post_type'   => 'samlab_behov',
		'post_status' => 'publish',
		'post_title'  => 'G5 rapporttest-behov',
	)
);
$rydd[] = $behov;

// Kobling fra matching gjennom hele kjeden nå: matchforslag,
// forespurt, godkjent (to ja), introdusert og utfall avtale.
$kobling = samlab_opprett_kobling(
	array(
		'tittel'      => 'G5 matchkobling',
		'begrunnelse' => 'Rapporttest.',
		'kilde'       => 'matching',
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
$rydd[]  = $kobling;
samlab_sett_kobling_status( $kobling, 'forespurt', $moderator->ID );
samlab_kobling_svar( $kobling, 'a', 'ja', $kari->ID );
samlab_kobling_svar( $kobling, 'b', 'ja', $jonas->ID );
samlab_sett_kobling_status( $kobling, 'introdusert', $moderator->ID );
samlab_sett_kobling_utfall( $kobling, 'avtale', 'Rammeavtale', $moderator->ID );

// Manuell kobling med hendelser 60 dager tilbake (kun 90/365).
$kobling2 = samlab_opprett_kobling(
	array(
		'tittel'      => 'G5 gammel kobling',
		'begrunnelse' => 'Rapporttest.',
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
$rydd[]   = $kobling2;
delete_post_meta( $kobling2, '_samlab_statuslogg' );
g5_logg_tilbake( $kobling2, 'foreslatt', 61 );
g5_logg_tilbake( $kobling2, 'forespurt', 60 );
g5_logg_tilbake( $kobling2, 'avvist', 59 );

// Kobling med introduksjon 200 dager tilbake (kun 365).
$kobling3 = samlab_opprett_kobling(
	array(
		'tittel'      => 'G5 eldgammel kobling',
		'begrunnelse' => 'Rapporttest.',
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
$rydd[]   = $kobling3;
delete_post_meta( $kobling3, '_samlab_statuslogg' );
g5_logg_tilbake( $kobling3, 'introdusert', 200 );

// Avholdte arrangementer: ett for 10 dager siden, ett for 200.
foreach ( array( 10, 200 ) as $dager_siden ) {
	$arrangement = wp_insert_post(
		array(
			'post_type'   => 'samlab_arrangement',
			'post_status' => 'publish',
			'post_title'  => 'G5 arrangement -' . $dager_siden . 'd',
		)
	);
	update_post_meta( $arrangement, '_samlab_start', gmdate( 'Y-m-d H:i', time() - $dager_siden * DAY_IN_SECONDS ) );
	$rydd[] = $arrangement;
}

// Aktivt medlem: fersk bruker med én reaksjon nå.
$aktiv_id = wp_insert_user(
	array(
		'user_login' => 'g5.aktiv',
		'user_email' => 'g5.aktiv@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'samlab_member',
	)
);
Samlab_Reaksjon::add( 'innlegg', 999999, $aktiv_id, 'like' );

// 2) Deltaene per periode.
$forventet = array(
	30  => array(
		'nye_behov'             => 1,
		'matchforslag'          => 1,
		'forespurte'            => 1,
		'godkjente'             => 1,
		'avviste'               => 0,
		'introduserte'          => 1,
		'utfall_avtale'         => 1,
		'arrangementer_avholdt' => 1,
		'aktive_medlemmer'      => 1,
	),
	90  => array(
		'nye_behov'             => 1,
		'matchforslag'          => 1,
		'forespurte'            => 2,
		'godkjente'             => 1,
		'avviste'               => 1,
		'introduserte'          => 1,
		'utfall_avtale'         => 1,
		'arrangementer_avholdt' => 1,
		'aktive_medlemmer'      => 1,
	),
	365 => array(
		'nye_behov'             => 1,
		'matchforslag'          => 1,
		'forespurte'            => 2,
		'godkjente'             => 1,
		'avviste'               => 1,
		'introduserte'          => 2,
		'utfall_avtale'         => 1,
		'arrangementer_avholdt' => 2,
		'aktive_medlemmer'      => 1,
	),
);
foreach ( $forventet as $dager => $deltaer ) {
	$tall = samlab_rapport_tall( $dager );
	foreach ( $deltaer as $nokkel => $delta ) {
		sjekk( "{$dager}d: {$nokkel} +{$delta}", $tall[ $nokkel ] - $basis[ $dager ][ $nokkel ] === $delta );
	}
}

// 3) CSV-formatet: header, alle rader, semikolon og siterte felt.
$csv    = samlab_rapport_csv_tekst();
$linjer = array_filter( explode( "\r\n", $csv ) );
sjekk( 'CSV har header + alle måltall + lesegrad', count( samlab_rapport_etiketter() ) + 2 === count( $linjer ) );
sjekk( 'CSV-headeren har fire kolonner', 4 === count( explode( ';', $linjer[0] ) ) && false !== strpos( $linjer[0], 'Siste 30 dager' ) );
sjekk( 'CSV-feltene er siterte', '"' === $csv[0] && false !== strpos( $csv, '"Matchforslag";' ) );
$matchrad = array();
foreach ( $linjer as $linje ) {
	if ( 0 === strpos( $linje, '"Matchforslag"' ) ) {
		$matchrad = str_getcsv( $linje, ';', '"', '\\' );
	}
}
sjekk( 'CSV-tallene samsvarer med rapporten', (string) samlab_rapport_tall( 30 )['matchforslag'] === $matchrad[1] );

// 4) Tilgang: rendringen er tom uten koblings-capability, og
// lesegraden svarer uten feil.
wp_set_current_user( $medlem->ID );
ob_start();
samlab_render_rapport();
sjekk( 'medlem får ingen rapport', '' === ob_get_clean() );
wp_set_current_user( $moderator->ID );
ob_start();
samlab_render_rapport();
$html = ob_get_clean();
sjekk( 'moderator får rapporten med periodevalg', false !== strpos( $html, 'Samlab rapport' ) && false !== strpos( $html, 'Siste 365 dager' ) );
sjekk( 'rapporten navngir ingen', false === strpos( $html, $jonas->display_name ) && false === strpos( $html, 'Brygga Design' ) );
$lesegrad = samlab_rapport_lesegrad();
sjekk( 'lesegraden er prosent eller null', null === $lesegrad || ( is_int( $lesegrad ) && $lesegrad >= 0 && $lesegrad <= 100 ) );

// Rydd.
foreach ( $rydd as $post_id ) {
	if ( ! is_wp_error( $post_id ) ) {
		wp_delete_post( $post_id, true );
	}
}
global $wpdb;
$wpdb->delete( samlab_table( 'reaksjoner' ), array( 'user_id' => $aktiv_id ), array( '%d' ) );
wp_delete_user( $aktiv_id );

exit( $fail );
