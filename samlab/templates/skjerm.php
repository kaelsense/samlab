<?php
/**
 * Infoskjermen: standalone storskjerm-layout uten innlogging og
 * uten portalens navigasjon. Auto-oppdaterer med meta-refresh
 * hvert 60. sekund; noindex både som meta og X-Robots-Tag (satt i
 * ruteren). Tokens leser temaets presets med nøytrale fallbacks.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$samlab_navn    = samlab_portal_name();
$samlab_aksent  = samlab_portal_accent();
$samlab_farge   = $samlab_aksent ? $samlab_aksent['aksent'] : '#3a5a40';
$samlab_alle    = Samlab_Innlegg::get_list( array( 'limit' => 30 ) );
$samlab_festede = array();
$samlab_siste   = array();
foreach ( $samlab_alle as $samlab_rad ) {
	if ( $samlab_rad->pinned ) {
		$samlab_festede[] = $samlab_rad;
	} elseif ( count( $samlab_siste ) < 6 ) {
		$samlab_siste[] = $samlab_rad;
	}
}
$samlab_kommende = function_exists( 'samlab_kommende_arrangementer' ) ? samlab_kommende_arrangementer( 6 ) : array();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<meta http-equiv="refresh" content="60" />
	<title><?php echo esc_html( $samlab_navn . ' - ' . __( 'Infoskjerm', 'samlab' ) ); ?></title>
	<style>
		:root {
			--samlab-skjerm-bg: var(--wp--preset--color--base, #f6f5f1);
			--samlab-skjerm-fg: var(--wp--preset--color--contrast, #1f1f1f);
			--samlab-skjerm-aksent: <?php echo esc_html( $samlab_farge ); ?>;
			--samlab-skjerm-font: var(--wp--preset--font-family--body, system-ui, sans-serif);
		}
		* { box-sizing: border-box; }
		body {
			margin: 0;
			padding: 2.5rem 3rem;
			background: var(--samlab-skjerm-bg);
			color: var(--samlab-skjerm-fg);
			font-family: var(--samlab-skjerm-font);
			font-size: 1.35rem;
			line-height: 1.5;
		}
		.samlab-skjerm-hode { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 4px solid var(--samlab-skjerm-aksent); padding-bottom: 1rem; margin-bottom: 2rem; }
		.samlab-skjerm-hode h1 { margin: 0; font-size: 2.6rem; }
		.samlab-skjerm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; }
		.samlab-skjerm-del h2 { font-size: 1.6rem; margin: 0 0 1rem; color: var(--samlab-skjerm-aksent); }
		.samlab-skjerm-del ul { list-style: none; margin: 0; padding: 0; }
		.samlab-skjerm-del li { padding: 0.9rem 1.1rem; margin-bottom: 0.9rem; background: rgba(0, 0, 0, 0.04); border-left: 5px solid var(--samlab-skjerm-aksent); border-radius: 6px; }
		.samlab-skjerm-meta { display: block; font-size: 1.05rem; opacity: 0.75; margin-top: 0.25rem; }
		.samlab-skjerm-festet { grid-column: 1 / -1; }
		.samlab-skjerm-festet li { font-size: 1.6rem; }
		.samlab-skjerm-tom { opacity: 0.6; }
	</style>
</head>
<body>
	<header class="samlab-skjerm-hode">
		<h1><?php echo esc_html( $samlab_navn ); ?></h1>
		<p><?php echo esc_html( date_i18n( get_option( 'date_format' ) ) ); ?></p>
	</header>
	<div class="samlab-skjerm-grid">
		<?php if ( array() !== $samlab_festede ) : ?>
			<section class="samlab-skjerm-del samlab-skjerm-festet">
				<h2><?php esc_html_e( 'Oppslag', 'samlab' ); ?></h2>
				<ul>
					<?php foreach ( $samlab_festede as $samlab_innlegg ) : ?>
						<li><?php echo esc_html( wp_strip_all_tags( $samlab_innlegg->content ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
		<section class="samlab-skjerm-del">
			<h2><?php esc_html_e( 'Siste fra veggen', 'samlab' ); ?></h2>
			<?php if ( array() === $samlab_siste ) : ?>
				<p class="samlab-skjerm-tom"><?php esc_html_e( 'Ingen innlegg ennå.', 'samlab' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $samlab_siste as $samlab_innlegg ) : ?>
						<li>
							<?php echo esc_html( wp_html_excerpt( wp_strip_all_tags( $samlab_innlegg->content ), 120, '…' ) ); ?>
							<span class="samlab-skjerm-meta">
								<?php
								$samlab_forfatter = get_userdata( (int) $samlab_innlegg->user_id );
								echo esc_html( $samlab_forfatter ? $samlab_forfatter->display_name : __( 'Ukjent', 'samlab' ) );
								/* translators: %s: tid siden innlegget, f.eks. «2 timer». */
								echo esc_html( ' · ' . sprintf( __( '%s siden', 'samlab' ), human_time_diff( strtotime( $samlab_innlegg->created_at ) ) ) );
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<section class="samlab-skjerm-del">
			<h2><?php esc_html_e( 'Kommende arrangementer', 'samlab' ); ?></h2>
			<?php if ( array() === $samlab_kommende ) : ?>
				<p class="samlab-skjerm-tom"><?php esc_html_e( 'Ingenting planlagt akkurat nå.', 'samlab' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $samlab_kommende as $samlab_arrangement ) : ?>
						<li>
							<?php echo esc_html( get_the_title( $samlab_arrangement ) ); ?>
							<span class="samlab-skjerm-meta">
								<?php
								echo esc_html( samlab_arrangement_tid_visning( $samlab_arrangement->ID ) );
								$samlab_sted = (string) get_post_meta( $samlab_arrangement->ID, '_samlab_sted', true );
								if ( '' !== $samlab_sted ) {
									echo esc_html( ' · ' . $samlab_sted );
								}
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>
</body>
</html>
