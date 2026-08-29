<?php
// Røyk-test for E2: in-app-varsler - utløsere, modell og opprydding.
// Kjøres med: wp eval-file test-e2.php  (etter reaktivering, DB v2)

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
sjekk( 'varseltabellen finnes', null !== $wpdb->get_var( 'SELECT COUNT(*) FROM ' . samlab_table( 'varsler' ) ) );
sjekk( 'db-versjon matcher koden', SAMLAB_DB_VERSION === get_option( 'samlab_db_version' ) );

$kari  = get_user_by( 'login', 'kari.demo' );
$jonas = get_user_by( 'login', 'jonas.demo' );
$ola   = get_user_by( 'login', 'ola.demo' );

// Nullstill for deterministisk telling.
$wpdb->query( 'DELETE FROM ' . samlab_table( 'varsler' ) );

// 1) Mention: Jonas nevner Kari (og seg selv - selv-varsel skal ikke skje).
$innlegg = Samlab_Innlegg::create(
	array(
		'user_id' => $jonas->ID,
		'content' => 'Takk @kari.demo for hjelpen! Hilsen @jonas.demo',
	)
);
do_action( 'samlab_innlegg_opprettet', $innlegg, $jonas->ID );
sjekk( 'mention ga varsel til Kari', 1 === Samlab_Varsel::unread_count( $kari->ID ) );
sjekk( 'ingen selv-varsel til Jonas', 0 === Samlab_Varsel::unread_count( $jonas->ID ) );

// 2) Kommentar på Jonas' innlegg fra Ola.
wp_insert_comment(
	array(
		'comment_post_ID'      => 0,
		'comment_type'         => 'samlab_innlegg',
		'comment_content'      => 'Enig!',
		'user_id'              => $ola->ID,
		'comment_author'       => $ola->display_name,
		'comment_author_email' => $ola->user_email,
		'comment_approved'     => 1,
		'comment_meta'         => array( '_samlab_innlegg' => $innlegg ),
	)
);
sjekk( 'kommentar ga varsel til forfatteren', 1 === Samlab_Varsel::unread_count( $jonas->ID ) );

// 3) Reaksjon fra Kari på Jonas' innlegg (via samme action REST bruker).
Samlab_Reaksjon::add( 'innlegg', $innlegg, $kari->ID );
do_action( 'samlab_reaksjon_endret', 'innlegg', $innlegg, $kari->ID, 'like', true );
sjekk( 'reaksjon ga varsel', 2 === Samlab_Varsel::unread_count( $jonas->ID ) );

// Dedup: samme reaksjon på nytt gir ikke dobbelt ulest varsel.
do_action( 'samlab_reaksjon_endret', 'innlegg', $innlegg, $kari->ID, 'like', true );
sjekk( 'identisk ulest varsel dedupliseres', 2 === Samlab_Varsel::unread_count( $jonas->ID ) );

// Fjerning av reaksjon varsler ikke.
do_action( 'samlab_reaksjon_endret', 'innlegg', $innlegg, $kari->ID, 'like', false );
sjekk( 'fjernet reaksjon varsler ikke', 2 === Samlab_Varsel::unread_count( $jonas->ID ) );

// 4) Kobling: moderator godkjenner - begge parter varsles, ikke moderatoren.
$moderator = get_user_by( 'login', 'testmod' );
$brygga    = get_page_by_path( 'brygga-design', OBJECT, 'samlab_bedrift' );
$kobling   = samlab_opprett_kobling(
	array(
		'tittel' => 'Brygga Design ↔ Jonas Dal',
		'part_a' => array(
			'type' => 'bedrift',
			'id'   => $brygga->ID,
		),
		'part_b' => array(
			'type' => 'bruker',
			'id'   => $jonas->ID,
		),
	)
);
$kari_for  = Samlab_Varsel::unread_count( $kari->ID );
$jonas_for = Samlab_Varsel::unread_count( $jonas->ID );
sjekk( 'foreslått varsler ikke partene', 1 === $kari_for && 2 === $jonas_for );
samlab_sett_kobling_status( $kobling, 'godkjent', $moderator->ID );
sjekk( 'godkjent varsler begge parter', Samlab_Varsel::unread_count( $kari->ID ) === $kari_for + 1 && Samlab_Varsel::unread_count( $jonas->ID ) === $jonas_for + 1 );
sjekk( 'moderatoren varsles ikke', 0 === Samlab_Varsel::unread_count( $moderator->ID ) );

// Tekst og lenke.
$siste   = Samlab_Varsel::for_user( $jonas->ID, 1 );
$visning = samlab_varsel_visning( $siste[0] );
sjekk( 'koblingsvarsel har lesbar tekst', false !== strpos( $visning['tekst'], 'godkjent' ) );
$mention_varsel = Samlab_Varsel::for_user( $kari->ID, 10 );
$m_visning      = samlab_varsel_visning( end( $mention_varsel ) );
sjekk( 'mention-varsel lenker til innlegget', false !== strpos( $m_visning['lenke'], '#innlegg-' . $innlegg ) );

// Lest-markering.
Samlab_Varsel::mark_all_read( $jonas->ID );
sjekk( 'markér alle som lest', 0 === Samlab_Varsel::unread_count( $jonas->ID ) );
sjekk( 'varslene finnes fortsatt etter lesing', count( Samlab_Varsel::for_user( $jonas->ID ) ) >= 3 );

// Nytt identisk varsel er nå lov (forrige er lest).
do_action( 'samlab_reaksjon_endret', 'innlegg', $innlegg, $kari->ID, 'like', true );
sjekk( 'dedup gjelder kun uleste', 1 === Samlab_Varsel::unread_count( $jonas->ID ) );

// Kaskade: sletting av innlegget fjerner varslene knyttet til det.
Samlab_Innlegg::delete( $innlegg );
$rest = 0;
foreach ( Samlab_Varsel::for_user( $jonas->ID, 100 ) as $v ) {
	if ( 'innlegg' === $v->object_type && (int) $v->object_id === (int) $innlegg ) {
		$rest++;
	}
}
sjekk( 'sletting av innlegg kaskaderer varsler', 0 === $rest );

wp_delete_post( $kobling, true );
exit( $fail );
