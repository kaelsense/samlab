<?php
// Røyk-test for admin-laget: skjermregister, screen-gating, enqueue og
// kroppsklasse. Ingen fasebokstav i navnet - designarbeidet er ikke en
// egen fase i BACKLOG.md ennå.
//
// Testen bruker de faktisk registrerte hook-suffiksene fra
// samlab_admin_skjermer(), ikke gjettede navn som
// «settings_page_samlab» - hele poenget med registeret er at navnene
// ikke skal gjettes.

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

require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
set_current_screen( 'dashboard' );
do_action( 'admin_menu' );

$skjermer = samlab_admin_skjermer();
sjekk( 'de tre egne sidene er registrert', 3 === count( $skjermer ) );
sjekk( 'kontrollpanelet er med', in_array( 'toplevel_page_samlab-kontrollpanel', $skjermer, true ) );
sjekk( 'registeret tar ikke imot false fra manglende capability', ! in_array( false, $skjermer, true ) && ! in_array( '', $skjermer, true ) );

/**
 * Hjelper: sett skjerm, kjør enqueue-kroken, og rapporter tilstanden.
 *
 * @param string $id        Skjerm-ID.
 * @param string $post_type Post-type å tvinge på skjermen, om noen.
 * @return array{flate: string, lastet: bool, kropp: string}
 */
function samlab_test_admin_skjerm( $id, $post_type = '' ) {
	set_current_screen( $id );
	if ( '' !== $post_type ) {
		get_current_screen()->post_type = $post_type;
	}
	wp_styles()->queue = array();
	do_action( 'admin_enqueue_scripts', $id );
	return array(
		'flate'  => samlab_admin_flate(),
		'lastet' => wp_style_is( 'samlab-admin', 'enqueued' ),
		'kropp'  => trim( apply_filters( 'admin_body_class', '' ) ),
	);
}

// --- Egne sider ---
$alle_sider = true;
foreach ( $skjermer as $samlab_id ) {
	$res = samlab_test_admin_skjerm( $samlab_id );
	if ( 'side' !== $res['flate'] || ! $res['lastet'] || 'samlab-admin samlab-admin-side' !== $res['kropp'] ) {
		$alle_sider = false;
	}
}
sjekk( 'alle tre sidene laster stilarket og får kroppsklassen', $alle_sider );

// --- Listetabell og metaboks ---
$liste = samlab_test_admin_skjerm( 'edit-samlab_bedrift', 'samlab_bedrift' );
sjekk( 'listetabellen får flate og stilark', 'liste' === $liste['flate'] && $liste['lastet'] );
sjekk( 'listetabellens kroppsklasse', 'samlab-admin samlab-admin-liste' === $liste['kropp'] );

$boks = samlab_test_admin_skjerm( 'samlab_kobling', 'samlab_kobling' );
sjekk( 'editoren får flate og stilark', 'metaboks' === $boks['flate'] && $boks['lastet'] );

// Sideeditoren er med fordi håndbok-metaboksen sitter der.
$side = samlab_test_admin_skjerm( 'page', 'page' );
sjekk( 'sideeditoren regnes som metaboks-flate', 'metaboks' === $side['flate'] );

// --- Utenfor Samlab skal ingenting lastes ---
$dash = samlab_test_admin_skjerm( 'dashboard' );
sjekk( 'dashbordet laster ikke stilarket', '' === $dash['flate'] && ! $dash['lastet'] && '' === $dash['kropp'] );

$innlegg = samlab_test_admin_skjerm( 'edit-post', 'post' );
sjekk( 'innleggslisten laster ikke stilarket', '' === $innlegg['flate'] && ! $innlegg['lastet'] );

// --- Avhengigheten på core sine designsystem-tokens ---
$reg = wp_styles()->registered['samlab-admin'];
sjekk( 'stilarket er versjonert', SAMLAB_VERSION === $reg->ver );
sjekk(
	'wp-theme brukes som avhengighet når den finnes',
	wp_style_is( 'wp-theme', 'registered' )
		? array( 'wp-theme' ) === $reg->deps
		: array() === $reg->deps
);

// --- Hygienen fra fase 1 ---
set_current_screen( 'toplevel_page_samlab-kontrollpanel' );
ob_start();
samlab_render_kontrollpanel();
$html = ob_get_clean();
sjekk( 'kontrollpanelet har wp-header-end for varselplassering', false !== strpos( $html, 'wp-header-end' ) );
sjekk( 'kolonneoverskriftene har scope', false === strpos( $html, '<th>' ) );
sjekk( 'seksjonene har id-er å hoppe til', false !== strpos( $html, 'id="samlab-forslag"' ) && false !== strpos( $html, 'id="samlab-oppmerksomhet"' ) );
sjekk( 'ingen inline style igjen i kontrollpanelet', false === strpos( $html, 'style="' ) );

ob_start();
samlab_render_rapport();
$html = ob_get_clean();
sjekk( 'rapporten har wp-header-end', false !== strpos( $html, 'wp-header-end' ) );
sjekk( 'ingen inline style igjen i rapporten', false === strpos( $html, 'style="' ) );

exit( $fail );
