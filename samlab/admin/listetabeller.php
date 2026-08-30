<?php
/**
 * Kolonner, filtre og visninger på Samlabs egne listetabeller i
 * wp-admin.
 *
 * Alt går gjennom core sine egne kroker - manage_{type}_posts_columns,
 * restrict_manage_posts, pre_get_posts, views_edit_{type}. Vi innfører
 * bevisst ingen Samlab-egne filtre oppå: core gir allerede enhver vert
 * de samme krokene gratis, og et filter til ville duplisert en
 * core-krok og skapt en API-forpliktelse uten gevinst.
 *
 * Kolonnene gjenbruker funksjonene flatene allerede bruker
 * (samlab_kp_part_tekst, samlab_kobling_samtykke,
 * samlab_arrangement_tid_visning, samlab_bedrift_mangler) - ingen ny
 * logikk, ingen andre svar enn resten av pluginen gir.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setter inn kolonner rett før datokolonnen.
 *
 * @param array<string, string> $kolonner Kolonnene fra core.
 * @param array<string, string> $nye      Kolonnene som skal inn.
 * @return array<string, string>
 */
function samlab_liste_kolonner_for_dato( $kolonner, $nye ) {
	$ut = array();
	foreach ( $kolonner as $nokkel => $etikett ) {
		if ( 'date' === $nokkel ) {
			$ut = array_merge( $ut, $nye );
		}
		$ut[ $nokkel ] = $etikett;
	}
	// Ingen datokolonne (kan skje om noen filtrerer den bort) - legg til slutt.
	return array_merge( $ut, array_diff_key( $nye, $ut ) );
}

/**
 * Leser en filterverdi fra listetabellens GET-parametre.
 *
 * Listetabeller filtreres via lenker og et skjema uten nonce - det er
 * slik core selv gjør det, og verdien brukes kun til å avgrense en
 * visning, aldri til å endre noe.
 *
 * @param string $navn Parameternavnet.
 * @return string Sanitert verdi, eller tom streng.
 */
function samlab_liste_filter( $navn ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Kun avgrensning av en visning.
	return isset( $_GET[ $navn ] ) ? sanitize_key( wp_unslash( $_GET[ $navn ] ) ) : '';
}

/**
 * Om denne forespørselen er hovedspørringen på en gitt listetabell.
 *
 * Vakten er ikke pynt: en feilgatet pre_get_posts endrer spørringer
 * stille over hele nettstedet.
 *
 * @param WP_Query $query     Spørringen.
 * @param string   $post_type Post-typen listen gjelder.
 * @return bool
 */
function samlab_liste_er_hovedliste( $query, $post_type ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return false;
	}
	return $post_type === $query->get( 'post_type' );
}

// --- Bedrifter ---

/**
 * Kolonner på bedriftslisten.
 *
 * Kategorikolonnen kommer allerede fra taksonomiens show_admin_column.
 *
 * @param array<string, string> $kolonner Kolonnene.
 * @return array<string, string>
 */
function samlab_bedrift_kolonner( $kolonner ) {
	return samlab_liste_kolonner_for_dato(
		$kolonner,
		array(
			'samlab_kontakt'  => __( 'Kontaktperson', 'samlab' ),
			'samlab_komplett' => __( 'Profil', 'samlab' ),
		)
	);
}
add_filter( 'manage_samlab_bedrift_posts_columns', 'samlab_bedrift_kolonner' );

/**
 * Innholdet i bedriftskolonnene.
 *
 * @param string $kolonne Kolonnenøkkelen.
 * @param int    $post_id Bedriften.
 * @return void
 */
function samlab_bedrift_kolonne_innhold( $kolonne, $post_id ) {
	if ( 'samlab_kontakt' === $kolonne ) {
		$bruker = get_userdata( (int) get_post_meta( $post_id, '_samlab_kontaktperson', true ) );
		echo $bruker ? esc_html( $bruker->display_name ) : '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__( 'Ingen kontaktperson', 'samlab' ) . '</span>';
		return;
	}
	if ( 'samlab_komplett' === $kolonne ) {
		$mangler = samlab_bedrift_mangler( $post_id );
		if ( array() === $mangler ) {
			echo esc_html__( 'Komplett', 'samlab' );
			return;
		}
		/* translators: %s: kommaseparert liste over det som mangler. */
		echo esc_html( sprintf( __( 'Mangler %s', 'samlab' ), implode( ', ', $mangler ) ) );
	}
}
add_action( 'manage_samlab_bedrift_posts_custom_column', 'samlab_bedrift_kolonne_innhold', 10, 2 );

// --- Behov og tilbud ---

/**
 * Kolonner på behovslisten.
 *
 * Retning og behovstype kommer fra taksonomienes show_admin_column.
 *
 * @param array<string, string> $kolonner Kolonnene.
 * @return array<string, string>
 */
