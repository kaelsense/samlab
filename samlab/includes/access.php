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

/**
 * Om en side er merket som håndbok-innhold (portalinnhold).
 *
 * @param int $post_id Sidens ID.
 * @return bool
 */
function samlab_is_handbok_page( $post_id ) {
	return '1' === get_post_meta( (int) $post_id, '_samlab_handbok', true );
}

/**
 * Håndbok-sider er portalinnhold: den vanlige permalenken sender alle
 * til portalens håndbok-rute, der innloggingsporten og noindex
 * gjelder. Uten dette ville merkede sider ligget åpne på sin
 * ordinære URL.
 *
 * @return void
 */
function samlab_handbok_canonical_gate() {
	if ( ! is_singular( 'page' ) ) {
		return;
	}
	$side = get_queried_object();
	if ( ! $side || ! samlab_is_handbok_page( $side->ID ) ) {
		return;
	}
	nocache_headers();
	wp_safe_redirect( samlab_portal_url( 'handbok', $side->post_name ), 301 );
	exit;
}
add_action( 'template_redirect', 'samlab_handbok_canonical_gate', 8 );

/**
 * Meta-spørring som utelater håndbok-sider.
 *
 * @param array $meta_query Eksisterende meta_query (eller tom).
 * @return array
 */
function samlab_uten_handbok_meta_query( $meta_query ) {
	$meta_query   = is_array( $meta_query ) ? $meta_query : array();
	$meta_query[] = array(
		'key'     => '_samlab_handbok',
		'compare' => 'NOT EXISTS',
	);
	return $meta_query;
}

/**
 * Holder håndbok-sider ute av offentlige sitemaps.
 *
 * @param array  $args      Spørringsargumenter for sitemap-leverandøren.
 * @param string $post_type Post-typen sitemapen bygges for.
 * @return array
 */
function samlab_handbok_uten_sitemap( $args, $post_type ) {
	if ( 'page' === $post_type ) {
		$args['meta_query'] = samlab_uten_handbok_meta_query( isset( $args['meta_query'] ) ? $args['meta_query'] : array() ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Sitemaps bygges sjelden; portalinnhold skal ikke listes.
	}
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'samlab_handbok_uten_sitemap', 10, 2 );

/**
 * Holder håndbok-sider ute av det offentlige søket (temaets ?s=).
 *
 * @param WP_Query $query Spørringen.
 * @return void
 */
function samlab_handbok_uten_sok( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}
	$query->set( 'meta_query', samlab_uten_handbok_meta_query( $query->get( 'meta_query' ) ) );
}
add_action( 'pre_get_posts', 'samlab_handbok_uten_sok' );

/**
 * Holder håndbok-sider ute av REST for uinnloggede
 * (/wp/v2/pages-listen).
 *
 * @param array $args Spørringsargumenter.
 * @return array
 */
function samlab_handbok_uten_rest_liste( $args ) {
	if ( ! is_user_logged_in() ) {
		$args['meta_query'] = samlab_uten_handbok_meta_query( isset( $args['meta_query'] ) ? $args['meta_query'] : array() ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Portalinnhold skal ikke eksponeres anonymt.
	}
	return $args;
}
add_filter( 'rest_page_query', 'samlab_handbok_uten_rest_liste' );

/**
 * Avviser anonyme enkeltoppslag av håndbok-sider i REST.
 *
 * @param WP_REST_Response|WP_Error $response Svaret.
 * @param WP_Post                   $post     Siden.
 * @return WP_REST_Response|WP_Error
 */
function samlab_handbok_uten_rest_enkelt( $response, $post ) {
	if ( ! is_user_logged_in() && samlab_is_handbok_page( $post->ID ) ) {
		return new WP_REST_Response(
			array(
				'code'    => 'samlab_ikke_innlogget',
				'message' => __( 'Du må være innlogget.', 'samlab' ),
			),
			401
		);
	}
	return $response;
}
add_filter( 'rest_prepare_page', 'samlab_handbok_uten_rest_enkelt', 10, 2 );
