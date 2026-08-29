<?php
/**
 * Assistentens REST-endepunkt (F3): POST samlab/v1/assistent tar
 * imot medlemmets melding (pluss avgrenset samtalehistorikk fra
 * klienten) og kaller Claude Messages API server-side med
 * wp_remote_post - nøkkelen forlater aldri serveren, og klienten
 * snakker aldri direkte med API-et.
 *
 * Systemprompten består av to blokker: instruks (navn, tone,
 * regler) og kunnskapsgrunnlaget fra F2, der grunnlaget er merket
 * med cache_control for prompt-caching. Rate-limiting per bruker
 * er transient-basert. Spørsmål og svar logges aldri - ved
 * API-feil logges kun statuskoden, og bare når WP_DEBUG er på.
 *
 * Lastes kun via modul.php (modulen på); med modulen av finnes
 * ikke ruten (404).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate-grensen: antall kall per bruker innenfor vinduet.
 */
const SAMLAB_ASSISTENT_RATE_ANTALL = 15;

/**
 * Rate-vinduet i sekunder.
 */
const SAMLAB_ASSISTENT_RATE_VINDU = 5 * MINUTE_IN_SECONDS;

/**
 * Maks antall historikk-innslag som sendes videre til API-et.
 */
const SAMLAB_ASSISTENT_HISTORIKK_MAKS = 10;

/**
 * Maks lengde på én meldingstekst, i tegn.
 */
const SAMLAB_ASSISTENT_TEKST_MAKS = 4000;

/**
 * Registrerer assistent-ruten.
 *
 * @return void
 */
function samlab_assistent_register_rest() {
	register_rest_route(
		'samlab/v1',
		'/assistent',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'samlab_rest_assistent',
			'permission_callback' => 'samlab_rest_can_react',
			'args'                => array(
				'melding'   => array(
					'type'      => 'string',
					'required'  => true,
					'minLength' => 1,
					'maxLength' => SAMLAB_ASSISTENT_TEKST_MAKS,
				),
				'historikk' => array(
					'type'    => 'array',
					'default' => array(),
					'items'   => array( 'type' => 'object' ),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'samlab_assistent_register_rest' );

/**
 * Teller brukerens kall i vinduet og sier om dette er innenfor.
 *
 * @param int $user_id Brukeren.
 * @return bool Om kallet er innenfor grensen.
 */
function samlab_assistent_rate_ok( $user_id ) {
	$nokkel = 'samlab_assistent_rl_' . absint( $user_id );
	$antall = (int) get_transient( $nokkel );
	if ( $antall >= SAMLAB_ASSISTENT_RATE_ANTALL ) {
		return false;
	}
	set_transient( $nokkel, $antall + 1, SAMLAB_ASSISTENT_RATE_VINDU );
	return true;
}

/**
 * Systemprompten: instruksblokk + kunnskapsblokk med cache_control
 * (prompt-caching - grunnlaget er stort og likt mellom kall).
 *
 * @return array Blokkliste til Messages API-ets system-felt.
 */
function samlab_assistent_systemblokker() {
	$instruks = sprintf(
		/* translators: 1: assistentens navn, 2: portalnavnet. */
		__( 'Du er %1$s, assistenten i portalen «%2$s» for et norsk kontorfellesskap. Svar på norsk bokmål, kort og konkret. Bruk kun kunnskapsgrunnlaget under - ikke gjett, og si ifra når du ikke vet. Henvis til portalsidene (URL-ene i grunnlaget) for detaljer og kontakt. Del aldri passord eller sensitive detaljer, og uttal deg ikke om personer utover det grunnlaget sier.', 'samlab' ),
		samlab_assistent_navn(),
		samlab_portal_name()
	);
	$tone = samlab_assistent_tone();
	if ( '' !== $tone ) {
		$instruks .= "\n\n" . __( 'Toneinstruks fra verten:', 'samlab' ) . ' ' . $tone;
	}

	$grunnlag = samlab_assistent_kunnskap();
	$kunnskap = $grunnlag ? $grunnlag['tekst'] : __( '(Kunnskapsgrunnlaget er ikke bygget ennå.)', 'samlab' );

	return array(
		array(
			'type' => 'text',
			'text' => $instruks,
		),
		array(
			'type'          => 'text',
			'text'          => $kunnskap,
			'cache_control' => array( 'type' => 'ephemeral' ),
		),
	);
}

/**
 * Saniterer og kapper én meldingstekst. Saniteringen kan tømme
 * teksten helt (f.eks. «<hei>»), og kallerne må derfor sjekke
 * resultatet - en tom tekstblokk avvises av API-et.
 *
 * @param mixed $tekst Rå tekst.
 * @return string Sanitert tekst, kappet til grensen.
 */
function samlab_assistent_rens_tekst( $tekst ) {
	return mb_substr( sanitize_textarea_field( (string) $tekst ), 0, SAMLAB_ASSISTENT_TEKST_MAKS );
}

/**
 * Trimmer meldingslisten til formen Messages API-et krever: første
 * melding fra brukeren, siste fra assistenten. Da kan medlemmets
 * nye melding legges til uten to brukerturer på rad. En liste som
 * er kappet midt i en samtale starter ellers ofte med assistenten,
 * og API-et svarer 400.
 *
 * @param array $meldinger Vekslende meldingsliste.
 * @return array Trimmet liste - kan være tom.
 */
function samlab_assistent_trim_meldinger( $meldinger ) {
	while ( array() !== $meldinger && 'user' !== $meldinger[0]['role'] ) {
		array_shift( $meldinger );
	}
	while ( array() !== $meldinger ) {
		$siste = end( $meldinger );
		if ( 'assistant' === $siste['role'] ) {
			break;
		}
		array_pop( $meldinger );
	}
	return array_values( $meldinger );
}

/**
 * Vasker og avgrenser samtalehistorikken fra klienten: kun kjente
 * roller, tekst sanitert og kappet, maks N siste innslag - og alltid
 * vekslende roller. Faller et innslag ut (ukjent rolle, tom tekst
 * etter sanitering), beholdes det nyeste av to like roller på rad
 * slik at vekslingen holder.
 *
 * @param array $historikk Rå historikk fra forespørselen.
 * @return array Meldingsliste for Messages API.
 */
function samlab_assistent_vask_historikk( $historikk ) {
	$meldinger = array();
	foreach ( (array) $historikk as $innslag ) {
		if ( ! is_array( $innslag ) || ! isset( $innslag['rolle'], $innslag['tekst'] ) ) {
			continue;
		}
		if ( ! in_array( $innslag['rolle'], array( 'user', 'assistant' ), true ) ) {
			continue;
		}
		$tekst = samlab_assistent_rens_tekst( $innslag['tekst'] );
		if ( '' === $tekst ) {
			continue;
		}
		$siste = count( $meldinger ) - 1;
		if ( $siste >= 0 && $meldinger[ $siste ]['role'] === $innslag['rolle'] ) {
			array_pop( $meldinger );
		}
		$meldinger[] = array(
			'role'    => $innslag['rolle'],
			'content' => $tekst,
		);
	}

	return samlab_assistent_trim_meldinger( array_slice( $meldinger, - SAMLAB_ASSISTENT_HISTORIKK_MAKS ) );
}

/**
 * Kaller Claude Messages API server-side.
 *
 * @param array $meldinger Meldingslisten (historikk + ny melding).
 * @return string|WP_Error Svarteksten, eller WP_Error.
 */
function samlab_assistent_kall_api( $meldinger ) {
	$svar = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 60,
			'headers' => array(
				'x-api-key'         => SAMLAB_CLAUDE_API_KEY,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'model'      => samlab_assistent_modell(),
					'max_tokens' => 1024,
					'system'     => samlab_assistent_systemblokker(),
					'messages'   => $meldinger,
				)
			),
		)
	);

	if ( is_wp_error( $svar ) ) {
		return new WP_Error( 'samlab_assistent_feil', __( 'Assistenten fikk ikke kontakt - prøv igjen om litt.', 'samlab' ), array( 'status' => 502 ) );
	}

	$kode = wp_remote_retrieve_response_code( $svar );
	$data = json_decode( wp_remote_retrieve_body( $svar ), true );
	if ( 200 !== $kode || ! isset( $data['content'][0]['text'] ) ) {
		// Aldri innhold i loggen - kun statuskoden, og kun ved feilsøking.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Samlab-assistenten: API-kall feilet med HTTP ' . (int) $kode . '.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Kun statuskode, kun med WP_DEBUG.
		}
		return new WP_Error( 'samlab_assistent_feil', __( 'Assistenten fikk ikke svar - prøv igjen om litt.', 'samlab' ), array( 'status' => 502 ) );
	}

	return (string) $data['content'][0]['text'];
}

