<?php
/**
 * Portalens app-skall: eget komplett HTML-dokument, ikke temaets
 * template. Struktur fra prototypens InternLayout; farger og fonter
 * kommer fra temaets designtokens (B9).
 *
 * Forventer $samlab_view (flate eller «404») og $samlab_item.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$samlab_views  = samlab_portal_views();
$samlab_navn   = samlab_portal_name();
$samlab_tittel = '404' === $samlab_view ? __( 'Fant ikke siden', 'samlab' ) : ( 'hjem' === $samlab_view ? $samlab_navn : $samlab_views[ $samlab_view ]['label'] );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $samlab_tittel . ' - ' . $samlab_navn ); ?></title>
</head>
<body class="samlab-portal">
	<a href="#samlab-hoved" class="samlab-hopp"><?php esc_html_e( 'Hopp til hovedinnhold', 'samlab' ); ?></a>

	<header class="samlab-topp">
		<div class="samlab-topp-indre">
			<a href="<?php echo esc_url( samlab_portal_url() ); ?>" class="samlab-merke"><?php echo esc_html( $samlab_navn ); ?></a>
			<nav class="samlab-nav" aria-label="<?php esc_attr_e( 'Portalmeny', 'samlab' ); ?>">
				<ul>
					<?php foreach ( $samlab_views as $samlab_key => $samlab_flate ) : ?>
						<li>
							<a href="<?php echo esc_url( samlab_portal_url( $samlab_key ) ); ?>"
								class="samlab-nav-lenke<?php echo $samlab_key === $samlab_view ? ' er-aktiv' : ''; ?>"
								<?php echo $samlab_key === $samlab_view ? 'aria-current="page"' : ''; ?>>
								<?php echo esc_html( $samlab_flate['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		</div>
	</header>

	<main id="samlab-hoved" class="samlab-hoved">
		<?php
		$samlab_flatefil = SAMLAB_PLUGIN_DIR . 'templates/flater/' . $samlab_view . '.php';
		if ( in_array( $samlab_view, array( 'hjem', 'vegg', 'behov', 'bedrifter', 'handbok', '404' ), true ) && file_exists( $samlab_flatefil ) ) {
			require $samlab_flatefil;
		}
		?>
	</main>

	<footer class="samlab-bunn">
		<p><?php echo esc_html( $samlab_navn ); ?> - <?php esc_html_e( 'internt innhold, ikke offentlig', 'samlab' ); ?></p>
	</footer>
</body>
</html>
