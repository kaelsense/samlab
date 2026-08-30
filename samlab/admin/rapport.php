<?php
/**
 * Rapporten: aggregerte tall for fasiliteringen (G5, deckets slide
 * 7) - undermeny under kontrollpanelet, samme capability.
 *
 * Kun aggregater: rapporten lister aldri hvem som gjorde hva, og
 * det finnes ikke noe beløpsfelt. Tidsgrunnlaget er statusloggene
 * på koblingene og egentabellenes created_at - ingen nye tabeller.
 * Gårdeier-metrikkene fra decket (fornyelsesgrad, frafall,
 * lokalbruk) er utenfor pluginens datagrunnlag - se avklaring 8 i
 * AVKLARINGER.md.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Periodene rapporten kan vise, i dager.
 *
 * @return int[]
 */
function samlab_rapport_perioder() {
	return array( 30, 90, 365 );
}

/**
 * Etiketter for måltallene, i visningsrekkefølge.
 *
 * @return array<string, string> Nøkkel => etikett.
 */
function samlab_rapport_etiketter() {
	$etiketter = array(
		'nye_behov'    => __( 'Nye behov og tilbud', 'samlab' ),
		'matchforslag' => __( 'Matchforslag', 'samlab' ),
		'forespurte'   => __( 'Forespurte koblinger', 'samlab' ),
		'godkjente'    => __( 'Godkjente koblinger', 'samlab' ),
		'avviste'      => __( 'Avviste koblinger', 'samlab' ),
		'introduserte' => __( 'Introduserte koblinger', 'samlab' ),
	);
	foreach ( samlab_kobling_utfall_typer() as $slug => $navn ) {
		/* translators: %s: utfallets etikett. */
		$etiketter[ 'utfall_' . $slug ] = sprintf( __( 'Utfall: %s', 'samlab' ), $navn );
	}
	$etiketter['arrangementer_avholdt'] = __( 'Arrangementer avholdt', 'samlab' );
	$etiketter['aktive_medlemmer']      = __( 'Aktive medlemmer', 'samlab' );
	return $etiketter;
}

/**
 * Varmer meta-cachen for en liste post-ID-er.
 *
 * Rapporten henter poster med «fields => ids» fordi den kun trenger
 * ID-ene til å lese meta. Da primer ikke WP_Query meta-cachen, og hver
 * get_post_meta() blir sin egen spørring. Én update_meta_cache() foran
 * løkken gjør N spørringer til én.
 *
 * @param int[] $ids Post-ID-ene løkken skal lese meta fra.
 * @return void
 */
function samlab_rapport_prim_meta( $ids ) {
	if ( array() === $ids ) {
		return;
	}
	update_meta_cache( 'post', $ids );
}

/**
 * Rapporttallene for en periode. Koblingstallene teller hendelser i
 * statusloggene (ikke dagens status), så en kobling som ble både
 * forespurt og godkjent i perioden telles i begge rader.
 *
 * @param int $dager Periode bakover i tid.
 * @return array<string, int> Nøkkel (se etikettene) => antall.
 */
