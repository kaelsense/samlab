<?php
// Røyk-test for G4: utfallsregistrering («ble det noe?») - vakter,
// CM-veien i kontrollpanelet, parts-veien over REST, metaboks-
// overstyring, påminnelse nøyaktig én gang, og visning i
// kontrollpanel og portalflate.
// Kjøres med: wp eval-file test-g4.php

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

function g4_kobling( $brygga, $jonas, $tittel = 'G4-test' ) {
	return samlab_opprett_kobling(
		array(
			'tittel'      => $tittel,
			'begrunnelse' => 'Utfallstest.',
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

function g4_utfall_rest( $kobling_id, $utfall, $notat = '' ) {
	$request = new WP_REST_Request( 'POST', '/samlab/v1/koblinger/' . $kobling_id . '/utfall' );
	$request->set_body_params( array_filter( array( 'utfall' => $utfall, 'notat' => $notat ) ) );
	return rest_do_request( $request );
}

function g4_varsler_av_type( $user_id, $type, $kobling_id ) {
	$treff = array();
	foreach ( Samlab_Varsel::for_user( $user_id, 50 ) as $rad ) {
		if ( $type === $rad->type && 'kobling' === $rad->object_type && $kobling_id === (int) $rad->object_id ) {
			$treff[] = $rad;
		}
	}
	return $treff;
}

// 1) Vaktene i samlab_sett_kobling_utfall.
$kobling = g4_kobling( $brygga, $jonas );
$rydd[]  = $kobling;
$resultat = samlab_sett_kobling_utfall( $kobling, 'avtale' );
sjekk( 'utfall før introduksjonen avvises', is_wp_error( $resultat ) && 'samlab_feil_status' === $resultat->get_error_code() );
samlab_sett_kobling_status( $kobling, 'introdusert', $moderator->ID );
sjekk( 'ugyldig utfall avvises', is_wp_error( samlab_sett_kobling_utfall( $kobling, 'gevinst' ) ) );
sjekk( 'ikke-kobling avvises', is_wp_error( samlab_sett_kobling_utfall( $brygga->ID, 'avtale' ) ) );

// 2) CM-veien: fulgt opp-handlingen med utfall og notat.
$fyrt = array();
add_action(
	'samlab_kobling_utfall_satt',
	function ( $id, $utfall ) use ( &$fyrt ) {
		$fyrt[] = $utfall;
	},
	10,
	2
);
sjekk( 'medlem uten cap avvises', is_wp_error( samlab_kontrollpanel_utfor( $kobling, 'fulgt_opp', $medlem->ID, 'avtale' ) ) );
sjekk( 'CM fører utfall med fulgt opp', true === samlab_kontrollpanel_utfor( $kobling, 'fulgt_opp', $moderator->ID, 'avtale', 'Rammeavtale om design' ) );
sjekk( 'status er fulgt opp', 'fulgt_opp' === get_post_meta( $kobling, '_samlab_status', true ) );
$utfall = samlab_kobling_utfall( $kobling );
sjekk( 'utfall og notat er lagret', null !== $utfall && 'avtale' === $utfall['slug'] && 'Rammeavtale om design' === $utfall['notat'] );
sjekk( 'utfall-action fyrte', array( 'avtale' ) === $fyrt );
$logg = wp_list_pluck( get_post_meta( $kobling, '_samlab_statuslogg', true ), 'status' );
sjekk( 'loggen fører utfallet før løftet', array( 'utfall_avtale', 'fulgt_opp' ) === array_slice( $logg, -2 ) );

// 3) Parts-veien over REST.
$kobling2 = g4_kobling( $brygga, $jonas );
$rydd[]   = $kobling2;
samlab_sett_kobling_status( $kobling2, 'introdusert', $moderator->ID );

wp_set_current_user( 0 );
sjekk( 'utfall utlogget gir 401', 401 === g4_utfall_rest( $kobling2, 'mote' )->get_status() );
wp_set_current_user( $medlem->ID );
sjekk( 'utfall fra ikke-part gir 403', 403 === g4_utfall_rest( $kobling2, 'mote' )->get_status() );
wp_set_current_user( $jonas->ID );
sjekk( 'ugyldig utfall gir 400', 400 === g4_utfall_rest( $kobling2, 'gevinst' )->get_status() );
$svar = g4_utfall_rest( $kobling2, 'mote', 'Godt kvarter over kaffen' );
sjekk( 'parten fører utfall (200, fulgt opp)', 200 === $svar->get_status() && 'fulgt_opp' === $svar->get_data()['status'] );
sjekk( 'svaret bærer utfallet', 'mote' === $svar->get_data()['utfall']['slug'] && 'Godt kvarter over kaffen' === $svar->get_data()['utfall']['notat'] );

$kobling3 = g4_kobling( $brygga, $jonas );
$rydd[]   = $kobling3;
samlab_sett_kobling_status( $kobling3, 'forespurt', $moderator->ID );
sjekk( 'utfall på forespurt kobling gir 409', 409 === g4_utfall_rest( $kobling3, 'mote' )->get_status() );

// 4) Notatet kappes til 500 tegn, og metaboks-veien kan justere.
samlab_sett_kobling_utfall( $kobling2, 'mote', str_repeat( 'a', 600 ), $moderator->ID );
sjekk( 'notatet kappes til 500 tegn', 500 === mb_strlen( samlab_kobling_utfall( $kobling2 )['notat'] ) );
wp_set_current_user( 1 );
$_POST = array(
	'samlab_kobling_nonce' => wp_create_nonce( 'samlab_kobling_meta' ),
	'samlab_status'        => 'fulgt_opp',
	'samlab_utfall'        => 'henvisning',
	'samlab_utfall_notat'  => 'Sendte dem videre til fotografen',
);
samlab_save_kobling_meta( $kobling2 );
$_POST = array();
$utfall = samlab_kobling_utfall( $kobling2 );
sjekk( 'metaboksen justerer utfallet', 'henvisning' === $utfall['slug'] && 'Sendte dem videre til fotografen' === $utfall['notat'] );

// 5) Påminnelsen: nøyaktig én gang, kun for gamle introduserte uten
// utfall.
$gammel = g4_kobling( $brygga, $jonas, 'G4 gammel introduksjon' );
$rydd[]  = $gammel;
samlab_sett_kobling_status( $gammel, 'introdusert', $moderator->ID );
$logg = get_post_meta( $gammel, '_samlab_statuslogg', true );
foreach ( $logg as $i => $rad ) {
	if ( 'introdusert' === $rad['status'] ) {
		$logg[ $i ]['tid'] = gmdate( 'Y-m-d H:i:s', time() - 15 * DAY_IN_SECONDS );
	}
}
update_post_meta( $gammel, '_samlab_statuslogg', $logg );

$fersk = g4_kobling( $brygga, $jonas, 'G4 fersk introduksjon' );
$rydd[] = $fersk;
samlab_sett_kobling_status( $fersk, 'introdusert', $moderator->ID );

sjekk( 'én kobling får påminnelse', 1 === samlab_kobling_utfall_paminnelser() );
sjekk( 'begge parter er påminnet', 1 === count( g4_varsler_av_type( $jonas->ID, 'kobling_utfall_paminnelse', $gammel ) ) && 1 === count( g4_varsler_av_type( $kari->ID, 'kobling_utfall_paminnelse', $gammel ) ) );
sjekk( 'fersk introduksjon påminnes ikke', 0 === count( g4_varsler_av_type( $jonas->ID, 'kobling_utfall_paminnelse', $fersk ) ) );
sjekk( 'ny runde sender ingenting (én gang per kobling)', 0 === samlab_kobling_utfall_paminnelser() && 1 === count( g4_varsler_av_type( $jonas->ID, 'kobling_utfall_paminnelse', $gammel ) ) );
do_action( 'samlab_matching_kjort', array() );
sjekk( 'matching-cronen re-sender heller ikke', 1 === count( g4_varsler_av_type( $jonas->ID, 'kobling_utfall_paminnelse', $gammel ) ) );
$visning = samlab_varsel_visning( g4_varsler_av_type( $jonas->ID, 'kobling_utfall_paminnelse', $gammel )[0] );
sjekk( 'påminnelsen spør og lenker til flaten', false !== strpos( $visning['tekst'], 'Ble det noe' ) && samlab_portal_url( 'koblinger' ) === $visning['lenke'] );

// 6) Visning: kontrollpanelet og portalflaten.
wp_set_current_user( 1 );
ob_start();
samlab_render_kontrollpanel();
$html = ob_get_clean();
sjekk( 'kontrollpanelet viser utfallet', false !== strpos( $html, 'Avtale inngått' ) && false !== strpos( $html, 'Rammeavtale om design' ) );
sjekk( 'fulgt opp-handlingen har utfallsvalg', false !== strpos( $html, 'name="samlab_utfall"' ) );

wp_set_current_user( $jonas->ID );
ob_start();
samlab_render_portal( 'koblinger' );
$flate = ob_get_clean();
sjekk( 'flaten har utfall-skjema på introdusert kobling', false !== strpos( $flate, 'samlab-kobling-utfall' ) && false !== strpos( $flate, 'Registrer utfall' ) );
sjekk( 'historikken viser utfallet', false !== strpos( $flate, 'Utfall: Avtale inngått' ) );

foreach ( $rydd as $post_id ) {
	if ( ! is_wp_error( $post_id ) ) {
		wp_delete_post( $post_id, true );
	}
}

exit( $fail );
