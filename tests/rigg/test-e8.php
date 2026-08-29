<?php
// Røyk-test for E8: lesebekreftelser på festede oppslag - modellen,
// oversikten og vaktene. REST-flyten (401 utlogget, bekreft én gang,
// idempotens, toggle-vakten) verifiseres i tillegg med curl.
// Kjøres med: wp eval-file test-e8.php

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

$kari   = get_user_by( 'login', 'kari.demo' );
$jonas  = get_user_by( 'login', 'jonas.demo' );
$medlem = get_user_by( 'login', 'testmedlem' );

// --- Moderator krever lest på et festet oppslag ---
$innlegg_id = Samlab_Innlegg::create(
	array(
		'user_id' => $kari->ID,
		'content' => 'Viktig: brannøvelse på torsdag',
		'pinned'  => 1,
	)
);
sjekk( 'lest-krav kan settes', Samlab_Innlegg::update( $innlegg_id, array( 'confirm_read' => 1 ) ) );
sjekk( 'flagget leses tilbake', ! empty( Samlab_Innlegg::get( $innlegg_id )->confirm_read ) );

// --- Bekrefte: én gang per medlem ---
Samlab_Reaksjon::add( 'innlegg', $innlegg_id, $jonas->ID, 'lest' );
Samlab_Reaksjon::add( 'innlegg', $innlegg_id, $medlem->ID, 'lest' );
$dobbel = Samlab_Reaksjon::add( 'innlegg', $innlegg_id, $jonas->ID, 'lest' );
$antall = Samlab_Reaksjon::counts( 'innlegg', $innlegg_id );
sjekk( 'gjentatt bekreftelse er idempotent (fortsatt to)', true === $dobbel && 2 === $antall['lest'] );
sjekk( 'brukerlisten stemmer', array( $jonas->ID, $medlem->ID ) === array_values( array_intersect( Samlab_Reaksjon::users( 'innlegg', $innlegg_id, 'lest' ), array( $jonas->ID, $medlem->ID ) ) ) );

// --- Oversikten i kontrollpanelet ---
$oversikt = samlab_kp_lesebekreftelser();
$rad      = null;
foreach ( $oversikt as $o ) {
	if ( (int) $o['innlegg']->id === $innlegg_id ) {
		$rad = $o;
	}
}
sjekk( 'oppslaget er i oversikten', null !== $rad );
$bekreftet_navn = $rad ? wp_list_pluck( $rad['bekreftet'], 'user_login' ) : array();
$mangler_navn   = $rad ? wp_list_pluck( $rad['mangler'], 'user_login' ) : array();
sjekk( 'jonas og testmedlem står som bekreftet', in_array( 'jonas.demo', $bekreftet_navn, true ) && in_array( 'testmedlem', $bekreftet_navn, true ) );
sjekk( 'kari står som ikke bekreftet', in_array( 'kari.demo', $mangler_navn, true ) );
sjekk( 'bekreftet står ikke som manglende', ! in_array( 'jonas.demo', $mangler_navn, true ) );

// --- Kun moderator+ ser oversikten (siden er bak edit_samlab_koblinger) ---
sjekk( 'medlem har ikke kontrollpanel-cap', ! user_can( $medlem, 'edit_samlab_koblinger' ) );
sjekk( 'moderator har kontrollpanel-cap', user_can( get_user_by( 'login', 'testmod' ), 'edit_samlab_koblinger' ) );

// --- Lest-kravet følger festingen ---
Samlab_Innlegg::update( $innlegg_id, array( 'confirm_read' => 0 ) );
$oversikt_uten = wp_list_pluck( wp_list_pluck( samlab_kp_lesebekreftelser(), 'innlegg' ), 'id' );
sjekk( 'uten flagg er oppslaget ute av oversikten', ! in_array( (string) $innlegg_id, array_map( 'strval', $oversikt_uten ), true ) );

// --- Rydd ---
Samlab_Innlegg::delete( $innlegg_id );
sjekk( 'bekreftelsene slettes med innlegget', array() === Samlab_Reaksjon::users( 'innlegg', $innlegg_id, 'lest' ) );
exit( $fail );
