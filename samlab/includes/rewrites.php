<?php
/**
 * Portal-ruter og app-skall: pluginen eier alt under portal-stien
 * med et eget komplett sideskall; temaet eier resten av nettstedet.
 *
 * Portal-sti og flatenavn er innstillinger (B10) med nøytrale
 * standarder - aldri hardkodede kundeverdier.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Leser en innstilling fra samlab_settings med fallback.
 *
 * @param string $key      Innstillingsnøkkel.
 * @param string $standard Standardverdi når innstillingen mangler.
 * @return string
 */
function samlab_get_setting( $key, $standard = '' ) {
	$settings = get_option( 'samlab_settings', array() );
	if ( is_array( $settings ) && isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
		return (string) $settings[ $key ];
	}
	return $standard;
}

/**
 * Portalens URL-sti (uten skråstreker), standard «portal».
 *
 * @return string
 */
function samlab_portal_path() {
	$sti = sanitize_title( samlab_get_setting( 'portal_sti', 'portal' ) );
	return '' !== $sti ? $sti : 'portal';
}

/**
 * Portalens visningsnavn, standard «Portalen».
 *
 * @return string
 */
function samlab_portal_name() {
	return samlab_get_setting( 'portal_navn', __( 'Portalen', 'samlab' ) );
}

/**
 * Portalens flater: visningsnøkkel => slug og etikett.
 * Slugs og etiketter kan overstyres i innstillingene (B10).
 *
 * @return array<string, array{slug: string, label: string}>
 */
function samlab_portal_views() {
	return array(
		'vegg'          => array(
			'slug'  => sanitize_title( samlab_get_setting( 'slug_vegg', 'vegg' ) ),
			'label' => samlab_get_setting( 'navn_vegg', __( 'Veggen', 'samlab' ) ),
		),
		'behov'         => array(
			'slug'  => sanitize_title( samlab_get_setting( 'slug_behov', 'behov' ) ),
			'label' => samlab_get_setting( 'navn_behov', __( 'Behov og tilbud', 'samlab' ) ),
		),
		'bedrifter'     => array(
			'slug'  => sanitize_title( samlab_get_setting( 'slug_bedrifter', 'bedrifter' ) ),
			'label' => samlab_get_setting( 'navn_bedrifter', __( 'Bedrifter', 'samlab' ) ),
		),
		'koblinger'     => array(
			'slug'  => sanitize_title( samlab_get_setting( 'slug_koblinger', 'koblinger' ) ),
			'label' => samlab_get_setting( 'navn_koblinger', __( 'Koblinger', 'samlab' ) ),
		),
		'arrangementer' => array(
			'slug'  => sanitize_title( samlab_get_setting( 'slug_arrangementer', 'arrangementer' ) ),
			'label' => samlab_get_setting( 'navn_arrangementer', __( 'Arrangementer', 'samlab' ) ),
		),
		'handbok'       => array(
			'slug'  => sanitize_title( samlab_get_setting( 'slug_handbok', 'handbok' ) ),
			'label' => samlab_get_setting( 'navn_handbok', __( 'Håndboken', 'samlab' ) ),
		),
	);
}

/**
 * URL til en portalflate («hjem» gir portalroten).
 *
 * @param string $view Visningsnøkkel.
 * @param string $item Valgfri undersides-slug.
 * @return string
 */
function samlab_portal_url( $view = 'hjem', $item = '' ) {
	$sti = samlab_portal_path();
	if ( 'hjem' === $view ) {
		return home_url( '/' . $sti . '/' );
	}
	$views = samlab_portal_views();
	$slug  = isset( $views[ $view ] ) ? $views[ $view ]['slug'] : $view;
	$url   = home_url( '/' . $sti . '/' . $slug . '/' );
	if ( '' !== $item ) {
		$url .= rawurlencode( $item ) . '/';
	}
	return $url;
}

/**
 * Portalens aksentfarge med lesbar kontrastfarge.
 *
 * Prioritet: innstillingen, deretter temaets accent-1/accent-preset.
 * Kontrastfargen (tekst på aksent) velges ut fra fargens luminans
 * slik at lyse aksentfarger får mørk tekst.
 *
 * @return array{aksent: string, kontrast: string}|null Null når
 *         temaets preset skal stå urørt (ingen kjent aksent).
 */
