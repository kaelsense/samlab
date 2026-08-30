<?php
/**
 * Custom post type: arrangement, med dato/tid, sted og valgfri
 * arrangør-bedrift. Egen portalflate (kommende først) som
 * standardflate i nav og globalt søk; medlemmer med
 * samlab_create_arrangement kan opprette fra portalen (E6).
 * Kommende arrangementer legges også inn i ukesbrevet via
 * filteret samlab_ukesbrev_seksjoner (E5).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrerer post-typen samlab_arrangement.
 *
 * Visning skjer i portal-rutene - ingen offentlig rewrite/arkiv.
 *
 * @return void
 */
function samlab_register_arrangement() {
	register_post_type(
		'samlab_arrangement',
		array(
			'labels'              => array(
				'name'          => __( 'Arrangementer', 'samlab' ),
				'singular_name' => __( 'Arrangement', 'samlab' ),
				'add_new_item'  => __( 'Legg til arrangement', 'samlab' ),
				'edit_item'     => __( 'Rediger arrangement', 'samlab' ),
				'search_items'  => __( 'Søk i arrangementer', 'samlab' ),
				'not_found'     => __( 'Ingen arrangementer funnet', 'samlab' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-calendar-alt',
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'editor' ),
		)
	);
}
add_action( 'init', 'samlab_register_arrangement' );

/**
 * Normaliserer et tidspunkt fra skjema («Y-m-d H:i» eller
 * datetime-local-formatet «Y-m-d\TH:i») til «Y-m-d H:i».
 *
 * @param string $verdi Rå verdi.
 * @return string Normalisert tidspunkt, eller tom streng.
 */
function samlab_arrangement_sanitize_tid( $verdi ) {
	$verdi = str_replace( 'T', ' ', trim( (string) $verdi ) );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $verdi ) ) {
		return '';
	}
	return $verdi;
}

/**
 * Kommende arrangementer, nærmeste først. Tidspunkter lagres og
 * sammenlignes i nettstedets lokale tid («Y-m-d H:i» sorterer
 * leksikografisk riktig).
 *
 * @param int $grense Maks antall.
 * @return WP_Post[]
 */
function samlab_kommende_arrangementer( $grense = 50 ) {
	return get_posts(
		array(
			'post_type'      => 'samlab_arrangement',
			'post_status'    => 'publish',
			'posts_per_page' => min( 100, max( 1, (int) $grense ) ),
			'meta_key'       => '_samlab_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lavvolum CPT, flatevisning.
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_samlab_start',
					'value'   => current_time( 'Y-m-d H:i' ),
					'compare' => '>=',
				),
			),
		)
	);
}

/**
 * Tidligere arrangementer, nyeste først.
 *
 * @param int $grense Maks antall.
 * @return WP_Post[]
 */
function samlab_tidligere_arrangementer( $grense = 10 ) {
	return get_posts(
		array(
			'post_type'      => 'samlab_arrangement',
			'post_status'    => 'publish',
			'posts_per_page' => min( 100, max( 1, (int) $grense ) ),
			'meta_key'       => '_samlab_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lavvolum CPT, flatevisning.
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_samlab_start',
					'value'   => current_time( 'Y-m-d H:i' ),
					'compare' => '<',
				),
			),
		)
	);
}

/**
 * Leselig tidsvisning for et arrangement: start (og ev. slutt).
 *
 * @param int $arrangement_id Arrangementet.
 * @return string
 */
function samlab_arrangement_tid_visning( $arrangement_id ) {
	$start = (string) get_post_meta( $arrangement_id, '_samlab_start', true );
	if ( '' === $start ) {
		return '';
	}
	$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	$tekst  = date_i18n( $format, (int) strtotime( $start ) );

	$slutt = (string) get_post_meta( $arrangement_id, '_samlab_slutt', true );
	if ( '' !== $slutt ) {
		$samme_dag = substr( $start, 0, 10 ) === substr( $slutt, 0, 10 );
		$tekst    .= ' - ' . date_i18n( $samme_dag ? get_option( 'time_format' ) : $format, (int) strtotime( $slutt ) );
	}
	return $tekst;
}

/**
 * Registrerer metaboksen for arrangement.
 *
 * @return void
 */
function samlab_arrangement_meta_boxes() {
	add_meta_box( 'samlab_arrangement_detaljer', __( 'Arrangementsdetaljer', 'samlab' ), 'samlab_render_arrangement_box', 'samlab_arrangement', 'normal', 'high' );
}
add_action( 'add_meta_boxes_samlab_arrangement', 'samlab_arrangement_meta_boxes' );

/**
 * Metaboks: start, slutt, sted og arrangør-bedrift.
 *
 * @param WP_Post $post Arrangementet som redigeres.
 * @return void
 */
