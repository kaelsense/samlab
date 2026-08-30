<?php
// Røyk-test for G2: forespørsel-varsler og svar-endepunkt over REST.
// Hele flyten forespurt → varsler → to ja → godkjent → varsel, med
// 401/403/409-vaktene og kontaktinfo-sperren i forespurt-varselet.
// Kjøres med: wp eval-file test-g2.php

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

function g2_svar( $kobling_id, $svar ) {
	$request = new WP_REST_Request( 'POST', '/samlab/v1/koblinger/' . $kobling_id . '/svar' );
	$request->set_body_params( array( 'svar' => $svar ) );
	return rest_do_request( $request );
}

function g2_liste() {
	return rest_do_request( new WP_REST_Request( 'GET', '/samlab/v1/koblinger' ) );
}

function g2_finn( $svar, $kobling_id ) {
	foreach ( (array) $svar->get_data()['koblinger'] as $rad ) {
		if ( $kobling_id === $rad['id'] ) {
			return $rad;
		}
	}
	return null;
}

function g2_varsler_av_type( $user_id, $type, $kobling_id ) {
	$treff = array();
	foreach ( Samlab_Varsel::for_user( $user_id, 50 ) as $rad ) {
		if ( $type === $rad->type && 'kobling' === $rad->object_type && $kobling_id === (int) $rad->object_id ) {
			$treff[] = $rad;
		}
	}
	return $treff;
}

$kobling = samlab_opprett_kobling(
	array(
		'tittel'      => 'Brygga Design ↔ Jonas Dal',
		'begrunnelse' => 'Jonas trenger designhjelp til lanseringen.',
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

// 1) Vaktene: 401 utlogget, 403 ikke-part, 404 ukjent, 409 feil status.
wp_set_current_user( 0 );
sjekk( 'GET koblinger utlogget gir 401', 401 === g2_liste()->get_status() );
sjekk( 'svar utlogget gir 401', 401 === g2_svar( $kobling, 'ja' )->get_status() );

wp_set_current_user( $medlem->ID );
sjekk( 'svar fra ikke-part gir 403', 403 === g2_svar( $kobling, 'ja' )->get_status() );
sjekk( 'svar på ukjent kobling gir 404', 404 === g2_svar( $brygga->ID, 'ja' )->get_status() );
sjekk( 'listen til ikke-part er uten koblingen', null === g2_finn( g2_liste(), $kobling ) );

wp_set_current_user( $jonas->ID );
sjekk( 'svar på foreslått kobling gir 409', 409 === g2_svar( $kobling, 'ja' )->get_status() );

// 2) Forespørselen: varsel til begge parter, med begrunnelse, uten
// kontaktinfo.
samlab_kontrollpanel_utfor( $kobling, 'godkjenn', $moderator->ID );
$kari_forespurt  = g2_varsler_av_type( $kari->ID, 'kobling_forespurt', $kobling );
$jonas_forespurt = g2_varsler_av_type( $jonas->ID, 'kobling_forespurt', $kobling );
sjekk( 'forespurt varsler begge parter', 1 === count( $kari_forespurt ) && 1 === count( $jonas_forespurt ) );

$visning = samlab_varsel_visning( $jonas_forespurt[0] );
sjekk( 'forespurt-varselet bærer begrunnelsen', false !== strpos( $visning['tekst'], 'designhjelp til lanseringen' ) );
sjekk( 'forespurt-varselet er uten kontaktinfo', false === strpos( $visning['tekst'], $kari->user_email ) && false === strpos( $visning['tekst'], $jonas->user_email ) );

// 3) Listen for en part: status, samtykke og sperret kontaktinfo.
$rad = g2_finn( g2_liste(), $kobling );
sjekk( 'parten ser koblingen i listen', null !== $rad );
sjekk( 'listen viser forespurt og venter', 'forespurt' === $rad['status'] && 'venter' === $rad['mitt_samtykke'] && 'b' === $rad['min_part'] );
sjekk( 'motpartens navn vises, kontakt sperres', 'Brygga Design' === $rad['motpart'] && null === $rad['motpart_kontakt'] );

// 4) To ja over REST: forespurt → godkjent, varsler og kontaktinfo.
wp_set_current_user( $kari->ID );
$svar1 = g2_svar( $kobling, 'ja' );
sjekk( 'første ja gir 200 og forespurt', 200 === $svar1->get_status() && 'forespurt' === $svar1->get_data()['status'] && 'ja' === $svar1->get_data()['mitt_samtykke'] );

wp_set_current_user( $jonas->ID );
$svar2 = g2_svar( $kobling, 'ja' );
sjekk( 'andre ja gir 200 og godkjent', 200 === $svar2->get_status() && 'godkjent' === $svar2->get_data()['status'] );
sjekk( 'godkjent deler motpartens kontakt', is_array( $svar2->get_data()['motpart_kontakt'] ) && $kari->user_email === $svar2->get_data()['motpart_kontakt']['epost'] );
sjekk( 'godkjent varslet begge parter', 1 === count( g2_varsler_av_type( $kari->ID, 'kobling_godkjent', $kobling ) ) && 1 === count( g2_varsler_av_type( $jonas->ID, 'kobling_godkjent', $kobling ) ) );
sjekk( 'moderator varsles når begge har svart', 1 === count( g2_varsler_av_type( $moderator->ID, 'kobling_besvart', $kobling ) ) );
sjekk( 'svar etter godkjent gir 409', 409 === g2_svar( $kobling, 'ja' )->get_status() );

// 5) Ett nei: avvist, nøytralt varsel til motparten uten navn.
$kobling2 = samlab_opprett_kobling(
	array(
		'tittel'      => 'G2 avslagstest',
		'begrunnelse' => 'Test av nei-flyten.',
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
samlab_kontrollpanel_utfor( $kobling2, 'godkjenn', $moderator->ID );
wp_set_current_user( $kari->ID );
g2_svar( $kobling2, 'ja' );
wp_set_current_user( $jonas->ID );
$nei = g2_svar( $kobling2, 'nei' );
sjekk( 'nei gir 200 og avvist', 200 === $nei->get_status() && 'avvist' === $nei->get_data()['status'] );

$ikke_noe = g2_varsler_av_type( $kari->ID, 'kobling_ikke_noe', $kobling2 );
sjekk( 'motparten får nøytralt varsel', 1 === count( $ikke_noe ) && 0 === (int) $ikke_noe[0]->actor_id );
$visning = samlab_varsel_visning( $ikke_noe[0] );
sjekk( 'nei-varselet navngir ingen', false === strpos( $visning['tekst'], $jonas->display_name ) && false === strpos( $visning['tekst'], 'takket' ) );
sjekk( 'den som takket nei får ikke nøytralt varsel', 0 === count( g2_varsler_av_type( $jonas->ID, 'kobling_ikke_noe', $kobling2 ) ) );
sjekk( 'moderator varsles også ved nei', 1 === count( g2_varsler_av_type( $moderator->ID, 'kobling_besvart', $kobling2 ) ) );

// Rydd: varsler kaskadeslettes med koblingene.
foreach ( $rydd as $post_id ) {
	if ( ! is_wp_error( $post_id ) ) {
		wp_delete_post( $post_id, true );
	}
}

exit( $fail );