function samlab_rapport_tall( $dager ) {
	global $wpdb;
	$dager  = max( 1, (int) $dager );
	$grense = time() - $dager * DAY_IN_SECONDS;
	$dato   = gmdate( 'Y-m-d H:i:s', $grense );

	$tall = array_fill_keys( array_keys( samlab_rapport_etiketter() ), 0 );

	$tall['nye_behov'] = count(
		get_posts(
			array(
				'post_type'      => 'samlab_behov',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'after'  => $dato,
						'column' => 'post_date_gmt',
					),
				),
			)
		)
	);

	// Koblingshendelser fra statusloggene.
	$hendelsesmap = array(
		'forespurt'   => 'forespurte',
		'godkjent'    => 'godkjente',
		'avvist'      => 'avviste',
		'introdusert' => 'introduserte',
	);
	$koblinger    = get_posts(
		array(
			'post_type'      => 'samlab_kobling',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	// «fields => ids» hopper over meta-primingen WP_Query ellers gjør,
	// så uten denne linjen koster løkken under én spørring per kobling.
	// Målt: 206 koblinger ga 206 spørringer uten, 1 med.
	samlab_rapport_prim_meta( $koblinger );
	foreach ( $koblinger as $kobling_id ) {
		$matching = 'matching' === get_post_meta( $kobling_id, '_samlab_kilde', true );
		$logg     = get_post_meta( $kobling_id, '_samlab_statuslogg', true );
		foreach ( is_array( $logg ) ? $logg : array() as $rad ) {
			if ( ! isset( $rad['status'], $rad['tid'] ) || (int) strtotime( $rad['tid'] . ' UTC' ) < $grense ) {
				continue;
			}
			if ( isset( $hendelsesmap[ $rad['status'] ] ) ) {
				++$tall[ $hendelsesmap[ $rad['status'] ] ];
			} elseif ( 'foreslatt' === $rad['status'] && $matching ) {
				++$tall['matchforslag'];
			} elseif ( isset( $tall[ $rad['status'] ] ) && 0 === strpos( $rad['status'], 'utfall_' ) ) {
				++$tall[ $rad['status'] ];
			}
		}
	}

	// Arrangementer avholdt: start i perioden og tilbakelagt.
	$arrangementer = get_posts(
		array(
			'post_type'      => 'samlab_arrangement',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	samlab_rapport_prim_meta( $arrangementer );
	foreach ( $arrangementer as $arrangement_id ) {
		$start = (int) strtotime( (string) get_post_meta( $arrangement_id, '_samlab_start', true ) );
		if ( $start && $start <= time() && $start >= $grense ) {
			++$tall['arrangementer_avholdt'];
		}
	}

	// Aktive medlemmer: minst én registrert hendelse i perioden -
	// innlegg, kommentar, reaksjon (inkl. lesebekreftelser) eller
	// stemme. Kun antallet - aldri hvem.
	$innlegg                  = samlab_table( 'innlegg' );
	$reaksjoner               = samlab_table( 'reaksjoner' );
	$stemmer                  = samlab_table( 'stemmer' );
	$aktive                   = array_map(
		'intval',
		array_merge(
			$wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$innlegg} WHERE created_at >= %s", $dato ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Egen tabell, navn fra samlab_table().
			$wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$reaksjoner} WHERE created_at >= %s", $dato ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Egen tabell, navn fra samlab_table().
			$wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$stemmer} WHERE created_at >= %s", $dato ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Egen tabell, navn fra samlab_table().
			$wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->comments} WHERE comment_type = 'samlab_innlegg' AND comment_date_gmt >= %s", $dato ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Enkel rapportspørring.
		)
	);
	$tall['aktive_medlemmer'] = count( array_unique( array_filter( $aktive ) ) );

	return $tall;
}

/**
 * Lesebekreftelsesgraden på dagens festede oppslag med lest-krav, i
 * prosent - et nå-bilde, uavhengig av rapportperioden.
 *
 * @return int|null Prosent, eller null uten krav-oppslag.
 */
function samlab_rapport_lesegrad() {
	$bekreftet = 0;
	$totalt    = 0;
	// Eget, høyere tak enn kontrollpanelets: dette er et måltall, ikke
	// en arbeidsliste, og skal ikke endre seg fordi dashbordet ble
	// trimmet.
	foreach ( samlab_kp_lesebekreftelser( SAMLAB_RAPPORT_LESEKRAV ) as $rad ) {
		$bekreftet += count( $rad['bekreftet'] );
		$totalt    += count( $rad['bekreftet'] ) + count( $rad['mangler'] );
	}
	return $totalt > 0 ? (int) round( 100 * $bekreftet / $totalt ) : null;
}

/**
 * Rapporten som CSV - alle tre periodene i samme fil, semikolon som
 * skilletegn (norske regneark-oppsett).
 *
 * @return string CSV-teksten, uten BOM.
 */
