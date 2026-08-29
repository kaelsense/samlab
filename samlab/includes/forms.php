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

/**
 * Tar imot «nytt arrangement»-skjemaet fra portalen (E6).
 *
 * @return void
 */
function samlab_handle_arrangement_form() {
	if ( ! isset( $_POST['samlab_arrangement_skjema_nonce'] ) || '' === (string) get_query_var( 'samlab_portal' ) ) {
		return;
	}

	$nonce = sanitize_key( wp_unslash( $_POST['samlab_arrangement_skjema_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'samlab_nytt_arrangement' ) ) {
		wp_die( esc_html__( 'Ugyldig eller utløpt skjema - gå tilbake og prøv igjen.', 'samlab' ), '', 403 );
	}
	if ( ! current_user_can( 'samlab_create_arrangement' ) ) {
		wp_die( esc_html__( 'Du har ikke tilgang til å opprette arrangementer.', 'samlab' ), '', 403 );
	}

	$tittel = isset( $_POST['samlab_tittel'] ) ? sanitize_text_field( wp_unslash( $_POST['samlab_tittel'] ) ) : '';
	if ( '' === $tittel ) {
		wp_safe_redirect( add_query_arg( 'feil', 'tittel', samlab_portal_url( 'arrangementer' ) ) );
		exit;
	}
	$start = isset( $_POST['samlab_start'] ) ? samlab_arrangement_sanitize_tid( sanitize_text_field( wp_unslash( $_POST['samlab_start'] ) ) ) : '';
	if ( '' === $start ) {
		wp_safe_redirect( add_query_arg( 'feil', 'tid', samlab_portal_url( 'arrangementer' ) ) );
		exit;
	}

	$arrangement_id = wp_insert_post(
		array(
			'post_type'    => 'samlab_arrangement',
			'post_status'  => 'publish',
			'post_title'   => $tittel,
			'post_content' => isset( $_POST['samlab_beskrivelse'] ) ? sanitize_textarea_field( wp_unslash( $_POST['samlab_beskrivelse'] ) ) : '',
			'post_author'  => get_current_user_id(),
		)
	);
	if ( is_wp_error( $arrangement_id ) || ! $arrangement_id ) {
		wp_die( esc_html__( 'Kunne ikke lagre arrangementet.', 'samlab' ), '', 500 );
	}

	update_post_meta( $arrangement_id, '_samlab_start', $start );
	if ( isset( $_POST['samlab_slutt'] ) ) {
		update_post_meta( $arrangement_id, '_samlab_slutt', samlab_arrangement_sanitize_tid( sanitize_text_field( wp_unslash( $_POST['samlab_slutt'] ) ) ) );
	}
	if ( isset( $_POST['samlab_sted'] ) && '' !== $_POST['samlab_sted'] ) {
		update_post_meta( $arrangement_id, '_samlab_sted', sanitize_text_field( wp_unslash( $_POST['samlab_sted'] ) ) );
	}

	$bedrift_id = isset( $_POST['samlab_bedrift'] ) ? absint( $_POST['samlab_bedrift'] ) : 0;
	if ( $bedrift_id ) {
		$tillatte = wp_list_pluck( samlab_behov_bedrifter_for( get_current_user_id() ), 'ID' );
		if ( in_array( $bedrift_id, array_map( 'intval', $tillatte ), true ) ) {
			update_post_meta( $arrangement_id, '_samlab_bedrift', $bedrift_id );
		}
	}

	/**
	 * Kjøres når et arrangement er opprettet fra portalskjemaet.
	 *
	 * @since 0.2.0
	 *
	 * @param int $arrangement_id Arrangementets post-ID.
	 * @param int $user_id        Innsenderen.
	 */
	do_action( 'samlab_arrangement_opprettet', $arrangement_id, get_current_user_id() );

	wp_safe_redirect( add_query_arg( 'opprettet', (string) $arrangement_id, samlab_portal_url( 'arrangementer' ) ) );
	exit;
}
add_action( 'template_redirect', 'samlab_handle_arrangement_form', 9 );

/**
 * Tar imot «nytt innlegg»-skjemaet på veggen (tekst + valgfritt bilde).
 *
 * @return void
 */
function samlab_handle_vegg_form() {
	if ( ! isset( $_POST['samlab_vegg_nonce'] ) || '' === (string) get_query_var( 'samlab_portal' ) ) {
		return;
	}

	$nonce = sanitize_key( wp_unslash( $_POST['samlab_vegg_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'samlab_nytt_innlegg' ) ) {
		wp_die( esc_html__( 'Ugyldig eller utløpt skjema - gå tilbake og prøv igjen.', 'samlab' ), '', 403 );
	}
	if ( ! current_user_can( 'samlab_post_wall' ) ) {
		wp_die( esc_html__( 'Du har ikke tilgang til å poste på veggen.', 'samlab' ), '', 403 );
	}

	$innhold = isset( $_POST['samlab_innhold'] ) ? trim( wp_kses_post( wp_unslash( $_POST['samlab_innhold'] ) ) ) : '';
	if ( '' === $innhold ) {
		wp_safe_redirect( add_query_arg( 'feil', 'innlegg', samlab_portal_url( 'vegg' ) ) );
		exit;
	}

	// Valgfri avstemning: spørsmål + 2-5 alternativer (ett per linje).
	$poll_sporsmal = isset( $_POST['samlab_poll_sporsmal'] ) ? sanitize_text_field( wp_unslash( $_POST['samlab_poll_sporsmal'] ) ) : '';
	$poll_valg     = array();
	if ( '' !== $poll_sporsmal ) {
		$linjer    = isset( $_POST['samlab_poll_valg'] ) ? explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['samlab_poll_valg'] ) ) ) : array();
		$poll_valg = array_values( array_filter( array_map( 'trim', $linjer ) ) );
		if ( count( $poll_valg ) < 2 || count( $poll_valg ) > 5 ) {
			wp_safe_redirect( add_query_arg( 'feil', 'avstemning', samlab_portal_url( 'vegg' ) ) );
			exit;
		}
	}

	$image_id = 0;
	if ( ! empty( $_FILES['samlab_bilde']['name'] ) && current_user_can( 'upload_files' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$opplastet = media_handle_upload( 'samlab_bilde', 0 );
		if ( ! is_wp_error( $opplastet ) ) {
			$image_id = (int) $opplastet;
		}
	}

	$innlegg_id = Samlab_Innlegg::create(
		array(
			'user_id'       => get_current_user_id(),
			'content'       => $innhold,
			'image_id'      => $image_id,
			'poll_sporsmal' => $poll_sporsmal,
			'poll_valg'     => $poll_valg,
		)
	);

	/**
	 * Kjøres når et vegginnlegg er opprettet fra portalen.
	 *
	 * @since 0.1.0
	 *
	 * @param int $innlegg_id Innleggets ID i samlab_innlegg-tabellen.
	 * @param int $user_id    Forfatteren.
	 */
	do_action( 'samlab_innlegg_opprettet', (int) $innlegg_id, get_current_user_id() );

	wp_safe_redirect( samlab_portal_url( 'vegg' ) . '#innlegg-' . (int) $innlegg_id );
	exit;
}
add_action( 'template_redirect', 'samlab_handle_vegg_form', 9 );

/**
 * Tar imot kommentar-skjemaet på et vegginnlegg. Kommentarene bor i
 * WordPress' kommentartabell med type samlab_innlegg og kobles til
 * innlegget med comment-meta.
 *
 * @return void
 */
function samlab_handle_kommentar_form() {
	if ( ! isset( $_POST['samlab_kommentar_nonce'] ) || '' === (string) get_query_var( 'samlab_portal' ) ) {
		return;
	}

	$nonce = sanitize_key( wp_unslash( $_POST['samlab_kommentar_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'samlab_kommentar' ) ) {
		wp_die( esc_html__( 'Ugyldig eller utløpt skjema - gå tilbake og prøv igjen.', 'samlab' ), '', 403 );
	}
	if ( ! current_user_can( 'samlab_read_portal' ) ) {
		wp_die( esc_html__( 'Du har ikke tilgang til portalen.', 'samlab' ), '', 403 );
	}

	$innlegg_id = isset( $_POST['samlab_innlegg_id'] ) ? absint( $_POST['samlab_innlegg_id'] ) : 0;
	$innlegg    = $innlegg_id ? Samlab_Innlegg::get( $innlegg_id ) : null;
	$tekst      = isset( $_POST['samlab_kommentar'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['samlab_kommentar'] ) ) ) : '';
	if ( ! $innlegg || 'publish' !== $innlegg->status || '' === $tekst ) {
		wp_safe_redirect( samlab_portal_url( 'vegg' ) );
		exit;
	}

	$bruker = wp_get_current_user();
	wp_insert_comment(
		array(
			'comment_post_ID'      => 0,
			'comment_type'         => 'samlab_innlegg',
			'comment_content'      => $tekst,
			'user_id'              => $bruker->ID,
			'comment_author'       => $bruker->display_name,
			'comment_author_email' => $bruker->user_email,
			'comment_approved'     => 1,
			'comment_meta'         => array( '_samlab_innlegg' => $innlegg_id ),
		)
	);

	wp_safe_redirect( samlab_portal_url( 'vegg' ) . '#innlegg-' . $innlegg_id );
	exit;
}
add_action( 'template_redirect', 'samlab_handle_kommentar_form', 9 );

/**
 * Moderering av vegginnlegg: feste/løsne (samlab_pin_posts) og
 * skjule (samlab_hide_content).
 *
 * @return void
 */
function samlab_handle_innlegg_moderering() {
	if ( ! isset( $_POST['samlab_moderer_nonce'] ) || '' === (string) get_query_var( 'samlab_portal' ) ) {
		return;
	}

	$nonce = sanitize_key( wp_unslash( $_POST['samlab_moderer_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'samlab_moderer_innlegg' ) ) {
		wp_die( esc_html__( 'Ugyldig eller utløpt skjema - gå tilbake og prøv igjen.', 'samlab' ), '', 403 );
	}

	$handling   = isset( $_POST['samlab_handling'] ) ? sanitize_key( wp_unslash( $_POST['samlab_handling'] ) ) : '';
	$innlegg_id = isset( $_POST['samlab_innlegg_id'] ) ? absint( $_POST['samlab_innlegg_id'] ) : 0;
	if ( ! $innlegg_id || ! Samlab_Innlegg::get( $innlegg_id ) ) {
		wp_safe_redirect( samlab_portal_url( 'vegg' ) );
		exit;
	}

	if ( 'fest' === $handling || 'losne' === $handling ) {
		if ( ! current_user_can( 'samlab_pin_posts' ) ) {
			wp_die( esc_html__( 'Kun moderatorer kan feste oppslag.', 'samlab' ), '', 403 );
		}
		Samlab_Innlegg::update( $innlegg_id, array( 'pinned' => 'fest' === $handling ? 1 : 0 ) );
	} elseif ( 'skjul' === $handling ) {
		if ( ! current_user_can( 'samlab_hide_content' ) ) {
			wp_die( esc_html__( 'Kun moderatorer kan skjule innhold.', 'samlab' ), '', 403 );
		}
		Samlab_Innlegg::update( $innlegg_id, array( 'status' => 'hidden' ) );
	}

	wp_safe_redirect( samlab_portal_url( 'vegg' ) . '#innlegg-' . $innlegg_id );
	exit;
}
add_action( 'template_redirect', 'samlab_handle_innlegg_moderering', 9 );