function samlab_render_arrangement_box( $post ) {
	wp_nonce_field( 'samlab_arrangement_meta', 'samlab_arrangement_nonce' );

	$tidsfelter = array(
		'samlab_start' => __( 'Start', 'samlab' ),
		'samlab_slutt' => __( 'Slutt (valgfri)', 'samlab' ),
	);

	echo '<table class="form-table" role="presentation">';
	foreach ( $tidsfelter as $id => $label ) {
		$verdi = (string) get_post_meta( $post->ID, '_' . $id, true );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="datetime-local" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( str_replace( ' ', 'T', $verdi ) ) . '" /></td></tr>';
	}

	$sted = (string) get_post_meta( $post->ID, '_samlab_sted', true );
	echo '<tr><th scope="row"><label for="samlab_sted">' . esc_html__( 'Sted', 'samlab' ) . '</label></th>';
	echo '<td><input type="text" class="regular-text" id="samlab_sted" name="samlab_sted" value="' . esc_attr( $sted ) . '" /></td></tr>';

	$valgt     = (int) get_post_meta( $post->ID, '_samlab_bedrift', true );
	$bedrifter = samlab_bedrifter_for_valg();
	echo '<tr><th scope="row"><label for="samlab_bedrift">' . esc_html__( 'Arrangør (bedrift, valgfri)', 'samlab' ) . '</label></th><td>';
	echo '<select id="samlab_bedrift" name="samlab_bedrift">';
	echo '<option value="0">' . esc_html__( '- Huset / ingen bedrift -', 'samlab' ) . '</option>';
	foreach ( $bedrifter as $bedrift ) {
		echo '<option value="' . esc_attr( (string) $bedrift->ID ) . '"' . selected( $valgt, $bedrift->ID, false ) . '>' . esc_html( get_the_title( $bedrift ) ) . '</option>';
	}
	echo '</select>';
	samlab_bedrift_tomtilstand( $bedrifter );
	echo '</td></tr>';
	echo '</table>';
}

/**
 * Lagrer arrangements-meta med nonce- og capability-sjekk.
 *
 * @param int $post_id Arrangementets post-ID.
 * @return void
 */
function samlab_save_arrangement_meta( $post_id ) {
	$nonce = isset( $_POST['samlab_arrangement_nonce'] ) ? sanitize_key( wp_unslash( $_POST['samlab_arrangement_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'samlab_arrangement_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( array( 'samlab_start', 'samlab_slutt' ) as $name ) {
		if ( isset( $_POST[ $name ] ) ) {
			update_post_meta( $post_id, '_' . $name, samlab_arrangement_sanitize_tid( sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) ) );
		}
	}

	if ( isset( $_POST['samlab_sted'] ) ) {
		update_post_meta( $post_id, '_samlab_sted', sanitize_text_field( wp_unslash( $_POST['samlab_sted'] ) ) );
	}

	if ( isset( $_POST['samlab_bedrift'] ) ) {
		$bedrift_id = absint( $_POST['samlab_bedrift'] );
		if ( $bedrift_id && 'samlab_bedrift' !== get_post_type( $bedrift_id ) ) {
			$bedrift_id = 0; // Arrangøren må være en bedrift.
		}
		update_post_meta( $post_id, '_samlab_bedrift', $bedrift_id );
	}
}
add_action( 'save_post_samlab_arrangement', 'samlab_save_arrangement_meta' );

/**
 * Legger kommende arrangementer inn i ukesbrevet (E5-filteret).
 *
 * @param array $seksjoner Ukesbrevets seksjoner.
 * @return array
 */
function samlab_ukesbrev_arrangementer( $seksjoner ) {
	$kommende = samlab_kommende_arrangementer( 10 );
	if ( array() === $kommende ) {
		return $seksjoner;
	}

	$linjer = array();
	foreach ( $kommende as $arrangement ) {
		$deler = array( samlab_arrangement_tid_visning( $arrangement->ID ) );
		$sted  = (string) get_post_meta( $arrangement->ID, '_samlab_sted', true );
		if ( '' !== $sted ) {
			$deler[] = $sted;
		}
		$linjer[] = array(
			'tekst' => $arrangement->post_title . ' (' . implode( ', ', array_filter( $deler ) ) . ')',
			'url'   => samlab_portal_url( 'arrangementer' ),
		);
	}
	$seksjoner[] = array(
		'tittel' => __( 'Kommende arrangementer', 'samlab' ),
		'linjer' => $linjer,
	);
	return $seksjoner;
}
add_filter( 'samlab_ukesbrev_seksjoner', 'samlab_ukesbrev_arrangementer' );
