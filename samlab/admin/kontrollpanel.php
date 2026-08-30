<?php
/**
 * Kontrollpanelet: community-managerens wp-admin-side (planens 3.4).
 *
 * Koblingskø med godkjenn/avvis (godkjenn setter forespurt og
 * overlater neste steg til partenes samtykke, G1), forespurte
 * koblinger som venter på partene, aktive koblinger med
 * statuskjede, og «trenger oppmerksomhet»-listene: nye medlemmer
 * uten introduksjon, åpne behov eldre enn 14 dager, ufullstendige
 * bedriftsprofiler og stille medlemmer.
 *
 * Tilgang: koblings-capability (moderator, redaktør, administrator).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrerer kontrollpanel-siden i wp-admin.
 *
 * @return void
 */
function samlab_kontrollpanel_menu() {
	samlab_admin_skjermer(
		add_menu_page(
			__( 'Samlab kontrollpanel', 'samlab' ),
			__( 'Kontrollpanel', 'samlab' ),
			'edit_samlab_koblinger',
			'samlab-kontrollpanel',
			'samlab_render_kontrollpanel',
			'dashicons-groups',
			26
		)
	);
}
add_action( 'admin_menu', 'samlab_kontrollpanel_menu' );

/**
 * Taket på kontrollpanelets lister.
 *
 * Ingen av seksjonene har paginering, så listene er avkortet. Der
 * taket er nådd, viser sammendraget «100+» framfor å love et presist
 * tall det ikke har dekning for. Fase 5 gjør avkortingen navigerbar.
 */
const SAMLAB_KP_TAK = 100;

/**
 * Koblinger med gitt status, eldste først.
 *
 * @param string[] $statuser Status-slugs.
 * @return WP_Post[]
 */
function samlab_kp_koblinger( $statuser ) {
	return get_posts(
		array(
			'post_type'      => 'samlab_kobling',
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'ASC',
			'posts_per_page' => SAMLAB_KP_TAK,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Lavvolum admin-liste.
				array(
					'key'     => '_samlab_status',
					'value'   => $statuser,
					'compare' => 'IN',
				),
			),
		)
	);
}

/**
 * Lesbar partsbeskrivelse for en kobling.
 *
 * @param int $kobling_id Koblingen.
 * @return string
 */
function samlab_kp_part_tekst( $kobling_id ) {
	$navn = array();
	foreach ( array( 'a', 'b' ) as $part ) {
		$type = get_post_meta( $kobling_id, '_samlab_part_' . $part . '_type', true );
		$id   = (int) get_post_meta( $kobling_id, '_samlab_part_' . $part . '_id', true );
		if ( 'bedrift' === $type && $id ) {
			$navn[] = get_the_title( $id );
		} elseif ( 'bruker' === $type && $id ) {
			$bruker = get_userdata( $id );
			$navn[] = $bruker ? $bruker->display_name : __( 'Slettet bruker', 'samlab' );
		}
	}
	return implode( ' ↔ ', $navn );
}

/**
 * Bruker-ID-er som er part i minst én kobling (uansett status).
 *
 * @return int[]
 */
function samlab_kp_brukere_med_kobling() {
	$brukere = array();
	foreach ( samlab_kp_koblinger( array_keys( samlab_kobling_statuser() ) ) as $kobling ) {
		$brukere = array_merge( $brukere, samlab_kobling_part_brukere( $kobling->ID ) );
	}
	return array_values( array_unique( $brukere ) );
}

/**
 * Nye medlemmer (siste $dager) som ikke er part i noen kobling.
 *
 * @param int $dager Vindu bakover i tid.
 * @return WP_User[]
 */
function samlab_kp_nye_uten_intro( $dager = 30 ) {
	$med_kobling = samlab_kp_brukere_med_kobling();
	$grense      = gmdate( 'Y-m-d H:i:s', time() - $dager * DAY_IN_SECONDS );

	$nye = get_users(
		array(
			'role__in' => array_keys( samlab_get_roles() ),
			'orderby'  => 'registered',
			'order'    => 'DESC',
			'number'   => SAMLAB_KP_TAK,
		)
	);

	return array_values(
		array_filter(
			$nye,
			function ( $bruker ) use ( $med_kobling, $grense ) {
				return $bruker->user_registered >= $grense && ! in_array( (int) $bruker->ID, $med_kobling, true );
			}
		)
	);
}

