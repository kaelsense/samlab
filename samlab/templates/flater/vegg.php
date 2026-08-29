<?php
/**
 * Veggen: feed fra samlab_innlegg-tabellen med nytt innlegg-skjema,
 * reaksjoner via REST, WordPress-kommentarer og festede oppslag.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$samlab_flate = samlab_portal_views()['vegg'];

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$samlab_feil = isset( $_GET['feil'] ) ? sanitize_key( wp_unslash( $_GET['feil'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$samlab_innlegg_liste = Samlab_Innlegg::get_list( array( 'limit' => 50 ) );
$samlab_kan_moderere  = current_user_can( 'samlab_pin_posts' ) || current_user_can( 'samlab_hide_content' );
?>
<header class="samlab-flate-hode">
	<h1><?php echo esc_html( $samlab_flate['label'] ); ?></h1>
</header>

<?php if ( 'innlegg' === $samlab_feil ) : ?>
	<p class="samlab-melding er-feil"><?php esc_html_e( 'Innlegget kan ikke være tomt.', 'samlab' ); ?></p>
<?php endif; ?>

<?php if ( current_user_can( 'samlab_post_wall' ) ) : ?>
	<section class="samlab-kort samlab-skjema samlab-vegg-skjema">
		<h2 class="screen-reader-text"><?php esc_html_e( 'Nytt innlegg', 'samlab' ); ?></h2>
		<form method="post" action="<?php echo esc_url( samlab_portal_url( 'vegg' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'samlab_nytt_innlegg', 'samlab_vegg_nonce' ); ?>
			<p>
				<label class="screen-reader-text" for="samlab-innhold"><?php esc_html_e( 'Hva skjer?', 'samlab' ); ?></label>
				<textarea id="samlab-innhold" name="samlab_innhold" rows="3" placeholder="<?php esc_attr_e( 'Del noe med huset …', 'samlab' ); ?>" required></textarea>
			</p>
			<ul id="samlab-mention-forslag" class="samlab-mention-forslag" hidden></ul>
			<p class="samlab-vegg-verktoy">
				<label for="samlab-bilde"><?php esc_html_e( 'Bilde (valgfritt)', 'samlab' ); ?></label>
				<input type="file" id="samlab-bilde" name="samlab_bilde" accept="image/*" />
				<button type="submit" class="samlab-knapp er-primar"><?php esc_html_e( 'Del innlegg', 'samlab' ); ?></button>
			</p>
		</form>
	</section>
<?php endif; ?>

<?php if ( array() === $samlab_innlegg_liste ) : ?>
	<p class="samlab-tom"><?php esc_html_e( 'Ingen innlegg ennå - del det første!', 'samlab' ); ?></p>
<?php else : ?>
	<ul class="samlab-vegg">
		<?php
		foreach ( $samlab_innlegg_liste as $samlab_innlegg ) :
			$samlab_forfatter = get_userdata( (int) $samlab_innlegg->user_id );
			$samlab_navn      = $samlab_forfatter ? $samlab_forfatter->display_name : __( 'Ukjent', 'samlab' );
			$samlab_antall    = Samlab_Reaksjon::counts( 'innlegg', (int) $samlab_innlegg->id );
			$samlab_liker     = isset( $samlab_antall['like'] ) ? $samlab_antall['like'] : 0;
			$samlab_har_likt  = Samlab_Reaksjon::user_has( 'innlegg', (int) $samlab_innlegg->id, get_current_user_id() );
			$samlab_komm      = get_comments(
				array(
					'type'       => 'samlab_innlegg',
					'status'     => 'approve',
					'orderby'    => 'comment_date',
					'order'      => 'ASC',
					'meta_key'   => '_samlab_innlegg', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Kobling kommentar<->innlegg.
					'meta_value' => (int) $samlab_innlegg->id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			?>
			<li class="samlab-kort samlab-innlegg<?php echo $samlab_innlegg->pinned ? ' er-festet' : ''; ?>" id="innlegg-<?php echo esc_attr( (string) $samlab_innlegg->id ); ?>">
				<?php if ( $samlab_innlegg->pinned ) : ?>
					<p class="samlab-festet-merke"><?php esc_html_e( 'Festet oppslag', 'samlab' ); ?></p>
				<?php endif; ?>
				<div class="samlab-innlegg-hode">
					<span class="samlab-avatar" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $samlab_navn, 0, 1 ) ) ); ?></span>
					<div>
						<p class="samlab-person-navn"><?php echo esc_html( $samlab_navn ); ?></p>
						<p class="samlab-kort-meta">
							<?php
							/* translators: %s: tid siden innlegget, f.eks. «2 timer». */
							echo esc_html( sprintf( __( '%s siden', 'samlab' ), human_time_diff( strtotime( $samlab_innlegg->created_at ) ) ) );
							?>
						</p>
					</div>
				</div>
				<div class="samlab-innlegg-tekst"><?php echo wp_kses_post( samlab_render_mentions( wpautop( $samlab_innlegg->content ) ) ); ?></div>
				<?php if ( $samlab_innlegg->image_id ) : ?>
					<figure class="samlab-innlegg-bilde"><?php echo wp_get_attachment_image( (int) $samlab_innlegg->image_id, 'large' ); ?></figure>
				<?php endif; ?>
				<p class="samlab-innlegg-handlinger">
					<button type="button" class="samlab-knapp samlab-liker" data-id="<?php echo esc_attr( (string) $samlab_innlegg->id ); ?>" aria-pressed="<?php echo $samlab_har_likt ? 'true' : 'false'; ?>">
						&#10084; <span class="samlab-liker-antall"><?php echo esc_html( (string) $samlab_liker ); ?></span>
					</button>
					<?php if ( $samlab_kan_moderere ) : ?>
					<form method="post" action="<?php echo esc_url( samlab_portal_url( 'vegg' ) ); ?>" class="samlab-moderer">
						<?php wp_nonce_field( 'samlab_moderer_innlegg', 'samlab_moderer_nonce' ); ?>
						<input type="hidden" name="samlab_innlegg_id" value="<?php echo esc_attr( (string) $samlab_innlegg->id ); ?>" />
						<?php if ( current_user_can( 'samlab_pin_posts' ) ) : ?>
							<button type="submit" class="samlab-knapp" name="samlab_handling" value="<?php echo $samlab_innlegg->pinned ? 'losne' : 'fest'; ?>">
								<?php $samlab_innlegg->pinned ? esc_html_e( 'Løsne', 'samlab' ) : esc_html_e( 'Fest', 'samlab' ); ?>
							</button>
						<?php endif; ?>
						<?php if ( current_user_can( 'samlab_hide_content' ) ) : ?>
							<button type="submit" class="samlab-knapp" name="samlab_handling" value="skjul"><?php esc_html_e( 'Skjul', 'samlab' ); ?></button>
						<?php endif; ?>
					</form>
					<?php endif; ?>
				</p>
				<?php if ( array() !== $samlab_komm ) : ?>
					<ul class="samlab-kommentarer">
						<?php foreach ( $samlab_komm as $samlab_kommentar ) : ?>
							<li>
								<span class="samlab-person-navn"><?php echo esc_html( $samlab_kommentar->comment_author ); ?></span>
								<?php echo esc_html( $samlab_kommentar->comment_content ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( samlab_portal_url( 'vegg' ) ); ?>" class="samlab-kommentar-skjema">
					<?php wp_nonce_field( 'samlab_kommentar', 'samlab_kommentar_nonce' ); ?>
					<input type="hidden" name="samlab_innlegg_id" value="<?php echo esc_attr( (string) $samlab_innlegg->id ); ?>" />
					<label class="screen-reader-text" for="samlab-kommentar-<?php echo esc_attr( (string) $samlab_innlegg->id ); ?>"><?php esc_html_e( 'Kommenter', 'samlab' ); ?></label>
					<input type="text" id="samlab-kommentar-<?php echo esc_attr( (string) $samlab_innlegg->id ); ?>" name="samlab_kommentar" placeholder="<?php esc_attr_e( 'Kommenter …', 'samlab' ); ?>" required />
					<button type="submit" class="samlab-knapp"><?php esc_html_e( 'Send', 'samlab' ); ?></button>
				</form>
			</li>
		<?php endforeach; ?>
	</ul>

	<script>
	( function () {
		if ( ! window.samlabRest ) {
			return;
		}

		// @-mentions: forslag mens man skriver i nytt innlegg-feltet.
		var felt    = document.getElementById( 'samlab-innhold' );
		var forslag = document.getElementById( 'samlab-mention-forslag' );
		if ( felt && forslag ) {
			felt.addEventListener( 'input', function () {
				var tekst = felt.value.slice( 0, felt.selectionStart );
				var treff = tekst.match( /@([a-zA-Z0-9._-]{1,})$/ );
				if ( ! treff ) {
					forslag.hidden = true;
					return;
				}
				fetch( window.samlabRest.url + 'samlab/v1/brukere?sok=' + encodeURIComponent( treff[1] ), {
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': window.samlabRest.nonce }
				} ).then( function ( svar ) {
					return svar.json();
				} ).then( function ( brukere ) {
					forslag.innerHTML = '';
					forslag.hidden = ! brukere.length;
					brukere.forEach( function ( bruker ) {
						var li = document.createElement( 'li' );
						var knapp = document.createElement( 'button' );
						knapp.type = 'button';
						knapp.textContent = bruker.navn + ' (@' + bruker.login + ')';
						knapp.addEventListener( 'click', function () {
							var start = felt.value.slice( 0, felt.selectionStart ).replace( /@[a-zA-Z0-9._-]*$/, '@' + bruker.login + ' ' );
							felt.value = start + felt.value.slice( felt.selectionStart );
							forslag.hidden = true;
							felt.focus();
						} );
						li.appendChild( knapp );
						forslag.appendChild( li );
					} );
				} );
			} );
		}
		document.querySelectorAll( '.samlab-liker' ).forEach( function ( knapp ) {
			knapp.addEventListener( 'click', function () {
				fetch( window.samlabRest.url + 'samlab/v1/reaksjoner', {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': window.samlabRest.nonce
					},
					body: JSON.stringify( { object_id: parseInt( knapp.dataset.id, 10 ) } )
				} ).then( function ( svar ) {
					return svar.json();
				} ).then( function ( data ) {
					if ( data && data.counts ) {
						knapp.querySelector( '.samlab-liker-antall' ).textContent = data.counts.like || 0;
						knapp.setAttribute( 'aria-pressed', data.reacted ? 'true' : 'false' );
					}
				} );
			} );
		} );
	}() );
	</script>
<?php endif; ?>
