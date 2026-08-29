<?php
/**
 * Ukesbrev: ukentlig digest via wp_mail med nye behov, nye
 * vegginnlegg og nye medlemmer siste uke. Ren tekst uten
 * temaavhengighet. Innstillinger: av/på, ukedag, avsendernavn.
 * Medlemmer kan reservere seg via profilinnstilling.
 *
 * Kjøres av den daglige cron-hooken samlab_ukesbrev, som selv
 * avgjør om i dag er utsendelsesdagen (robust mot at cron-kjøringer
 * hopper over enkeltdager). Kommende arrangementer legges til av
 * E6 via filteret samlab_ukesbrev_seksjoner.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Om ukesbrevet er slått på i innstillingene.
 *
 * @return bool
 */
function samlab_ukesbrev_aktiv() {
	return '1' === samlab_get_setting( 'ukesbrev_aktiv' );
}

/**
 * Ukedagen brevet sendes (ISO 8601: 1 = mandag … 7 = søndag).
 *
 * @return int
 */
function samlab_ukesbrev_ukedag() {
	$dag = (int) samlab_get_setting( 'ukesbrev_ukedag', '1' );
	return ( $dag >= 1 && $dag <= 7 ) ? $dag : 1;
}

/**
 * Mottakerne: portaldeltakere som ikke har reservert seg.
 *
 * @return WP_User[]
 */
function samlab_ukesbrev_mottakere() {
	$brukere = get_users(
		array(
			'capability' => 'samlab_read_portal',
			'number'     => 500,
			'orderby'    => 'ID',
		)
	);
	return array_values(
		array_filter(
			$brukere,
			static function ( $bruker ) {
				return '1' !== get_user_meta( $bruker->ID, 'samlab_ukesbrev_reservert', true );
			}
		)
	);
}

/**
 * Vegginnlegg opprettet etter et tidspunkt (publiserte).
 *
 * @param int $siden Unix-tidspunkt.
 * @return object[]
 */
function samlab_ukesbrev_innlegg( $siden ) {
	global $wpdb;
	$tabell = samlab_table( 'innlegg' );

	return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell, ukentlig cron.
		$wpdb->prepare(
			"SELECT * FROM {$tabell} WHERE status = 'publish' AND created_at >= %s ORDER BY created_at DESC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table(), ikke brukerdata.
			gmdate( 'Y-m-d H:i:s', $siden )
		)
	);
}

/**
 * Bygger brevets seksjoner: tittel + linjer (tekst og ev. URL).
 *
 * @param int $siden Unix-tidspunkt (typisk en uke tilbake).
 * @return array<int, array{tittel: string, linjer: array<int, array{tekst: string, url?: string}>}>
 */
function samlab_ukesbrev_seksjoner( $siden ) {
	$seksjoner = array();

	$behov = get_posts(
		array(
			'post_type'      => 'samlab_behov',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'date_query'     => array(
				array(
					'after'  => gmdate( 'Y-m-d H:i:s', $siden ),
					'column' => 'post_date_gmt',
				),
			),
		)
	);
	if ( $behov ) {
		$linjer = array();
		foreach ( $behov as $b ) {
			$linjer[] = array(
				'tekst' => $b->post_title,
				'url'   => samlab_portal_url( 'behov' ),
			);
		}
		$seksjoner[] = array(
			'tittel' => __( 'Nye behov og tilbud', 'samlab' ),
			'linjer' => $linjer,
		);
	}

	$innlegg = samlab_ukesbrev_innlegg( $siden );
	if ( $innlegg ) {
		$linjer = array();
		foreach ( $innlegg as $i ) {
			$forfatter = get_the_author_meta( 'display_name', (int) $i->user_id );
			$utdrag    = wp_html_excerpt( wp_strip_all_tags( $i->content ), 80, '…' );
			$linjer[]  = array(
				'tekst' => $forfatter . ': ' . $utdrag,
				'url'   => samlab_portal_url( 'vegg' ),
			);
		}
		$seksjoner[] = array(
			'tittel' => __( 'Nytt på veggen', 'samlab' ),
			'linjer' => $linjer,
		);
	}

	$nye_medlemmer = get_users(
		array(
			'capability' => 'samlab_read_portal',
			'number'     => 20,
			'orderby'    => 'registered',
			'order'      => 'DESC',
			'date_query' => array(
				array( 'after' => gmdate( 'Y-m-d H:i:s', $siden ) ),
			),
		)
	);
	if ( $nye_medlemmer ) {
		$linjer = array();
		foreach ( $nye_medlemmer as $medlem ) {
			$linjer[] = array( 'tekst' => $medlem->display_name );
		}
		$seksjoner[] = array(
			'tittel' => __( 'Nye medlemmer', 'samlab' ),
			'linjer' => $linjer,
		);
	}

	/**
	 * Filtrerer ukesbrevets seksjoner før utsending. E6 legger til
	 * kommende arrangementer her.
	 *
	 * @since 0.2.0
	 *
	 * @param array $seksjoner Seksjoner: tittel + linjer (tekst, ev. url).
	 * @param int   $siden     Unix-tidspunktet brevet dekker fra.
	 */
	return apply_filters( 'samlab_ukesbrev_seksjoner', $seksjoner, $siden );
}

/**
 * Rendrer brevet som ren tekst - ingen temaavhengighet.
 *
 * @param array $seksjoner Fra samlab_ukesbrev_seksjoner().
 * @return string
 */
