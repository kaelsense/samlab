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
	<?php
	// Temaets designtokens (--wp--preset--*) - skallet er utenfor
	// temaets template, så variablene må skrives ut her.
	echo '<style id="samlab-wp-tokens">' . wp_strip_all_tags( wp_get_global_stylesheet( array( 'variables' ) ) ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS fra wp_get_global_stylesheet, uten markup etter wp_strip_all_tags.

	// Bro fra temaets globale stiler (theme.json) til samlab-tokens:
	// font- og fargevalg følger temaet uansett hvilke preset-slugs
	// det bruker; fallbackene i portal.css tar over ellers.
	$samlab_styles = wp_get_global_styles();
	$samlab_bro    = array(
		'--samlab-font'         => $samlab_styles['typography']['fontFamily'] ?? '',
		'--samlab-font-heading' => $samlab_styles['elements']['heading']['typography']['fontFamily'] ?? '',
		'--samlab-bg'           => $samlab_styles['color']['background'] ?? '',
		'--samlab-fg'           => $samlab_styles['color']['text'] ?? '',
	);
	$samlab_regler = '';
	foreach ( $samlab_bro as $samlab_token => $samlab_verdi ) {
		if ( ! is_string( $samlab_verdi ) || '' === $samlab_verdi ) {
			continue;
		}
		$samlab_verdi   = preg_replace( '/[^a-zA-Z0-9,\'" \-\(\)#%.]/', '', $samlab_verdi );
		$samlab_regler .= $samlab_token . ':' . $samlab_verdi . ';';
	}
	if ( '' !== $samlab_regler ) {
		echo '<style id="samlab-tema-bro">.samlab-portal{' . $samlab_regler . '}</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Verdiene er vasket til trygge CSS-tegn rett over (ingen <>&;{}); esc_html ville odelagt siterte fontnavn.
	}

	$samlab_aksent = sanitize_hex_color( samlab_get_setting( 'aksentfarge' ) );
	if ( $samlab_aksent ) {
		echo '<style id="samlab-aksent">.samlab-portal{--samlab-aksent:' . esc_html( $samlab_aksent ) . ';}</style>';
	}
	?>
	<link rel="stylesheet" href="<?php echo esc_url( SAMLAB_PLUGIN_URL . 'assets/css/portal.css?ver=' . SAMLAB_VERSION ); ?>" /><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Skallet er et eget dokument uten wp_head; lenken skrives direkte. ?>
</head>
<body class="samlab-portal">
	<a href="#samlab-hoved" class="samlab-hopp"><?php esc_html_e( 'Hopp til hovedinnhold', 'samlab' ); ?></a>

	<header class="samlab-topp">
		<div class="samlab-topp-indre">
			<a href="<?php echo esc_url( samlab_portal_url() ); ?>" class="samlab-merke">
				<?php $samlab_logo = samlab_get_setting( 'logo' ); ?>
				<?php if ( '' !== $samlab_logo ) : ?>
					<img src="<?php echo esc_url( $samlab_logo ); ?>" alt="<?php echo esc_attr( $samlab_navn ); ?>" class="samlab-logo" />
				<?php else : ?>
					<?php echo esc_html( $samlab_navn ); ?>
				<?php endif; ?>
			</a>
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
