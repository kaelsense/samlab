<?php
// Røyk-test for B6: egne tabeller for vegg og reaksjoner med CRUD.
// Kjøres med: wp eval-file test-b6.php

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

// Tabellene finnes (probe med SELECT - fungerer også på SQLite-riggen).
$innlegg_ok    = null !== $wpdb->get_var( 'SELECT COUNT(*) FROM ' . samlab_table( 'innlegg' ) );
$reaksjoner_ok = null !== $wpdb->get_var( 'SELECT COUNT(*) FROM ' . samlab_table( 'reaksjoner' ) );
sjekk( 'tabellen innlegg finnes', $innlegg_ok );
sjekk( 'tabellen reaksjoner finnes', $reaksjoner_ok );
sjekk( 'db-versjon lagret', SAMLAB_DB_VERSION === get_option( 'samlab_db_version' ) );

// Create.
sjekk( 'create uten innhold feiler', false === Samlab_Innlegg::create( array( 'user_id' => 1, 'content' => '  ' ) ) );
sjekk( 'create uten bruker feiler', false === Samlab_Innlegg::create( array( 'content' => 'Hei' ) ) );

$a = Samlab_Innlegg::create( array( 'user_id' => 1, 'content' => 'Første innlegg <script>alert(1)</script>' ) );
$b = Samlab_Innlegg::create( array( 'user_id' => 1, 'content' => 'Andre innlegg med <strong>utheving</strong>' ) );
$c = Samlab_Innlegg::create( array( 'user_id' => 1, 'content' => 'Tredje - skal festes' ) );
sjekk( 'tre innlegg opprettet', $a && $b && $c );

// Read + sanitering.
$rad = Samlab_Innlegg::get( $a );
sjekk( 'get henter innlegget', $rad && 1 === (int) $rad->user_id );
sjekk( 'script strippet av wp_kses_post', false === strpos( $rad->content, '<script' ) );
sjekk( 'trygg markup beholdt', false !== strpos( Samlab_Innlegg::get( $b )->content, '<strong>' ) );

// Update.
sjekk( 'feste innlegg', Samlab_Innlegg::update( $c, array( 'pinned' => 1 ) ) );
sjekk( 'skjule innlegg', Samlab_Innlegg::update( $b, array( 'status' => 'hidden' ) ) );
sjekk( 'update uten felter feiler', false === Samlab_Innlegg::update( $a, array() ) );
sjekk( 'update til tomt innhold feiler', false === Samlab_Innlegg::update( $a, array( 'content' => '' ) ) );

// Liste: publiserte, festet først, skjult utelatt.
$liste = Samlab_Innlegg::get_list();
$ids   = array_map( static fn( $r ) => (int) $r->id, $liste );
sjekk( 'skjult innlegg ikke i listen', ! in_array( (int) $b, $ids, true ) );
sjekk( 'festet innlegg først', (int) $c === $ids[0] );
sjekk( 'limit respekteres', 1 === count( Samlab_Innlegg::get_list( array( 'limit' => 1 ) ) ) );

// Reaksjoner.
sjekk( 'reaksjon legges til', Samlab_Reaksjon::add( 'innlegg', $a, 1, 'like' ) );
sjekk( 'reaksjon er idempotent', Samlab_Reaksjon::add( 'innlegg', $a, 1, 'like' ) );
Samlab_Reaksjon::add( 'innlegg', $a, 2, 'like' );
Samlab_Reaksjon::add( 'innlegg', $a, 2, 'heart' );
sjekk( 'user_has ser reaksjonen', Samlab_Reaksjon::user_has( 'innlegg', $a, 1, 'like' ) );
sjekk( 'counts teller riktig', array( 'heart' => 1, 'like' => 2 ) === Samlab_Reaksjon::counts( 'innlegg', $a ) );
sjekk( 'reaksjon fjernes', Samlab_Reaksjon::remove( 'innlegg', $a, 2, 'heart' ) );
sjekk( 'counts etter fjerning', array( 'like' => 2 ) === Samlab_Reaksjon::counts( 'innlegg', $a ) );
sjekk( 'ugyldig objekttype vaskes til innlegg', Samlab_Reaksjon::user_has( "innlegg'; DROP TABLE wp_users;--", $a, 1, 'like' ) );

// Delete med kaskade.
sjekk( 'delete sletter innlegget', Samlab_Innlegg::delete( $a ) );
sjekk( 'innlegget er borte', null === Samlab_Innlegg::get( $a ) );
sjekk( 'reaksjonene fulgte med', array() === Samlab_Reaksjon::counts( 'innlegg', $a ) );

// Rydd.
Samlab_Innlegg::delete( $b );
Samlab_Innlegg::delete( $c );
exit( $fail );