function samlab_rapport_csv_tekst() {
	$perioder = samlab_rapport_perioder();
	$tall     = array();
	foreach ( $perioder as $dager ) {
		$tall[ $dager ] = samlab_rapport_tall( $dager );
	}

	$linjer = array();
	$hode   = array( __( 'Måltall', 'samlab' ) );
	foreach ( $perioder as $dager ) {
		/* translators: %d: antall dager. */
		$hode[] = sprintf( __( 'Siste %d dager', 'samlab' ), $dager );
	}
	$linjer[] = $hode;

	foreach ( samlab_rapport_etiketter() as $nokkel => $etikett ) {
		$rad = array( $etikett );
		foreach ( $perioder as $dager ) {
			$rad[] = (string) $tall[ $dager ][ $nokkel ];
		}
		$linjer[] = $rad;
	}

	$lesegrad = samlab_rapport_lesegrad();
	$rad      = array( __( 'Lesebekreftelsesgrad nå (%)', 'samlab' ) );
	foreach ( $perioder as $dager ) {
		$rad[] = null === $lesegrad ? '' : (string) $lesegrad;
	}
	$linjer[] = $rad;

	$ut = '';
	foreach ( $linjer as $linje ) {
		$felt = array();
		foreach ( $linje as $verdi ) {
			$felt[] = '"' . str_replace( '"', '""', $verdi ) . '"';
		}
		$ut .= implode( ';', $felt ) . "\r\n";
	}
	return $ut;
}

/**
 * Registrerer rapportsiden under kontrollpanelet.
 *
 * @return void
 */
function samlab_rapport_menu() {
	samlab_admin_skjermer(
		add_submenu_page(
			'samlab-kontrollpanel',
			__( 'Samlab rapport', 'samlab' ),
			__( 'Rapport', 'samlab' ),
			'edit_samlab_koblinger',
			'samlab-rapport',
			'samlab_render_rapport'
		)
	);
}
add_action( 'admin_menu', 'samlab_rapport_menu' );

/**
 * Rendrer rapportsiden.
 *
 * @return void
 */
