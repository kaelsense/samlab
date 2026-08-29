<?php
// Røyk-test for B4: bedriftsredaktør kan kun redigere egen bedrift.
// Kjøres med: wp eval-file test-b4.php

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

// Redaktør A: opprettes ved behov så testen virker i fersk rigg.
$redaktor_a = get_user_by( 'login', 'testbedred' );
if ( ! $redaktor_a ) {
	$id         = wp_insert_user(
		array(
			'user_login' => 'testbedred',
			'user_email' => 'bedred@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'samlab_company_editor',
		)
	);
	$redaktor_a = get_user_by( 'id', $id );
}
$medlem    = get_user_by( 'login', 'testmedlem' );
$moderator = get_user_by( 'login', 'testmod' );

// Redaktør B: enda en bedriftsredaktør for krysstesten.
$redaktor_b = get_user_by( 'login', 'testbedred2' );
if ( ! $redaktor_b ) {
	$id         = wp_insert_user(
		array(
			'user_login' => 'testbedred2',
			'user_email' => 'bedred2@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'samlab_company_editor',
		)
	);
	$redaktor_b = get_user_by( 'id', $id );
}

$bedrift_a = wp_insert_post(
	array(
		'post_type'   => 'samlab_bedrift',
		'post_title'  => 'Bedrift A',
		'post_status' => 'publish',
		'meta_input'  => array( '_samlab_kontaktperson' => $redaktor_a->ID ),
	)
);
$bedrift_b = wp_insert_post(
	array(
		'post_type'   => 'samlab_bedrift',
		'post_title'  => 'Bedrift B',
		'post_status' => 'publish',
		'meta_input'  => array( '_samlab_kontaktperson' => $redaktor_b->ID ),
	)
);
$uten_kontakt = wp_insert_post(
	array(
		'post_type'   => 'samlab_bedrift',
		'post_title'  => 'Bedrift uten kontaktperson',
		'post_status' => 'publish',
	)
);

sjekk( 'redaktør A kan redigere bedrift A', user_can( $redaktor_a, 'edit_post', $bedrift_a ) );
sjekk( 'redaktør A kan IKKE redigere bedrift B', ! user_can( $redaktor_a, 'edit_post', $bedrift_b ) );
sjekk( 'redaktør B kan redigere bedrift B', user_can( $redaktor_b, 'edit_post', $bedrift_b ) );
sjekk( 'redaktør B kan IKKE redigere bedrift A', ! user_can( $redaktor_b, 'edit_post', $bedrift_a ) );
sjekk( 'ingen redigerer bedrift uten kontaktperson', ! user_can( $redaktor_a, 'edit_post', $uten_kontakt ) && ! user_can( $redaktor_b, 'edit_post', $uten_kontakt ) );
sjekk( 'redaktør A kan IKKE slette egen bedrift', ! user_can( $redaktor_a, 'delete_post', $bedrift_a ) );
sjekk( 'medlem kan IKKE redigere bedrift A', ! user_can( $medlem, 'edit_post', $bedrift_a ) );
sjekk( 'moderator kan IKKE redigere bedrift A', ! user_can( $moderator, 'edit_post', $bedrift_a ) );
sjekk( 'admin kan redigere begge', user_can( 1, 'edit_post', $bedrift_a ) && user_can( 1, 'edit_post', $bedrift_b ) );
sjekk( 'admin kan slette', user_can( 1, 'delete_post', $bedrift_a ) );
sjekk( 'vanlige poster upåvirket for admin', user_can( 1, 'edit_post', wp_insert_post( array( 'post_title' => 'Vanlig innlegg', 'post_status' => 'publish' ) ) ) );

// Meta-lagring: redaktør A kan lagre på egen bedrift, avvises på B.
wp_set_current_user( $redaktor_a->ID );
$_POST = array(
	'samlab_bedrift_nonce' => wp_create_nonce( 'samlab_bedrift_meta' ),
	'samlab_plass'         => 'Oppdatert av redaktør A',
);
samlab_save_bedrift_meta( $bedrift_a );
samlab_save_bedrift_meta( $bedrift_b );
sjekk( 'meta lagret på egen bedrift', 'Oppdatert av redaktør A' === get_post_meta( $bedrift_a, '_samlab_plass', true ) );
sjekk( 'meta avvist på annens bedrift', '' === get_post_meta( $bedrift_b, '_samlab_plass', true ) );

foreach ( array( $bedrift_a, $bedrift_b, $uten_kontakt ) as $p ) {
	wp_delete_post( $p, true );
}
exit( $fail );