/**
 * Åpne behov eldre enn $dager.
 *
 * @param int $dager Aldersgrense.
 * @return WP_Post[]
 */
function samlab_kp_gamle_behov( $dager = 14 ) {
	return get_posts(
		array(
			'post_type'      => 'samlab_behov',
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'ASC',
			'posts_per_page' => SAMLAB_KP_TAK,
			'date_query'     => array(
				array( 'before' => $dager . ' days ago' ),
			),
		)
	);
}

/**
 * Bedrifter med manglende profilfelter.
 *
 * @return array<int, array{bedrift: WP_Post, mangler: string[]}>
 */
function samlab_kp_ufullstendige_bedrifter() {
	$sjekker = array(
		'_samlab_kort'          => __( 'kort beskrivelse', 'samlab' ),
		'_samlab_kontaktperson' => __( 'kontaktperson', 'samlab' ),
		'_samlab_leverer'       => __( 'intensjoner', 'samlab' ),
	);

	$resultat  = array();
	$bedrifter = get_posts(
		array(
			'post_type'      => 'samlab_bedrift',
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'posts_per_page' => SAMLAB_KP_TAK,
		)
	);
	foreach ( $bedrifter as $bedrift ) {
		$mangler = array();
		foreach ( $sjekker as $meta => $etikett ) {
			if ( '' === (string) get_post_meta( $bedrift->ID, $meta, true ) ) {
				$mangler[] = $etikett;
			}
		}
		if ( ! has_post_thumbnail( $bedrift ) ) {
			$mangler[] = __( 'logo', 'samlab' );
		}
		if ( array() !== $mangler ) {
			$resultat[] = array(
				'bedrift' => $bedrift,
				'mangler' => $mangler,
			);
		}
	}
	return $resultat;
}

/**
 * Medlemmer registrert for mer enn $dager siden uten aktivitet
 * (innlegg, kommentar eller reaksjon) i samme vindu.
 *
 * @param int $dager Aktivitetsvindu.
 * @return WP_User[]
 */
function samlab_kp_stille_medlemmer( $dager = 30 ) {
	global $wpdb;
	$grense = gmdate( 'Y-m-d H:i:s', time() - $dager * DAY_IN_SECONDS );

	$innlegg    = samlab_table( 'innlegg' );
	$reaksjoner = samlab_table( 'reaksjoner' );
	$aktive     = array_map(
		'intval',
		array_merge(
			$wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$innlegg} WHERE created_at >= %s", $grense ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Egen tabell, navn fra samlab_table().
			$wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$reaksjoner} WHERE created_at >= %s", $grense ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Egen tabell, navn fra samlab_table().
			$wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->comments} WHERE comment_type = 'samlab_innlegg' AND comment_date_gmt >= %s", $grense ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Enkel admin-liste.
		)
	);

	$medlemmer = get_users(
		array(
			'role__in' => array_keys( samlab_get_roles() ),
			'orderby'  => 'registered',
			'order'    => 'ASC',
			'number'   => SAMLAB_KP_TAK,
		)
	);

	return array_values(
		array_filter(
			$medlemmer,
			function ( $bruker ) use ( $aktive, $grense ) {
				return $bruker->user_registered < $grense && ! in_array( (int) $bruker->ID, $aktive, true );
			}
		)
	);
}

/**
 * Lesebekreftelses-oversikten: oppslag med lest-krav og hvem som
 * har/ikke har bekreftet (E8). Kun for moderator+ (siden er bak
 * edit_samlab_koblinger).
 *
 * @return array<int, array{innlegg: object, bekreftet: WP_User[], mangler: WP_User[]}>
 */
