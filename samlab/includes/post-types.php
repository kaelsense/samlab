<?php
/**
 * Custom post type: bedrift, med taksonomien kategori og egne
 * metabokser (ingen ACF). Feltmodellen speiler prototypens
 * Bedrift- og Intensjoner-typer.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrerer post-typen samlab_bedrift og taksonomien samlab_kategori.
 *
 * Visning skjer i portal-rutene (B7), derfor ingen egen offentlig
 * rewrite eller arkivside her.
 *
 * @return void
 */
function samlab_register_bedrift() {
	register_post_type(
		'samlab_bedrift',
		array(
			'labels'              => array(
				'name'          => __( 'Bedrifter', 'samlab' ),
				'singular_name' => __( 'Bedrift', 'samlab' ),
				'add_new_item'  => __( 'Legg til bedrift', 'samlab' ),
				'edit_item'     => __( 'Rediger bedrift', 'samlab' ),
				'search_items'  => __( 'Søk i bedrifter', 'samlab' ),
				'not_found'     => __( 'Ingen bedrifter funnet', 'samlab' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-building',
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'editor', 'thumbnail' ),
		)
	);

	register_taxonomy(
		'samlab_kategori',
		'samlab_bedrift',
		array(
			'labels'            => array(
				'name'          => __( 'Kategorier', 'samlab' ),
				'singular_name' => __( 'Kategori', 'samlab' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'samlab_register_bedrift' );

/**
 * Enkle metafelter for bedrift: meta-nøkkel => skjemadefinisjon.
 *
 * Tjenester og «åpen for» håndteres separat (sammensatte felter).
 *
 * @return array<string, array{label: string, type: string}>
 */
function samlab_bedrift_fields() {
	return array(
		'_samlab_kort'          => array(
			'label' => __( 'Kort beskrivelse', 'samlab' ),
			'type'  => 'textarea',
		),
		'_samlab_plass'         => array(
			'label' => __( 'Plass i huset', 'samlab' ),
			'type'  => 'text',
		),
		'_samlab_nettside'      => array(
			'label' => __( 'Nettside', 'samlab' ),
			'type'  => 'url',
		),
		'_samlab_kontaktperson' => array(
			'label' => __( 'Kontaktperson', 'samlab' ),
			'type'  => 'user',
		),
	);
}

/**
 * Intensjonsfeltene («Dette ser vi etter»): meta-nøkkel => etikett.
 *
 * @return array<string, string>
 */
function samlab_bedrift_intent_fields() {
	return array(
		'_samlab_leverer'     => __( 'Leverer', 'samlab' ),
		'_samlab_kjoper'      => __( 'Kjøper', 'samlab' ),
		'_samlab_trenger_na'  => __( 'Trenger nå', 'samlab' ),
		'_samlab_idealkunder' => __( 'Ideelle kunder', 'samlab' ),
	);
}

/**
 * Registrerer metaboksene for bedrift.
 *
 * @return void
 */
function samlab_bedrift_meta_boxes() {
	add_meta_box( 'samlab_bedrift_detaljer', __( 'Bedriftsdetaljer', 'samlab' ), 'samlab_render_detaljer_box', 'samlab_bedrift', 'normal', 'high' );
	add_meta_box( 'samlab_bedrift_tjenester', __( 'Tjenester', 'samlab' ), 'samlab_render_tjenester_box', 'samlab_bedrift', 'normal', 'default' );
	add_meta_box( 'samlab_bedrift_intensjoner', __( 'Dette ser vi etter', 'samlab' ), 'samlab_render_intensjoner_box', 'samlab_bedrift', 'normal', 'default' );
}
add_action( 'add_meta_boxes_samlab_bedrift', 'samlab_bedrift_meta_boxes' );

/**
 * Metaboks: kort beskrivelse, plass, nettside og kontaktperson.
 *
 * @param WP_Post $post Bedriften som redigeres.
 * @return void
 */
function samlab_render_detaljer_box( $post ) {
	wp_nonce_field( 'samlab_bedrift_meta', 'samlab_bedrift_nonce' );

	echo '<table class="form-table" role="presentation">';
	foreach ( samlab_bedrift_fields() as $key => $field ) {
		$id    = ltrim( $key, '_' );
		$value = get_post_meta( $post->ID, $key, true );

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
		if ( 'textarea' === $field['type'] ) {
			echo '<textarea class="large-text" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '">' . esc_textarea( $value ) . '</textarea>';
		} elseif ( 'user' === $field['type'] ) {
			wp_dropdown_users(
				array(
					'name'              => $id,
					'id'                => $id,
					'selected'          => absint( $value ),
					'show_option_none'  => __( '- Velg kontaktperson -', 'samlab' ),
					'option_none_value' => 0,
				)
			);
		} else {
			$type = 'url' === $field['type'] ? 'url' : 'text';
			echo '<input type="' . esc_attr( $type ) . '" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" />';
		}
		echo '</td></tr>';
	}
	echo '</table>';
}

/**
 * Metaboks: tjenester som repeterbare rader (tittel + punkter).
 *
 * @param WP_Post $post Bedriften som redigeres.
 * @return void
 */
function samlab_render_tjenester_box( $post ) {
	$tjenester = get_post_meta( $post->ID, '_samlab_tjenester', true );
	if ( ! is_array( $tjenester ) || array() === $tjenester ) {
		$tjenester = array(
			array(
				'tittel'  => '',
				'punkter' => array(),
			),
		);
	}

	echo '<div id="samlab-tjenester">';
	foreach ( $tjenester as $i => $tjeneste ) {
		samlab_render_tjeneste_row( (int) $i, $tjeneste );
	}
	echo '</div>';
	echo '<p><button type="button" class="button" id="samlab-legg-til-tjeneste">' . esc_html__( 'Legg til tjeneste', 'samlab' ) . '</button></p>';

	ob_start();
	samlab_render_tjeneste_row( '__i__', array() );
	$mal = ob_get_clean();
	echo '<script type="text/template" id="samlab-tjeneste-mal">' . $mal . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Malen bygges av samme escapede render-funksjon som radene over.
	?>
	<script>
	( function () {
		var beholder = document.getElementById( 'samlab-tjenester' );
		var knapp    = document.getElementById( 'samlab-legg-til-tjeneste' );
		var mal      = document.getElementById( 'samlab-tjeneste-mal' );
		var teller   = beholder.children.length;
		knapp.addEventListener( 'click', function () {
			var div = document.createElement( 'div' );
			div.innerHTML = mal.innerHTML.replace( /__i__/g, String( teller++ ) );
			beholder.appendChild( div.firstElementChild );
		} );
		beholder.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'samlab-fjern-tjeneste' ) ) {
				e.target.closest( '.samlab-tjeneste' ).remove();
			}
		} );
	}() );
	</script>
	<?php
}

/**
 * Én tjeneste-rad i metaboksen.
 *
 * @param int|string           $i        Radindeks, eller «__i__» i JS-malen.
 * @param array<string, mixed> $tjeneste Tjenesten (tittel, punkter).
 * @return void
 */
function samlab_render_tjeneste_row( $i, $tjeneste ) {
	$tittel  = isset( $tjeneste['tittel'] ) ? $tjeneste['tittel'] : '';
	$punkter = isset( $tjeneste['punkter'] ) && is_array( $tjeneste['punkter'] ) ? implode( "\n", $tjeneste['punkter'] ) : '';
	$i       = (string) $i;

	echo '<div class="samlab-tjeneste" style="border:1px solid #ccd0d4;padding:8px 12px;margin-bottom:8px;">';
	echo '<p><label>' . esc_html__( 'Tittel', 'samlab' ) . '<br /><input type="text" class="regular-text" name="samlab_tjenester[' . esc_attr( $i ) . '][tittel]" value="' . esc_attr( $tittel ) . '" /></label></p>';
	echo '<p><label>' . esc_html__( 'Punkter (ett per linje)', 'samlab' ) . '<br /><textarea class="large-text" rows="3" name="samlab_tjenester[' . esc_attr( $i ) . '][punkter]">' . esc_textarea( $punkter ) . '</textarea></label></p>';
	echo '<p><button type="button" class="button-link-delete samlab-fjern-tjeneste">' . esc_html__( 'Fjern tjeneste', 'samlab' ) . '</button></p>';
	echo '</div>';
}

/**
 * Metaboks: intensjonsfeltene og «åpen for».
 *
 * @param WP_Post $post Bedriften som redigeres.
 * @return void
 */
function samlab_render_intensjoner_box( $post ) {
	echo '<table class="form-table" role="presentation">';
	foreach ( samlab_bedrift_intent_fields() as $key => $label ) {
		$id    = ltrim( $key, '_' );
		$value = get_post_meta( $post->ID, $key, true );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><textarea class="large-text" rows="2" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '">' . esc_textarea( $value ) . '</textarea></td></tr>';
	}

	$apen_for = get_post_meta( $post->ID, '_samlab_apen_for', true );
	$apen_for = is_array( $apen_for ) ? implode( "\n", $apen_for ) : '';
	echo '<tr><th scope="row"><label for="samlab_apen_for">' . esc_html__( 'Åpne for (ett per linje)', 'samlab' ) . '</label></th>';
	echo '<td><textarea class="large-text" rows="3" id="samlab_apen_for" name="samlab_apen_for">' . esc_textarea( $apen_for ) . '</textarea></td></tr>';
	echo '</table>';
}

/**
 * Lagrer bedrifts-meta med nonce- og capability-sjekk.
 *
 * @param int $post_id Bedriftens post-ID.
 * @return void
 */
function samlab_save_bedrift_meta( $post_id ) {
	$nonce = isset( $_POST['samlab_bedrift_nonce'] ) ? sanitize_key( wp_unslash( $_POST['samlab_bedrift_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'samlab_bedrift_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( samlab_bedrift_fields() as $key => $field ) {
		$name = ltrim( $key, '_' );
		if ( ! isset( $_POST[ $name ] ) ) {
			continue;
		}
		$raw = wp_unslash( $_POST[ $name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Saniteres feltvis under.
		switch ( $field['type'] ) {
			case 'url':
				$value = esc_url_raw( $raw );
				break;
			case 'user':
				$value = absint( $raw );
				break;
			case 'textarea':
				$value = sanitize_textarea_field( $raw );
				break;
			default:
				$value = sanitize_text_field( $raw );
		}
		update_post_meta( $post_id, $key, $value );
	}

	foreach ( array_keys( samlab_bedrift_intent_fields() ) as $key ) {
		$name = ltrim( $key, '_' );
		if ( isset( $_POST[ $name ] ) ) {
			update_post_meta( $post_id, $key, sanitize_textarea_field( wp_unslash( $_POST[ $name ] ) ) );
		}
	}

	if ( isset( $_POST['samlab_apen_for'] ) ) {
		$linjer = explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['samlab_apen_for'] ) ) );
		$linjer = array_values( array_filter( array_map( 'trim', $linjer ) ) );
		update_post_meta( $post_id, '_samlab_apen_for', $linjer );
	}

	if ( isset( $_POST['samlab_tjenester'] ) && is_array( $_POST['samlab_tjenester'] ) ) {
		$tjenester = array();
		foreach ( wp_unslash( $_POST['samlab_tjenester'] ) as $rad ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Saniteres feltvis under.
			if ( ! is_array( $rad ) ) {
				continue;
			}
			$tittel  = isset( $rad['tittel'] ) ? sanitize_text_field( $rad['tittel'] ) : '';
			$punkter = array();
			if ( isset( $rad['punkter'] ) ) {
				$punkter = explode( "\n", sanitize_textarea_field( $rad['punkter'] ) );
				$punkter = array_values( array_filter( array_map( 'trim', $punkter ) ) );
			}
			if ( '' === $tittel && array() === $punkter ) {
				continue;
			}
			$tjenester[] = array(
				'tittel'  => $tittel,
				'punkter' => $punkter,
			);
		}
		update_post_meta( $post_id, '_samlab_tjenester', $tjenester );
	}
}
add_action( 'save_post_samlab_bedrift', 'samlab_save_bedrift_meta' );
