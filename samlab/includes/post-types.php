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
 * Registrerer post-typen samlab_behov med taksonomiene
 * samlab_retning (trenger/tilbyr) og samlab_behovstype.
 *
 * @return void
 */
function samlab_register_behov() {
	register_post_type(
		'samlab_behov',
		array(
			'labels'              => array(
				'name'          => __( 'Behov og tilbud', 'samlab' ),
				'singular_name' => __( 'Behov', 'samlab' ),
				'add_new_item'  => __( 'Legg til behov', 'samlab' ),
				'edit_item'     => __( 'Rediger behov', 'samlab' ),
				'search_items'  => __( 'Søk i behov', 'samlab' ),
				'not_found'     => __( 'Ingen behov funnet', 'samlab' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-megaphone',
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'editor' ),
		)
	);

	register_taxonomy(
		'samlab_retning',
		'samlab_behov',
		array(
			'labels'            => array(
				'name'          => __( 'Retning', 'samlab' ),
				'singular_name' => __( 'Retning', 'samlab' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);

	register_taxonomy(
		'samlab_behovstype',
		'samlab_behov',
		array(
			'labels'            => array(
				'name'          => __( 'Behovstyper', 'samlab' ),
				'singular_name' => __( 'Behovstype', 'samlab' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'samlab_register_behov' );

/**
 * Sørger for at retningstermene «Trenger» og «Tilbyr» finnes.
 * Kalles ved aktivering (etter eksplisitt taksonomi-registrering).
 *
 * @return void
 */
function samlab_ensure_retning_terms() {
	foreach ( array(
		'trenger' => __( 'Trenger', 'samlab' ),
		'tilbyr'  => __( 'Tilbyr', 'samlab' ),
	) as $slug => $navn ) {
		if ( ! term_exists( $slug, 'samlab_retning' ) ) {
			wp_insert_term( $navn, 'samlab_retning', array( 'slug' => $slug ) );
		}
	}
}

/**
 * Finner en publisert bedrift på slug.
 *
 * @param string $slug Bedriftens post_name.
 * @return WP_Post|null
 */
function samlab_get_bedrift_by_slug( $slug ) {
	$treff = get_posts(
		array(
			'post_type'      => 'samlab_bedrift',
			'post_status'    => 'publish',
			'name'           => sanitize_title( $slug ),
			'posts_per_page' => 1,
		)
	);
	return array() === $treff ? null : $treff[0];
}

/**
 * Håndhever at bedriftsredaktører kun kan redigere bedriften der de
 * er kontaktperson - som capability-sjekk, ikke skjult UI.
 *
 * Administrator og redaktør beholder standardmappingen
 * (edit_others_posts). Alle andre: redigering krever
 * samlab_edit_bedrift OG at brukeren er bedriftens kontaktperson;
 * sletting er forbeholdt admin/redaktør.
 *
 * @param string[] $caps    Primitive capabilities som kreves.
 * @param string   $cap     Meta-capability som sjekkes.
 * @param int      $user_id Brukeren som sjekkes.
 * @param array    $args    Ekstra argumenter; $args[0] er post-ID.
 * @return string[]
 */
function samlab_map_bedrift_caps( $caps, $cap, $user_id, $args ) {
	if ( ! in_array( $cap, array( 'edit_post', 'delete_post' ), true ) || empty( $args[0] ) ) {
		return $caps;
	}

	$post = get_post( $args[0] );
	if ( ! $post || 'samlab_bedrift' !== $post->post_type ) {
		return $caps;
	}

	if ( user_can( $user_id, 'edit_others_posts' ) ) {
		return $caps;
	}

	if ( 'edit_post' === $cap ) {
		$kontakt = (int) get_post_meta( $post->ID, '_samlab_kontaktperson', true );
		if ( $kontakt > 0 && $kontakt === (int) $user_id ) {
			return array( 'samlab_edit_bedrift' );
		}
	}

	return array( 'do_not_allow' );
}
add_filter( 'map_meta_cap', 'samlab_map_bedrift_caps', 10, 4 );

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

	// Løpende teller, ikke nøkkelen fra meta: skulle noen ha skrevet
	// _samlab_tjenester utenfra med hull eller strengnøkler, ville
	// nøklene kunne kollidere i skjemaet og slå to rader sammen til én.
	echo '<div id="samlab-tjenester">';
	$i = 0;
	foreach ( $tjenester as $tjeneste ) {
		samlab_render_tjeneste_row( $i, $tjeneste );
		++$i;
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
		// Neste indeks fra den høyeste som allerede finnes - ikke fra
		// antall rader, som ville kollidert om indeksene har hull.
		var teller = 0;
		Array.prototype.forEach.call( beholder.children, function ( rad ) {
			var i = parseInt( rad.getAttribute( 'data-samlab-indeks' ), 10 );
			if ( ! isNaN( i ) && i >= teller ) {
				teller = i + 1;
			}
		} );
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
 * Publiserte bedrifter til nedtrekksvalg, sortert på tittel.
 *
 * Bevisst ubundet: et nedtrekk som kapper på et tak skjuler bedriften
 * brukeren skal velge, og det er verre enn en litt større spørring.
 * Kostnaden ved -1 ligger i hydreringen av meta og termer, ikke i
 * radene - kallerne trenger bare ID og tittel, så den slås av.
 * (`get_posts()` setter `no_found_rows` selv.)
 *
 * @return WP_Post[]
 */
function samlab_bedrifter_for_valg() {
	return get_posts(
		array(
			'post_type'              => 'samlab_bedrift',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
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

	echo '<div class="samlab-tjeneste" data-samlab-indeks="' . esc_attr( $i ) . '" style="border:1px solid #ccd0d4;padding:8px 12px;margin-bottom:8px;">';
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

/**
 * Håndboken: vanlige WordPress-sider merkes som portalinnhold med
 * meta-flagget _samlab_handbok og vises i portal-skallet (C5).
 *
 * @return WP_Post[] Merkede sider i menyrekkefølge.
 */
function samlab_get_handbok_pages() {
	return get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'posts_per_page' => 50,
			'meta_key'       => '_samlab_handbok', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lavvolum sidegruppe.
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
}

/**
 * Finner en håndbok-side på slug - kun blant merkede sider.
 *
 * @param string $slug Sidens post_name.
 * @return WP_Post|null
 */
function samlab_get_handbok_page_by_slug( $slug ) {
	$slug = sanitize_title( $slug );
	foreach ( samlab_get_handbok_pages() as $side ) {
		if ( $side->post_name === $slug ) {
			return $side;
		}
	}
	return null;
}

/**
 * Metaboks på sider: «Vis i portalens håndbok».
 *
 * @return void
 */
function samlab_handbok_meta_box() {
	add_meta_box( 'samlab_handbok', __( 'Samlab-portalen', 'samlab' ), 'samlab_render_handbok_box', 'page', 'side' );
}
add_action( 'add_meta_boxes_page', 'samlab_handbok_meta_box' );

/**
 * Rendrer håndbok-avkrysningen.
 *
 * @param WP_Post $post Siden som redigeres.
 * @return void
 */
function samlab_render_handbok_box( $post ) {
	wp_nonce_field( 'samlab_handbok_meta', 'samlab_handbok_nonce' );
	$valgt = get_post_meta( $post->ID, '_samlab_handbok', true );
	echo '<label><input type="checkbox" name="samlab_handbok" value="1"' . checked( $valgt, '1', false ) . ' /> ' . esc_html__( 'Vis i portalens håndbok', 'samlab' ) . '</label>';
	echo '<p class="description">' . esc_html__( 'Rekkefølgen styres av sidens «Rekkefølge»-felt.', 'samlab' ) . '</p>';
}

/**
 * Lagrer håndbok-flagget.
 *
 * @param int $post_id Sidens ID.
 * @return void
 */
function samlab_save_handbok_meta( $post_id ) {
	$nonce = isset( $_POST['samlab_handbok_nonce'] ) ? sanitize_key( wp_unslash( $_POST['samlab_handbok_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'samlab_handbok_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['samlab_handbok'] ) && '1' === $_POST['samlab_handbok'] ) {
		update_post_meta( $post_id, '_samlab_handbok', '1' );
	} else {
		delete_post_meta( $post_id, '_samlab_handbok' );
	}
}
add_action( 'save_post_page', 'samlab_save_handbok_meta' );

/**
 * Registrerer metaboksen for behov.
 *
 * @return void
 */
function samlab_behov_meta_boxes() {
	add_meta_box( 'samlab_behov_detaljer', __( 'Behovsdetaljer', 'samlab' ), 'samlab_render_behov_box', 'samlab_behov', 'normal', 'high' );
}
add_action( 'add_meta_boxes_samlab_behov', 'samlab_behov_meta_boxes' );

/**
 * Metaboks: frist, budsjett, kompetanse, kontaktform og bedrift.
 *
 * @param WP_Post $post Behovet som redigeres.
 * @return void
 */
function samlab_render_behov_box( $post ) {
	wp_nonce_field( 'samlab_behov_meta', 'samlab_behov_nonce' );

	$felter = array(
		'samlab_frist'       => __( 'Frist', 'samlab' ),
		'samlab_budsjett'    => __( 'Budsjett', 'samlab' ),
		'samlab_kontaktform' => __( 'Ønsket kontaktform', 'samlab' ),
	);

	echo '<table class="form-table" role="presentation">';
	foreach ( $felter as $id => $label ) {
		$value = get_post_meta( $post->ID, '_' . $id, true );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" /></td></tr>';
	}

	$kompetanse = get_post_meta( $post->ID, '_samlab_kompetanse', true );
	$kompetanse = is_array( $kompetanse ) ? implode( "\n", $kompetanse ) : '';
	echo '<tr><th scope="row"><label for="samlab_kompetanse">' . esc_html__( 'Kompetanse (én per linje)', 'samlab' ) . '</label></th>';
	echo '<td><textarea class="large-text" rows="3" id="samlab_kompetanse" name="samlab_kompetanse">' . esc_textarea( $kompetanse ) . '</textarea></td></tr>';

	$valgt     = (int) get_post_meta( $post->ID, '_samlab_bedrift', true );
	$bedrifter = samlab_bedrifter_for_valg();
	echo '<tr><th scope="row"><label for="samlab_bedrift">' . esc_html__( 'Bedrift', 'samlab' ) . '</label></th><td>';
	echo '<select id="samlab_bedrift" name="samlab_bedrift">';
	echo '<option value="0">' . esc_html__( '- Velg bedrift -', 'samlab' ) . '</option>';
	foreach ( $bedrifter as $bedrift ) {
		echo '<option value="' . esc_attr( (string) $bedrift->ID ) . '"' . selected( $valgt, $bedrift->ID, false ) . '>' . esc_html( get_the_title( $bedrift ) ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '</table>';
}

/**
 * Lagrer behovs-meta med nonce- og capability-sjekk.
 *
 * @param int $post_id Behovets post-ID.
 * @return void
 */
function samlab_save_behov_meta( $post_id ) {
	$nonce = isset( $_POST['samlab_behov_nonce'] ) ? sanitize_key( wp_unslash( $_POST['samlab_behov_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'samlab_behov_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( array( 'samlab_frist', 'samlab_budsjett', 'samlab_kontaktform' ) as $name ) {
		if ( isset( $_POST[ $name ] ) ) {
			update_post_meta( $post_id, '_' . $name, sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) );
		}
	}

	if ( isset( $_POST['samlab_kompetanse'] ) ) {
		$linjer = explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['samlab_kompetanse'] ) ) );
		$linjer = array_values( array_filter( array_map( 'trim', $linjer ) ) );
		update_post_meta( $post_id, '_samlab_kompetanse', $linjer );
	}

	if ( isset( $_POST['samlab_bedrift'] ) ) {
		$bedrift_id = absint( $_POST['samlab_bedrift'] );
		if ( $bedrift_id && 'samlab_bedrift' !== get_post_type( $bedrift_id ) ) {
			$bedrift_id = 0; // Koblingen må peke på en bedrift.
		}
		update_post_meta( $post_id, '_samlab_bedrift', $bedrift_id );
	}
}
add_action( 'save_post_samlab_behov', 'samlab_save_behov_meta' );