function samlab_kp_lesebekreftelser() {
	global $wpdb;
	$tabell = samlab_table( 'innlegg' );

	$oppslag = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell, admin-liste.
		"SELECT * FROM {$tabell} WHERE confirm_read = 1 AND status = 'publish' ORDER BY created_at DESC LIMIT 20" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table(), ingen brukerdata.
	);
	if ( array() === $oppslag ) {
		return array();
	}

	$medlemmer = get_users(
		array(
			'capability' => 'samlab_read_portal',
			'number'     => 500,
			'orderby'    => 'display_name',
		)
	);

	$resultat = array();
	foreach ( $oppslag as $innlegg ) {
		$lest_ids  = Samlab_Reaksjon::users( 'innlegg', (int) $innlegg->id, 'lest' );
		$bekreftet = array();
		$mangler   = array();
		foreach ( $medlemmer as $medlem ) {
			if ( in_array( (int) $medlem->ID, $lest_ids, true ) ) {
				$bekreftet[] = $medlem;
			} else {
				$mangler[] = $medlem;
			}
		}
		$resultat[] = array(
			'innlegg'   => $innlegg,
			'bekreftet' => $bekreftet,
			'mangler'   => $mangler,
		);
	}
	return $resultat;
}

/**
 * Utfører en koblingshandling med capability-sjekk.
 *
 * @param int    $kobling_id Koblingen.
 * @param string $handling   godkjenn|avvis|introdusert|fulgt_opp.
 * @param int    $user_id    Utførende bruker.
 * @param string $utfall     Valgfritt utfall ved fulgt_opp (G4).
 * @param string $notat      Valgfritt utfallsnotat.
 * @return true|WP_Error
 */
function samlab_kontrollpanel_utfor( $kobling_id, $handling, $user_id, $utfall = '', $notat = '' ) {
	$statusmap = array(
		// Godkjenn setter forespurt: veien til godkjent går gjennom
		// partenes eget samtykke (samlab_kobling_svar, G1).
		'godkjenn'    => 'forespurt',
		'avvis'       => 'avvist',
		'introdusert' => 'introdusert',
		'fulgt_opp'   => 'fulgt_opp',
	);
	if ( ! isset( $statusmap[ $handling ] ) ) {
		return new WP_Error( 'samlab_ukjent_handling', __( 'Ukjent handling.', 'samlab' ) );
	}
	if ( 'samlab_kobling' !== get_post_type( $kobling_id ) ) {
		return new WP_Error( 'samlab_ukjent_kobling', __( 'Fant ikke koblingen.', 'samlab' ) );
	}
	if ( ! user_can( $user_id, 'edit_post', $kobling_id ) ) {
		return new WP_Error( 'samlab_ingen_tilgang', __( 'Du har ikke tilgang til å endre koblinger.', 'samlab' ) );
	}

	// Fulgt opp med utfall (G4): utfallet føres først og løfter
	// selv statusen fra introdusert.
	if ( 'fulgt_opp' === $handling && '' !== $utfall ) {
		$resultat = samlab_sett_kobling_utfall( $kobling_id, $utfall, $notat, $user_id );
		if ( is_wp_error( $resultat ) ) {
			return $resultat;
		}
		if ( 'fulgt_opp' === get_post_meta( $kobling_id, '_samlab_status', true ) ) {
			return true;
		}
	}

	samlab_sett_kobling_status( $kobling_id, $statusmap[ $handling ], $user_id );
	return true;
}

/**
 * Mottak fra admin-post.php for koblingshandlingene.
 *
 * @return void
 */
