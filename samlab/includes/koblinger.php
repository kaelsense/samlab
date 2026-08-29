<?php
/**
 * Koblinger/introduksjoner: CPT samlab_kobling (ikke offentlig).
 *
 * En kobling har to parter (bedrift eller bruker), begrunnelse
 * (brødteksten), kilde (manuell/matching) og en statuskjede:
 * foreslått → godkjent → introdusert → fulgt opp, med avvist som
 * terminal sidegren for kontrollpanelets avvis-knapp (E3).
 *
 * Tilgang: moderator+ administrerer via egne capability-primitiver
 * (edit_samlab_koblinger m.fl. - aldri vanlige post-caps), partene
 * kan kun lese sine egne koblinger, alle andre avvises.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gyldige statuser i kjeden, i rekkefølge, pluss terminalen avvist.
 *
 * @return array<string, string> Status-slug => etikett.
 */
function samlab_kobling_statuser() {
	return array(
		'foreslatt'   => __( 'Foreslått', 'samlab' ),
		'godkjent'    => __( 'Godkjent', 'samlab' ),
		'introdusert' => __( 'Introdusert', 'samlab' ),
		'fulgt_opp'   => __( 'Fulgt opp', 'samlab' ),
		'avvist'      => __( 'Avvist', 'samlab' ),
	);
}

/**
 * Registrerer post-typen samlab_kobling.
 *
 * @return void
 */
function samlab_register_kobling() {
	register_post_type(
		'samlab_kobling',
		array(
			'labels'              => array(
				'name'          => __( 'Koblinger', 'samlab' ),
				'singular_name' => __( 'Kobling', 'samlab' ),
				'add_new_item'  => __( 'Legg til kobling', 'samlab' ),
				'edit_item'     => __( 'Rediger kobling', 'samlab' ),
				'search_items'  => __( 'Søk i koblinger', 'samlab' ),
				'not_found'     => __( 'Ingen koblinger funnet', 'samlab' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-networking',
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'editor' ),
			'map_meta_cap'        => true,
			'capability_type'     => array( 'samlab_kobling', 'samlab_koblinger' ),
		)
	);
}
add_action( 'init', 'samlab_register_kobling' );

/**
 * Om brukeren er part i koblingen - direkte, eller som kontaktperson
 * for en bedrift som er part.
 *
 * @param int $kobling_id Koblingens post-ID.
 * @param int $user_id    Brukeren.
 * @return bool
 */
function samlab_er_kobling_part( $kobling_id, $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return false;
	}

	foreach ( array( 'a', 'b' ) as $part ) {
		$type = get_post_meta( $kobling_id, '_samlab_part_' . $part . '_type', true );
		$id   = (int) get_post_meta( $kobling_id, '_samlab_part_' . $part . '_id', true );
		if ( ! $id ) {
			continue;
		}
		if ( 'bruker' === $type && $id === $user_id ) {
			return true;
		}
		if ( 'bedrift' === $type && (int) get_post_meta( $id, '_samlab_kontaktperson', true ) === $user_id ) {
			return true;
		}
	}
	return false;
}

/**
 * Lesetilgang for parter: moderator+ har primitivene, en part mappes
 * til `read`, alle andre avvises. Redigering røres ikke - den styres
 * av koblingens egne capability-primitiver.
 *
 * @param string[] $caps    Primitive capabilities som kreves.
 * @param string   $cap     Meta-capability som sjekkes.
 * @param int      $user_id Brukeren som sjekkes.
 * @param array    $args    Ekstra argumenter; $args[0] er post-ID.
 * @return string[]
 */
function samlab_map_kobling_read( $caps, $cap, $user_id, $args ) {
	if ( 'read_post' !== $cap || empty( $args[0] ) ) {
		return $caps;
	}
	$post = get_post( $args[0] );
	if ( ! $post || 'samlab_kobling' !== $post->post_type ) {
		return $caps;
	}

	if ( user_can( $user_id, 'edit_others_samlab_koblinger' ) ) {
		return array( 'read' );
	}
	if ( samlab_er_kobling_part( $post->ID, $user_id ) ) {
		return array( 'read' );
	}
	return array( 'do_not_allow' );
}
add_filter( 'map_meta_cap', 'samlab_map_kobling_read', 10, 4 );

/**
 * Oppretter en kobling programmatisk (brukes av matching i E4 og
 * kontrollpanelet i E3).
 *
 * @param array $args tittel, begrunnelse, kilde (manuell|matching),
 *                    part_a/part_b som array( type: bedrift|bruker, id: int ).
 * @return int|WP_Error Koblingens post-ID.
 */