function samlab_behov_kolonner( $kolonner ) {
	return samlab_liste_kolonner_for_dato(
		$kolonner,
		array(
			'samlab_bedrift' => __( 'Bedrift', 'samlab' ),
			'samlab_frist'   => __( 'Frist', 'samlab' ),
		)
	);
}
add_filter( 'manage_samlab_behov_posts_columns', 'samlab_behov_kolonner' );

/**
 * Innholdet i behovskolonnene.
 *
 * Fristen gjøres bevisst ikke sorterbar: feltet er fritekst, ikke en
 * dato, og sortering ville lovet en rekkefølge dataene ikke kan levere.
 *
 * @param string $kolonne Kolonnenøkkelen.
 * @param int    $post_id Behovet.
 * @return void
 */
function samlab_behov_kolonne_innhold( $kolonne, $post_id ) {
	if ( 'samlab_bedrift' === $kolonne ) {
		$bedrift = get_post( (int) get_post_meta( $post_id, '_samlab_bedrift', true ) );
		if ( ! $bedrift ) {
			echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__( 'Ingen bedrift', 'samlab' ) . '</span>';
			return;
		}
		echo '<a href="' . esc_url( (string) get_edit_post_link( $bedrift->ID ) ) . '">' . esc_html( get_the_title( $bedrift ) ) . '</a>';
		return;
	}
	if ( 'samlab_frist' === $kolonne ) {
		$frist = (string) get_post_meta( $post_id, '_samlab_frist', true );
		echo '' !== $frist ? esc_html( $frist ) : '<span aria-hidden="true">&#8212;</span>';
	}
}
add_action( 'manage_samlab_behov_posts_custom_column', 'samlab_behov_kolonne_innhold', 10, 2 );

// --- Koblinger ---

/**
 * Kolonner på koblingslisten.
 *
 * Tittelen beholder plassen som primærkolonne selv om partene er mer
 * meningsbærende - primærkolonnen er den som bærer radhandlingene.
 *
 * @param array<string, string> $kolonner Kolonnene.
 * @return array<string, string>
 */
function samlab_kobling_kolonner( $kolonner ) {
	return samlab_liste_kolonner_for_dato(
		$kolonner,
		array(
			'samlab_parter'   => __( 'Parter', 'samlab' ),
			'samlab_status'   => __( 'Status', 'samlab' ),
			'samlab_samtykke' => __( 'Samtykke', 'samlab' ),
			'samlab_utfall'   => __( 'Utfall', 'samlab' ),
		)
	);
}
add_filter( 'manage_samlab_kobling_posts_columns', 'samlab_kobling_kolonner' );

/**
 * Innholdet i koblingskolonnene.
 *
 * @param string $kolonne Kolonnenøkkelen.
 * @param int    $post_id Koblingen.
 * @return void
 */
function samlab_kobling_kolonne_innhold( $kolonne, $post_id ) {
	if ( 'samlab_parter' === $kolonne ) {
		echo esc_html( samlab_kp_part_tekst( $post_id ) );
		return;
	}
	if ( 'samlab_status' === $kolonne ) {
		$status   = (string) get_post_meta( $post_id, '_samlab_status', true );
		$statuser = samlab_kobling_statuser();
		echo esc_html( isset( $statuser[ $status ] ) ? $statuser[ $status ] : $status );
		return;
	}
	if ( 'samlab_samtykke' === $kolonne ) {
		$etiketter = array(
			'venter' => __( 'venter', 'samlab' ),
			'ja'     => __( 'ja', 'samlab' ),
			'nei'    => __( 'nei', 'samlab' ),
		);
		$a         = samlab_kobling_samtykke( $post_id, 'a' );
		$b         = samlab_kobling_samtykke( $post_id, 'b' );
		echo esc_html(
			sprintf(
				/* translators: 1: part A sitt samtykke, 2: part B sitt samtykke. */
				__( 'A: %1$s - B: %2$s', 'samlab' ),
				isset( $etiketter[ $a ] ) ? $etiketter[ $a ] : $a,
				isset( $etiketter[ $b ] ) ? $etiketter[ $b ] : $b
			)
		);
		return;
	}
	if ( 'samlab_utfall' === $kolonne ) {
		$utfall = samlab_kobling_utfall( $post_id );
		echo $utfall ? esc_html( $utfall['etikett'] ) : '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__( 'Ikke registrert', 'samlab' ) . '</span>';
	}
}
add_action( 'manage_samlab_kobling_posts_custom_column', 'samlab_kobling_kolonne_innhold', 10, 2 );

/**
 * Post-statusene listetabellen viser når ingen status er valgt.
 *
 * Dette er kjernens eget «alle»-utvalg: publisert, planlagt, utkast,
 * til gjennomsyn og privat - alt unntatt papirkurv og auto-utkast.
 *
 * @return string[]
 */
