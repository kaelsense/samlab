<?php
/**
 * Koblinger: partens egne introduksjoner (G3). Åpne forespørsler
 * øverst med Takk ja / Nei takk mot svar-endepunktet (G2), deretter
 * aktive koblinger (motpartens kontakt deles først fra godkjent) og
 * historikk - med statuskjede-visning som i prototypen.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$samlab_flate  = samlab_portal_views()['koblinger'];
$samlab_bruker = get_current_user_id();

$samlab_foresporsler = array();
$samlab_aktive       = array();
$samlab_historikk    = array();
foreach ( samlab_koblinger_for( $samlab_bruker ) as $samlab_kobling ) {
	$samlab_status = get_post_meta( $samlab_kobling->ID, '_samlab_status', true );
	if ( 'forespurt' === $samlab_status ) {
		$samlab_foresporsler[] = $samlab_kobling;
	} elseif ( in_array( $samlab_status, array( 'godkjent', 'introdusert' ), true ) ) {
		$samlab_aktive[] = $samlab_kobling;
	} elseif ( in_array( $samlab_status, array( 'fulgt_opp', 'avvist' ), true ) ) {
		$samlab_historikk[] = $samlab_kobling;
	}
	// Foreslåtte koblinger er kun moderatorens arbeidsflate og
	// vises ikke for partene.
}
?>
<header class="samlab-flate-hode">
	<h1><?php echo esc_html( $samlab_flate['label'] ); ?></h1>
	<p><?php esc_html_e( 'Introduksjoner mellom deg og andre i huset - ingen kobles uten at begge har takket ja.', 'samlab' ); ?></p>
</header>

<p class="samlab-melding er-feil" id="samlab-kobling-feil" hidden><?php esc_html_e( 'Svaret nådde ikke frem - prøv igjen.', 'samlab' ); ?></p>

<?php if ( array() === $samlab_foresporsler && array() === $samlab_aktive && array() === $samlab_historikk ) : ?>
	<p class="samlab-tom"><?php esc_html_e( 'Ingen koblinger ennå. Når verten foreslår en introduksjon for deg, dukker forespørselen opp her.', 'samlab' ); ?></p>
<?php endif; ?>

<?php if ( array() !== $samlab_foresporsler ) : ?>
	<section class="samlab-profil-del">
		<h2><?php esc_html_e( 'Forespørsler til deg', 'samlab' ); ?></h2>
		<ul class="samlab-kort-grid">
			<?php foreach ( $samlab_foresporsler as $samlab_kobling ) : ?>
				<?php
				$samlab_part     = samlab_kobling_bruker_part( $samlab_kobling->ID, $samlab_bruker );
				$samlab_motpart  = samlab_kobling_part_navn( $samlab_kobling->ID, 'a' === $samlab_part ? 'b' : 'a' );
				$samlab_samtykke = samlab_kobling_samtykke( $samlab_kobling->ID, $samlab_part );
				?>
				<li class="samlab-kort" id="kobling-<?php echo esc_attr( (string) $samlab_kobling->ID ); ?>">
					<h3><?php echo esc_html( get_the_title( $samlab_kobling ) ); ?></h3>
					<?php if ( '' !== $samlab_motpart ) : ?>
						<?php /* translators: %s: motpartens navn. */ ?>
						<p class="samlab-kort-meta"><?php echo esc_html( sprintf( __( 'Foreslått kobling med %s', 'samlab' ), $samlab_motpart ) ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== trim( $samlab_kobling->post_content ) ) : ?>
						<p class="samlab-kort-tekst"><?php echo esc_html( $samlab_kobling->post_content ); ?></p>
					<?php endif; ?>
					<?php if ( 'venter' === $samlab_samtykke ) : ?>
						<p>
							<button type="button" class="samlab-knapp er-primar samlab-kobling-svar" data-kobling="<?php echo esc_attr( (string) $samlab_kobling->ID ); ?>" data-svar="ja"><?php esc_html_e( 'Takk ja', 'samlab' ); ?></button>
							<button type="button" class="samlab-knapp samlab-kobling-svar" data-kobling="<?php echo esc_attr( (string) $samlab_kobling->ID ); ?>" data-svar="nei"><?php esc_html_e( 'Nei takk', 'samlab' ); ?></button>
						</p>
					<?php else : ?>
						<p class="samlab-kort-meta"><?php esc_html_e( 'Du har takket ja - venter på svar fra motparten.', 'samlab' ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php if ( array() !== $samlab_aktive ) : ?>
	<section class="samlab-profil-del">
		<h2><?php esc_html_e( 'Aktive koblinger', 'samlab' ); ?></h2>
		<ul class="samlab-kort-grid">
			<?php foreach ( $samlab_aktive as $samlab_kobling ) : ?>
				<?php
				$samlab_part    = samlab_kobling_bruker_part( $samlab_kobling->ID, $samlab_bruker );
				$samlab_kontakt = samlab_kobling_part_bruker( $samlab_kobling->ID, 'a' === $samlab_part ? 'b' : 'a' );
				?>
				<li class="samlab-kort" id="kobling-<?php echo esc_attr( (string) $samlab_kobling->ID ); ?>">
					<h3><?php echo esc_html( get_the_title( $samlab_kobling ) ); ?></h3>
					<?php samlab_render_kobling_statuskjede( $samlab_kobling->ID ); ?>
					<?php if ( '' !== trim( $samlab_kobling->post_content ) ) : ?>
						<p class="samlab-kort-tekst"><?php echo esc_html( $samlab_kobling->post_content ); ?></p>
					<?php endif; ?>
					<?php if ( $samlab_kontakt ) : ?>
						<p class="samlab-kort-meta">
							<?php esc_html_e( 'Ta kontakt:', 'samlab' ); ?>
							<?php echo esc_html( $samlab_kontakt->display_name ); ?> -
							<a href="<?php echo esc_url( 'mailto:' . $samlab_kontakt->user_email ); ?>"><?php echo esc_html( $samlab_kontakt->user_email ); ?></a>
						</p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php if ( array() !== $samlab_historikk ) : ?>
	<section class="samlab-profil-del">
		<h2><?php esc_html_e( 'Historikk', 'samlab' ); ?></h2>
		<ul class="samlab-sokeliste">
			<?php foreach ( $samlab_historikk as $samlab_kobling ) : ?>
				<li id="kobling-<?php echo esc_attr( (string) $samlab_kobling->ID ); ?>">
					<?php echo esc_html( get_the_title( $samlab_kobling ) ); ?>
					<?php samlab_render_kobling_statuskjede( $samlab_kobling->ID ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php if ( array() !== $samlab_foresporsler ) : ?>
	<script>
		// Svar-knappene poster mot G2-endepunktet. Kjøres på
		// DOMContentLoaded fordi window.samlabRest settes i skallet
		// etter hovedinnholdet.
		document.addEventListener( 'DOMContentLoaded', function () {
			var feil = document.getElementById( 'samlab-kobling-feil' );
			document.querySelectorAll( '.samlab-kobling-svar' ).forEach( function ( knapp ) {
				knapp.addEventListener( 'click', function () {
					if ( 'nei' === knapp.dataset.svar && ! window.confirm( <?php echo wp_json_encode( __( 'Takke nei? Da avsluttes forslaget, og motparten får et nøytralt varsel uten navn.', 'samlab' ) ); ?> ) ) {
						return;
					}
					knapp.disabled = true;
					fetch( window.samlabRest.url + 'samlab/v1/koblinger/' + knapp.dataset.kobling + '/svar', {
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.samlabRest.nonce
						},
						body: JSON.stringify( { svar: knapp.dataset.svar } )
					} ).then( function ( svar ) {
						if ( svar.ok ) {
							window.location.reload();
							return;
						}
						knapp.disabled = false;
						feil.hidden = false;
					} ).catch( function () {
						knapp.disabled = false;
						feil.hidden = false;
					} );
				} );
			} );
		} );
	</script>
<?php endif; ?>