function samlab_portal_accent() {
	$aksent = sanitize_hex_color( samlab_get_setting( 'aksentfarge' ) );

	if ( ! $aksent ) {
		$palett = wp_get_global_settings( array( 'color', 'palette' ) );
		$farger = array();
		foreach ( (array) $palett as $gruppe ) {
			foreach ( (array) $gruppe as $farge ) {
				if ( isset( $farge['slug'], $farge['color'] ) ) {
					$farger[ $farge['slug'] ] = $farge['color'];
				}
			}
		}
		foreach ( array( 'accent-1', 'accent' ) as $slug ) {
			if ( isset( $farger[ $slug ] ) && sanitize_hex_color( $farger[ $slug ] ) ) {
				$aksent = sanitize_hex_color( $farger[ $slug ] );
				break;
			}
		}
	}

	if ( ! $aksent ) {
		return null;
	}

	$heks = ltrim( $aksent, '#' );
	if ( 3 === strlen( $heks ) ) {
		$heks = $heks[0] . $heks[0] . $heks[1] . $heks[1] . $heks[2] . $heks[2];
	}
	$rgb      = array( hexdec( substr( $heks, 0, 2 ) ), hexdec( substr( $heks, 2, 2 ) ), hexdec( substr( $heks, 4, 2 ) ) );
	$luminans = ( 299 * $rgb[0] + 587 * $rgb[1] + 114 * $rgb[2] ) / 1000;

	return array(
		'aksent'   => $aksent,
		'kontrast' => $luminans >= 140 ? '#111111' : '#ffffff',
		// Aksent brukt som ren tekst på bakgrunnen: lyse aksenter
		// faller tilbake til vanlig tekstfarge for lesbarhet.
		'tekst'    => $luminans >= 140 ? 'var(--samlab-fg)' : $aksent,
	);
}

/**
 * Registrerer rewrite-reglene for portalen. Kalles på init og ved
 * aktivering (før flush).
 *
 * @return void
 */
function samlab_register_rewrites() {
	$sti = samlab_portal_path();

	add_rewrite_rule( '^' . $sti . '/?$', 'index.php?samlab_portal=hjem', 'top' );
	add_rewrite_rule( '^' . $sti . '/([^/]+)/([^/]+)/?$', 'index.php?samlab_portal=$matches[1]&samlab_item=$matches[2]', 'top' );
	add_rewrite_rule( '^' . $sti . '/([^/]+)/?$', 'index.php?samlab_portal=$matches[1]', 'top' );
}
add_action( 'init', 'samlab_register_rewrites' );

/**
 * Registrerer portalens query-variabler.
 *
 * @param string[] $vars Eksisterende query-variabler.
 * @return string[]
 */
function samlab_query_vars( $vars ) {
	$vars[] = 'samlab_portal';
	$vars[] = 'samlab_item';
	return $vars;
}
add_filter( 'query_vars', 'samlab_query_vars' );

/**
 * Hindrer WordPress i å svare 404 på portal-ruter (hovedspørringen
 * finner naturlig nok ingen poster for dem).
 *
 * @param bool     $preempt Om 404-håndteringen skal hoppes over.
 * @param WP_Query $query   Hovedspørringen.
 * @return bool
 */
function samlab_pre_handle_404( $preempt, $query ) {
	if ( '' !== (string) $query->get( 'samlab_portal' ) ) {
		return true;
	}
	return $preempt;
}
add_filter( 'pre_handle_404', 'samlab_pre_handle_404', 10, 2 );

/**
 * Fanger portal-ruter på template_redirect og rendrer app-skallet
 * i stedet for temaets template. Innloggingsporten kommer i B8.
 *
 * @return void
 */
function samlab_route_portal() {
	$rute = (string) get_query_var( 'samlab_portal' );
	if ( '' === $rute ) {
		return;
	}

	$item = (string) get_query_var( 'samlab_item' );
	$view = '';
	if ( 'hjem' === $rute ) {
		$view = 'hjem';
	} else {
		foreach ( samlab_portal_views() as $key => $flate ) {
			if ( $flate['slug'] === $rute ) {
				$view = $key;
				break;
			}
		}
	}

	header( 'X-Robots-Tag: noindex, nofollow' );
	nocache_headers();

	$ukjent_underside = ( 'bedrifter' === $view && '' !== $item && ! samlab_get_bedrift_by_slug( $item ) )
		|| ( 'handbok' === $view && '' !== $item && ! samlab_get_handbok_page_by_slug( $item ) );
	if ( '' === $view || $ukjent_underside ) {
		status_header( 404 );
		samlab_render_portal( '404', $item );
		exit;
	}

	status_header( 200 );
	samlab_render_portal( $view, $item );
	exit;
}
add_action( 'template_redirect', 'samlab_route_portal' );

/**
 * Rendrer app-skallet med gitt flate.
 *
 * @param string $view Visningsnøkkel, eller «404».
 * @param string $item Valgfri undersides-slug.
 * @return void
 */
function samlab_render_portal( $view, $item = '' ) {
	$samlab_view = $view;
	$samlab_item = $item;
	require SAMLAB_PLUGIN_DIR . 'templates/skall.php';
}
