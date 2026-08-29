<?php
/**
 * Bedriftsprofilen: logo, om-tekst, «Dette ser vi etter», tjenester,
 * folkene, galleri og aktive behov. Struktur fra prototypens
 * bedrifter-slug; alle data fra B3/B5-feltene.
 *
 * Forventer $samlab_item (bedriftens slug) fra bedrifter.php.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$samlab_bedrift = samlab_get_bedrift_by_slug( $samlab_item );
if ( ! $samlab_bedrift ) {
	require __DIR__ . '/404.php';
	return;
}

$samlab_id      = $samlab_bedrift->ID;
$samlab_navn    = get_the_title( $samlab_bedrift );
$samlab_kort    = get_post_meta( $samlab_id, '_samlab_kort', true );
$samlab_plass   = get_post_meta( $samlab_id, '_samlab_plass', true );
$samlab_nett    = get_post_meta( $samlab_id, '_samlab_nettside', true );
$samlab_kontakt = get_userdata( (int) get_post_meta( $samlab_id, '_samlab_kontaktperson', true ) );
$samlab_tjen    = get_post_meta( $samlab_id, '_samlab_tjenester', true );
$samlab_apen    = get_post_meta( $samlab_id, '_samlab_apen_for', true );
$samlab_kats    = get_the_terms( $samlab_id, 'samlab_kategori' );
$samlab_katnavn = $samlab_kats && ! is_wp_error( $samlab_kats ) ? wp_list_pluck( $samlab_kats, 'name' ) : array();

$samlab_intensjoner = array();
foreach ( samlab_bedrift_intent_fields() as $samlab_nokkel => $samlab_etikett ) {
	$samlab_verdi = get_post_meta( $samlab_id, $samlab_nokkel, true );
	if ( '' !== $samlab_verdi ) {
		$samlab_intensjoner[ $samlab_etikett ] = $samlab_verdi;
	}
}

$samlab_galleri = get_attached_media( 'image', $samlab_id );

$samlab_behov_liste = get_posts(
	array(
		'post_type'      => 'samlab_behov',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'meta_key'       => '_samlab_bedrift', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lavvolum kobling bedrift<->behov.
		'meta_value'     => $samlab_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	)
);

$samlab_ord      = preg_split( '/\s+/', trim( $samlab_navn ) );
$samlab_initial  = mb_strtoupper( mb_substr( $samlab_ord[0], 0, 1 ) );
$samlab_initial .= count( $samlab_ord ) > 1 ? mb_strtoupper( mb_substr( end( $samlab_ord ), 0, 1 ) ) : '';
?>
<p class="samlab-tilbake"><a href="<?php echo esc_url( samlab_portal_url( 'bedrifter' ) ); ?>">&larr; <?php echo esc_html( samlab_portal_views()['bedrifter']['label'] ); ?></a></p>

<header class="samlab-profil-hode">
	<span class="samlab-kort-logo">
		<?php if ( has_post_thumbnail( $samlab_bedrift ) ) : ?>
			<?php echo get_the_post_thumbnail( $samlab_bedrift, 'medium', array( 'alt' => $samlab_navn ) ); ?>
		<?php else : ?>
			<span class="samlab-avatar er-aksent samlab-avatar-stor" aria-hidden="true"><?php echo esc_html( $samlab_initial ); ?></span>
		<?php endif; ?>
	</span>
	<div>
		<h1><?php echo esc_html( $samlab_navn ); ?></h1>
		<?php if ( array() !== $samlab_katnavn ) : ?>
			<p class="samlab-kort-kategori"><?php echo esc_html( implode( ' · ', $samlab_katnavn ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $samlab_kort ) : ?>
			<p class="samlab-kort-tekst"><?php echo esc_html( $samlab_kort ); ?></p>
		<?php endif; ?>
		<p class="samlab-kort-meta">
			<?php if ( '' !== $samlab_plass ) : ?>
				<span><?php echo esc_html( $samlab_plass ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $samlab_nett ) : ?>
				<a href="<?php echo esc_url( $samlab_nett ); ?>" rel="noopener"><?php echo esc_html( wp_parse_url( $samlab_nett, PHP_URL_HOST ) ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</header>

<?php if ( '' !== trim( (string) $samlab_bedrift->post_content ) ) : ?>
	<section class="samlab-profil-del">
		<h2><?php esc_html_e( 'Om oss', 'samlab' ); ?></h2>
		<?php echo wp_kses_post( apply_filters( 'the_content', $samlab_bedrift->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Kjernens the_content-filter anvendes, ikke defineres. ?>
	</section>
<?php endif; ?>

<?php if ( array() !== $samlab_intensjoner || ( is_array( $samlab_apen ) && array() !== $samlab_apen ) ) : ?>
	<section class="samlab-profil-del samlab-kort">
		<h2><?php esc_html_e( 'Dette ser vi etter', 'samlab' ); ?></h2>
		<dl class="samlab-intensjoner">
			<?php foreach ( $samlab_intensjoner as $samlab_etikett => $samlab_verdi ) : ?>
				<div>
					<dt><?php echo esc_html( $samlab_etikett ); ?></dt>
					<dd><?php echo esc_html( $samlab_verdi ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
		<?php if ( is_array( $samlab_apen ) && array() !== $samlab_apen ) : ?>
			<h3><?php esc_html_e( 'Åpne for', 'samlab' ); ?></h3>
			<ul class="samlab-chips">
				<?php foreach ( $samlab_apen as $samlab_punkt ) : ?>
					<li><span class="samlab-chip"><?php echo esc_html( $samlab_punkt ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( is_array( $samlab_tjen ) && array() !== $samlab_tjen ) : ?>
	<section class="samlab-profil-del">
		<h2><?php esc_html_e( 'Tjenester', 'samlab' ); ?></h2>
		<ul class="samlab-kort-grid">
			<?php foreach ( $samlab_tjen as $samlab_tjeneste ) : ?>
				<li class="samlab-kort">
					<h3><?php echo esc_html( $samlab_tjeneste['tittel'] ); ?></h3>
					<?php if ( ! empty( $samlab_tjeneste['punkter'] ) ) : ?>
						<ul>
							<?php foreach ( $samlab_tjeneste['punkter'] as $samlab_punkt ) : ?>
								<li><?php echo esc_html( $samlab_punkt ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php if ( $samlab_kontakt ) : ?>
	<section class="samlab-profil-del">
		<h2><?php esc_html_e( 'Folkene', 'samlab' ); ?></h2>
		<div class="samlab-kort samlab-person">
			<span class="samlab-avatar" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $samlab_kontakt->display_name, 0, 1 ) ) ); ?></span>
			<div>
				<p class="samlab-person-navn"><?php echo esc_html( $samlab_kontakt->display_name ); ?></p>
				<p class="samlab-kort-meta"><?php esc_html_e( 'Kontaktperson', 'samlab' ); ?></p>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( array() !== $samlab_galleri ) : ?>
	<section class="samlab-profil-del">
		<h2><?php esc_html_e( 'Galleri', 'samlab' ); ?></h2>
		<ul class="samlab-galleri">
			<?php foreach ( $samlab_galleri as $samlab_bilde ) : ?>
				<li><?php echo wp_get_attachment_image( $samlab_bilde->ID, 'medium' ); ?></li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php if ( array() !== $samlab_behov_liste ) : ?>
	<section class="samlab-profil-del">
		<h2><?php esc_html_e( 'Aktive behov', 'samlab' ); ?></h2>
		<ul class="samlab-kort-grid">
			<?php foreach ( $samlab_behov_liste as $samlab_behov ) : ?>
				<?php
				$samlab_retning = get_the_terms( $samlab_behov->ID, 'samlab_retning' );
				$samlab_retning = $samlab_retning && ! is_wp_error( $samlab_retning ) ? $samlab_retning[0]->name : '';
				$samlab_frist   = get_post_meta( $samlab_behov->ID, '_samlab_frist', true );
				?>
				<li class="samlab-kort">
					<?php if ( '' !== $samlab_retning ) : ?>
						<span class="samlab-chip er-aktiv"><?php echo esc_html( $samlab_retning ); ?></span>
					<?php endif; ?>
					<h3><?php echo esc_html( get_the_title( $samlab_behov ) ); ?></h3>
					<?php if ( '' !== $samlab_frist ) : ?>
						<p class="samlab-kort-meta"><?php echo esc_html( __( 'Frist:', 'samlab' ) . ' ' . $samlab_frist ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>
