<?php
// Røyk-test for E4: regelbasert matching mot seed-data.
// Forventet fasit mot seed: nøyaktig ETT forslag - behovet
// «Ny nettside til regnskapsbyrå» (Tallknuserne, kompetanse
// WordPress/Design) matcher Brygga Design (leverer design og
// nettsider) på stammene «nettsid» og «design». Ingen andre
// behov/bedrift-par deler to stammer.
// Kjøres med: wp eval-file test-e4.php

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

// Rent utgangspunkt: fjern alle koblinger.
foreach ( get_posts(
	array(
		'post_type'      => 'samlab_kobling',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
) as $gammel ) {
	wp_delete_post( $gammel, true );
}

// Stemming-enheter.
sjekk( 'stammer: nettside == nettsider', samlab_match_stammer( 'nettside' ) === samlab_match_stammer( 'nettsider' ) );
sjekk( 'stoppord fjernes', array() === samlab_match_stammer( 'og til for med' ) );

// --- Kjøring 1 mot seed ---
$forslag = samlab_kjor_matching();
sjekk( 'nøyaktig ett forslag mot seed-data', 1 === count( $forslag ) );

$kobling = $forslag[0];
$brygga  = get_page_by_path( 'brygga-design', OBJECT, 'samlab_bedrift' );
$tall    = get_page_by_path( 'tallknuserne', OBJECT, 'samlab_bedrift' );

sjekk( 'part A er Brygga Design', $brygga->ID === (int) get_post_meta( $kobling, '_samlab_part_a_id', true ) );
sjekk( 'part B er Tallknuserne', $tall->ID === (int) get_post_meta( $kobling, '_samlab_part_b_id', true ) && 'bedrift' === get_post_meta( $kobling, '_samlab_part_b_type', true ) );
sjekk( 'status er foreslått (aldri automatisk introduksjon)', 'foreslatt' === get_post_meta( $kobling, '_samlab_status', true ) );
sjekk( 'kilde er matching', 'matching' === get_post_meta( $kobling, '_samlab_kilde', true ) );
$begrunnelse = get_post( $kobling )->post_content;
sjekk( 'begrunnelsen nevner behovet', false !== strpos( $begrunnelse, 'Ny nettside til regnskapsbyrå' ) );
sjekk( 'begrunnelsen viser felles stammer', false !== strpos( $begrunnelse, 'design' ) && false !== strpos( $begrunnelse, 'nettsid' ) );
sjekk( 'forslaget ligger i kontrollpanelets kø', in_array( $kobling, wp_list_pluck( samlab_kp_koblinger( array( 'foreslatt' ) ), 'ID' ), true ) );

// --- Kjøring 2: idempotens ---
sjekk( 'gjentatt kjøring gir null nye', array() === samlab_kjor_matching() );

// --- Avvist forslag gjenoppstår ikke ---
$moderator = get_user_by( 'login', 'testmod' );
samlab_kontrollpanel_utfor( $kobling, 'avvis', $moderator->ID );
sjekk( 'avvist forslag gjenoppstår ikke', array() === samlab_kjor_matching() );

// --- Konstruert tilfelle: ny bedrift som matcher foto-behovet ---
$foto = wp_insert_post(
	array(
		'post_type'   => 'samlab_bedrift',
		'post_title'  => 'Foto Fokus',
		'post_status' => 'publish',
		'meta_input'  => array(
			'_samlab_leverer' => 'Foto og redigering for bedrifter',
			'_samlab_kort'    => 'Fotograf i huset.',
		),
	)
);
$nye  = samlab_kjor_matching();
sjekk( 'ny bedrift utløser nytt forslag', 1 === count( $nye ) );
sjekk( 'forslaget gjelder Foto Fokus', $foto === (int) get_post_meta( $nye[0], '_samlab_match_bedrift', true ) );
$behov_id = (int) get_post_meta( $nye[0], '_samlab_match_behov', true );
sjekk( 'mot foto-behovet', 'Fotograf til kundecaser' === get_the_title( $behov_id ) );

// --- Egen bedrift matches aldri mot eget behov ---
$eget_behov = wp_insert_post(
	array(
		'post_type'   => 'samlab_behov',
		'post_title'  => 'Trenger foto og redigering',
		'post_status' => 'publish',
		'meta_input'  => array(
			'_samlab_bedrift'    => $foto,
			'_samlab_kompetanse' => array( 'Foto', 'Redigering' ),
		),
	)
);
$term = get_term_by( 'slug', 'trenger', 'samlab_retning' );
wp_set_object_terms( $eget_behov, array( $term->term_id ), 'samlab_retning' );
$selv = samlab_kjor_matching();
$selvmatch = false;
foreach ( $selv as $s ) {
	if ( $foto === (int) get_post_meta( $s, '_samlab_match_bedrift', true ) && $eget_behov === (int) get_post_meta( $s, '_samlab_match_behov', true ) ) {
		$selvmatch = true;
	}
}
sjekk( 'egen bedrift matches aldri mot eget behov', ! $selvmatch );

// --- Cron er planlagt ---
sjekk( 'samlab_matching er planlagt daglig', false !== wp_next_scheduled( 'samlab_matching' ) );

// Rydd konstruerte data og matchekoblinger.
wp_delete_post( $eget_behov, true );
wp_delete_post( $foto, true );
foreach ( get_posts(
	array(
		'post_type'      => 'samlab_kobling',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
) as $k ) {
	wp_delete_post( $k, true );
}
exit( $fail );
