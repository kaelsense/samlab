<?php
/**
 * Kunnskapsgrunnlaget (F2): daglig cron (og «bygg nå»-knapp) som
 * bygger assistentens grunnlag fra portalinnholdet - bedrifter med
 * intensjoner, åpne behov, kommende arrangementer og håndboken -
 * pluss eksterne URL-er fra innstillingene, hentet server-side og
 * strippet til ren tekst.
 *
 * Hemmelighetsprinsippet: grunnlaget skal aldri inneholde passord
 * eller sensitive detaljer. Kun håndbok-MERKEDE sider tas med
 * (aldri andre sider/innlegg), passordbeskyttet innhold hoppes
 * alltid over, og persondata begrenses til det portalmedlemmene
 * uansett ser (visningsnavn på kontaktpersoner). Grunnlaget viser
 * til de innloggede portalsidene for detaljer.
 *
 * Lastes kun via modul.php (modulen på).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maks antall tegn som hentes fra én ekstern kilde.
 */
const SAMLAB_KUNNSKAP_KILDEGRENSE = 20000;

/**
 * Timeout per ekstern kilde, i sekunder.
 */
const SAMLAB_KUNNSKAP_KILDETIMEOUT = 10;

/**
 * Tidsbudsjett for hele kildehentingen når PHP ikke har en
 * kjøretidsgrense (typisk CLI/WP-CLI), i sekunder.
 */
const SAMLAB_KUNNSKAP_TIDSBUDSJETT = 45;

/**
 * Det lagrede kunnskapsgrunnlaget.
 *
 * @return array{versjon: int, bygget: int, storrelse: int, tekst: string, kilder_ok: int, kilder_feilet: string[]}|null
 */
function samlab_assistent_kunnskap() {
	$grunnlag = get_option( 'samlab_kunnskap', null );
	return is_array( $grunnlag ) && isset( $grunnlag['tekst'] ) ? $grunnlag : null;
}

/**
 * Strippet ren tekst fra post-innhold (blokker rendres først).
 *
 * @param string $innhold Rått post_content.
 * @return string
 */
function samlab_kunnskap_tekst( $innhold ) {
	$tekst = wp_strip_all_tags( do_blocks( (string) $innhold ) );
	return trim( preg_replace( '/\n{3,}/', "\n\n", $tekst ) );
}

/**
 * Bedriftsseksjonen: katalogen med intensjoner og tjenester.
 *
 * @return string
 */
