<?php
/**
 * Bedriftskatalogen: kort-grid med kategori-chips og søk.
 * Struktur fra prototypens bedrifter-index; farger fra temaet.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Med undersides-slug vises profilen i stedet for katalogen.
if ( isset( $samlab_item ) && '' !== $samlab_item ) {
	require __DIR__ . '/bedrift-profil.php';
	return;
}

$samlab_flate = samlab_portal_views()['bedrifter'];

// Lesefiltre fra URL-en (ingen tilstandsendring - nonce er ikke påkrevd).
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$samlab_sok      = isset( $_GET['sok'] ) ? sanitize_text_field( wp_unslash( $_GET['sok'] ) ) : '';
$samlab_kategori = isset( $_GET['kategori'] ) ? sanitize_title( wp_unslash( $_GET['kategori'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$samlab_args = array(
	'post_type'      => 'samlab_bedrift',
	'post_status'    => 'publish',
	'orderby'        => 'title',
	'order'          => 'ASC',
	'posts_per_page' => 100,
);
if ( '' !== $samlab_sok ) {
	$samlab_args['s'] = $samlab_sok;
}
if ( '' !== $samlab_kategori ) {
	$samlab_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Katalogfilter; lavvolum CPT.
		array(
			'taxonomy' => 'samlab_kategori',
			'field'    => 'slug',
			'terms'    => $samlab_kategori,
		),
	);
}
$samlab_sporring = new WP_Query( $samlab_args );

$samlab_termer = get_terms(
	array(
		'taxonomy'   => 'samlab_kategori',
		'hide_empty' => true,
	)
);
if ( is_wp_error( $samlab_termer ) ) {
	$samlab_termer = array();
}
?>
<header class="samlab-flate-hode">
	<h1><?php echo esc_html( $samlab_flate['label'] ); ?></h1>
	<p><?php esc_html_e( 'Bli kjent med bedriftene i huset - hver har sin egen side med folk, tjenester og hva de ser etter.', 'samlab' ); ?></p>
</header>

<form class="samlab-sok" method="get" action="<?php echo esc_url( samlab_portal_url( 'bedrifter' ) ); ?>" role="search">
	<label class="screen-reader-text" for="samlab-sok-felt"><?php esc_html_e( 'Søk i bedrifter', 'samlab' ); ?></label>
	<input type="search" id="samlab-sok-felt" name="sok" value="<?php echo esc_attr( $samlab_sok ); ?>" placeholder="<?php esc_attr_e( 'Søk i bedrifter …', 'samlab' ); ?>" />
	<?php if ( '' !== $samlab_kategori ) : ?>
		<input type="hidden" name="kategori" value="<?php echo esc_attr( $samlab_kategori ); ?>" />
	<?php endif; ?>
	<button type="submit" class="samlab-knapp"><?php esc_html_e( 'Søk', 'samlab' ); ?></button>
</form>

<ul class="samlab-chips samlab-katalog-filter">
	<li>
		<a class="samlab-chip<?php echo '' === $samlab_kategori ? ' er-aktiv' : ''; ?>" href="<?php echo esc_url( samlab_portal_url( 'bedrifter' ) ); ?>"><?php esc_html_e( 'Alle', 'samlab' ); ?></a>
	</li>
	<?php foreach ( $samlab_termer as $samlab_term ) : ?>
		<li>
			<a class="samlab-chip<?php echo $samlab_term->slug === $samlab_kategori ? ' er-aktiv' : ''; ?>"
				href="<?php echo esc_url( add_query_arg( 'kategori', $samlab_term->slug, samlab_portal_url( 'bedrifter' ) ) ); ?>">
				<?php echo esc_html( $samlab_term->name ); ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>

<?php if ( ! $samlab_sporring->have_posts() ) : ?>
	<p class="samlab-tom"><?php esc_html_e( 'Ingen bedrifter matchet søket.', 'samlab' ); ?></p>
<?php else : ?>
	<ul class="samlab-kort-grid">
		<?php
		while ( $samlab_sporring->have_posts() ) :
			$samlab_sporring->the_post();
			$samlab_id      = get_the_ID();
			$samlab_navn    = get_the_title();
			$samlab_kort    = get_post_meta( $samlab_id, '_samlab_kort', true );
			$samlab_plass   = get_post_meta( $samlab_id, '_samlab_plass', true );
			$samlab_kats    = get_the_terms( $samlab_id, 'samlab_kategori' );
			$samlab_katnavn = $samlab_kats && ! is_wp_error( $samlab_kats ) ? wp_list_pluck( $samlab_kats, 'name' ) : array();

			$samlab_ord      = preg_split( '/\s+/', trim( $samlab_navn ) );
			$samlab_initial  = mb_strtoupper( mb_substr( $samlab_ord[0], 0, 1 ) );
			$samlab_initial .= count( $samlab_ord ) > 1 ? mb_strtoupper( mb_substr( end( $samlab_ord ), 0, 1 ) ) : '';
			?>
			<li>
				<a class="samlab-kort samlab-kort-lenke" href="<?php echo esc_url( samlab_portal_url( 'bedrifter', get_post_field( 'post_name', $samlab_id ) ) ); ?>">
					<span class="samlab-kort-logo">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'thumbnail', array( 'alt' => $samlab_navn ) ); ?>
						<?php else : ?>
							<span class="samlab-avatar er-aksent" aria-hidden="true"><?php echo esc_html( $samlab_initial ); ?></span>
						<?php endif; ?>
					</span>
					<span class="samlab-kort-innhold">
						<h2><?php echo esc_html( $samlab_navn ); ?></h2>
						<?php if ( array() !== $samlab_katnavn ) : ?>
							<p class="samlab-kort-kategori"><?php echo esc_html( implode( ' · ', $samlab_katnavn ) ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $samlab_kort ) : ?>
							<p class="samlab-kort-tekst"><?php echo esc_html( $samlab_kort ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $samlab_plass ) : ?>
							<p class="samlab-kort-meta"><?php echo esc_html( $samlab_plass ); ?></p>
						<?php endif; ?>
					</span>
				</a>
			</li>
		<?php endwhile; ?>
	</ul>
	<?php wp_reset_postdata(); ?>
<?php endif; ?>
