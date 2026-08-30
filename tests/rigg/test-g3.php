<?php
// Røyk-test for G3: portalflaten «Koblinger» - flate-registrering,
// bøtter, escaping, ukesbrev-seksjon og varsel-lenker. Selve
// klikk-flyten i nettleser dekkes av test-g3-flyt.js.
// Kjøres med: wp eval-file test-g3.php

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

$moderator = get_user_by( 'login', 'testmod' );
$medlem    = get_user_by( 'login', 'testmedlem' );
$kari      = get_user_by( 'login', 'kari.demo' );   // kontaktperson for Brygga Design (seed)
$jonas     = get_user_by( 'login', 'jonas.demo' );  // medlem (seed)
$brygga    = get_page_by_path( 'brygga-design', OBJECT, 'samlab_bedrift' );
$rydd      = array();

function g3_render( $user_id ) {
	wp_set_current_user( $user_id );
	ob_start();
	samlab_render_portal( 'koblinger' );
	return ob_get_clean();
}

// 1) Flaten er registrert og i nav.
$views = samlab_portal_views();
sjekk( 'koblinger er en portalflate', isset( $views['koblinger'] ) && 'koblinger' === $views['koblinger']['slug'] );
sjekk( 'flate-URL-en peker under portalen', false !== strpos( samlab_portal_url( 'koblinger' ), '/' . samlab_portal_path() . '/koblinger/' ) );