function samlab_kunnskap_bedrifter() {
	$bedrifter = get_posts(
		array(
			'post_type'      => 'samlab_bedrift',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	if ( array() === $bedrifter ) {
		return '';
	}

	$ut = '## ' . __( 'Bedriftene i huset', 'samlab' ) . "\n\n";
	foreach ( $bedrifter as $bedrift ) {
		if ( '' !== $bedrift->post_password ) {
			continue;
		}
		$ut     .= '### ' . $bedrift->post_title . "\n";
		$felt    = array(
			__( 'Kort', 'samlab' )           => get_post_meta( $bedrift->ID, '_samlab_kort', true ),
			__( 'Plass', 'samlab' )          => get_post_meta( $bedrift->ID, '_samlab_plass', true ),
			__( 'Leverer', 'samlab' )        => get_post_meta( $bedrift->ID, '_samlab_leverer', true ),
			__( 'Kjøper', 'samlab' )         => get_post_meta( $bedrift->ID, '_samlab_kjoper', true ),
			/* translators: intensjonsfeltet «Trenger nå». */
			__( 'Trenger nå', 'samlab' )     => get_post_meta( $bedrift->ID, '_samlab_trenger_na', true ),
			__( 'Ideelle kunder', 'samlab' ) => get_post_meta( $bedrift->ID, '_samlab_idealkunder', true ),
		);
		$kontakt = get_userdata( (int) get_post_meta( $bedrift->ID, '_samlab_kontaktperson', true ) );
		if ( $kontakt ) {
			$felt[ __( 'Kontaktperson', 'samlab' ) ] = $kontakt->display_name;
		}
		foreach ( $felt as $navn => $verdi ) {
			if ( is_string( $verdi ) && '' !== $verdi ) {
				$ut .= $navn . ': ' . $verdi . "\n";
			}
		}
		$tjenester = get_post_meta( $bedrift->ID, '_samlab_tjenester', true );
		if ( is_array( $tjenester ) ) {
			foreach ( $tjenester as $tjeneste ) {
				if ( empty( $tjeneste['tittel'] ) ) {
					continue;
				}
				$punkter = isset( $tjeneste['punkter'] ) && is_array( $tjeneste['punkter'] ) ? implode( ', ', $tjeneste['punkter'] ) : '';
				$ut     .= __( 'Tjeneste', 'samlab' ) . ': ' . $tjeneste['tittel'] . ( '' !== $punkter ? ' (' . $punkter . ')' : '' ) . "\n";
			}
		}
		$ut .= __( 'Profil', 'samlab' ) . ': ' . samlab_portal_url( 'bedrifter', $bedrift->post_name ) . "\n\n";
	}
	return $ut;
}

/**
 * Behovsseksjonen: åpne behov og tilbud.
 *
 * @return string
 */
function samlab_kunnskap_behov() {
	$behov_liste = get_posts(
		array(
			'post_type'      => 'samlab_behov',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
		)
	);
	if ( array() === $behov_liste ) {
		return '';
	}

	$ut = '## ' . __( 'Åpne behov og tilbud', 'samlab' ) . "\n\n";
	foreach ( $behov_liste as $behov ) {
		if ( '' !== $behov->post_password ) {
			continue;
		}
		$retning = get_the_terms( $behov->ID, 'samlab_retning' );
		$retning = $retning && ! is_wp_error( $retning ) ? $retning[0]->name : '';
		$ut     .= '- ' . ( '' !== $retning ? '[' . $retning . '] ' : '' ) . $behov->post_title;

		$detaljer = array();
		$bedrift  = (int) get_post_meta( $behov->ID, '_samlab_bedrift', true );
		if ( $bedrift && 'publish' === get_post_status( $bedrift ) ) {
			$detaljer[] = get_the_title( $bedrift );
		}
		$komp = get_post_meta( $behov->ID, '_samlab_kompetanse', true );
		if ( is_array( $komp ) && array() !== $komp ) {
			$detaljer[] = implode( ', ', $komp );
		}
		$frist = get_post_meta( $behov->ID, '_samlab_frist', true );
		if ( '' !== $frist ) {
			/* translators: %s: fristen slik den er skrevet inn. */
			$detaljer[] = sprintf( __( 'frist %s', 'samlab' ), $frist );
		}
		if ( array() !== $detaljer ) {
			$ut .= ' (' . implode( '; ', $detaljer ) . ')';
		}
		$ut .= "\n";
	}
	return $ut . "\n" . __( 'Detaljer og kontakt', 'samlab' ) . ': ' . samlab_portal_url( 'behov' ) . "\n\n";
}

/**
 * Arrangementsseksjonen: kommende arrangementer.
 *
 * @return string
 */
function samlab_kunnskap_arrangementer() {
	$kommende = samlab_kommende_arrangementer( 20 );
	if ( array() === $kommende ) {
		return '';
	}

	$ut = '## ' . __( 'Kommende arrangementer', 'samlab' ) . "\n\n";
	foreach ( $kommende as $arrangement ) {
		if ( '' !== $arrangement->post_password ) {
			continue;
		}
		$deler = array( samlab_arrangement_tid_visning( $arrangement->ID ) );
		$sted  = (string) get_post_meta( $arrangement->ID, '_samlab_sted', true );
		if ( '' !== $sted ) {
			$deler[] = $sted;
		}
		$ut .= '- ' . $arrangement->post_title . ' (' . implode( ', ', array_filter( $deler ) ) . ')' . "\n";
	}
	return $ut . "\n" . __( 'Påmelding og detaljer', 'samlab' ) . ': ' . samlab_portal_url( 'arrangementer' ) . "\n\n";
}

/**
 * Håndbokseksjonen: KUN sider merket som håndbok-innhold, og aldri
 * passordbeskyttede - andre sider på nettstedet havner aldri her.
 *
 * @return string
 */
function samlab_kunnskap_handbok() {
	$sider = samlab_get_handbok_pages();
	if ( array() === $sider ) {
		return '';
	}

	$ut = '## ' . __( 'Håndboken', 'samlab' ) . "\n\n";
	foreach ( $sider as $side ) {
		if ( '' !== $side->post_password ) {
			continue;
		}
		$ut .= '### ' . $side->post_title . "\n";
		$ut .= samlab_kunnskap_tekst( $side->post_content ) . "\n";
		$ut .= __( 'Les mer', 'samlab' ) . ': ' . samlab_portal_url( 'handbok', $side->post_name ) . "\n\n";
	}
	return $ut;
}

/**
 * Henter én ekstern kilde og stripper den til tekst.
 *
 * Kildelisten er redaktørstyrt, og henting skjer server-side - derfor
 * wp_safe_remote_get, som validerer URL-en og sperrer loopback og
 * interne adresser (f.eks. metadata-tjenester i skyen). Uten den kan
 * en URL i innstillingene få serveren til å hente interne ressurser
 * inn i kunnskapsgrunnlaget, som ethvert medlem kan lese via
 * assistenten.
 *
 * @param string $url     Kilden.
 * @param int    $timeout Timeout i sekunder. Standard: kildetimeouten.
 * @return string|WP_Error Teksten, eller WP_Error ved feil.
 */
function samlab_kunnskap_hent_kilde( $url, $timeout = SAMLAB_KUNNSKAP_KILDETIMEOUT ) {
	$svar = wp_safe_remote_get( $url, array( 'timeout' => max( 1, (int) $timeout ) ) );
	if ( is_wp_error( $svar ) ) {
		return $svar;
	}
	$kode = wp_remote_retrieve_response_code( $svar );
	if ( 200 !== $kode ) {
		/* translators: %d: HTTP-statuskoden. */
		return new WP_Error( 'samlab_kilde_feilet', sprintf( __( 'HTTP %d', 'samlab' ), $kode ) );
	}
	$tekst = wp_strip_all_tags( preg_replace( '#<(script|style)[^>]*>.*?</\1>#si', '', wp_remote_retrieve_body( $svar ) ) );
	$tekst = trim( preg_replace( '/\s{3,}/', "\n", $tekst ) );
	return mb_substr( $tekst, 0, SAMLAB_KUNNSKAP_KILDEGRENSE );
}

/**
 * Tidsbudsjettet kildehentingen har til rådighet, i sekunder.
 *
 * Bygget kjøres av wp-cron over HTTP, der max_execution_time gjelder.
 * Budsjettet er en andel av den grensen, og hentingen kapper hver
 * kilde mot det som er igjen - ellers kan noen få trege kilder ta
 * livet av hele jobben. Uten kjøretidsgrense (typisk WP-CLI) brukes
 * standardbudsjettet.
 *
 * @return int Sekunder.
 */
function samlab_kunnskap_tidsbudsjett() {
	$maks     = (int) ini_get( 'max_execution_time' );
	$budsjett = $maks > 0 ? (int) floor( $maks * 0.6 ) : SAMLAB_KUNNSKAP_TIDSBUDSJETT;

	/**
	 * Filtrerer tidsbudsjettet kildehentingen har til rådighet.
	 *
	 * @since 0.2.0
	 *
	 * @param int $budsjett Sekunder.
	 */
	return (int) apply_filters( 'samlab_kunnskap_tidsbudsjett', $budsjett );
}

/**
 * Kildecachen: teksten fra forrige henting per URL, pluss hvilken
 * kilde neste bygg skal starte på.
 *
 * Cachen ligger i sin egen option, ikke i grunnlaget - grunnlaget
 * leses ved hvert assistent-kall, og skal ikke bære med seg en
 * dobbel kopi av kildeteksten.
 *
 * @return array{tekst: array<string, array{tekst: string, hentet: int}>, neste: int}
 */
function samlab_kunnskap_kildecache() {
	$cache = get_option( 'samlab_kunnskap_kilder', array() );
	return array(
		'tekst' => isset( $cache['tekst'] ) && is_array( $cache['tekst'] ) ? $cache['tekst'] : array(),
		'neste' => isset( $cache['neste'] ) ? (int) $cache['neste'] : 0,
	);
}

/**
 * Lagrer kildecachen.
 *
 * @param array $tekst Kildetekst per URL.
 * @param int   $neste Indeksen neste bygg starter hentingen på.
 * @return void
 */
function samlab_kunnskap_lagre_kildecache( $tekst, $neste ) {
	update_option(
		'samlab_kunnskap_kilder',
		array(
			'tekst' => $tekst,
			'neste' => (int) $neste,
		),
		false
	);
}

/**
 * Bygger kildeseksjonen av teksten cachen inneholder, i den
 * rekkefølgen kildene er satt opp - ikke i hentingsrekkefølgen.
 *
 * @param string[] $kilder Konfigurerte URL-er.
 * @param array    $cache  Kildetekst per URL.
 * @return array{tekst: string, ok: int, mangler: string[]}
 */
function samlab_kunnskap_kildetekst( $kilder, $cache ) {
	$ut      = '';
	$ok      = 0;
	$mangler = array();
	foreach ( $kilder as $url ) {
		if ( ! isset( $cache[ $url ]['tekst'] ) || '' === $cache[ $url ]['tekst'] ) {
			$mangler[] = $url;
			continue;
		}
		$ut .= '## ' . sprintf( /* translators: %s: kildens URL. */ __( 'Fra %s', 'samlab' ), $url ) . "\n\n" . $cache[ $url ]['tekst'] . "\n\n";
		++$ok;
	}
	return array(
		'tekst'   => $ut,
		'ok'      => $ok,
		'mangler' => $mangler,
	);
}

/**
 * Henter de eksterne kildene innenfor fristen.
 *
 * Hentingen er seriell og starter der forrige bygg stoppet, slik at
 * et fast budsjett ikke sulter ut de samme kildene bygg etter bygg.
 * Tekst fra forrige henting brukes for kilder som ikke rekkes eller
 * feiler, så grunnlaget ikke mister innhold det allerede hadde.
 *
 * @param string[] $kilder Konfigurerte URL-er (minst én).
 * @param array    $cache  Kildetekst per URL fra forrige bygg.
 * @param int      $fra    Indeksen hentingen starter på.
 * @param float    $frist  Tidspunktet (microtime) hentingen må være ferdig.
 * @return array{tekst: string, ok: int, feilet: string[], cache: array, neste: int}
 */
function samlab_kunnskap_kilder( $kilder, $cache, $fra, $frist ) {
	$antall = count( $kilder );
	$ny     = array();
	$feilet = array();

	// Kilder som er fjernet fra innstillingene skal ikke ligge igjen.
	foreach ( $kilder as $url ) {
		if ( isset( $cache[ $url ]['tekst'] ) ) {
			$ny[ $url ] = $cache[ $url ];
		}
	}

	$fra   = $antall > 0 ? ( $fra % $antall + $antall ) % $antall : 0;
	$neste = $fra;
	for ( $i = 0; $i < $antall; $i++ ) {
		$indeks = ( $fra + $i ) % $antall;
		$url    = $kilder[ $indeks ];
		$igjen  = $frist - microtime( true );
		if ( $igjen < 1 ) {
			// Resten venter til neste bygg, som starter her.
			$neste = $indeks;
			break;
		}
		$neste = ( $indeks + 1 ) % $antall;
		$tekst = samlab_kunnskap_hent_kilde( $url, (int) min( SAMLAB_KUNNSKAP_KILDETIMEOUT, floor( $igjen ) ) );
		if ( is_wp_error( $tekst ) || '' === $tekst ) {
			$feilet[] = $url;
			continue;
		}
		$ny[ $url ] = array(
			'tekst'  => $tekst,
			'hentet' => time(),
		);
	}

	$bygget = samlab_kunnskap_kildetekst( $kilder, $ny );

	return array(
		'tekst'  => $bygget['tekst'],
		'ok'     => $bygget['ok'],
		'feilet' => array_values( array_unique( array_merge( $feilet, $bygget['mangler'] ) ) ),
		'cache'  => $ny,
		'neste'  => $neste,
	);
}

/**
 * Lagrer grunnlaget. Versjonen sendes inn, slik at et bygg som
 * lagrer flere ganger (portalinnhold først, kilder etterpå) blir
 * stående som én versjon.
 *
 * @param int      $versjon Versjonsnummeret dette bygget har.
 * @param string   $tekst   Grunnlagsteksten.
 * @param int      $ok      Antall kilder med tekst i grunnlaget.
 * @param string[] $feilet  Kilder uten tekst i grunnlaget.
 * @return array Det lagrede grunnlaget.
 */
function samlab_kunnskap_lagre( $versjon, $tekst, $ok, $feilet ) {
	$grunnlag = array(
		'versjon'       => $versjon,
		'bygget'        => time(),
		'storrelse'     => strlen( $tekst ),
		'tekst'         => $tekst,
		'kilder_ok'     => $ok,
		'kilder_feilet' => $feilet,
	);
	update_option( 'samlab_kunnskap', $grunnlag, false );
	return $grunnlag;
}

/**
 * Bygger og lagrer kunnskapsgrunnlaget. Versjonen teller opp for
 * hvert bygg, med tidsstempel og størrelse til statusvisningen.
 *
 * Portalinnholdet lagres før de eksterne kildene hentes - hentingen
 * er seriell og kan bli avbrutt av max_execution_time. Den delvise
 * lagringen tar med kildeteksten fra forrige bygg, så grunnlaget
 * aldri blir dårligere enn det allerede var.
 *
 * @return array Det lagrede grunnlaget.
 */
function samlab_assistent_bygg_kunnskap() {
	$start  = microtime( true );
	$tekst  = '# ' . sprintf( /* translators: %s: portalnavnet. */ __( 'Kunnskapsgrunnlag for %s', 'samlab' ), samlab_portal_name() ) . "\n\n";
	$tekst .= __( 'Grunnlaget er bygget fra portalens eget innhold. Detaljer og kontakt skjer på de innloggede portalsidene.', 'samlab' ) . "\n\n";
	$tekst .= samlab_kunnskap_bedrifter();
	$tekst .= samlab_kunnskap_behov();
	$tekst .= samlab_kunnskap_arrangementer();
	$tekst .= samlab_kunnskap_handbok();

	$forrige = samlab_assistent_kunnskap();
	$versjon = $forrige ? (int) $forrige['versjon'] + 1 : 1;
	$kilder  = samlab_assistent_kilder();

	if ( array() === $kilder ) {
		samlab_kunnskap_lagre_kildecache( array(), 0 );
		$grunnlag = samlab_kunnskap_lagre( $versjon, $tekst, 0, array() );
	} else {
		$cache  = samlab_kunnskap_kildecache();
		$forrig = samlab_kunnskap_kildetekst( $kilder, $cache['tekst'] );

		// Delvis lagring - samme versjon skrives på nytt under.
		samlab_kunnskap_lagre( $versjon, $tekst . $forrig['tekst'], $forrig['ok'], $forrig['mangler'] );

		$hentet = samlab_kunnskap_kilder( $kilder, $cache['tekst'], $cache['neste'], $start + samlab_kunnskap_tidsbudsjett() );
		samlab_kunnskap_lagre_kildecache( $hentet['cache'], $hentet['neste'] );
		$grunnlag = samlab_kunnskap_lagre( $versjon, $tekst . $hentet['tekst'], $hentet['ok'], $hentet['feilet'] );
	}

	/**
	 * Kjøres etter at kunnskapsgrunnlaget er bygget.
	 *
	 * @since 0.2.0
	 *
	 * @param array $grunnlag versjon, bygget, storrelse, kilder_ok, kilder_feilet, tekst.
	 */
	do_action( 'samlab_kunnskap_bygget', $grunnlag );

	return $grunnlag;
}
add_action( 'samlab_assistent_kunnskap', 'samlab_assistent_bygg_kunnskap' );

/**
 * Planlegger den daglige byggingen når modulen er på (denne filen
 * lastes kun da). Avplanlegging når modulen slås av skjer i
 * bootstrapen (assistent.php).
 *
 * @return void
 */
function samlab_kunnskap_planlegg() {
	if ( ! wp_next_scheduled( 'samlab_assistent_kunnskap' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'samlab_assistent_kunnskap' );
	}
}
add_action( 'init', 'samlab_kunnskap_planlegg' );

/**
 * «Bygg nå»-knappen: admin-post med nonce, kun manage_options.
 *
 * @return void
 */
function samlab_kunnskap_bygg_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Du har ikke tilgang til dette.', 'samlab' ), '', 403 );
	}
	check_admin_referer( 'samlab_bygg_kunnskap' );
	samlab_assistent_bygg_kunnskap();
	wp_safe_redirect( admin_url( 'options-general.php?page=samlab' ) );
	exit;
}
add_action( 'admin_post_samlab_bygg_kunnskap', 'samlab_kunnskap_bygg_handler' );
