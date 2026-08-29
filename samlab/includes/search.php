<?php
/**
 * Globalt søk (bedrifter, behov, håndbok) og mention-rendring.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Søker på tvers av bedrifter, behov og håndbok-sider.
 *
 * @param string $sok Søkestrengen.
 * @return array<string, WP_Post[]> Grupper: bedrifter, behov,
 *         arrangementer, handbok.
 */
function samlab_global_search( $sok ) {
	$sok = trim( $sok );
	if ( '' === $sok ) {
		return array();
	}

	$felles = array(
		'post_status'    => 'publish',
		's'              => $sok,
		'posts_per_page' => 20,
	);

	$grupper = array(
		'bedrifter'     => get_posts( array_merge( $felles, array( 'post_type' => 'samlab_bedrift' ) ) ),
		'behov'         => get_posts( array_merge( $felles, array( 'post_type' => 'samlab_behov' ) ) ),
		'arrangementer' => get_posts( array_merge( $felles, array( 'post_type' => 'samlab_arrangement' ) ) ),
		'handbok'       => get_posts(
			array_merge(
				$felles,
				array(
					'post_type'  => 'page',
					'meta_key'   => '_samlab_handbok', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Avgrenser søket til håndboken.
					'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			)
		),
	);

	return array_filter( $grupper );
}

/**
 * URL-en en mention skal lenke til: bedriftsprofilen der brukeren er
 * kontaktperson, ellers globalt søk på visningsnavnet.
 *
 * @param WP_User $bruker Brukeren som er nevnt.
 * @return string
 */
function samlab_mention_url( $bruker ) {
	$bedrifter = get_posts(
		array(
			'post_type'      => 'samlab_bedrift',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_samlab_kontaktperson', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lavvolum oppslag.
			'meta_value'     => $bruker->ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( array() !== $bedrifter ) {
		return samlab_portal_url( 'bedrifter', $bedrifter[0]->post_name );
	}
	return add_query_arg( 'sok', rawurlencode( $bruker->display_name ), samlab_portal_url() );
}

/**
 * Gjør @brukernavn i (allerede kses-vasket) innhold om til lenker.
 *
 * @param string $html Innholdet.
 * @return string
 */
function samlab_render_mentions( $html ) {
	return preg_replace_callback(
		'/(^|[\s>(])@([A-Za-z0-9._\-]+)/u',
		function ( $treff ) {
			$bruker = get_user_by( 'login', $treff[2] );
			if ( ! $bruker ) {
				return $treff[0];
			}
			return $treff[1] . '<a class="samlab-mention" href="' . esc_url( samlab_mention_url( $bruker ) ) . '">@' . esc_html( $bruker->display_name ) . '</a>';
		},
		$html
	);
}