function samlab_liste_synlige_statuser() {
	$statuser = get_post_stati( array( 'show_in_admin_all_list' => true ) );
	return array() !== $statuser ? array_values( $statuser ) : array( 'publish' );
}

/**
 * Antall koblinger per status, i én gruppert spørring.
 *
 * Seks WP_Query-kall for å telle seks statuser ville vært sløsing på en
 * side som allerede kjører mange spørringer.
 *
 * Tellingen må dekke nøyaktig de post-statusene lenken bak tallet
 * faktisk viser. Talte vi bare publiserte, ville et utkast med samme
 * status gi «Foreslått (1)» over en liste med to rader - tallet og
 * listen ville sagt hver sin ting om samme sett.
 *
 * @return array<string, int> Status-slug => antall.
 */
function samlab_kobling_statusantall() {
	global $wpdb;

	$statuser = samlab_liste_synlige_statuser();

	// Antallet statuser er ikke kjent på skrivetidspunktet, så IN-listen
	// får én %s per status. Det er kun plassholderne som settes sammen
	// her - hver eneste verdi går som argument til prepare(), aldri inn
	// i SQL-strengen. Sniffene under kan ikke se det: den ene teller
	// argumenter (og ser én array framfor N verdier), den andre reagerer
	// på at variabelen $plassholdere står i strengen.
	$plassholdere = implode( ', ', array_fill( 0, count( $statuser ), '%s' ) );
	$argumenter   = array_merge( array( '_samlab_status', 'samlab_kobling' ), $statuser );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rader = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT pm.meta_value AS status, COUNT(*) AS antall
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status IN ( {$plassholdere} )
			GROUP BY pm.meta_value",
			$argumenter
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	$antall = array();
	foreach ( (array) $rader as $rad ) {
		$antall[ (string) $rad->status ] = (int) $rad->antall;
	}
	return $antall;
}

/**
 * Visningslenkene over koblingslisten - én per status, med antall.
 *
 * @param array<string, string> $visninger Visningene fra core.
 * @return array<string, string>
 */
function samlab_kobling_visninger( $visninger ) {
	$antall = samlab_kobling_statusantall();
	$valgt  = samlab_liste_filter( 'samlab_status' );

	foreach ( samlab_kobling_statuser() as $slug => $navn ) {
		if ( empty( $antall[ $slug ] ) ) {
			continue;
		}
		$url                            = add_query_arg(
			array(
				'post_type'     => 'samlab_kobling',
				'samlab_status' => $slug,
			),
			admin_url( 'edit.php' )
		);
		$visninger[ 'samlab_' . $slug ] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
			esc_url( $url ),
			$valgt === $slug ? ' class="current" aria-current="page"' : '',
			esc_html( $navn ),
			(int) $antall[ $slug ]
		);
	}
	return $visninger;
}
add_filter( 'views_edit-samlab_kobling', 'samlab_kobling_visninger' );

// --- Arrangementer ---

/**
 * Kolonner på arrangementslisten.
 *
 * @param array<string, string> $kolonner Kolonnene.
 * @return array<string, string>
 */
function samlab_arrangement_kolonner( $kolonner ) {
	return samlab_liste_kolonner_for_dato(
		$kolonner,
		array(
			'samlab_tid'      => __( 'Tid', 'samlab' ),
			'samlab_sted'     => __( 'Sted', 'samlab' ),
			'samlab_arrangor' => __( 'Arrangør', 'samlab' ),
		)
	);
}
add_filter( 'manage_samlab_arrangement_posts_columns', 'samlab_arrangement_kolonner' );

/**
 * Innholdet i arrangementskolonnene.
 *
 * @param string $kolonne Kolonnenøkkelen.
 * @param int    $post_id Arrangementet.
 * @return void
 */
function samlab_arrangement_kolonne_innhold( $kolonne, $post_id ) {
	if ( 'samlab_tid' === $kolonne ) {
		$tid = samlab_arrangement_tid_visning( $post_id );
		echo '' !== $tid ? esc_html( $tid ) : '<span aria-hidden="true">&#8212;</span>';
		return;
	}
	if ( 'samlab_sted' === $kolonne ) {
		$sted = (string) get_post_meta( $post_id, '_samlab_sted', true );
		echo '' !== $sted ? esc_html( $sted ) : '<span aria-hidden="true">&#8212;</span>';
		return;
	}
	if ( 'samlab_arrangor' === $kolonne ) {
		$bedrift = get_post( (int) get_post_meta( $post_id, '_samlab_bedrift', true ) );
		echo $bedrift ? esc_html( get_the_title( $bedrift ) ) : esc_html__( 'Huset', 'samlab' );
	}
}
add_action( 'manage_samlab_arrangement_posts_custom_column', 'samlab_arrangement_kolonne_innhold', 10, 2 );

