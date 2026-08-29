<?php
// Røyk-test for B3: CPT, taksonomi og meta-lagring med sanitering.
// Kjøres med: wp eval-file test-b3.php

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

sjekk( 'CPT samlab_bedrift registrert', post_type_exists( 'samlab_bedrift' ) );
sjekk( 'Taksonomi samlab_kategori registrert', taxonomy_exists( 'samlab_kategori' ) );
sjekk( 'CPT støtter thumbnail', post_type_supports( 'samlab_bedrift', 'thumbnail' ) );
sjekk( 'CPT er ikke offentlig', ! get_post_type_object( 'samlab_bedrift' )->public );

wp_set_current_user( 1 ); // admin

$post_id = wp_insert_post(
	array(
		'post_type'   => 'samlab_bedrift',
		'post_title'  => 'Testbedrift AS',
		'post_status' => 'publish',
	)
);
sjekk( 'Bedrift opprettet', $post_id > 0 );

$term = wp_insert_term( 'Rådgivning', 'samlab_kategori' );
wp_set_object_terms( $post_id, array( $term['term_id'] ), 'samlab_kategori' );
$terms = wp_get_object_terms( $post_id, 'samlab_kategori', array( 'fields' => 'names' ) );
sjekk( 'Kategori tilordnet', array( 'Rådgivning' ) === $terms );

// Simuler admin-skjemaets POST, med XSS-forsøk i flere felter.
$_POST = array(
	'samlab_bedrift_nonce' => wp_create_nonce( 'samlab_bedrift_meta' ),
	'samlab_kort'          => "Kort tekst <script>alert(1)</script>",
	'samlab_plass'         => '3. etasje - fast plass',
	'samlab_nettside'      => 'javascript:alert(1)',
	'samlab_kontaktperson' => '1',
	'samlab_leverer'       => 'Nettsider og rådgivning',
	'samlab_kjoper'        => 'Regnskap',
	'samlab_trenger_na'    => "Designhjelp<img src=x onerror=alert(1)>",
	'samlab_idealkunder'   => 'SMB i huset',
	'samlab_apen_for'      => "Samarbeid\n\n  Kaffeprat  \n",
	'samlab_tjenester'     => array(
		array(
			'tittel'  => 'Nettsider <b>hei</b>',
			'punkter' => "WordPress\nUniversell utforming\n\n",
		),
		array(
			'tittel'  => '',
			'punkter' => '',
		),
	),
);
samlab_save_bedrift_meta( $post_id );

sjekk( 'kort lagret og script fjernet', 'Kort tekst' === get_post_meta( $post_id, '_samlab_kort', true ) );
sjekk( 'plass lagret', '3. etasje - fast plass' === get_post_meta( $post_id, '_samlab_plass', true ) );
sjekk( 'javascript:-URL avvist', '' === get_post_meta( $post_id, '_samlab_nettside', true ) );
sjekk( 'kontaktperson = 1', 1 === (int) get_post_meta( $post_id, '_samlab_kontaktperson', true ) );
sjekk( 'trenger_na uten img-tag', false === strpos( get_post_meta( $post_id, '_samlab_trenger_na', true ), '<img' ) );
sjekk( 'apen_for = 2 trimmede linjer', array( 'Samarbeid', 'Kaffeprat' ) === get_post_meta( $post_id, '_samlab_apen_for', true ) );

$tj = get_post_meta( $post_id, '_samlab_tjenester', true );
sjekk( 'tom tjeneste-rad droppet', 1 === count( $tj ) );
sjekk( 'tjeneste-tittel uten markup', 'Nettsider hei' === $tj[0]['tittel'] );
sjekk( 'tjeneste-punkter = 2', array( 'WordPress', 'Universell utforming' ) === $tj[0]['punkter'] );

// Gyldig URL skal beholdes.
$_POST['samlab_nettside'] = 'https://example.no/';
samlab_save_bedrift_meta( $post_id );
sjekk( 'gyldig URL beholdt', 'https://example.no/' === get_post_meta( $post_id, '_samlab_nettside', true ) );

// Uten gyldig nonce skal ingenting lagres.
$_POST['samlab_bedrift_nonce'] = 'ugyldig';
$_POST['samlab_plass']         = 'Skal ikke lagres';
samlab_save_bedrift_meta( $post_id );
sjekk( 'ugyldig nonce avvist', '3. etasje - fast plass' === get_post_meta( $post_id, '_samlab_plass', true ) );

// Bruker uten rettigheter skal avvises selv med gyldig nonce.
$medlem = get_user_by( 'login', 'testmedlem' );
wp_set_current_user( $medlem->ID );
$_POST['samlab_bedrift_nonce'] = wp_create_nonce( 'samlab_bedrift_meta' );
samlab_save_bedrift_meta( $post_id );
sjekk( 'medlem uten edit_post avvist', '3. etasje - fast plass' === get_post_meta( $post_id, '_samlab_plass', true ) );

wp_delete_post( $post_id, true );
exit( $fail );