// 2) Egne koblinger: partene ser den, andre gjør ikke.
$kobling = samlab_opprett_kobling(
	array(
		'tittel'      => 'Brygga Design ↔ Jonas Dal',
		'begrunnelse' => 'Jonas trenger designhjelp til lanseringen.',
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
$rydd[]  = $kobling;
$mine    = wp_list_pluck( samlab_koblinger_for( $jonas->ID ), 'ID' );
sjekk( 'parten har koblingen i listen', in_array( $kobling, $mine, true ) );
sjekk( 'kontaktpersonen har den også', in_array( $kobling, wp_list_pluck( samlab_koblinger_for( $kari->ID ), 'ID' ), true ) );
sjekk( 'utenforstående har den ikke', ! in_array( $kobling, wp_list_pluck( samlab_koblinger_for( $medlem->ID ), 'ID' ), true ) );

// 3) Foreslått vises ikke for partene - først forespurt gir kort.
$html = g3_render( $jonas->ID );
sjekk( 'foreslått kobling vises ikke i flaten', false === strpos( $html, 'kobling-' . $kobling ) );

samlab_kontrollpanel_utfor( $kobling, 'godkjenn', $moderator->ID );
$html = g3_render( $jonas->ID );
sjekk( 'forespørselen vises med kort', false !== strpos( $html, 'kobling-' . $kobling ) && false !== strpos( $html, 'Forespørsler til deg' ) );
sjekk( 'begrunnelsen vises', false !== strpos( $html, 'designhjelp til lanseringen' ) );
sjekk( 'Takk ja / Nei takk-knappene finnes', false !== strpos( $html, 'data-svar="ja"' ) && false !== strpos( $html, 'data-svar="nei"' ) );
sjekk( 'nav-lenken til flaten er med', false !== strpos( $html, 'koblinger/' ) && false !== strpos( $html, '>Koblinger<' ) );
sjekk( 'kontaktinfo deles ikke i forespørselen', false === strpos( $html, $kari->user_email ) );

// 4) Én part har svart: knappene erstattes av ventemelding.
samlab_kobling_svar( $kobling, 'b', 'ja', $jonas->ID );
$html = g3_render( $jonas->ID );
sjekk( 'eget ja gir ventemelding uten knapper', false !== strpos( $html, 'venter på svar fra motparten' ) && false === strpos( $html, 'data-svar="ja"' ) );

// 5) Begge ja: aktiv kobling med statuskjede og kontaktinfo.
samlab_kobling_svar( $kobling, 'a', 'ja', $kari->ID );
$html = g3_render( $jonas->ID );
sjekk( 'godkjent kobling står som aktiv', false !== strpos( $html, 'Aktive koblinger' ) );
sjekk( 'statuskjeden rendres', false !== strpos( $html, 'samlab-status-kjede' ) && false !== strpos( $html, 'er-naadd' ) );
sjekk( 'kontaktinfo deles fra godkjent', false !== strpos( $html, $kari->user_email ) );

// 6) Escaping: rå markup i tittel/begrunnelse tolkes aldri.
// Oppdateres som admin (unfiltered_html) så payloaden overlever
// kses og faktisk når templaten.
wp_set_current_user( 1 );
wp_update_post(
	array(
		'ID'           => $kobling,
		'post_title'   => 'XSS <em>kobling</em>',
		'post_content' => '<script>alert("xss-g3")</script>',
	)
);
$html = g3_render( $jonas->ID );
sjekk( 'script-forsøk i begrunnelsen escapes', false === strpos( $html, '<script>alert("xss-g3")' ) && false !== strpos( $html, '&lt;script&gt;alert(&quot;xss-g3&quot;)' ) );
sjekk( 'markup i tittelen escapes', false === strpos( $html, '<em>kobling</em>' ) );

// 7) Historikk: avvist kobling havner nederst med kjede.
$kobling2 = samlab_opprett_kobling(
	array(
		'tittel'      => 'G3 avslagstest',
		'begrunnelse' => 'Test.',
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
$rydd[]   = $kobling2;
samlab_sett_kobling_status( $kobling2, 'forespurt', $moderator->ID );
samlab_kobling_svar( $kobling2, 'b', 'nei', $jonas->ID );
$html = g3_render( $jonas->ID );
sjekk( 'avvist kobling står i historikken', false !== strpos( $html, 'Historikk' ) && false !== strpos( $html, 'kobling-' . $kobling2 ) );

// 8) Tom-tilstand for medlem uten koblinger.
$html = g3_render( $medlem->ID );
sjekk( 'tom-tilstanden vises uten koblinger', false !== strpos( $html, 'Ingen koblinger ennå' ) );

// 9) Ukesbrevet: aggregert forespørsel-seksjon uten navn.
$kobling3 = samlab_opprett_kobling(
	array(
		'tittel'      => 'Brygga Design ↔ Jonas Dal',
		'begrunnelse' => 'Ukesbrevtest.',
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
$rydd[]   = $kobling3;
samlab_sett_kobling_status( $kobling3, 'forespurt', $moderator->ID );
$seksjon = null;
foreach ( samlab_ukesbrev_seksjoner( time() - WEEK_IN_SECONDS ) as $s ) {
	if ( 'Koblingsforespørsler' === $s['tittel'] ) {
		$seksjon = $s;
	}
}
sjekk( 'ukesbrevet har forespørsel-seksjon', null !== $seksjon );
sjekk( 'seksjonen navngir ingen parter', null !== $seksjon && false === strpos( wp_json_encode( $seksjon ), 'Jonas' ) && false === strpos( wp_json_encode( $seksjon ), 'Brygga' ) );
sjekk( 'seksjonen lenker til flaten', null !== $seksjon && samlab_portal_url( 'koblinger' ) === $seksjon['linjer'][0]['url'] );

// 10) Varsel-lenkene peker til flaten.
foreach ( array( 'kobling_forespurt', 'kobling_godkjent', 'kobling_ikke_noe' ) as $type ) {
	$visning = samlab_varsel_visning(
		(object) array(
			'type'        => $type,
			'object_type' => 'kobling',
			'object_id'   => $kobling3,
			'actor_id'    => 0,
		)
	);
	sjekk( "varsel {$type} lenker til flaten", samlab_portal_url( 'koblinger' ) === $visning['lenke'] );
}

foreach ( $rydd as $post_id ) {
	if ( ! is_wp_error( $post_id ) ) {
		wp_delete_post( $post_id, true );
	}
}

exit( $fail );
