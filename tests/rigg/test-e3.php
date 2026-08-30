<?php
// Røyk-test for E3: kontrollpanelets lister og koblingshandlinger.
// Kjøres med: wp eval-file test-e3.php

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
$moderator = get_user_by( 'login', 'testmod' );
$medlem    = get_user_by( 'login', 'testmedlem' );
$jonas     = get_user_by( 'login', 'jonas.demo' );
$kari      = get_user_by( 'login', 'kari.demo' );
$brygga    = get_page_by_path( 'brygga-design', OBJECT, 'samlab_bedrift' );

$rydd = array();

// --- Kanttilfeller ---

// Ny bruker uten kobling (registrert nå).
$ny_id  = wp_insert_user(
	array(
		'user_login' => 'ny.uten.intro',
		'user_email' => 'ny@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'samlab_member',
	)
);
$rydd[] = array( 'bruker', $ny_id );

// Gammel stille bruker (registrert for 60 dager siden, ingen aktivitet).
$stille_id = wp_insert_user(
	array(
		'user_login' => 'stille.medlem',
		'user_email' => 'stille@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'samlab_member',
	)
);
$wpdb->update( $wpdb->users, array( 'user_registered' => gmdate( 'Y-m-d H:i:s', time() - 60 * DAY_IN_SECONDS ) ), array( 'ID' => $stille_id ) );
clean_user_cache( $stille_id );
$rydd[] = array( 'bruker', $stille_id );

// Gammelt behov (20 dager).
$gammelt_behov = wp_insert_post(
	array(
		'post_type'   => 'samlab_behov',
		'post_title'  => 'Gammelt åpent behov',
		'post_status' => 'publish',
		'post_date'   => gmdate( 'Y-m-d H:i:s', time() - 20 * DAY_IN_SECONDS ),
	)
);
$rydd[] = array( 'post', $gammelt_behov );

// Ufullstendig bedrift (ingen meta, ingen logo).
$tom_bedrift = wp_insert_post(
	array(
		'post_type'   => 'samlab_bedrift',
		'post_title'  => 'Tom Bedrift AS',
		'post_status' => 'publish',
	)
);
$rydd[] = array( 'post', $tom_bedrift );

// Jonas får en kobling (skal dermed IKKE stå som ny uten introduksjon).
$jonas_kobling = samlab_opprett_kobling(
	array(
		'tittel' => 'Jonas-kobling',
		'part_a' => array(
			'type' => 'bruker',
			'id'   => $jonas->ID,
		),
	)
);
$rydd[]        = array( 'post', $jonas_kobling );
// Jonas er registrert av seed i dag - han er «ny», men har kobling.

// --- Listene ---

$nye_navn = wp_list_pluck( samlab_kp_nye_uten_intro(), 'user_login' );
sjekk( 'ny bruker uten kobling er i listen', in_array( 'ny.uten.intro', $nye_navn, true ) );
sjekk( 'jonas (har kobling) er IKKE i listen', ! in_array( 'jonas.demo', $nye_navn, true ) );
sjekk( 'gammel bruker er IKKE i nye-listen', ! in_array( 'stille.medlem', $nye_navn, true ) );

$gamle_titler = wp_list_pluck( samlab_kp_gamle_behov(), 'post_title' );
sjekk( 'gammelt behov er i listen', in_array( 'Gammelt åpent behov', $gamle_titler, true ) );
sjekk( 'ferskt seed-behov er IKKE i listen', ! in_array( 'Fotograf til kundecaser', $gamle_titler, true ) );

$ufull = samlab_kp_ufullstendige_bedrifter();
$ufull_titler = array();
$tom_mangler  = array();
foreach ( $ufull as $rad ) {
	$ufull_titler[] = $rad['bedrift']->post_title;
	if ( 'Tom Bedrift AS' === $rad['bedrift']->post_title ) {
		$tom_mangler = $rad['mangler'];
	}
}
sjekk( 'tom bedrift er i ufullstendig-listen', in_array( 'Tom Bedrift AS', $ufull_titler, true ) );
sjekk( 'tom bedrift mangler alle fire felter', 4 === count( $tom_mangler ) );
sjekk( 'komplett seed-bedrift er IKKE i listen', ! in_array( 'Brygga Design', $ufull_titler, true ) );

