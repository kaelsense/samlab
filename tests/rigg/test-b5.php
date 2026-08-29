<?php
// Røyk-test for B5: CPT behov, taksonomier og meta-lagring.
// Kjøres med: wp eval-file test-b5.php

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

sjekk( 'CPT samlab_behov registrert', post_type_exists( 'samlab_behov' ) );
sjekk( 'Taksonomi samlab_retning registrert', taxonomy_exists( 'samlab_retning' ) );
sjekk( 'Taksonomi samlab_behovstype registrert', taxonomy_exists( 'samlab_behovstype' ) );
sjekk( 'Retningstermen trenger finnes', (bool) term_exists( 'trenger', 'samlab_retning' ) );
sjekk( 'Retningstermen tilbyr finnes', (bool) term_exists( 'tilbyr', 'samlab_retning' ) );
sjekk( 'CPT er ikke offentlig', ! get_post_type_object( 'samlab_behov' )->public );

wp_set_current_user( 1 );

$bedrift_id = wp_insert_post(
	array(
		'post_type'   => 'samlab_bedrift',
		'post_title'  => 'Koblingsbedrift AS',
		'post_status' => 'publish',
	)
);
$vanlig_post = wp_insert_post(
	array(
		'post_title'  => 'Ikke en bedrift',
		'post_status' => 'publish',
	)
);
$behov_id = wp_insert_post(
	array(
		'post_type'   => 'samlab_behov',
		'post_title'  => 'UX-designer til beta-appen',
		'post_status' => 'publish',
	)
);
sjekk( 'Behov opprettet', $behov_id > 0 );

$trenger = get_term_by( 'slug', 'trenger', 'samlab_retning' );
wp_set_object_terms( $behov_id, array( $trenger->term_id ), 'samlab_retning' );
$type = wp_insert_term( 'Trenger kompetanse eller rådgivning', 'samlab_behovstype' );
wp_set_object_terms( $behov_id, array( $type['term_id'] ), 'samlab_behovstype' );
sjekk( 'Retning tilordnet', array( 'Trenger' ) === wp_get_object_terms( $behov_id, 'samlab_retning', array( 'fields' => 'names' ) ) );
sjekk( 'Behovstype tilordnet', 1 === count( wp_get_object_terms( $behov_id, 'samlab_behovstype' ) ) );

// Simuler admin-skjemaets POST med XSS-forsøk.
$_POST = array(
	'samlab_behov_nonce' => wp_create_nonce( 'samlab_behov_meta' ),
	'samlab_frist'       => '1. september <script>alert(1)</script>',
	'samlab_budsjett'    => '20 000 - 40 000 kr',
	'samlab_kontaktform' => 'Kort videomøte',
	'samlab_kompetanse'  => "UX\n Figma \n\nMobil\n",
	'samlab_bedrift'     => (string) $bedrift_id,
);
samlab_save_behov_meta( $behov_id );

sjekk( 'frist lagret uten script', '1. september' === get_post_meta( $behov_id, '_samlab_frist', true ) );
sjekk( 'budsjett lagret', '20 000 - 40 000 kr' === get_post_meta( $behov_id, '_samlab_budsjett', true ) );
sjekk( 'kontaktform lagret', 'Kort videomøte' === get_post_meta( $behov_id, '_samlab_kontaktform', true ) );
sjekk( 'kompetanse = 3 trimmede linjer', array( 'UX', 'Figma', 'Mobil' ) === get_post_meta( $behov_id, '_samlab_kompetanse', true ) );
sjekk( 'bedriftskobling lagret', $bedrift_id === (int) get_post_meta( $behov_id, '_samlab_bedrift', true ) );

// Kobling til noe som ikke er en bedrift skal nulles.
$_POST['samlab_bedrift'] = (string) $vanlig_post;
samlab_save_behov_meta( $behov_id );
sjekk( 'ikke-bedrift som kobling nulles', 0 === (int) get_post_meta( $behov_id, '_samlab_bedrift', true ) );

// Ugyldig nonce skal avvises.
$_POST['samlab_behov_nonce'] = 'ugyldig';
$_POST['samlab_frist']       = 'Skal ikke lagres';
samlab_save_behov_meta( $behov_id );
sjekk( 'ugyldig nonce avvist', '1. september' === get_post_meta( $behov_id, '_samlab_frist', true ) );

// Medlem uten edit_post på behovet avvises.
$medlem = get_user_by( 'login', 'testmedlem' );
wp_set_current_user( $medlem->ID );
$_POST['samlab_behov_nonce'] = wp_create_nonce( 'samlab_behov_meta' );
samlab_save_behov_meta( $behov_id );
sjekk( 'medlem uten rettigheter avvist', '1. september' === get_post_meta( $behov_id, '_samlab_frist', true ) );

foreach ( array( $behov_id, $bedrift_id, $vanlig_post ) as $p ) {
	wp_delete_post( $p, true );
}
exit( $fail );
