<?php
// Røyk-test for G6: ubesvart-deteksjonen - markøren strippes fra
// svaret, spørsmålet havner anonymt i køen, dedupe og FIFO-tak
// holder, og innstillingen av stopper all lagring. Krever modulen
// PÅ (kjøres i samme gruppe som f2-f4); API-et mockes.
// Kjøres med: wp eval-file test-g6.php

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

$medlem = get_user_by( 'login', 'testmedlem' );
wp_set_current_user( $medlem->ID );
delete_transient( samlab_assistent_rate_nokkel( $medlem->ID ) );
wp_cache_delete( samlab_assistent_rate_nokkel( $medlem->ID ), SAMLAB_ASSISTENT_RATE_GRUPPE );
delete_option( 'samlab_ubesvart' );

if ( ! defined( 'SAMLAB_CLAUDE_API_KEY' ) ) {
	define( 'SAMLAB_CLAUDE_API_KEY', 'sk-test-mock-42' );
}

// Mock: svarer med teksten testen ber om.
global $g6_mock_svar;
$g6_mock_svar = 'Vanlig svar.';
add_filter(
	'pre_http_request',
	function ( $ignorert, $parsed_args, $url ) {
		global $g6_mock_svar;
		if ( false === strpos( $url, 'api.anthropic.com' ) ) {
			return $ignorert;
		}
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'content' => array(
						array(
							'type' => 'text',
							'text' => $g6_mock_svar,
						),
					),
				)
			),
		);
	},
	10,
	3
);

function g6_spor( $melding ) {
	$request = new WP_REST_Request( 'POST', '/samlab/v1/assistent' );
	$request->set_body_params( array( 'melding' => $melding ) );
	return rest_do_request( $request );
}

// 1) Systemprompten instruerer om markøren.
$blokker = samlab_assistent_systemblokker();
sjekk( 'systemprompten forklarer UBESVART-markøren', false !== strpos( $blokker[0]['text'], '[UBESVART]' ) );
sjekk( 'innstillingen er på som standard', samlab_assistent_ubesvart_aktiv() );

// 2) Markert svar: strippes for medlemmet, havner anonymt i køen.
$g6_mock_svar = "[UBESVART] Det finner jeg ikke i grunnlaget - spør verten.";
$svar         = g6_spor( 'Hvor leverer jeg pakkeretur?' );
sjekk( 'kallet lykkes', 200 === $svar->get_status() );
sjekk( 'markøren er strippet fra svaret', 'Det finner jeg ikke i grunnlaget - spør verten.' === $svar->get_data()['svar'] );

$liste = samlab_ubesvart_liste();
sjekk( 'spørsmålet ligger i køen', 1 === count( $liste ) && 'Hvor leverer jeg pakkeretur?' === $liste[0]['sporsmal'] );
sjekk( 'innslaget er anonymt og uten svar', ! isset( $liste[0]['user_id'] ) && ! isset( $liste[0]['svar'] ) && gmdate( 'Y-m-d' ) === $liste[0]['dato'] && 1 === $liste[0]['antall'] );
sjekk( 'køen står med autoload av', ! array_key_exists( 'samlab_ubesvart', wp_load_alloptions() ) );

// 3) Dedupe: samme spørsmål i annen drakt øker telleren.
g6_spor( '  hvor   leverer jeg PAKKERETUR???' );
$liste = samlab_ubesvart_liste();
sjekk( 'dedupe øker telleren i stedet for ny rad', 1 === count( $liste ) && 2 === $liste[0]['antall'] );

// 4) Svar uten markør lagres aldri.
$g6_mock_svar = 'Brygga Design leverer design - se bedriftskatalogen.';
g6_spor( 'Hvem lager nettsider?' );
sjekk( 'vanlige svar lagres aldri', 1 === count( samlab_ubesvart_liste() ) );

// 5) FIFO-taket på 200.
for ( $i = 1; $i <= 205; $i++ ) {
	samlab_ubesvart_registrer( 'G6 fylltest nummer ' . $i );
}
$liste = samlab_ubesvart_liste();
$tekster = wp_list_pluck( $liste, 'sporsmal' );
sjekk( 'taket holder på 200', 200 === count( $liste ) );
sjekk( 'eldste ryker først, nyeste beholdes', ! in_array( 'Hvor leverer jeg pakkeretur?', $tekster, true ) && in_array( 'G6 fylltest nummer 205', $tekster, true ) );

// 6) Innstillingen av stopper all lagring.
$innstillinger                       = get_option( 'samlab_settings', array() );
$innstillinger['assistent_ubesvart'] = 'av';
update_option( 'samlab_settings', $innstillinger );
sjekk( 'av-bryteren slår av', ! samlab_assistent_ubesvart_aktiv() );
sjekk( 'registrering avvises når av', false === samlab_ubesvart_registrer( 'Skal ikke lagres' ) );
$g6_mock_svar = '[UBESVART] Vet ikke.';
$svar         = g6_spor( 'Lagres dette?' );
sjekk( 'markøren strippes fortsatt når køen er av', 'Vet ikke.' === $svar->get_data()['svar'] );
sjekk( 'ingenting lagres når køen er av', 200 === count( samlab_ubesvart_liste() ) && ! in_array( 'Lagres dette?', wp_list_pluck( samlab_ubesvart_liste(), 'sporsmal' ), true ) );

// Rydd: innstilling tilbake til standard, kø og rate-teller vekk.
unset( $innstillinger['assistent_ubesvart'] );
update_option( 'samlab_settings', $innstillinger );
delete_option( 'samlab_ubesvart' );
delete_transient( samlab_assistent_rate_nokkel( $medlem->ID ) );
wp_cache_delete( samlab_assistent_rate_nokkel( $medlem->ID ), SAMLAB_ASSISTENT_RATE_GRUPPE );

exit( $fail );
