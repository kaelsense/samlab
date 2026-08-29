<?php
// Røyk-test for F4: widget-rendring og escaping. Selve samtale-
// flyten (klikk, melding, mock-svar i DOM) verifiseres i ekte
// nettleser med Playwright mot riggen - se BACKLOG-notatet.
// Kjøres med assistent-modulen PÅ (som test-f2/f3).

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

if ( ! function_exists( 'samlab_assistent_widget' ) ) {
	echo "FEIL modulen er ikke lastet - slå på assistenten før testen\n";
	exit( 1 );
}

$medlem = get_user_by( 'login', 'testmedlem' );

// --- Widgeten er hektet på skall-punktet ---
sjekk( 'widgeten er hektet på samlab_portal_bunn', false !== has_action( 'samlab_portal_bunn', 'samlab_assistent_widget' ) );

/**
 * Hjelper: rendrer widgeten og gir markupen tilbake.
 *
 * @return string
 */
function samlab_test_widget_html() {
	ob_start();
	samlab_assistent_widget();
	return ob_get_clean();
}

// --- Utlogget: ingenting rendres ---
wp_set_current_user( 0 );
sjekk( 'utlogget får ingen widget', '' === samlab_test_widget_html() );

// --- Modulen på, men uten API-nøkkel: ingen knapp som bare feiler ---
wp_set_current_user( $medlem->ID );
sjekk( 'uten API-nøkkel får medlemmet ingen widget', ! samlab_assistent_har_nokkel() && '' === samlab_test_widget_html() );

define( 'SAMLAB_CLAUDE_API_KEY', 'sk-test-mock-42' );

// --- Innlogget uten portaltilgang: ingen widget (endepunktet gir 403) ---
// wp_set_current_user returnerer tidlig på samme ID, så vi må innom 0
// for at capability-endringen skal slå gjennom på den aktive brukeren.
$medlem->add_cap( 'samlab_read_portal', false );
wp_set_current_user( 0 );
wp_set_current_user( $medlem->ID );
sjekk( 'uten samlab_read_portal får brukeren ingen widget', ! current_user_can( 'samlab_read_portal' ) && '' === samlab_test_widget_html() );
$medlem->remove_cap( 'samlab_read_portal' );
wp_set_current_user( 0 );
wp_set_current_user( $medlem->ID );
sjekk( 'portaltilgangen er tilbakestilt', current_user_can( 'samlab_read_portal' ) );

// --- Innlogget: markup med escaped innstillinger ---
$orig = get_option( 'samlab_settings', array() );
$s    = $orig;
$s['assistent_navn']     = 'Kompis & venner';
$s['assistent_velkomst'] = 'Hei "du" <der> & velkommen!';
update_option( 'samlab_settings', $s );

wp_set_current_user( $medlem->ID );
$html = samlab_test_widget_html();

sjekk( 'knapp og panel rendres', false !== strpos( $html, 'samlab-assistent-knapp' ) && false !== strpos( $html, 'samlab-assistent-panel' ) );
sjekk( 'navnet escapes ved output', false !== strpos( $html, 'Kompis &amp; venner' ) );
sjekk( 'velkomstmeldingen escapes ved output', false !== strpos( $html, 'Hei &#8220;du&#8221;' ) || false !== strpos( $html, 'Hei &quot;du&quot;' ) );
sjekk( 'skriver-indikatoren er med (skjult)', false !== strpos( $html, 'samlab-assistent-skriver' ) && false !== strpos( $html, 'skriver &#8230;' ) || false !== strpos( $html, 'skriver …' ) );
sjekk( 'JS poster mot assistent-endepunktet', false !== strpos( $html, "samlab/v1/assistent'" ) );
sjekk( 'JS bruker kun textContent for data', false === strpos( $html, '.innerHTML' ) );

update_option( 'samlab_settings', $orig );
exit( $fail );
