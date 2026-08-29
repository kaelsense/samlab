<?php
/**
 * Frontend-skjemaer i portalen. Kjører på template_redirect etter
 * innloggingsporten og før rendering, slik at vellykkede
 * innsendinger kan redirecte (post/redirect/get).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bedrifter innsenderen kan knytte et behov til: bedrifter der
 * brukeren er kontaktperson, eller alle for admin/redaktør.
 *
 * @param int $user_id Brukeren.
 * @return WP_Post[]
 */
function samlab_behov_bedrifter_for( $user_id ) {
	$args = array(
		'post_type'      => 'samlab_bedrift',
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'posts_per_page' => 100,
	);
	if ( ! user_can( $user_id, 'edit_others_posts' ) ) {
		$args['meta_key']   = '_samlab_kontaktperson'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lavvolum oppslag.
		$args['meta_value'] = $user_id; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	}
	return get_posts( $args );
}

/**
 * Tar imot «nytt behov»-skjemaet fra portalen.
 *
 * @return void
 */
function samlab_handle_behov_form() {
	if ( ! isset( $_POST['samlab_behov_skjema_nonce'] ) || '' === (string) get_query_var( 'samlab_portal' ) ) {
		return;
	}

	$nonce = sanitize_key( wp_unslash( $_POST['samlab_behov_skjema_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'samlab_nytt_behov' ) ) {
		wp_die( esc_html__( 'Ugyldig eller utløpt skjema - gå tilbake og prøv igjen.', 'samlab' ), '', 403 );
	}
	if ( ! current_user_can( 'samlab_create_behov' ) ) {
		wp_die( esc_html__( 'Du har ikke tilgang til å opprette behov.', 'samlab' ), '', 403 );
	}

	$tittel = isset( $_POST['samlab_tittel'] ) ? sanitize_text_field( wp_unslash( $_POST['samlab_tittel'] ) ) : '';
	if ( '' === $tittel ) {
		wp_safe_redirect( add_query_arg( 'feil', 'tittel', samlab_portal_url( 'behov' ) ) );
		exit;
	}

	$behov_id = wp_insert_post(
		array(
			'post_type'    => 'samlab_behov',
			'post_status'  => 'publish',
			'post_title'   => $tittel,
			'post_content' => isset( $_POST['samlab_beskrivelse'] ) ? sanitize_textarea_field( wp_unslash( $_POST['samlab_beskrivelse'] ) ) : '',
			'post_author'  => get_current_user_id(),
		)
	);
	if ( is_wp_error( $behov_id ) || ! $behov_id ) {
		wp_die( esc_html__( 'Kunne ikke lagre behovet.', 'samlab' ), '', 500 );
	}

	$retning = isset( $_POST['samlab_retning'] ) && 'tilbyr' === $_POST['samlab_retning'] ? 'tilbyr' : 'trenger';
	$term    = get_term_by( 'slug', $retning, 'samlab_retning' );
	if ( $term ) {
		wp_set_object_terms( $behov_id, array( $term->term_id ), 'samlab_retning' );
	}

	$type_id = isset( $_POST['samlab_behovstype'] ) ? absint( $_POST['samlab_behovstype'] ) : 0;
	if ( $type_id && get_term( $type_id, 'samlab_behovstype' ) instanceof WP_Term ) {
		wp_set_object_terms( $behov_id, array( $type_id ), 'samlab_behovstype' );
	}

	foreach ( array( 'samlab_frist', 'samlab_budsjett', 'samlab_kontaktform' ) as $name ) {
		if ( isset( $_POST[ $name ] ) && '' !== $_POST[ $name ] ) {
			update_post_meta( $behov_id, '_' . $name, sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) );
		}
	}

	if ( isset( $_POST['samlab_kompetanse'] ) ) {
		$linjer = explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['samlab_kompetanse'] ) ) );
		$linjer = array_values( array_filter( array_map( 'trim', $linjer ) ) );
		if ( array() !== $linjer ) {
			update_post_meta( $behov_id, '_samlab_kompetanse', $linjer );
		}
	}

	$bedrift_id = isset( $_POST['samlab_bedrift'] ) ? absint( $_POST['samlab_bedrift'] ) : 0;
	if ( $bedrift_id ) {
		$tillatte = wp_list_pluck( samlab_behov_bedrifter_for( get_current_user_id() ), 'ID' );
		if ( in_array( $bedrift_id, array_map( 'intval', $tillatte ), true ) ) {
			update_post_meta( $behov_id, '_samlab_bedrift', $bedrift_id );
		}
	}

	/**
	 * Kjøres når et behov er opprettet fra portalskjemaet.
	 *
	 * @since 0.1.0
	 *
	 * @param int $behov_id Behovets post-ID.
	 * @param int $user_id  Innsenderen.
	 */
	do_action( 'samlab_behov_opprettet', $behov_id, get_current_user_id() );

	wp_safe_redirect( add_query_arg( 'opprettet', (string) $behov_id, samlab_portal_url( 'behov' ) ) );
	exit;
}
add_action( 'template_redirect', 'samlab_handle_behov_form', 9 );
