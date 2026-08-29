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
	<link rel="stylesheet" href="<?php echo esc_url( SAMLAB_PLUGIN_URL . 'assets/css/portal.css?ver=' . SAMLAB_VERSION ); ?>" /><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Skallet er et eget dokument uten wp_head; lenken skrives direkte. ?>
	<?php
	// Overstyringene under må komme ETTER portal.css - stilarket
	// deklarerer de samme tokenene med fallbacks, og siste
	// deklarasjon vinner ved lik spesifisitet.
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

	$samlab_aksent = samlab_portal_accent();
	if ( $samlab_aksent ) {
		echo '<style id="samlab-aksent">.samlab-portal{--samlab-aksent:' . esc_html( $samlab_aksent['aksent'] ) . ';--samlab-aksent-kontrast:' . esc_html( $samlab_aksent['kontrast'] ) . ';--samlab-aksent-tekst:' . esc_html( $samlab_aksent['tekst'] ) . ';}</style>';
	}
	?>
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
			<?php $samlab_uleste = Samlab_Varsel::unread_count( get_current_user_id() ); ?>
			<div class="samlab-varsler">
				<button type="button" class="samlab-varsel-knapp" id="samlab-varsel-knapp" aria-expanded="false" aria-controls="samlab-varsel-panel">
					<span aria-hidden="true">&#128276;</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Varsler', 'samlab' ); ?></span>
					<span class="samlab-varsel-teller" id="samlab-varsel-teller" <?php echo 0 === $samlab_uleste ? 'hidden' : ''; ?>><?php echo esc_html( (string) $samlab_uleste ); ?></span>
				</button>
				<div class="samlab-varsel-panel" id="samlab-varsel-panel" hidden>
					<p class="samlab-kort-meta"><?php esc_html_e( 'Laster …', 'samlab' ); ?></p>
				</div>
			</div>
			<form class="samlab-globalsok" method="get" action="<?php echo esc_url( samlab_portal_url() ); ?>" role="search">
				<label class="screen-reader-text" for="samlab-globalsok-felt"><?php esc_html_e( 'Søk i portalen', 'samlab' ); ?></label>
				<input type="search" id="samlab-globalsok-felt" name="sok" placeholder="<?php esc_attr_e( 'Søk …', 'samlab' ); ?>" />
			</form>
		</div>
	</header>

	<main id="samlab-hoved" class="samlab-hoved">
		<?php
		$samlab_flatefil = SAMLAB_PLUGIN_DIR . 'templates/flater/' . $samlab_view . '.php';
		if ( in_array( $samlab_view, array( 'hjem', 'vegg', 'behov', 'bedrifter', 'arrangementer', 'handbok', '404' ), true ) && file_exists( $samlab_flatefil ) ) {
			require $samlab_flatefil;
		}
		?>
	</main>

	<?php if ( is_user_logged_in() ) : ?>
		<script>
			window.samlabRest = {
				url: <?php echo wp_json_encode( esc_url_raw( rest_url() ) ); ?>,
				nonce: <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>
			};
			( function () {
				var knapp  = document.getElementById( 'samlab-varsel-knapp' );
				var panel  = document.getElementById( 'samlab-varsel-panel' );
				var teller = document.getElementById( 'samlab-varsel-teller' );
				if ( ! knapp || ! panel ) {
					return;
				}
				knapp.addEventListener( 'click', function () {
					var apen = ! panel.hidden;
					panel.hidden = apen;
					knapp.setAttribute( 'aria-expanded', String( ! apen ) );
					if ( apen ) {
						return;
					}
					fetch( window.samlabRest.url + 'samlab/v1/varsler', {
						credentials: 'same-origin',
						headers: { 'X-WP-Nonce': window.samlabRest.nonce }
					} ).then( function ( svar ) {
						return svar.json();
					} ).then( function ( data ) {
						panel.innerHTML = '';
						if ( ! data.varsler || ! data.varsler.length ) {
							var tom = document.createElement( 'p' );
							tom.className = 'samlab-kort-meta';
							tom.textContent = <?php echo wp_json_encode( __( 'Ingen varsler ennå.', 'samlab' ) ); ?>;
							panel.appendChild( tom );
							return;
						}
						var liste = document.createElement( 'ul' );
						data.varsler.forEach( function ( varsel ) {
							var li = document.createElement( 'li' );
							if ( ! varsel.lest ) {
								li.className = 'er-ulest';
							}
							var innhold = varsel.lenke ? document.createElement( 'a' ) : document.createElement( 'span' );
							if ( varsel.lenke ) {
								innhold.href = varsel.lenke;
							}
							innhold.textContent = varsel.tekst;
							var tid = document.createElement( 'small' );
							tid.textContent = varsel.tid;
							li.appendChild( innhold );
							li.appendChild( tid );
							liste.appendChild( li );
						} );
						panel.appendChild( liste );
						fetch( window.samlabRest.url + 'samlab/v1/varsler/lest', {
							method: 'POST',
							credentials: 'same-origin',
							headers: { 'X-WP-Nonce': window.samlabRest.nonce }
						} ).then( function () {
							teller.hidden = true;
						} );
					} );
				} );
			}() );
		</script>
	<?php endif; ?>

	<?php
	/**
	 * Kjøres nederst i portalskallet, før footeren - assistentens
	 * chat-widget (F4) hekter seg på her når modulen er på.
	 *
	 * @since 0.2.0
	 */
	do_action( 'samlab_portal_bunn' );
	?>

	<footer class="samlab-bunn">
		<p><?php echo esc_html( $samlab_navn ); ?> - <?php esc_html_e( 'internt innhold, ikke offentlig', 'samlab' ); ?></p>
	</footer>
</body>
</html>
