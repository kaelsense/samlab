<?php
/**
 * Chat-widgeten (F4): flytende assistentknapp i portalskallet med
 * panel, meldingsliste, «skriver …»-indikator og hel-svar-levering
 * mot POST samlab/v1/assistent (F3). Lastes kun via modul.php
 * (modulen på), og rendres kun for medlemmer som faktisk kan bruke
 * den: innlogget, med samlab_read_portal, og med API-nøkkel satt.
 *
 * All dynamisk output escapes: PHP-siden med esc_html/esc_attr,
 * JS-siden utelukkende med textContent (aldri innerHTML med data).
 * SSE-streaming er dokumentert som senere oppgradering i
 * docs/hooks.md - hel-svar er planens plan B og det som bygges her.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Om widgeten skal vises for den innloggede brukeren. Porten er den
 * samme som endepunktets: uten portaltilgang svarer REST 403, og uten
 * API-nøkkel svarer det 503. Da skal knappen heller ikke vises - en
 * chat som alltid feiler er verre enn ingen chat. Innloggingsporten
 * slipper inn alle innloggede, så capability-sjekken må gjøres her.
 *
 * @return bool
 */
function samlab_assistent_vis_widget() {
	if ( ! is_user_logged_in() || ! current_user_can( 'samlab_read_portal' ) ) {
		return false;
	}
	return samlab_assistent_har_nokkel();
}

/**
 * Rendrer widgeten i portalskallets bunn (samlab_portal_bunn).
 *
 * @return void
 */
function samlab_assistent_widget() {
	if ( ! samlab_assistent_vis_widget() ) {
		return;
	}
	$navn = samlab_assistent_navn();
	?>
	<div class="samlab-assistent" id="samlab-assistent">
		<button type="button" class="samlab-assistent-knapp" id="samlab-assistent-knapp"
			aria-expanded="false" aria-controls="samlab-assistent-panel">
			<span aria-hidden="true">&#128172;</span>
			<span class="screen-reader-text"><?php echo esc_html( $navn ); ?></span>
		</button>
		<section class="samlab-assistent-panel" id="samlab-assistent-panel" hidden aria-label="<?php echo esc_attr( $navn ); ?>">
			<header class="samlab-assistent-hode">
				<h2><?php echo esc_html( $navn ); ?></h2>
				<button type="button" class="samlab-assistent-lukk" id="samlab-assistent-lukk">
					<span aria-hidden="true">&times;</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Lukk', 'samlab' ); ?></span>
				</button>
			</header>
			<div class="samlab-assistent-meldinger" id="samlab-assistent-meldinger" aria-live="polite">
				<p class="samlab-assistent-boble er-assistent"><?php echo esc_html( samlab_assistent_velkomst() ); ?></p>
			</div>
			<p class="samlab-assistent-skriver" id="samlab-assistent-skriver" hidden>
				<?php
				/* translators: %s: assistentens navn. */
				echo esc_html( sprintf( __( '%s skriver …', 'samlab' ), $navn ) );
				?>
			</p>
			<form class="samlab-assistent-skjema" id="samlab-assistent-skjema">
				<label class="screen-reader-text" for="samlab-assistent-felt"><?php esc_html_e( 'Spør assistenten', 'samlab' ); ?></label>
				<input type="text" id="samlab-assistent-felt" maxlength="4000" autocomplete="off"
					placeholder="<?php esc_attr_e( 'Skriv et spørsmål …', 'samlab' ); ?>" />
				<button type="submit" class="samlab-knapp er-primar"><?php esc_html_e( 'Send', 'samlab' ); ?></button>
			</form>
		</section>
	</div>
	<script>
	( function () {
		if ( ! window.samlabRest ) {
			return;
		}
		var knapp     = document.getElementById( 'samlab-assistent-knapp' );
		var panel     = document.getElementById( 'samlab-assistent-panel' );
		var lukk      = document.getElementById( 'samlab-assistent-lukk' );
		var meldinger = document.getElementById( 'samlab-assistent-meldinger' );
		var skriver   = document.getElementById( 'samlab-assistent-skriver' );
		var skjema    = document.getElementById( 'samlab-assistent-skjema' );
		var felt      = document.getElementById( 'samlab-assistent-felt' );
		var historikk = [];

		function veksle( apen ) {
			panel.hidden = ! apen;
			knapp.setAttribute( 'aria-expanded', String( apen ) );
			if ( apen ) {
				felt.focus();
			}
		}
		knapp.addEventListener( 'click', function () {
			veksle( panel.hidden );
		} );
		lukk.addEventListener( 'click', function () {
			veksle( false );
		} );

		// All tekst inn i DOM-en går via textContent - aldri innerHTML.
		function boble( tekst, klasse ) {
			var p = document.createElement( 'p' );
			p.className = 'samlab-assistent-boble ' + klasse;
			p.textContent = tekst;
			meldinger.appendChild( p );
			meldinger.scrollTop = meldinger.scrollHeight;
		}

		skjema.addEventListener( 'submit', function ( hendelse ) {
			hendelse.preventDefault();
			var melding = felt.value.trim();
			if ( '' === melding || ! skriver.hidden ) {
				return;
			}
			felt.value = '';
			boble( melding, 'er-medlem' );
			skriver.hidden = false;

			fetch( window.samlabRest.url + 'samlab/v1/assistent', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.samlabRest.nonce
				},
				body: JSON.stringify( { melding: melding, historikk: historikk } )
			} ).then( function ( svar ) {
				return svar.json();
			} ).then( function ( data ) {
				skriver.hidden = true;
				if ( data && data.svar ) {
					boble( data.svar, 'er-assistent' );
					historikk.push( { rolle: 'user', tekst: melding } );
					historikk.push( { rolle: 'assistant', tekst: data.svar } );
					historikk = historikk.slice( -10 );
					return;
				}
				boble( data && data.message ? data.message : <?php echo wp_json_encode( __( 'Noe gikk galt - prøv igjen.', 'samlab' ) ); ?>, 'er-feil' );
			} ).catch( function () {
				skriver.hidden = true;
				boble( <?php echo wp_json_encode( __( 'Noe gikk galt - prøv igjen.', 'samlab' ) ); ?>, 'er-feil' );
			} );
		} );
	}() );
	</script>
	<?php
}
add_action( 'samlab_portal_bunn', 'samlab_assistent_widget' );
