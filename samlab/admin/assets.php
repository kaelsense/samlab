<?php
/**
 * Admin-laget: skjermregister, screen-gating, enqueue og kroppsklasse
 * for Samlabs egne flater i wp-admin, pluss små delte flate-hjelpere.
 *
 * Stilarket lastes kun på skjermer Samlab faktisk eier - egne sider,
 * egne listetabeller og editorene som har Samlab-metabokser. Se
 * assets/css/admin.css for reglene laget er skrevet etter.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post-typene Samlab eier skjermer for.
 *
 * @return string[]
 */
function samlab_admin_post_typer() {
	return array( 'samlab_bedrift', 'samlab_behov', 'samlab_kobling', 'samlab_arrangement' );
}

/**
 * Registeret over Samlabs egne admin-sider.
 *
 * Menyfunksjonene sender inn hook-suffikset add_menu_page() og
 * søsknene returnerer, i stedet for at vi gjetter oss fram til navn
 * som «toplevel_page_samlab-kontrollpanel». Funksjonene returnerer
 * false når capability-sjekken feiler, og den verdien skal ikke inn i
 * registeret - derfor is_string.
 *
 * Mekanismen hviler på at admin_menu alltid fyres før
 * admin_enqueue_scripts i samme forespørsel.
 *
 * @param string|false|null $ny Skjerm som skal registreres, eller null for kun å lese.
 * @return string[]
 */
function samlab_admin_skjermer( $ny = null ) {
	static $skjermer = array();
	if ( is_string( $ny ) && '' !== $ny ) {
		$skjermer[] = $ny;
	}
	return $skjermer;
}

/**
 * Hvilken Samlab-flate den gjeldende admin-skjermen er, om noen.
 *
 * Både enqueue og kroppsklassen går gjennom denne. Kroppsklassefilteret
 * får ikke $hook_suffix som argument, så gatingen må uansett bygge på
 * get_current_screen() - da er det bedre å ha den ett sted.
 *
 * @return string 'side', 'liste', 'metaboks', eller tom streng utenfor Samlab.
 */
function samlab_admin_flate() {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return '';
	}
	$skjerm = get_current_screen();
	if ( ! $skjerm ) {
		return '';
	}
	if ( in_array( $skjerm->id, samlab_admin_skjermer(), true ) ) {
		return 'side';
	}
	if ( 'edit' === $skjerm->base && in_array( $skjerm->post_type, samlab_admin_post_typer(), true ) ) {
		return 'liste';
	}
	// «page» er med fordi håndbok-metaboksen sitter på sideeditoren.
	if ( 'post' === $skjerm->base && in_array( $skjerm->post_type, array_merge( samlab_admin_post_typer(), array( 'page' ) ), true ) ) {
		return 'metaboks';
	}
	return '';
}

/**
 * Laster admin-stilarket på Samlabs egne skjermer.
 *
 * @return void
 */
function samlab_admin_assets() {
	if ( '' === samlab_admin_flate() ) {
		return;
	}

	// wp-theme er core sine designsystem-tokens, registrert fra WP 7.1.
	// Vakten er ikke pynt: en uregistrert avhengighet får
	// wp_enqueue_style til å droppe HELE stilarket stille. Hver token i
	// admin.css har fallback, så siden ser lik ut uten avhengigheten.
	$deps = wp_style_is( 'wp-theme', 'registered' ) ? array( 'wp-theme' ) : array();

	wp_enqueue_style( 'samlab-admin', SAMLAB_PLUGIN_URL . 'assets/css/admin.css', $deps, SAMLAB_VERSION );
}
add_action( 'admin_enqueue_scripts', 'samlab_admin_assets' );

/**
 * Merker kroppen på Samlabs admin-skjermer, så stilarket kan scopes.
 *
 * Metaboksene og listetabellene ligger i en .wrap core rendrer, så vi
 * kan ikke sette klasse der - kroppsklassen er den ene mekanismen som
 * dekker alle tre flatene.
 *
 * @param string $classes Kroppsklassene, mellomromsseparert.
 * @return string
 */
function samlab_admin_body_class( $classes ) {
	$flate = samlab_admin_flate();
	if ( '' === $flate ) {
		return $classes;
	}
	return $classes . ' samlab-admin samlab-admin-' . $flate;
}
add_filter( 'admin_body_class', 'samlab_admin_body_class' );

/**
 * Åpner en vannrett scroll-region rundt en bred tabell.
 *
 * De håndskrevne widefat-tabellene er bredere enn 320 px og ga
 * horisontal scroll på hele siden (WCAG 1.4.10 Reflow). Core sine egne
 * listetabeller slipper unna fordi WP_List_Table-markupen får den
 * responsive behandlingen fra list-tables.css - den arver vi ikke.
 *
 * Regionen må kunne nås med tastatur, ellers bytter vi 1.4.10 mot et
 * brudd på 2.1.1: tabindex="0" gjør den scrollbar uten mus, og
 * role="region" med navn gjør at skjermlesere annonserer den. Prisen
 * er ett ekstra tabbstopp per tabell også på brede skjermer der
 * ingenting scroller - det krever JS å unngå, og det er ikke verdt
 * en scriptfil her.
 *
 * @param string $etikett Tilgjengelig navn, normalt seksjonens overskrift.
 * @return void
 */
function samlab_admin_tabellramme( $etikett ) {
	echo '<div class="samlab-tabellramme" role="region" tabindex="0" aria-label="' . esc_attr( $etikett ) . '">';
}

/**
 * Lukker scroll-regionen rundt en tabell.
 *
 * @return void
 */
function samlab_admin_tabellramme_slutt() {
	echo '</div>';
}
