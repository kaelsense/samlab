<?php
/**
 * Arrangementer: kommende først (nærmeste øverst), deretter
 * tidligere, og «nytt arrangement»-skjema for medlemmer med
 * samlab_create_arrangement.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$samlab_flate = samlab_portal_views()['arrangementer'];

// Statusmeldinger fra URL-en (ingen tilstandsendring).
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$samlab_opprettet = isset( $_GET['opprettet'] ) ? absint( $_GET['opprettet'] ) : 0;
$samlab_feil      = isset( $_GET['feil'] ) ? sanitize_key( wp_unslash( $_GET['feil'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$samlab_kommende       = samlab_kommende_arrangementer();
$samlab_tidligere      = samlab_tidligere_arrangementer();
$samlab_mine_bedrifter = is_user_logged_in() ? samlab_behov_bedrifter_for( get_current_user_id() ) : array();
?>
<header class="samlab-flate-hode">
	<h1><?php echo esc_html( $samlab_flate['label'] ); ?></h1>
	<p><?php esc_html_e( 'Det som skjer i huset - legg inn ditt eget arrangement nederst.', 'samlab' ); ?></p>
</header>

<?php if ( $samlab_opprettet ) : ?>
	<p class="samlab-melding er-suksess"><?php esc_html_e( 'Arrangementet er publisert.', 'samlab' ); ?></p>
<?php elseif ( 'tittel' === $samlab_feil ) : ?>
	<p class="samlab-melding er-feil"><?php esc_html_e( 'Arrangementet trenger en tittel - prøv igjen.', 'samlab' ); ?></p>
<?php elseif ( 'tid' === $samlab_feil ) : ?>
	<p class="samlab-melding er-feil"><?php esc_html_e( 'Arrangementet trenger et starttidspunkt - prøv igjen.', 'samlab' ); ?></p>
<?php endif; ?>

<?php if ( array() === $samlab_kommende ) : ?>
	<p class="samlab-tom"><?php esc_html_e( 'Ingen kommende arrangementer - legg gjerne inn et selv.', 'samlab' ); ?></p>
<?php else : ?>
	<ul class="samlab-kort-grid">
		<?php foreach ( $samlab_kommende as $samlab_arrangement ) : ?>
			<?php
			$samlab_sted = (string) get_post_meta( $samlab_arrangement->ID, '_samlab_sted', true );
			$samlab_bid  = (int) get_post_meta( $samlab_arrangement->ID, '_samlab_bedrift', true );
			?>
			<li class="samlab-kort" id="arrangement-<?php echo esc_attr( (string) $samlab_arrangement->ID ); ?>">
				<p class="samlab-kort-meta"><?php echo esc_html( samlab_arrangement_tid_visning( $samlab_arrangement->ID ) ); ?></p>
				<h2><?php echo esc_html( get_the_title( $samlab_arrangement ) ); ?></h2>
				<?php if ( '' !== $samlab_sted ) : ?>
					<p class="samlab-kort-meta"><?php echo esc_html( $samlab_sted ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== trim( $samlab_arrangement->post_content ) ) : ?>
					<p class="samlab-kort-tekst"><?php echo esc_html( wp_trim_words( $samlab_arrangement->post_content, 30 ) ); ?></p>
				<?php endif; ?>
				<?php if ( $samlab_bid && 'publish' === get_post_status( $samlab_bid ) ) : ?>
					<p class="samlab-kort-meta">
						<a href="<?php echo esc_url( samlab_portal_url( 'bedrifter', get_post_field( 'post_name', $samlab_bid ) ) ); ?>"><?php echo esc_html( get_the_title( $samlab_bid ) ); ?></a>
					</p>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php if ( array() !== $samlab_tidligere ) : ?>
	<section class="samlab-profil-del">
		<h2><?php esc_html_e( 'Tidligere arrangementer', 'samlab' ); ?></h2>
		<ul class="samlab-sokeliste">
			<?php foreach ( $samlab_tidligere as $samlab_arrangement ) : ?>
				<li id="arrangement-<?php echo esc_attr( (string) $samlab_arrangement->ID ); ?>">
					<?php echo esc_html( get_the_title( $samlab_arrangement ) ); ?>
					<p class="samlab-kort-meta"><?php echo esc_html( samlab_arrangement_tid_visning( $samlab_arrangement->ID ) ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php if ( current_user_can( 'samlab_create_arrangement' ) ) : ?>
	<section class="samlab-profil-del samlab-kort samlab-skjema">
		<h2><?php esc_html_e( 'Nytt arrangement', 'samlab' ); ?></h2>
		<form method="post" action="<?php echo esc_url( samlab_portal_url( 'arrangementer' ) ); ?>">
			<?php wp_nonce_field( 'samlab_nytt_arrangement', 'samlab_arrangement_skjema_nonce' ); ?>
			<p>
				<label for="samlab-tittel"><?php esc_html_e( 'Tittel', 'samlab' ); ?> *</label><br />
				<input type="text" id="samlab-tittel" name="samlab_tittel" required />
			</p>
			<p>
				<label for="samlab-start"><?php esc_html_e( 'Start', 'samlab' ); ?> *</label><br />
				<input type="datetime-local" id="samlab-start" name="samlab_start" required />
			</p>
			<p>
				<label for="samlab-slutt"><?php esc_html_e( 'Slutt (valgfri)', 'samlab' ); ?></label><br />
				<input type="datetime-local" id="samlab-slutt" name="samlab_slutt" />
			</p>
			<p>
				<label for="samlab-sted"><?php esc_html_e( 'Sted', 'samlab' ); ?></label><br />
				<input type="text" id="samlab-sted" name="samlab_sted" />
			</p>
			<p>
				<label for="samlab-beskrivelse"><?php esc_html_e( 'Beskrivelse', 'samlab' ); ?></label><br />
				<textarea id="samlab-beskrivelse" name="samlab_beskrivelse" rows="4"></textarea>
			</p>
			<?php if ( array() !== $samlab_mine_bedrifter ) : ?>
				<p>
					<label for="samlab-bedrift"><?php esc_html_e( 'Arrangør (bedrift)', 'samlab' ); ?></label><br />
					<select id="samlab-bedrift" name="samlab_bedrift">
						<option value="0"><?php esc_html_e( '- Huset / ingen bedrift -', 'samlab' ); ?></option>
						<?php foreach ( $samlab_mine_bedrifter as $samlab_bedrift ) : ?>
							<option value="<?php echo esc_attr( (string) $samlab_bedrift->ID ); ?>"><?php echo esc_html( get_the_title( $samlab_bedrift ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>
			<p><button type="submit" class="samlab-knapp er-primar"><?php esc_html_e( 'Publiser arrangementet', 'samlab' ); ?></button></p>
		</form>
	</section>
<?php endif; ?>
