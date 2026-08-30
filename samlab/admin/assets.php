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

	// Tjeneste-repeateren hører kun hjemme på bedriftseditoren.
	$skjerm = get_current_screen();
	if ( $skjerm && 'post' === $skjerm->base && 'samlab_bedrift' === $skjerm->post_type ) {
		wp_enqueue_script( 'samlab-admin-tjenester', SAMLAB_PLUGIN_URL . 'assets/js/admin-tjenester.js', array(), SAMLAB_VERSION, true );
	}
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
 * Sammendragsrad: noen få tall øverst på en flate.
 *
 * Tall med «id» blir lenker som hopper til seksjonen med den id-en -
 * navigasjon, altså <a> og ikke <button>. Tall uten id er ren
 * oppsummering og rendres som tekst.
 *
 * «tak» sier hvor listen bak tallet er avkortet. Treffer tallet taket,
 * vises «100+» framfor et presist tall det ikke er dekning for.
 *
 * «minst» sier det samme for et tall som ikke selv treffer et tak,
 * men som summerer lister der minst én er avkortet. Da er tallet et
 * gulv, ikke en fasit, og skal vises som «108+». Uten dette lover
 * flisen en presisjon den ikke har: en sum på 108 av fire lister der
 * én er kappet på 100 kan i virkeligheten være hva som helst over
 * 108.
 *
 * Raden setter ingen farge: tallet arver lenkefargen fra brukerens
 * fargeskjema.
 *
 * @param array<int, array{tall: int, etikett: string, id?: string, tak?: int, minst?: bool}> $tall Radene.
 * @return void
 */
function samlab_admin_sammendrag( $tall ) {
	echo '<ul class="samlab-sammendrag" aria-label="' . esc_attr__( 'Sammendrag', 'samlab' ) . '">';
	foreach ( $tall as $rad ) {
		$tak = isset( $rad['tak'] ) ? (int) $rad['tak'] : 0;
		if ( $tak > 0 && $rad['tall'] >= $tak ) {
			$vist = $tak . '+';
		} elseif ( ! empty( $rad['minst'] ) ) {
			$vist = $rad['tall'] . '+';
		} else {
			$vist = (string) $rad['tall'];
		}
		$inni = '<span class="samlab-sammendrag-tall">' . esc_html( $vist ) . '</span>'
			. '<span class="samlab-sammendrag-etikett">' . esc_html( $rad['etikett'] ) . '</span>';

		echo '<li>';
		if ( ! empty( $rad['id'] ) ) {
			echo '<a href="#' . esc_attr( $rad['id'] ) . '">' . $inni . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $inni er bygget av esc_html over.
		} else {
			echo '<span class="samlab-sammendrag-rute">' . $inni . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Samme.
		}
		echo '</li>';
	}
	echo '</ul>';
}

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
