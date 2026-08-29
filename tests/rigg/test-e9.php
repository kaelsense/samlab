<?php
// Røyk-test for E9: infoskjermens nøkkelhelpere. HTTP-delen (200
// med riktig nøkkel og innhold/auto-refresh/noindex, 404 ved
// feil/manglende nøkkel og etter regenerering/fjerning) kjøres
// med curl - se BACKLOG-notatet.
// Kjøres med: wp eval-file test-e9.php

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

// --- Av som standard ---
delete_option( 'samlab_skjerm_nokkel' );
sjekk( 'skjermen er av uten nøkkel', '' === samlab_skjerm_nokkel() && '' === samlab_skjerm_url() );

// --- Generering ---
$nokkel = samlab_skjerm_generer_nokkel();
sjekk( 'nøkkelen er lang og alfanumerisk', 1 === preg_match( '/^[a-zA-Z0-9]{24}$/', $nokkel ) );
sjekk( 'nøkkelen lagres', $nokkel === samlab_skjerm_nokkel() );
sjekk( 'URL-en inneholder sti, slug og nøkkel', false !== strpos( samlab_skjerm_url(), '/' . samlab_portal_path() . '/' . samlab_skjerm_slug() . '/' . $nokkel . '/' ) );

// --- Regenerering gjør gammel nøkkel ugyldig ---
$ny = samlab_skjerm_generer_nokkel();
sjekk( 'regenerering gir ny nøkkel', $ny !== $nokkel && $ny === samlab_skjerm_nokkel() );

// --- Slug er innstilling med nøytral standard ---
sjekk( 'standardslug er skjerm', 'skjerm' === samlab_skjerm_slug() );

// (nøkkelen beholdes - HTTP-delen bruker den og rydder selv)
exit( $fail );