function samlab_kontrollpanel_post() {
	$nonce = isset( $_POST['samlab_kp_nonce'] ) ? sanitize_key( wp_unslash( $_POST['samlab_kp_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'samlab_kobling_handling' ) ) {
		wp_die( esc_html__( 'Ugyldig eller utløpt skjema - gå tilbake og prøv igjen.', 'samlab' ), '', 403 );
	}

	$kobling_id = isset( $_POST['samlab_kobling_id'] ) ? absint( $_POST['samlab_kobling_id'] ) : 0;
	$handling   = isset( $_POST['samlab_handling'] ) ? sanitize_key( wp_unslash( $_POST['samlab_handling'] ) ) : '';
	$utfall     = isset( $_POST['samlab_utfall'] ) ? sanitize_key( wp_unslash( $_POST['samlab_utfall'] ) ) : '';
	$notat      = isset( $_POST['samlab_utfall_notat'] ) ? sanitize_text_field( wp_unslash( $_POST['samlab_utfall_notat'] ) ) : '';

	$resultat = samlab_kontrollpanel_utfor( $kobling_id, $handling, get_current_user_id(), $utfall, $notat );
	if ( is_wp_error( $resultat ) ) {
		wp_die( esc_html( $resultat->get_error_message() ), '', 403 );
	}

	wp_safe_redirect( add_query_arg( 'samlab_utfort', $handling, admin_url( 'admin.php?page=samlab-kontrollpanel' ) ) );
	exit;
}
add_action( 'admin_post_samlab_kobling', 'samlab_kontrollpanel_post' );

/**
 * Åpner et seksjonskort med overskrift.
 *
 * .postbox er core sin egen kort-idiom - stilene er alt lastet, så
 * Samlab-CSS-en trenger bare avstanden mellom kortene.
 *
 * @param string $id      Ankeret sammendraget hopper til.
 * @param string $tittel  Seksjonsoverskriften.
 * @return void
 */
function samlab_kp_kort( $id, $tittel ) {
	echo '<div class="postbox"><div class="inside">';
	echo '<h2 id="' . esc_attr( $id ) . '">' . esc_html( $tittel ) . '</h2>';
}

/**
 * Lukker et seksjonskort.
 *
 * @return void
 */
function samlab_kp_kort_slutt() {
	echo '</div></div>';
}

/**
 * Sammendragsraden: fire tall som hopper til hver sin seksjon.
 *
 * Tallene er navigasjon, ikke handlinger, så de er lenker og ikke
 * knapper. De kommer fra listene seksjonene allerede har hentet -
 * ingen nye spørringer.
 *
 * @param array<int, array{id: string, tall: int, etikett: string}> $tall Sammendraget.
 * @return void
 */
function samlab_kp_sammendrag( $tall ) {
	echo '<ul class="samlab-sammendrag" aria-label="' . esc_attr__( 'Sammendrag', 'samlab' ) . '">';
	foreach ( $tall as $rad ) {
		$vist = $rad['tall'] >= SAMLAB_KP_TAK ? SAMLAB_KP_TAK . '+' : (string) $rad['tall'];
		echo '<li><a href="#' . esc_attr( $rad['id'] ) . '">';
		echo '<span class="samlab-sammendrag-tall">' . esc_html( $vist ) . '</span>';
		echo '<span class="samlab-sammendrag-etikett">' . esc_html( $rad['etikett'] ) . '</span>';
		echo '</a></li>';
	}
	echo '</ul>';
}

/**
 * Skjemaknappene for en koblingsrad.
 *
 * @param int      $kobling_id Koblingen.
 * @param string[] $handlinger Handling-slug => knappetekst.
 * @param bool     $med_utfall Om utfallsvalg + notat skal med (G4).
 * @return void
 */
function samlab_kp_handlingsskjema( $kobling_id, $handlinger, $med_utfall = false ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="samlab-handlinger">';
	echo '<input type="hidden" name="action" value="samlab_kobling" />';
	echo '<input type="hidden" name="samlab_kobling_id" value="' . esc_attr( (string) $kobling_id ) . '" />';
	wp_nonce_field( 'samlab_kobling_handling', 'samlab_kp_nonce' );
	if ( $med_utfall ) {
		echo '<label class="screen-reader-text" for="samlab-utfall-' . esc_attr( (string) $kobling_id ) . '">' . esc_html__( 'Utfall', 'samlab' ) . '</label>';
		echo '<select id="samlab-utfall-' . esc_attr( (string) $kobling_id ) . '" name="samlab_utfall">';
		echo '<option value="">' . esc_html__( '- Utfall -', 'samlab' ) . '</option>';
		foreach ( samlab_kobling_utfall_typer() as $samlab_slug => $samlab_navn ) {
			echo '<option value="' . esc_attr( $samlab_slug ) . '">' . esc_html( $samlab_navn ) . '</option>';
		}
		echo '</select>';
		echo '<input type="text" name="samlab_utfall_notat" placeholder="' . esc_attr__( 'Notat (valgfritt)', 'samlab' ) . '" />';
	}
	foreach ( $handlinger as $slug => $tekst ) {
		$klasse = 'avvis' === $slug ? 'button button-link-delete' : 'button button-primary';
		echo '<button type="submit" class="' . esc_attr( $klasse ) . '" name="samlab_handling" value="' . esc_attr( $slug ) . '">' . esc_html( $tekst ) . '</button>';
	}
	echo '</form>';
}

/**
 * Rendrer kontrollpanelet.
 *
 * @return void
 */
function samlab_render_kontrollpanel() {
	if ( ! current_user_can( 'edit_samlab_koblinger' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Kun visning av bekreftelsesmeldinger.
	$utfort   = isset( $_GET['samlab_utfort'] ) ? sanitize_key( wp_unslash( $_GET['samlab_utfort'] ) ) : '';
	$ubesvart = isset( $_GET['samlab_ubesvart'] ) ? sanitize_key( wp_unslash( $_GET['samlab_ubesvart'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Samlab kontrollpanel', 'samlab' ) . '</h1>';
	echo '<hr class="wp-header-end" />';
	if ( '' !== $utfort ) {
		wp_admin_notice(
			esc_html__( 'Koblingen er oppdatert.', 'samlab' ),
			array(
				'type'        => 'success',
				'dismissible' => true,
			)
		);
	}
	if ( 'handtert' === $ubesvart ) {
		wp_admin_notice(
			esc_html__( 'Spørsmålet er fjernet fra køen.', 'samlab' ),
			array(
				'type'        => 'success',
				'dismissible' => true,
			)
		);
	}

	// Hentes én gang og gjenbrukes: sammendraget teller de samme
	// listene seksjonene under rendrer, uten en eneste ny spørring.
	$foreslatte    = samlab_kp_koblinger( array( 'foreslatt' ) );
	$forespurte    = samlab_kp_koblinger( array( 'forespurt' ) );
	$aktive        = samlab_kp_koblinger( array( 'godkjent', 'introdusert' ) );
	$fulgte        = samlab_kp_koblinger( array( 'fulgt_opp' ) );
	$nye           = samlab_kp_nye_uten_intro();
	$gamle         = samlab_kp_gamle_behov();
	$ufullstendige = samlab_kp_ufullstendige_bedrifter();
	$stille        = samlab_kp_stille_medlemmer();

	samlab_kp_sammendrag(
		array(
			array(
				'id'      => 'samlab-forslag',
				'tall'    => count( $foreslatte ),
				'etikett' => __( 'Forslag i køen', 'samlab' ),
			),
			array(
				'id'      => 'samlab-venter',
				'tall'    => count( $forespurte ),
				'etikett' => __( 'Venter på partene', 'samlab' ),
			),
			array(
				'id'      => 'samlab-aktive',
				'tall'    => count( $aktive ),
				'etikett' => __( 'Aktive koblinger', 'samlab' ),
			),
			array(
				'id'      => 'samlab-oppmerksomhet',
				'tall'    => count( $nye ) + count( $gamle ) + count( $ufullstendige ) + count( $stille ),
				'etikett' => __( 'Trenger oppmerksomhet', 'samlab' ),
			),
		)
	);

	// 1) Koblingskøen.
	samlab_kp_kort( 'samlab-forslag', __( 'Foreslåtte koblinger', 'samlab' ) );
	if ( array() === $foreslatte ) {
		echo '<p>' . esc_html__( 'Ingen forslag i køen.', 'samlab' ) . '</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Parter', 'samlab' ) . '</th><th scope="col">' . esc_html__( 'Begrunnelse', 'samlab' ) . '</th><th scope="col">' . esc_html__( 'Kilde', 'samlab' ) . '</th><th scope="col">' . esc_html__( 'Handling', 'samlab' ) . '</th></tr></thead><tbody>';
		foreach ( $foreslatte as $kobling ) {
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $kobling->ID ) ) . '">' . esc_html( samlab_kp_part_tekst( $kobling->ID ) ) . '</a></td>';
			echo '<td>' . esc_html( wp_trim_words( $kobling->post_content, 20 ) ) . '</td>';
			echo '<td>' . esc_html( 'matching' === get_post_meta( $kobling->ID, '_samlab_kilde', true ) ? __( 'Matchforslag', 'samlab' ) : __( 'Manuell', 'samlab' ) ) . '</td><td>';
			samlab_kp_handlingsskjema(
				$kobling->ID,
				array(
					'godkjenn' => __( 'Godkjenn og spør partene', 'samlab' ),
					'avvis'    => __( 'Avvis', 'samlab' ),
				)
			);
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	samlab_kp_kort_slutt();

	// 2) Forespurte koblinger som venter på partenes samtykke (G1).
	samlab_kp_kort( 'samlab-venter', __( 'Venter på partene', 'samlab' ) );
	if ( array() === $forespurte ) {
		echo '<p>' . esc_html__( 'Ingen forespørsler venter på svar.', 'samlab' ) . '</p>';
	} else {
		$samtykker = array(
			'venter' => __( 'venter', 'samlab' ),
			'ja'     => __( 'ja', 'samlab' ),
			'nei'    => __( 'nei', 'samlab' ),
		);
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Parter', 'samlab' ) . '</th><th scope="col">' . esc_html__( 'Samtykke', 'samlab' ) . '</th><th scope="col">' . esc_html__( 'Handling', 'samlab' ) . '</th></tr></thead><tbody>';
		foreach ( $forespurte as $kobling ) {
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $kobling->ID ) ) . '">' . esc_html( samlab_kp_part_tekst( $kobling->ID ) ) . '</a></td>';
			echo '<td>' . esc_html(
				sprintf(
					/* translators: 1: part A sitt samtykke, 2: part B sitt samtykke. */
					__( 'A: %1$s - B: %2$s', 'samlab' ),
					$samtykker[ samlab_kobling_samtykke( $kobling->ID, 'a' ) ],
					$samtykker[ samlab_kobling_samtykke( $kobling->ID, 'b' ) ]
				)
			) . '</td><td>';
			samlab_kp_handlingsskjema(
				$kobling->ID,
				array( 'avvis' => __( 'Trekk tilbake', 'samlab' ) )
			);
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	samlab_kp_kort_slutt();

	// 3) Aktive koblinger med statuskjeden.
	samlab_kp_kort( 'samlab-aktive', __( 'Aktive koblinger', 'samlab' ) );
	$statuser = samlab_kobling_statuser();
	if ( array() === $aktive ) {
		echo '<p>' . esc_html__( 'Ingen aktive koblinger.', 'samlab' ) . '</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Parter', 'samlab' ) . '</th><th scope="col">' . esc_html__( 'Status', 'samlab' ) . '</th><th scope="col">' . esc_html__( 'Neste steg', 'samlab' ) . '</th></tr></thead><tbody>';
		foreach ( $aktive as $kobling ) {
			$status = get_post_meta( $kobling->ID, '_samlab_status', true );
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $kobling->ID ) ) . '">' . esc_html( samlab_kp_part_tekst( $kobling->ID ) ) . '</a></td>';
			echo '<td>' . esc_html( isset( $statuser[ $status ] ) ? $statuser[ $status ] : $status ) . '</td><td>';
			if ( 'godkjent' === $status ) {
				samlab_kp_handlingsskjema( $kobling->ID, array( 'introdusert' => __( 'Marker introdusert', 'samlab' ) ) );
			} else {
				samlab_kp_handlingsskjema( $kobling->ID, array( 'fulgt_opp' => __( 'Marker fulgt opp', 'samlab' ) ), true );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	samlab_kp_kort_slutt();

	// 3b) Utfall på fulgte opp koblinger (G4).
	samlab_kp_kort( 'samlab-utfall', __( 'Utfall', 'samlab' ) );
	if ( array() === $fulgte ) {
		echo '<p>' . esc_html__( 'Ingen fulgte opp koblinger ennå.', 'samlab' ) . '</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Parter', 'samlab' ) . '</th><th scope="col">' . esc_html__( 'Utfall', 'samlab' ) . '</th><th scope="col">' . esc_html__( 'Notat', 'samlab' ) . '</th></tr></thead><tbody>';
		foreach ( $fulgte as $kobling ) {
			$utfall = samlab_kobling_utfall( $kobling->ID );
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $kobling->ID ) ) . '">' . esc_html( samlab_kp_part_tekst( $kobling->ID ) ) . '</a></td>';
			echo '<td>' . esc_html( $utfall ? $utfall['etikett'] : __( 'Ikke registrert', 'samlab' ) ) . '</td>';
			echo '<td>' . esc_html( $utfall ? $utfall['notat'] : '' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	samlab_kp_kort_slutt();

	// 4) Trenger oppmerksomhet - fire lister i et grid som brekker til
	// én kolonne på smale skjermer (WCAG 1.4.10).
	samlab_kp_kort( 'samlab-oppmerksomhet', __( 'Trenger oppmerksomhet', 'samlab' ) );
	echo '<div class="samlab-oppmerksomhet">';

	echo '<div class="samlab-oppmerksomhet-gruppe">';
	echo '<h3>' . esc_html__( 'Nye medlemmer uten introduksjon (siste 30 dager)', 'samlab' ) . '</h3><ul>';
	if ( array() === $nye ) {
		echo '<li>' . esc_html__( 'Ingen - alle nye er introdusert.', 'samlab' ) . '</li>';
	}
	foreach ( $nye as $bruker ) {
		echo '<li>' . esc_html( $bruker->display_name ) . ' <span class="description">(' . esc_html( gmdate( 'd.m.Y', strtotime( $bruker->user_registered ) ) ) . ')</span></li>';
	}
	echo '</ul></div>';

	echo '<div class="samlab-oppmerksomhet-gruppe">';
	echo '<h3>' . esc_html__( 'Åpne behov eldre enn 14 dager', 'samlab' ) . '</h3><ul>';
	if ( array() === $gamle ) {
		echo '<li>' . esc_html__( 'Ingen.', 'samlab' ) . '</li>';
	}
	foreach ( $gamle as $behov ) {
		echo '<li><a href="' . esc_url( get_edit_post_link( $behov->ID ) ) . '">' . esc_html( get_the_title( $behov ) ) . '</a> <span class="description">(' . esc_html( get_the_date( 'd.m.Y', $behov ) ) . ')</span></li>';
	}
	echo '</ul></div>';

	echo '<div class="samlab-oppmerksomhet-gruppe">';
	echo '<h3>' . esc_html__( 'Ufullstendige bedriftsprofiler', 'samlab' ) . '</h3><ul>';
	if ( array() === $ufullstendige ) {
		echo '<li>' . esc_html__( 'Ingen - alle profiler er komplette.', 'samlab' ) . '</li>';
	}
	foreach ( $ufullstendige as $rad ) {
		echo '<li><a href="' . esc_url( get_edit_post_link( $rad['bedrift']->ID ) ) . '">' . esc_html( get_the_title( $rad['bedrift'] ) ) . '</a>: ';
		/* translators: %s: kommaseparert liste over manglende felter. */
		echo esc_html( sprintf( __( 'mangler %s', 'samlab' ), implode( ', ', $rad['mangler'] ) ) ) . '</li>';
	}
	echo '</ul></div>';

	echo '<div class="samlab-oppmerksomhet-gruppe">';
	echo '<h3>' . esc_html__( 'Stille medlemmer (ingen aktivitet siste 30 dager)', 'samlab' ) . '</h3><ul>';
	if ( array() === $stille ) {
		echo '<li>' . esc_html__( 'Ingen.', 'samlab' ) . '</li>';
	}
	foreach ( $stille as $bruker ) {
		echo '<li>' . esc_html( $bruker->display_name ) . '</li>';
	}
	echo '</ul></div>';
	echo '</div>';
	samlab_kp_kort_slutt();

	// 5) Lesebekreftelser.
	samlab_kp_kort( 'samlab-lest', __( 'Lesebekreftelser', 'samlab' ) );
	$lesekrav = samlab_kp_lesebekreftelser();
	if ( array() === $lesekrav ) {
		echo '<p>' . esc_html__( 'Ingen oppslag krever lesebekreftelse. Fest et oppslag på veggen og velg «Krev lest».', 'samlab' ) . '</p>';
	}
	foreach ( $lesekrav as $rad ) {
		echo '<h3>' . esc_html( wp_html_excerpt( wp_strip_all_tags( $rad['innlegg']->content ), 80, '…' ) ) . '</h3>';
		echo '<p>';
		echo esc_html(
			sprintf(
				/* translators: 1: antall som har bekreftet, 2: antall medlemmer totalt. */
				__( '%1$d av %2$d har bekreftet.', 'samlab' ),
				count( $rad['bekreftet'] ),
				count( $rad['bekreftet'] ) + count( $rad['mangler'] )
			)
		);
		echo '</p><ul>';
		foreach ( $rad['bekreftet'] as $bruker ) {
			echo '<li>&#10003; ' . esc_html( $bruker->display_name ) . '</li>';
		}
		foreach ( $rad['mangler'] as $bruker ) {
			echo '<li>&#8211; ' . esc_html( $bruker->display_name ) . ' <span class="description">' . esc_html__( '(ikke bekreftet)', 'samlab' ) . '</span></li>';
		}
		echo '</ul>';
	}

	samlab_kp_kort_slutt();

	// 6) Ubesvarte spørsmål til assistenten (G7) - kun når modulen
	// (og dermed ubesvart-køen) er lastet.
	if ( function_exists( 'samlab_ubesvart_liste' ) ) {
		samlab_kp_kort( 'samlab-ubesvart', __( 'Ubesvarte spørsmål til assistenten', 'samlab' ) );
		$ubesvarte = samlab_ubesvart_liste();
		if ( array() === $ubesvarte ) {
			echo '<p>' . esc_html__( 'Ingen ubesvarte spørsmål - kunnskapsgrunnlaget holder.', 'samlab' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Anonyme spørsmål assistenten ikke fant svar på. Publiser svaret som håndbok-side - neste kunnskapsbygg tar den med, og assistenten kan svare.', 'samlab' ) . '</p>';
			echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Spørsmål', 'samlab' ) . '</th><th scope="col">' . esc_html__( 'Antall', 'samlab' ) . '</th><th scope="col">' . esc_html__( 'Sist spurt', 'samlab' ) . '</th><th scope="col">' . esc_html__( 'Handling', 'samlab' ) . '</th></tr></thead><tbody>';
			foreach ( $ubesvarte as $rad ) {
				echo '<tr><td>' . esc_html( $rad['sporsmal'] ) . '</td>';
				echo '<td>' . esc_html( (string) (int) $rad['antall'] ) . '</td>';
				echo '<td>' . esc_html( $rad['dato'] ) . '</td><td>';
				foreach ( array(
					'samlab_ubesvart_handbok'  => array( __( 'Legg i håndboken', 'samlab' ), 'button button-primary', current_user_can( 'edit_pages' ) ),
					'samlab_ubesvart_handtert' => array( __( 'Håndtert', 'samlab' ), 'button', true ),
				) as $samlab_handling => $samlab_knapp ) {
					if ( ! $samlab_knapp[2] ) {
						continue;
					}
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="samlab-handlinger">';
					echo '<input type="hidden" name="action" value="' . esc_attr( $samlab_handling ) . '" />';
					echo '<input type="hidden" name="samlab_sporsmal" value="' . esc_attr( $rad['sporsmal'] ) . '" />';
					wp_nonce_field( 'samlab_ubesvart_handling', 'samlab_ubesvart_nonce' );
					echo '<button type="submit" class="' . esc_attr( $samlab_knapp[1] ) . '">' . esc_html( $samlab_knapp[0] ) . '</button>';
					echo '</form>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		samlab_kp_kort_slutt();
	}
	echo '</div>';
}
