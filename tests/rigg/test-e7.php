<?php
// Røyk-test for E7: avstemninger på vegginnlegg - modellen og
// opprette/stemme/endre-flyten. REST-flaten (401 utlogget, 400/404,
// stemme + endring over HTTP) verifiseres i tillegg med curl.
// Kjøres med: wp eval-file test-e7.php

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

global $wpdb;
$kari   = get_user_by( 'login', 'kari.demo' );
$jonas  = get_user_by( 'login', 'jonas.demo' );
$medlem = get_user_by( 'login', 'testmedlem' );

// --- Opprette: avstemning følger innlegget ---
$innlegg_id = Samlab_Innlegg::create(
	array(
		'user_id'       => $kari->ID,
		'content'       => 'Avstemningstest: fredagslunsj',
		'poll_sporsmal' => 'Hva skal vi ha til fredagslunsj?',
		'poll_valg'     => array( 'Taco', 'Sushi', 'Suppe' ),
	)
);
$poll = Samlab_Innlegg::poll( Samlab_Innlegg::get( $innlegg_id ) );
sjekk( 'avstemningen lagres med innlegget', null !== $poll && 'Hva skal vi ha til fredagslunsj?' === $poll['sporsmal'] );
sjekk( 'alle tre alternativene er med', array( 'Taco', 'Sushi', 'Suppe' ) === $poll['valg'] );

// --- Ugyldige avstemninger avvises stille i modellen ---
$en_valg = Samlab_Innlegg::create(
	array(
		'user_id'       => $kari->ID,
		'content'       => 'Ett alternativ',
		'poll_sporsmal' => 'Spørsmål?',
		'poll_valg'     => array( 'Bare ett' ),
	)
);
sjekk( 'ett alternativ gir ingen avstemning', null === Samlab_Innlegg::poll( Samlab_Innlegg::get( $en_valg ) ) );
$seks_valg = Samlab_Innlegg::create(
	array(
		'user_id'       => $kari->ID,
		'content'       => 'Seks alternativer',
		'poll_sporsmal' => 'Spørsmål?',
		'poll_valg'     => array( 'a', 'b', 'c', 'd', 'e', 'f' ),
	)
);
sjekk( 'seks alternativer gir ingen avstemning', null === Samlab_Innlegg::poll( Samlab_Innlegg::get( $seks_valg ) ) );

// --- Stemme: én per medlem, riktige tall ---
Samlab_Stemme::vote( $innlegg_id, $kari->ID, 0 );
Samlab_Stemme::vote( $innlegg_id, $jonas->ID, 1 );
Samlab_Stemme::vote( $innlegg_id, $medlem->ID, 1 );
sjekk( 'stemmetallene er riktige', array( 1, 2, 0 ) === Samlab_Stemme::counts( $innlegg_id, 3 ) );
sjekk( 'kari sitt valg er registrert', 0 === Samlab_Stemme::user_choice( $innlegg_id, $kari->ID ) );

// --- Endre: samme medlem stemmer på nytt ---
Samlab_Stemme::vote( $innlegg_id, $kari->ID, 1 );
sjekk( 'endret stemme flytter tallet', array( 0, 3, 0 ) === Samlab_Stemme::counts( $innlegg_id, 3 ) );
sjekk( 'valget er oppdatert', 1 === Samlab_Stemme::user_choice( $innlegg_id, $kari->ID ) );

$tabell = samlab_table( 'stemmer' );
$rader  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabell} WHERE innlegg_id = %d AND user_id = %d", $innlegg_id, $kari->ID ) ); // phpcs:ignore
sjekk( 'fortsatt én rad per medlem', 1 === $rader );

// --- Kaskade ved sletting ---
Samlab_Innlegg::delete( $innlegg_id );
$rader = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabell} WHERE innlegg_id = %d", $innlegg_id ) ); // phpcs:ignore
sjekk( 'stemmene slettes med innlegget', 0 === $rader );

// --- Rydd ---
Samlab_Innlegg::delete( $en_valg );
Samlab_Innlegg::delete( $seks_valg );
exit( $fail );
