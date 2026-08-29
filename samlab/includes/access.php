<?php
/**
 * Innloggingsport: alt under portal-stien krever innlogging.
 * Uinnloggede sendes til WordPress' innloggingsskjema og tilbake
 * til siden de ba om etterpå.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sender uinnloggede på portal-ruter til wp-login med retur-URL.
 * Kjører før portal-rutingen (prioritet 9 mot rutingens 10).
 *
 * @return void
 */
function samlab_portal_login_gate() {
	if ( '' === (string) get_query_var( 'samlab_portal' ) || is_user_logged_in() ) {
		return;
	}

	global $wp;
	$retur = home_url( '/' . ltrim( trailingslashit( (string) $wp->request ), '/' ) );

	nocache_headers();
	wp_safe_redirect( wp_login_url( $retur ) );
	exit;
}
add_action( 'template_redirect', 'samlab_portal_login_gate', 9 );
