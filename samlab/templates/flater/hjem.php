<?php
/**
 * Portalens forside: globalt søk over bedrifter, behov og håndbok,
 * ellers velkomst med snarveier til flatene.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$samlab_sok = isset( $_GET['sok'] ) ? sanitize_text_field( wp_unslash( $_GET['sok'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( '' !== $samlab_sok ) :
	$samlab_grupper    = samlab_global_search( $samlab_sok );
	$samlab_gruppenavn = array(
		'bedrifter'     => samlab_portal_views()['bedrifter']['label'],
		'behov'         => samlab_portal_views()['behov']['label'],
		'arrangementer' => samlab_portal_views()['arrangementer']['label'],
		'handbok'       => samlab_portal_views()['handbok']['label'],
	);
	?>
	<header class="samlab-flate-hode">
		<h1>
			<?php
			/* translators: %s: søkestrengen. */
			echo esc_html( sprintf( __( 'Søk: «%s»', 'samlab' ), $samlab_sok ) );
			?>
		</h1>
	</header>

	<?php if ( array() === $samlab_grupper ) : ?>
		<p class="samlab-tom"><?php esc_html_e( 'Ingen treff i bedrifter, behov eller håndboken.', 'samlab' ); ?></p>
	<?php else : ?>
		<?php foreach ( $samlab_grupper as $samlab_gruppe => $samlab_treff ) : ?>
			<section class="samlab-profil-del">
				<h2><?php echo esc_html( $samlab_gruppenavn[ $samlab_gruppe ] ); ?></h2>
				<ul class="samlab-sokeliste">
					<?php foreach ( $samlab_treff as $samlab_post ) : ?>
						<?php
						if ( 'bedrifter' === $samlab_gruppe ) {
							$samlab_lenke = samlab_portal_url( 'bedrifter', $samlab_post->post_name );
						} elseif ( 'handbok' === $samlab_gruppe ) {
							$samlab_lenke = samlab_portal_url( 'handbok', $samlab_post->post_name );
						} elseif ( 'arrangementer' === $samlab_gruppe ) {
							$samlab_lenke = samlab_portal_url( 'arrangementer' ) . '#arrangement-' . $samlab_post->ID;
						} else {
							$samlab_lenke = samlab_portal_url( 'behov' ) . '#behov-' . $samlab_post->ID;
						}
						?>
						<li>
							<a href="<?php echo esc_url( $samlab_lenke ); ?>"><?php echo esc_html( get_the_title( $samlab_post ) ); ?></a>
							<?php if ( '' !== $samlab_post->post_excerpt || '' !== $samlab_post->post_content ) : ?>
								<p class="samlab-kort-meta"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $samlab_post->post_content ), 20 ) ); ?></p>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
<?php else : ?>
	<h1><?php echo esc_html( samlab_portal_name() ); ?></h1>
	<p><?php esc_html_e( 'Velkommen til portalen - snarveiene under tar deg til flatene.', 'samlab' ); ?></p>
	<ul class="samlab-kort-grid">
		<?php foreach ( samlab_portal_views() as $samlab_key => $samlab_flate ) : ?>
			<li>
				<a class="samlab-kort samlab-kort-lenke" href="<?php echo esc_url( samlab_portal_url( $samlab_key ) ); ?>">
					<span class="samlab-kort-innhold"><h2><?php echo esc_html( $samlab_flate['label'] ); ?></h2></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