/**
 * Tidskolonnen er sorterbar.
 *
 * _samlab_start lagres som «Y-m-d H:i», som sorterer leksikografisk
 * riktig - det er nettopp derfor formatet ble valgt.
 *
 * @param array<string, string> $kolonner Sorterbare kolonner.
 * @return array<string, string>
 */
function samlab_arrangement_sorterbare( $kolonner ) {
	$kolonner['samlab_tid'] = 'samlab_tid';
	return $kolonner;
}
add_filter( 'manage_edit-samlab_arrangement_sortable_columns', 'samlab_arrangement_sorterbare' );

// --- Filtrering og sortering ---

/**
 * Nedtrekksfiltrene over listetabellene.
 *
 * Kategorifilteret på bedrifter håndteres av core selv: edit.php leser
 * taksonomiens query-var, så det trengs ingen pre_get_posts for det.
 *
 * @param string $post_type Post-typen listen viser.
 * @return void
 */
function samlab_liste_filtre( $post_type ) {
	if ( 'samlab_bedrift' === $post_type ) {
		wp_dropdown_categories(
			array(
				'taxonomy'        => 'samlab_kategori',
				'name'            => 'samlab_kategori',
				'value_field'     => 'slug',
				'show_option_all' => __( 'Alle kategorier', 'samlab' ),
				'selected'        => samlab_liste_filter( 'samlab_kategori' ),
				'hide_empty'      => false,
				'hierarchical'    => true,
			)
		);
		return;
	}

	if ( 'samlab_kobling' === $post_type ) {
		$valgt_status = samlab_liste_filter( 'samlab_status' );
		echo '<label class="screen-reader-text" for="samlab_status">' . esc_html__( 'Filtrer på status', 'samlab' ) . '</label>';
		echo '<select name="samlab_status" id="samlab_status">';
		echo '<option value="">' . esc_html__( 'Alle statuser', 'samlab' ) . '</option>';
		foreach ( samlab_kobling_statuser() as $slug => $navn ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $valgt_status, $slug, false ) . '>' . esc_html( $navn ) . '</option>';
		}
		echo '</select>';

		$valgt_utfall = samlab_liste_filter( 'samlab_utfall' );
		echo '<label class="screen-reader-text" for="samlab_utfall">' . esc_html__( 'Filtrer på utfall', 'samlab' ) . '</label>';
		echo '<select name="samlab_utfall" id="samlab_utfall">';
		echo '<option value="">' . esc_html__( 'Alle utfall', 'samlab' ) . '</option>';
		foreach ( samlab_kobling_utfall_typer() as $slug => $navn ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $valgt_utfall, $slug, false ) . '>' . esc_html( $navn ) . '</option>';
		}
		echo '</select>';
	}
}
add_action( 'restrict_manage_posts', 'samlab_liste_filtre' );

/**
 * Setter meta-filtrene og sorteringen på listetabellene.
 *
 * @param WP_Query $query Spørringen.
 * @return void
 */
function samlab_liste_query( $query ) {
	if ( samlab_liste_er_hovedliste( $query, 'samlab_kobling' ) ) {
		$meta = array();
		foreach ( array(
			'samlab_status' => '_samlab_status',
			'samlab_utfall' => '_samlab_utfall',
		) as $param => $nokkel ) {
			$verdi = samlab_liste_filter( $param );
			if ( '' !== $verdi ) {
				$meta[] = array(
					'key'   => $nokkel,
					'value' => $verdi,
				);
			}
		}
		if ( array() !== $meta ) {
			$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Redaktørstyrt filtrering av en admin-liste.
		}
		return;
	}

	if ( samlab_liste_er_hovedliste( $query, 'samlab_arrangement' ) && 'samlab_tid' === $query->get( 'orderby' ) ) {
		$query->set( 'meta_key', '_samlab_start' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sortering på en admin-liste.
		$query->set( 'orderby', 'meta_value' );
	}
}
add_action( 'pre_get_posts', 'samlab_liste_query' );

// --- Radhandlinger ---

/**
 * «Se i portalen» på bedriftsrader.
 *
 * @param array<string, string> $handlinger Radhandlingene.
 * @param WP_Post               $post       Raden.
 * @return array<string, string>
 */
function samlab_liste_radhandlinger( $handlinger, $post ) {
	if ( 'samlab_bedrift' !== $post->post_type || 'publish' !== $post->post_status ) {
		return $handlinger;
	}
	$handlinger['samlab_portal'] = sprintf(
		'<a href="%1$s">%2$s</a>',
		esc_url( samlab_portal_url( 'bedrifter', $post->post_name ) ),
		esc_html__( 'Se i portalen', 'samlab' )
	);
	return $handlinger;
}
add_filter( 'post_row_actions', 'samlab_liste_radhandlinger', 10, 2 );
