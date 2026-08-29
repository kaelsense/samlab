<?php
/**
 * Håndboken: sidegruppe av merkede WordPress-sider i portal-skallet,
 * med sidenavigasjon og ankernavigasjon per side. FAQ dekkes av
 * Gutenbergs details-blokk (details/summary-mønsteret fra
 * prototypen), stylet i portal.css.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$samlab_flate = samlab_portal_views()['handbok'];
$samlab_sider = samlab_get_handbok_pages();

$samlab_aktiv = null;
if ( isset( $samlab_item ) && '' !== $samlab_item ) {
	$samlab_aktiv = samlab_get_handbok_page_by_slug( $samlab_item );
} elseif ( array() !== $samlab_sider ) {
	$samlab_aktiv = $samlab_sider[0];
}
?>
<header class="samlab-flate-hode">
	<h1><?php echo esc_html( $samlab_flate['label'] ); ?></h1>
</header>

<?php if ( array() === $samlab_sider ) : ?>
	<p class="samlab-tom"><?php esc_html_e( 'Ingen håndbok-sider ennå. Merk en side med «Vis i portalens håndbok» i sideredigeringen.', 'samlab' ); ?></p>
<?php else : ?>
	<div class="samlab-handbok">
		<nav class="samlab-handbok-nav" aria-label="<?php esc_attr_e( 'Håndbok-sider', 'samlab' ); ?>">
			<ul>
				<?php foreach ( $samlab_sider as $samlab_side ) : ?>
					<li>
						<a href="<?php echo esc_url( samlab_portal_url( 'handbok', $samlab_side->post_name ) ); ?>"
							class="<?php echo $samlab_aktiv && $samlab_side->ID === $samlab_aktiv->ID ? 'er-aktiv' : ''; ?>"
							<?php echo $samlab_aktiv && $samlab_side->ID === $samlab_aktiv->ID ? 'aria-current="page"' : ''; ?>>
							<?php echo esc_html( get_the_title( $samlab_side ) ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<?php if ( $samlab_aktiv ) : ?>
			<article class="samlab-handbok-innhold">
				<h2><?php echo esc_html( get_the_title( $samlab_aktiv ) ); ?></h2>
				<?php
				$samlab_innhold = apply_filters( 'the_content', $samlab_aktiv->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Kjernens the_content-filter anvendes, ikke defineres.

				// Ankernavigasjon: gi h2-overskrifter id-er og lag lenkeliste.
				$samlab_ankere  = array();
				$samlab_innhold = preg_replace_callback(
					'/<h2([^>]*)>(.*?)<\/h2>/is',
					function ( $treff ) use ( &$samlab_ankere ) {
						$tekst = wp_strip_all_tags( $treff[2] );
						$id    = sanitize_title( $tekst );
						if ( '' === $id ) {
							return $treff[0];
						}
						$samlab_ankere[ $id ] = $tekst;
						if ( false !== stripos( $treff[1], 'id=' ) ) {
							return $treff[0];
						}
						return '<h2 id="' . esc_attr( $id ) . '"' . $treff[1] . '>' . $treff[2] . '</h2>';
					},
					$samlab_innhold
				);
				?>
				<?php if ( count( $samlab_ankere ) > 1 ) : ?>
					<nav class="samlab-ankere" aria-label="<?php esc_attr_e( 'På denne siden', 'samlab' ); ?>">
						<strong><?php esc_html_e( 'På denne siden:', 'samlab' ); ?></strong>
						<ul>
							<?php foreach ( $samlab_ankere as $samlab_anker_id => $samlab_anker_tekst ) : ?>
								<li><a href="#<?php echo esc_attr( $samlab_anker_id ); ?>"><?php echo esc_html( $samlab_anker_tekst ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</nav>
				<?php endif; ?>
				<div class="samlab-prosa">
					<?php echo wp_kses_post( $samlab_innhold ); ?>
				</div>
			</article>
		<?php endif; ?>
	</div>
<?php endif; ?>