function samlab_render_rapport() {
	if ( ! current_user_can( 'edit_samlab_koblinger' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Kun periodevalg for visning.
	$dager = isset( $_GET['dager'] ) ? absint( $_GET['dager'] ) : 90;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ( ! in_array( $dager, samlab_rapport_perioder(), true ) ) {
		$dager = 90;
	}
	$tall     = samlab_rapport_tall( $dager );
	$lesegrad = samlab_rapport_lesegrad();

	echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Samlab rapport', 'samlab' ) . '</h1>';
	echo '<hr class="wp-header-end" />';
	echo '<p>' . esc_html__( 'Hva fasiliteringen skaper - aggregert, aldri hvem som gjorde hva, og aldri beløp.', 'samlab' ) . '</p>';

	// .subsubsub er core sin egen rad for gjensidig utelukkende
	// visninger av samme datasett - «Alle | Publisert | Utkast». Det er
	// semantisk dette. .nav-tab-wrapper er for å bytte mellom ULIKE
	// skjermer, og hører ikke hjemme her.
	$perioder = samlab_rapport_perioder();
	$siste    = count( $perioder ) - 1;
	echo '<ul class="subsubsub samlab-perioder">';
	foreach ( $perioder as $samlab_i => $samlab_periode ) {
		$url = add_query_arg(
			array(
				'page'  => 'samlab-rapport',
				'dager' => $samlab_periode,
			),
			admin_url( 'admin.php' )
		);
		/* translators: %d: antall dager. */
		$tekst  = sprintf( __( 'Siste %d dager', 'samlab' ), $samlab_periode );
		$aktiv  = $samlab_periode === $dager;
		$skille = $samlab_i < $siste ? ' | ' : '';
		echo '<li><a href="' . esc_url( $url ) . '"' . ( $aktiv ? ' class="current" aria-current="page"' : '' ) . '>';
		echo esc_html( $tekst ) . '</a>' . esc_html( $skille ) . '</li>';
	}
	echo '</ul>';
	// .subsubsub er float: left i core, og core tømmer den selv med
	// <br class="clear" /> (class-wp-list-table.php). Uten den blir
	// sammendragsraden under klemt ned til null bredde.
	echo '<br class="clear" />';

	$etiketter = samlab_rapport_etiketter();
	samlab_admin_sammendrag(
		array(
			array(
				'tall'    => (int) $tall['nye_behov'],
				'etikett' => $etiketter['nye_behov'],
			),
			array(
				'tall'    => (int) $tall['forespurte'],
				'etikett' => $etiketter['forespurte'],
			),
			array(
				'tall'    => (int) $tall['introduserte'],
				'etikett' => $etiketter['introduserte'],
			),
			array(
				'tall'    => (int) $tall['aktive_medlemmer'],
				'etikett' => $etiketter['aktive_medlemmer'],
			),
		)
	);

	samlab_admin_tabellramme( __( 'Måltall', 'samlab' ) );
	echo '<table class="widefat striped samlab-tabell-smal"><thead><tr><th scope="col">' . esc_html__( 'Måltall', 'samlab' ) . '</th><th scope="col" class="samlab-tallkolonne">' . esc_html__( 'Antall', 'samlab' ) . '</th></tr></thead><tbody>';
	foreach ( $etiketter as $nokkel => $etikett ) {
		echo '<tr><td>' . esc_html( $etikett ) . '</td><td class="samlab-tallkolonne">' . esc_html( (string) $tall[ $nokkel ] ) . '</td></tr>';
	}
	echo '<tr><td>' . esc_html__( 'Lesebekreftelsesgrad nå', 'samlab' ) . '</td><td class="samlab-tallkolonne">';
	echo esc_html( null === $lesegrad ? __( 'Ingen krav-oppslag', 'samlab' ) : $lesegrad . ' %' );
	echo '</td></tr>';
	echo '</tbody></table>';
	samlab_admin_tabellramme_slutt();

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="samlab-toppmargin">';
	echo '<input type="hidden" name="action" value="samlab_rapport_csv" />';
	wp_nonce_field( 'samlab_rapport_csv', 'samlab_rapport_nonce' );
	echo '<button type="submit" class="button">' . esc_html__( 'Last ned som CSV (alle periodene)', 'samlab' ) . '</button>';
	echo '</form>';

	echo '<p class="description samlab-lesebredde samlab-toppmargin">';
	echo esc_html__( 'Koblingstallene teller hendelser i perioden (en kobling kan telle i flere rader). Lesebekreftelsesgraden er et nå-bilde av dagens festede oppslag med lest-krav. Fornyelsesgrad, frafall og bruk av lokaler bor i drifts- og leiesystemene og er utenfor portalens datagrunnlag - se avklaring 8 i AVKLARINGER.md.', 'samlab' );
	echo '</p></div>';
}

/**
 * Mottak fra admin-post.php for CSV-eksporten.
 *
 * @return void
 */
function samlab_rapport_csv_handler() {
	$nonce = isset( $_POST['samlab_rapport_nonce'] ) ? sanitize_key( wp_unslash( $_POST['samlab_rapport_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'samlab_rapport_csv' ) ) {
		wp_die( esc_html__( 'Ugyldig eller utløpt skjema - gå tilbake og prøv igjen.', 'samlab' ), '', 403 );
	}
	if ( ! current_user_can( 'edit_samlab_koblinger' ) ) {
		wp_die( esc_html__( 'Du har ikke tilgang til rapporten.', 'samlab' ), '', 403 );
	}

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=samlab-rapport-' . gmdate( 'Y-m-d' ) . '.csv' );
	// BOM så norske tegn åpner riktig i Excel.
	echo "\xEF\xBB\xBF" . samlab_rapport_csv_tekst(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV-fil, ikke HTML; verdiene er siterte i byggeren.
	exit;
}
add_action( 'admin_post_samlab_rapport_csv', 'samlab_rapport_csv_handler' );