function samlab_ukesbrev_tekst( $seksjoner ) {
	/* translators: %s: portalnavnet. */
	$tekst  = sprintf( __( 'Siste uke i %s', 'samlab' ), samlab_portal_name() ) . "\n";
	$tekst .= str_repeat( '=', 40 ) . "\n\n";

	foreach ( $seksjoner as $seksjon ) {
		$tekst .= $seksjon['tittel'] . "\n" . str_repeat( '-', 40 ) . "\n";
		foreach ( $seksjon['linjer'] as $linje ) {
			$tekst .= '* ' . $linje['tekst'] . "\n";
			if ( ! empty( $linje['url'] ) ) {
				$tekst .= '  ' . $linje['url'] . "\n";
			}
		}
		$tekst .= "\n";
	}

	$tekst .= samlab_portal_url() . "\n\n";
	$tekst .= __( 'Du får denne e-posten som medlem av portalen. Reserver deg under «Profil» i wp-admin.', 'samlab' ) . "\n";

	return $tekst;
}

/**
 * Avsendernavnet fra innstillingene, til wp_mail_from_name-filteret
 * under utsending.
 *
 * @return string
 */
function samlab_ukesbrev_avsendernavn() {
	return samlab_get_setting( 'ukesbrev_avsender', samlab_portal_name() );
}

/**
 * Genererer og sender ukesbrevet til alle mottakere nå.
 *
 * Sender ikke tomt brev (ingen seksjoner = ingen e-post). Oppdaterer
 * samlab_ukesbrev_sist ved vellykket runde.
 *
 * @return int Antall e-poster sendt.
 */
function samlab_send_ukesbrev() {
	$siden     = time() - WEEK_IN_SECONDS;
	$seksjoner = samlab_ukesbrev_seksjoner( $siden );
	if ( array() === $seksjoner ) {
		return 0;
	}

	/* translators: %s: portalnavnet. */
	$emne  = sprintf( __( 'Ukesbrev fra %s', 'samlab' ), samlab_portal_name() );
	$tekst = samlab_ukesbrev_tekst( $seksjoner );

	add_filter( 'wp_mail_from_name', 'samlab_ukesbrev_avsendernavn' );
	$antall = 0;
	foreach ( samlab_ukesbrev_mottakere() as $mottaker ) {
		if ( wp_mail( $mottaker->user_email, $emne, $tekst ) ) {
			++$antall;
		}
	}
	remove_filter( 'wp_mail_from_name', 'samlab_ukesbrev_avsendernavn' );

	update_option( 'samlab_ukesbrev_sist', time(), false );

	/**
	 * Kjøres etter at et ukesbrev er sendt.
	 *
	 * @since 0.2.0
	 *
	 * @param int   $antall    Antall e-poster sendt.
	 * @param array $seksjoner Seksjonene brevet inneholdt.
	 */
	do_action( 'samlab_ukesbrev_sendt', $antall, $seksjoner );

	return $antall;
}

/**
 * Daglig cron-sjekk: send når brevet er på, i dag er valgt ukedag
 * og det er minst seks dager siden forrige utsending.
 *
 * @return void
 */
function samlab_ukesbrev_tick() {
	if ( ! samlab_ukesbrev_aktiv() ) {
		return;
	}
	if ( (int) wp_date( 'N' ) !== samlab_ukesbrev_ukedag() ) {
		return;
	}
	$sist = (int) get_option( 'samlab_ukesbrev_sist', 0 );
	if ( $sist && time() - $sist < 6 * DAY_IN_SECONDS ) {
		return;
	}
	samlab_send_ukesbrev();
}
add_action( 'samlab_ukesbrev', 'samlab_ukesbrev_tick' );

/**
 * Reservasjons-avkryssing på profilsiden. Kjernens profilskjema
 * håndterer nonce (update-user_ID) før disse hookene kjører.
 *
 * @param WP_User $bruker Brukeren som redigeres.
 * @return void
 */
function samlab_ukesbrev_profilfelt( $bruker ) {
	if ( ! user_can( $bruker, 'samlab_read_portal' ) ) {
		return;
	}
	$reservert = get_user_meta( $bruker->ID, 'samlab_ukesbrev_reservert', true );
	?>
	<h2><?php echo esc_html( samlab_portal_name() ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Ukesbrev', 'samlab' ); ?></th>
			<td>
				<label for="samlab-ukesbrev-reservert">
					<input type="checkbox" id="samlab-ukesbrev-reservert"
						name="samlab_ukesbrev_reservert" value="1"
						<?php checked( '1', $reservert ); ?> />
					<?php esc_html_e( 'Ikke send meg det ukentlige ukesbrevet på e-post', 'samlab' ); ?>
				</label>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'samlab_ukesbrev_profilfelt' );
add_action( 'edit_user_profile', 'samlab_ukesbrev_profilfelt' );

/**
 * Lagrer reservasjonen fra profilskjemaet.
 *
 * @param int $bruker_id Brukeren som lagres.
 * @return void
 */
function samlab_ukesbrev_lagre_profilfelt( $bruker_id ) {
	if ( ! current_user_can( 'edit_user', $bruker_id ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Kjernens profilskjema verifiserer update-user_ID-noncen før hooken.
	if ( isset( $_POST['samlab_ukesbrev_reservert'] ) ) {
		update_user_meta( $bruker_id, 'samlab_ukesbrev_reservert', '1' );
	} else {
		delete_user_meta( $bruker_id, 'samlab_ukesbrev_reservert' );
	}
}
add_action( 'personal_options_update', 'samlab_ukesbrev_lagre_profilfelt' );
add_action( 'edit_user_profile_update', 'samlab_ukesbrev_lagre_profilfelt' );