$stille_navn = wp_list_pluck( samlab_kp_stille_medlemmer(), 'user_login' );
sjekk( 'gammel inaktiv bruker er i stille-listen', in_array( 'stille.medlem', $stille_navn, true ) );
sjekk( 'aktiv seed-bruker (kari) er IKKE stille', ! in_array( 'kari.demo', $stille_navn, true ) );
sjekk( 'helt ny bruker regnes ikke som stille', ! in_array( 'ny.uten.intro', $stille_navn, true ) );

// --- Koblingskøen og handlingene ---

$wpdb->query( 'DELETE FROM ' . samlab_table( 'varsler' ) );
$kobling = samlab_opprett_kobling(
	array(
		'tittel'      => 'Brygga Design ↔ Jonas Dal',
		'begrunnelse' => 'Matchforslag fra kontrollpaneltesten.',
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
$rydd[]  = array( 'post', $kobling );

$ko_ids = wp_list_pluck( samlab_kp_koblinger( array( 'foreslatt' ) ), 'ID' );
sjekk( 'forslaget ligger i køen', in_array( $kobling, $ko_ids, true ) );

// Medlem uten cap avvises.
$resultat = samlab_kontrollpanel_utfor( $kobling, 'godkjenn', $medlem->ID );
sjekk( 'medlem avvises fra handling', is_wp_error( $resultat ) && 'samlab_ingen_tilgang' === $resultat->get_error_code() );
sjekk( 'ukjent handling avvises', is_wp_error( samlab_kontrollpanel_utfor( $kobling, 'sabotasje', $moderator->ID ) ) );
sjekk( 'ikke-kobling avvises', is_wp_error( samlab_kontrollpanel_utfor( $brygga->ID, 'godkjenn', $moderator->ID ) ) );

// Moderator godkjenner - G1: koblingen settes til forespurt og
// venter på partenes samtykke.
$resultat = samlab_kontrollpanel_utfor( $kobling, 'godkjenn', $moderator->ID );
sjekk( 'moderator godkjenner', true === $resultat );
sjekk( 'status er forespurt', 'forespurt' === get_post_meta( $kobling, '_samlab_status', true ) );
sjekk( 'kobling er ute av køen', ! in_array( $kobling, wp_list_pluck( samlab_kp_koblinger( array( 'foreslatt' ) ), 'ID' ), true ) );
sjekk( 'kobling venter på partene', in_array( $kobling, wp_list_pluck( samlab_kp_koblinger( array( 'forespurt' ) ), 'ID' ), true ) );

// Begge parter takker ja - først da er koblingen godkjent, og
// «Ferdig når»-varselet til partene går ut.
sjekk( 'part A (kontaktperson) takker ja', true === samlab_kobling_svar( $kobling, 'a', 'ja', $kari->ID ) );
sjekk( 'ett ja er ikke nok', 'forespurt' === get_post_meta( $kobling, '_samlab_status', true ) );
sjekk( 'part B takker ja', true === samlab_kobling_svar( $kobling, 'b', 'ja', $jonas->ID ) );
sjekk( 'status er godkjent', 'godkjent' === get_post_meta( $kobling, '_samlab_status', true ) );
sjekk( 'godkjenning varslet begge parter', 2 === Samlab_Varsel::unread_count( $jonas->ID ) && 2 === Samlab_Varsel::unread_count( $kari->ID ) ); // Forespørsel (G2) + godkjent.
sjekk( 'kobling er i aktive-listen', in_array( $kobling, wp_list_pluck( samlab_kp_koblinger( array( 'godkjent', 'introdusert' ) ), 'ID' ), true ) );

// Videre i kjeden.
samlab_kontrollpanel_utfor( $kobling, 'introdusert', $moderator->ID );
samlab_kontrollpanel_utfor( $kobling, 'fulgt_opp', $moderator->ID );
sjekk( 'kjeden fullført til fulgt_opp', 'fulgt_opp' === get_post_meta( $kobling, '_samlab_status', true ) );
sjekk( 'fulgt_opp er ute av aktive-listen', ! in_array( $kobling, wp_list_pluck( samlab_kp_koblinger( array( 'godkjent', 'introdusert' ) ), 'ID' ), true ) );

sjekk( 'parts-teksten er lesbar', 'Brygga Design ↔ Jonas Dal' === samlab_kp_part_tekst( $kobling ) );

// Rydd.
foreach ( $rydd as $r ) {
	if ( 'post' === $r[0] ) {
		wp_delete_post( $r[1], true );
	} else {
		wp_delete_user( $r[1] );
	}
}
exit( $fail );