/**
 * REST-callbacken: vakter (nøkkel, rate), API-kall og ryddig svar.
 * Feilmeldingene er generiske - konfigurasjonsdetaljer (konstantnavn,
 * modell, grenser) lekkes aldri til klienten.
 *
 * @param WP_REST_Request $request Forespørselen.
 * @return WP_REST_Response|WP_Error
 */
function samlab_rest_assistent( $request ) {
	if ( ! samlab_assistent_har_nokkel() ) {
		return new WP_Error( 'samlab_assistent_utilgjengelig', __( 'Assistenten er ikke tilgjengelig ennå - kontakt verten.', 'samlab' ), array( 'status' => 503 ) );
	}

	// Saniteringen kjøres etter REST-valideringen, og kan tømme en
	// melding som passerte minLength - da ville API-et fått en tom
	// tekstblokk. Sjekk før rate-telleren brukes: kallet når aldri ut.
	$melding = samlab_assistent_rens_tekst( $request['melding'] );
	if ( '' === $melding ) {
		return new WP_Error( 'samlab_assistent_tom_melding', __( 'Skriv et spørsmål med tekst i.', 'samlab' ), array( 'status' => 400 ) );
	}

	$user_id = get_current_user_id();
	if ( ! samlab_assistent_rate_ok( $user_id ) ) {
		return new WP_Error( 'samlab_assistent_grense', __( 'Rolig nå - du har sendt mange spørsmål på kort tid. Prøv igjen om noen minutter.', 'samlab' ), array( 'status' => 429 ) );
	}

	$meldinger   = samlab_assistent_vask_historikk( $request['historikk'] );
	$meldinger[] = array(
		'role'    => 'user',
		'content' => $melding,
	);

	$svar = samlab_assistent_kall_api( $meldinger );
	if ( is_wp_error( $svar ) ) {
		return $svar;
	}

	/**
	 * Kjøres etter et vellykket assistent-svar. Sender bevisst ALDRI
	 * med spørsmål eller svar - kun brukeren, til statistikkformål.
	 *
	 * @since 0.2.0
	 *
	 * @param int $user_id Brukeren som spurte.
	 */
	do_action( 'samlab_assistent_svarte', $user_id );

	return rest_ensure_response(
		array(
			'svar' => $svar,
			'navn' => samlab_assistent_navn(),
		)
	);
}