function samlab_opprett_kobling( $args ) {
	$kobling_id = wp_insert_post(
		array(
			'post_type'    => 'samlab_kobling',
			'post_status'  => 'publish',
			'post_title'   => isset( $args['tittel'] ) ? sanitize_text_field( $args['tittel'] ) : __( 'Kobling', 'samlab' ),
			'post_content' => isset( $args['begrunnelse'] ) ? sanitize_textarea_field( $args['begrunnelse'] ) : '',
		),
		true
	);
	if ( is_wp_error( $kobling_id ) ) {
		return $kobling_id;
	}

	update_post_meta( $kobling_id, '_samlab_kilde', isset( $args['kilde'] ) && 'matching' === $args['kilde'] ? 'matching' : 'manuell' );
	foreach ( array( 'part_a', 'part_b' ) as $part ) {
		if ( isset( $args[ $part ]['type'], $args[ $part ]['id'] ) ) {
			samlab_sett_kobling_part( $kobling_id, str_replace( 'part_', '', $part ), (string) $args[ $part ]['type'], (int) $args[ $part ]['id'] );
		}
	}
	samlab_sett_kobling_status( $kobling_id, 'foreslatt' );

	return $kobling_id;
}

/**
 * Setter en part på koblingen etter validering av at målet finnes.
 *
 * @param int    $kobling_id Koblingen.
 * @param string $part       «a» eller «b».
 * @param string $type       bedrift|bruker.
 * @param int    $id         Bedriftens post-ID eller brukerens ID.
 * @return bool Om parten ble satt.
 */
function samlab_sett_kobling_part( $kobling_id, $part, $type, $id ) {
	$part = 'b' === $part ? 'b' : 'a';
	$id   = absint( $id );

	if ( 'bedrift' === $type && 'samlab_bedrift' === get_post_type( $id ) ) {
		update_post_meta( $kobling_id, '_samlab_part_' . $part . '_type', 'bedrift' );
		update_post_meta( $kobling_id, '_samlab_part_' . $part . '_id', $id );
		return true;
	}
	if ( 'bruker' === $type && get_userdata( $id ) ) {
		update_post_meta( $kobling_id, '_samlab_part_' . $part . '_type', 'bruker' );
		update_post_meta( $kobling_id, '_samlab_part_' . $part . '_id', $id );
		return true;
	}
	return false;
}

/**
 * Setter status og fører statusloggen.
 *
 * @param int    $kobling_id Koblingen.
 * @param string $status     En av samlab_kobling_statuser().
 * @param int    $user_id    Hvem som endret (0 = system/cron).
 * @return bool Om statusen var gyldig og ble satt.
 */
function samlab_sett_kobling_status( $kobling_id, $status, $user_id = 0 ) {
	if ( ! array_key_exists( $status, samlab_kobling_statuser() ) ) {
		return false;
	}
	$gammel = get_post_meta( $kobling_id, '_samlab_status', true );
	update_post_meta( $kobling_id, '_samlab_status', $status );

	if ( $gammel !== $status ) {
		$logg   = get_post_meta( $kobling_id, '_samlab_statuslogg', true );
		$logg   = is_array( $logg ) ? $logg : array();
		$logg[] = array(
			'status'  => $status,
			'user_id' => (int) $user_id,
			'tid'     => gmdate( 'Y-m-d H:i:s' ),
		);
		update_post_meta( $kobling_id, '_samlab_statuslogg', $logg );

		/**
		 * Kjøres når en kobling endrer status.
		 *
		 * @since 0.2.0
		 *
		 * @param int    $kobling_id Koblingen.
		 * @param string $status     Ny status.
		 * @param string $gammel     Forrige status ('' ved opprettelse).
		 * @param int    $user_id    Hvem som endret (0 = system).
		 */
		do_action( 'samlab_kobling_status_endret', $kobling_id, $status, (string) $gammel, (int) $user_id );
	}
	return true;
}

/**
 * Registrerer metaboksen for kobling.
 *
 * @return void
 */
function samlab_kobling_meta_boxes() {
	add_meta_box( 'samlab_kobling_detaljer', __( 'Koblingsdetaljer', 'samlab' ), 'samlab_render_kobling_box', 'samlab_kobling', 'normal', 'high' );
}
add_action( 'add_meta_boxes_samlab_kobling', 'samlab_kobling_meta_boxes' );

/**
 * Metaboks: status, kilde og de to partene.
 *
 * @param WP_Post $post Koblingen som redigeres.
 * @return void
 */
