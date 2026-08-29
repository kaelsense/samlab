<?php
// Røyk-test for E1: kobling-CPT med statuskjede og part-tilgang.
// Kjøres med: wp eval-file test-e1.php  (etter reaktivering av pluginen)

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

sjekk( 'CPT samlab_kobling registrert', post_type_exists( 'samlab_kobling' ) );
sjekk( 'CPT er ikke offentlig', ! get_post_type_object( 'samlab_kobling' )->public );
sjekk( 'CPT bruker egne capability-primitiver', 'edit_samlab_koblinger' === get_post_type_object( 'samlab_kobling' )->cap->edit_posts );

$moderator = get_user_by( 'login', 'testmod' );
$medlem    = get_user_by( 'login', 'testmedlem' );
$kari      = get_user_by( 'login', 'kari.demo' );   // kontaktperson for Brygga Design (seed)
$jonas     = get_user_by( 'login', 'jonas.demo' );  // medlem (seed)
$brygga    = get_page_by_path( 'brygga-design', OBJECT, 'samlab_bedrift' );

sjekk( 'moderator har koblings-caps', user_can( $moderator, 'edit_samlab_koblinger' ) && user_can( $moderator, 'edit_others_samlab_koblinger' ) );
sjekk( 'medlem har IKKE koblings-caps', ! user_can( $medlem, 'edit_samlab_koblinger' ) );
sjekk( 'admin har koblings-caps', user_can( 1, 'edit_others_samlab_koblinger' ) );

// Opprett via helper (samme vei som E3/E4 skal bruke).
$kobling = samlab_opprett_kobling(
	array(
		'tittel'      => 'Brygga Design ↔ Jonas Dal',
		'begrunnelse' => 'Jonas trenger designhjelp til oppstarten.',
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
sjekk( 'kobling opprettet', ! is_wp_error( $kobling ) && $kobling > 0 );
sjekk( 'startstatus er foreslått', 'foreslatt' === get_post_meta( $kobling, '_samlab_status', true ) );
sjekk( 'kilde er matching', 'matching' === get_post_meta( $kobling, '_samlab_kilde', true ) );
sjekk( 'part A er bedriften', 'bedrift' === get_post_meta( $kobling, '_samlab_part_a_type', true ) && $brygga->ID === (int) get_post_meta( $kobling, '_samlab_part_a_id', true ) );

// Ugyldige parter avvises.
sjekk( 'vanlig innlegg avvises som bedriftspart', ! samlab_sett_kobling_part( $kobling, 'a', 'bedrift', 999999 ) );
sjekk( 'ukjent bruker avvises som part', ! samlab_sett_kobling_part( $kobling, 'b', 'bruker', 999999 ) );

// Statuskjeden.
$hendelser = array();
add_action(
	'samlab_kobling_status_endret',
	function ( $id, $status ) use ( &$hendelser ) {
		$hendelser[] = $status;
	},
	10,
	2
);
sjekk( 'godkjent settes', samlab_sett_kobling_status( $kobling, 'godkjent', $moderator->ID ) );
sjekk( 'introdusert settes', samlab_sett_kobling_status( $kobling, 'introdusert', $moderator->ID ) );
sjekk( 'fulgt_opp settes', samlab_sett_kobling_status( $kobling, 'fulgt_opp', $moderator->ID ) );
sjekk( 'ugyldig status avvises', ! samlab_sett_kobling_status( $kobling, 'tullball' ) );
sjekk( 'status-action fyrte per endring', array( 'godkjent', 'introdusert', 'fulgt_opp' ) === $hendelser );

$logg = get_post_meta( $kobling, '_samlab_statuslogg', true );
sjekk( 'statusloggen har 4 innslag (inkl. foreslått)', is_array( $logg ) && 4 === count( $logg ) );
sjekk( 'loggen navngir moderatoren', $moderator->ID === (int) $logg[1]['user_id'] );

// Metaboks-lagring (wp-admin-veien) som admin.
wp_set_current_user( 1 );
$_POST = array(
	'samlab_kobling_nonce'  => wp_create_nonce( 'samlab_kobling_meta' ),
	'samlab_status'         => 'godkjent',
	'samlab_part_a_bedrift' => (string) $brygga->ID,
	'samlab_part_b_bruker'  => (string) $jonas->ID,
);
samlab_save_kobling_meta( $kobling );
sjekk( 'metaboks-lagring satte status', 'godkjent' === get_post_meta( $kobling, '_samlab_status', true ) );

// Tilgang: parter leser, ingen andre; kun moderator+ endrer.
sjekk( 'jonas (part) kan lese', user_can( $jonas, 'read_post', $kobling ) );
sjekk( 'kari (kontaktperson for part-bedrift) kan lese', user_can( $kari, 'read_post', $kobling ) );
sjekk( 'jonas kan IKKE redigere', ! user_can( $jonas, 'edit_post', $kobling ) );
sjekk( 'kari kan IKKE redigere', ! user_can( $kari, 'edit_post', $kobling ) );
sjekk( 'utenforstående medlem kan IKKE lese', ! user_can( $medlem, 'read_post', $kobling ) );
sjekk( 'moderator kan lese og redigere', user_can( $moderator, 'read_post', $kobling ) && user_can( $moderator, 'edit_post', $kobling ) );
sjekk( 'admin kan redigere', user_can( 1, 'edit_post', $kobling ) );
sjekk( 'bedrifter/behov er upåvirket av koblings-caps', user_can( 1, 'edit_post', $brygga->ID ) );

wp_delete_post( $kobling, true );
exit( $fail );