function samlab_render_kobling_box( $post ) {
	wp_nonce_field( 'samlab_kobling_meta', 'samlab_kobling_nonce' );

	$status = get_post_meta( $post->ID, '_samlab_status', true );
	$status = '' !== $status ? $status : 'foreslatt';
	$kilde  = get_post_meta( $post->ID, '_samlab_kilde', true );

	echo '<table class="form-table" role="presentation">';

	echo '<tr><th scope="row"><label for="samlab_status">' . esc_html__( 'Status', 'samlab' ) . '</label></th><td>';
	echo '<select id="samlab_status" name="samlab_status">';
	foreach ( samlab_kobling_statuser() as $samlab_slug => $samlab_navn ) {
		echo '<option value="' . esc_attr( $samlab_slug ) . '"' . selected( $status, $samlab_slug, false ) . '>' . esc_html( $samlab_navn ) . '</option>';
	}
	echo '</select></td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'Kilde', 'samlab' ) . '</th><td>';
	echo esc_html( 'matching' === $kilde ? __( 'Matchforslag', 'samlab' ) : __( 'Manuell', 'samlab' ) );
	echo '</td></tr>';

	$bedrifter = get_posts(
		array(
			'post_type'      => 'samlab_bedrift',
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'posts_per_page' => 100,
		)
	);

	foreach ( array(
		'a' => __( 'Part A', 'samlab' ),
		'b' => __( 'Part B', 'samlab' ),
	) as $samlab_part => $samlab_etikett ) {
		$type = get_post_meta( $post->ID, '_samlab_part_' . $samlab_part . '_type', true );
		$id   = (int) get_post_meta( $post->ID, '_samlab_part_' . $samlab_part . '_id', true );

		echo '<tr><th scope="row">' . esc_html( $samlab_etikett ) . '</th><td>';
		echo '<label>' . esc_html__( 'Bedrift:', 'samlab' ) . ' ';
		echo '<select name="samlab_part_' . esc_attr( $samlab_part ) . '_bedrift">';
		echo '<option value="0">' . esc_html__( '- Ingen -', 'samlab' ) . '</option>';
		foreach ( $bedrifter as $samlab_bedrift ) {
			$valgt = 'bedrift' === $type && $id === $samlab_bedrift->ID;
			echo '<option value="' . esc_attr( (string) $samlab_bedrift->ID ) . '"' . selected( $valgt, true, false ) . '>' . esc_html( get_the_title( $samlab_bedrift ) ) . '</option>';
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__( 'eller bruker:', 'samlab' ) . ' ';
		wp_dropdown_users(
			array(
				'name'              => 'samlab_part_' . $samlab_part . '_bruker',
				'selected'          => 'bruker' === $type ? $id : 0,
				'show_option_none'  => __( '- Ingen -', 'samlab' ),
				'option_none_value' => 0,
			)
		);
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'Velg bedrift eller bruker - bedrift vinner om begge er satt.', 'samlab' ) . '</p>';
		echo '</td></tr>';
	}

	echo '</table>';

	$logg = get_post_meta( $post->ID, '_samlab_statuslogg', true );
	if ( is_array( $logg ) && array() !== $logg ) {
		echo '<h4>' . esc_html__( 'Statuslogg', 'samlab' ) . '</h4><ol>';
		$statuser = samlab_kobling_statuser();
		foreach ( $logg as $rad ) {
			$hvem = ! empty( $rad['user_id'] ) ? get_userdata( (int) $rad['user_id'] ) : null;
			echo '<li>' . esc_html( isset( $statuser[ $rad['status'] ] ) ? $statuser[ $rad['status'] ] : $rad['status'] );
			echo ' - ' . esc_html( $rad['tid'] );
			echo $hvem ? ' (' . esc_html( $hvem->display_name ) . ')' : ' (' . esc_html__( 'system', 'samlab' ) . ')';
			echo '</li>';
		}
		echo '</ol>';
	}
}

/**
 * Lagrer koblings-meta med nonce- og capability-sjekk.
 *
 * @param int $post_id Koblingens post-ID.
 * @return void
 */
function samlab_save_kobling_meta( $post_id ) {
	$nonce = isset( $_POST['samlab_kobling_nonce'] ) ? sanitize_key( wp_unslash( $_POST['samlab_kobling_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'samlab_kobling_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['samlab_status'] ) ) {
		samlab_sett_kobling_status( $post_id, sanitize_key( wp_unslash( $_POST['samlab_status'] ) ), get_current_user_id() );
	}

	if ( '' === (string) get_post_meta( $post_id, '_samlab_kilde', true ) ) {
		update_post_meta( $post_id, '_samlab_kilde', 'manuell' );
	}

	foreach ( array( 'a', 'b' ) as $part ) {
		$bedrift = isset( $_POST[ 'samlab_part_' . $part . '_bedrift' ] ) ? absint( $_POST[ 'samlab_part_' . $part . '_bedrift' ] ) : 0;
		$bruker  = isset( $_POST[ 'samlab_part_' . $part . '_bruker' ] ) ? absint( $_POST[ 'samlab_part_' . $part . '_bruker' ] ) : 0;
		if ( $bedrift ) {
			samlab_sett_kobling_part( $post_id, $part, 'bedrift', $bedrift );
		} elseif ( $bruker ) {
			samlab_sett_kobling_part( $post_id, $part, 'bruker', $bruker );
		} else {
			delete_post_meta( $post_id, '_samlab_part_' . $part . '_type' );
			delete_post_meta( $post_id, '_samlab_part_' . $part . '_id' );
		}
	}
}
add_action( 'save_post_samlab_kobling', 'samlab_save_kobling_meta' );
