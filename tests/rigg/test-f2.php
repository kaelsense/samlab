<?php
// Røyk-test for F2: kunnskapsgrunnlaget bygges fra seed-data med
// mockede eksterne kilder (pre_http_request - ingen nøkkel eller
// nett trengs). Kjøres med assistent-modulen PÅ:
//   wp eval '
//     $s = get_option("samlab_settings", array());
//     $s["assistent_aktiv"] = "1"; update_option("samlab_settings", $s);'
//   wp eval-file test-f2.php

// eval-file kjører i funksjons-scope: bind til den globale sjekk() skriver til.
global $fail;
$fail = 0;
function sjekk( $navn, $ok ) {
	global $fail;
	if ( $ok ) {
		echo "OK   $navn\n";
	} else {
		echo "FEIL $navn\n";
		$fail = 1;
	}
}

if ( ! function_exists( 'samlab_assistent_bygg_kunnskap' ) ) {
	echo "FEIL modulen er ikke lastet - slå på assistenten før testen\n";
	exit( 1 );
}

// --- Mock: eksterne kilder svarer uten nett ---
add_filter(
	'pre_http_request',
	function ( $ignorert, $parsed_args, $url ) {
		if ( false !== strpos( $url, 'finnes-ikke' ) ) {
			return array(
				'response' => array( 'code' => 404 ),
				'body'     => 'Not Found',
			);
		}
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '<html><head><style>.x{}</style><script>alert(1)</script></head><body><h1>Om huset</h1><p>Huset har 40 kontorplasser og en felles kantine.</p></body></html>',
		);
	},
	10,
	3
);

// --- Opptaker: hva var lagret da den første kilden ble hentet? ---
$under_henting = null;
add_filter(
	'pre_http_request',
	function ( $ignorert ) use ( &$under_henting ) {
		if ( null === $under_henting ) {
			$under_henting = get_option( 'samlab_kunnskap', null );
		}
		return $ignorert;
	},
	9
);

// --- Oppsett: kilder + innhold som ALDRI skal med ---
$orig = get_option( 'samlab_settings', array() );
$s    = $orig;
$s['assistent_kilder'] = "https://example.no/om-huset\nhttps://example.no/finnes-ikke";
update_option( 'samlab_settings', $s );

$hemmelig_side = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_title'   => 'Intern styreprotokoll',
		'post_content' => 'Hemmelig-styreinnhold-XYZZY som aldri skal i grunnlaget.',
		'post_status'  => 'publish',
	)
);
$laast_handbok = wp_insert_post(
	array(
		'post_type'     => 'page',
		'post_title'    => 'Låst håndbokside',
		'post_content'  => 'Passordbeskyttet-PLUGH-innhold.',
		'post_status'   => 'publish',
		'post_password' => 'topphemmelig',
		'meta_input'    => array( '_samlab_handbok' => '1' ),
	)
);

// --- Bygg ---
delete_option( 'samlab_kunnskap' );
delete_option( 'samlab_kunnskap_kilder' );
$grunnlag = samlab_assistent_bygg_kunnskap();
$tekst    = $grunnlag['tekst'];

// --- Portalinnholdet er med ---
sjekk( 'bedrift med intensjoner er med', false !== strpos( $tekst, 'Brygga Design' ) && false !== strpos( $tekst, 'Design, merkevare og nettsider' ) );
sjekk( 'tjenester er med', false !== strpos( $tekst, 'Visuell identitet (Logo, Profilhåndbok, Maler)' ) );
sjekk( 'behov er med, med retning', false !== strpos( $tekst, '[Trenger] Fotograf til kundecaser' ) );
sjekk( 'kommende arrangement er med', false !== strpos( $tekst, 'Felleslunsj med quiz' ) );
sjekk( 'tidligere arrangement er IKKE med', false === strpos( $tekst, 'Sommerfesten' ) );
sjekk( 'håndboksiden er med', false !== strpos( $tekst, 'Adgang og nøkler' ) );
sjekk( 'grunnlaget viser til portalsidene', false !== strpos( $tekst, samlab_portal_url( 'behov' ) ) );

// --- Hemmelighetsprinsippet ---
sjekk( 'ikke-portal-side er IKKE med', false === strpos( $tekst, 'XYZZY' ) && false === strpos( $tekst, 'Intern styreprotokoll' ) );
sjekk( 'passordbeskyttet håndbokside er IKKE med', false === strpos( $tekst, 'PLUGH' ) );

// --- Eksterne kilder (mocket) ---
sjekk( 'ekstern kilde er hentet og strippet', false !== strpos( $tekst, '40 kontorplasser' ) && false === strpos( $tekst, 'alert(1)' ) );
sjekk( 'én kilde ok, én feilet', 1 === $grunnlag['kilder_ok'] && array( 'https://example.no/finnes-ikke' ) === $grunnlag['kilder_feilet'] );
sjekk( 'grunnlaget er HTML-fritt', false === strpos( $tekst, '<p>' ) && false === strpos( $tekst, '<script' ) );

// --- Delvis lagring: portalinnholdet overlever en avbrutt jobb ---
sjekk( 'portalinnholdet er lagret før kildene hentes', is_array( $under_henting ) && false !== strpos( $under_henting['tekst'], 'Brygga Design' ) );
sjekk( 'delvis lagring bruker samme versjon som det ferdige bygget', $under_henting['versjon'] === $grunnlag['versjon'] );
sjekk( 'delvis lagring merker kildene som ikke hentet ennå', 0 === $under_henting['kilder_ok'] && 2 === count( $under_henting['kilder_feilet'] ) );

// --- Versjonering og status ---
sjekk( 'første bygg er versjon 1', 1 === $grunnlag['versjon'] && $grunnlag['bygget'] > 0 && strlen( $tekst ) === $grunnlag['storrelse'] );
$andre = samlab_assistent_bygg_kunnskap();
sjekk( 'nytt bygg teller opp versjonen', 2 === $andre['versjon'] );
sjekk( 'statusteksten viser versjon og størrelse', false !== strpos( samlab_assistent_kunnskap_status(), 'Versjon 2' ) );
sjekk( 'statusteksten navngir feilede kilder', false !== strpos( samlab_assistent_kunnskap_status(), 'finnes-ikke' ) );

// --- Cron er planlagt når modulen er på ---
samlab_kunnskap_planlegg();
sjekk( 'kunnskaps-cronen er planlagt daglig', false !== wp_next_scheduled( 'samlab_assistent_kunnskap' ) );

// --- Tidsbudsjettet holder seg innenfor kjøretidsgrensen ---
$maks_orig = ini_get( 'max_execution_time' );
ini_set( 'max_execution_time', '30' );
sjekk( 'budsjettet er en andel av kjøretidsgrensen', 18 === samlab_kunnskap_tidsbudsjett() );
ini_set( 'max_execution_time', '20' );
sjekk( 'budsjettet holder seg innenfor også ved lav grense', samlab_kunnskap_tidsbudsjett() < 20 );
ini_set( 'max_execution_time', '0' );
sjekk( 'uten kjøretidsgrense brukes standardbudsjettet', SAMLAB_KUNNSKAP_TIDSBUDSJETT === samlab_kunnskap_tidsbudsjett() );
ini_set( 'max_execution_time', (string) $maks_orig );

// --- Rotasjon, frist og cache i kildehentingen ---
$url_ok    = 'https://example.no/om-huset';
$url_feil  = 'https://example.no/finnes-ikke';
$rekkefolge = array();
$timeouts   = array();
add_filter(
	'pre_http_request',
	function ( $ignorert, $parsed_args, $url ) use ( &$rekkefolge, &$timeouts ) {
		$rekkefolge[] = $url;
		$timeouts[]   = $parsed_args['timeout'];
		return $ignorert;
	},
	8,
	3
);

$rotert = samlab_kunnskap_kilder( array( $url_ok, $url_feil ), array(), 1, microtime( true ) + 30 );
sjekk( 'hentingen starter der forrige bygg stoppet', array( $url_feil, $url_ok ) === $rekkefolge );
sjekk( 'alle hentet gir samme startpunkt neste gang', 1 === $rotert['neste'] );
sjekk( 'timeouten kappes aldri over kildetimeouten', SAMLAB_KUNNSKAP_KILDETIMEOUT === $timeouts[0] );

$rekkefolge = array();
$kort_frist = samlab_kunnskap_kilder( array( $url_ok ), array(), 0, microtime( true ) + 3 );
sjekk( 'timeouten kappes mot det som er igjen av fristen', $timeouts[ count( $timeouts ) - 1 ] <= 3 && $timeouts[ count( $timeouts ) - 1 ] >= 1 );

$rekkefolge = array();
$utlopt     = samlab_kunnskap_kilder( array( $url_ok, $url_feil ), array(), 1, microtime( true ) - 1 );
sjekk( 'utløpt frist henter ingenting', array() === $rekkefolge );
sjekk( 'utløpt frist husker hvor neste bygg skal starte', 1 === $utlopt['neste'] );
sjekk( 'kilder uten tekst rapporteres som feilet', 0 === $utlopt['ok'] && 2 === count( $utlopt['feilet'] ) );

$med_cache = samlab_kunnskap_kilder(
	array( $url_feil ),
	array(
		$url_feil => array(
			'tekst'  => 'Tekst fra forrige bygg',
			'hentet' => time(),
		),
	),
	0,
	microtime( true ) + 30
);
sjekk( 'feilet kilde beholder teksten fra forrige bygg', 1 === $med_cache['ok'] && false !== strpos( $med_cache['tekst'], 'Tekst fra forrige bygg' ) );
sjekk( 'feilet kilde rapporteres likevel som feilet', array( $url_feil ) === $med_cache['feilet'] );

$uten_kilde = samlab_kunnskap_kilder( array( $url_ok ), array( 'https://example.no/fjernet' => array( 'tekst' => 'Skal ut', 'hentet' => time() ) ), 0, microtime( true ) + 30 );
sjekk( 'cache for fjernede kilder følger ikke med videre', ! isset( $uten_kilde['cache']['https://example.no/fjernet'] ) );

// --- Et bygg uten tid til kildene beholder forrige byggs kildetekst ---
$tomt_budsjett = function () {
	return 0;
};
$for_bygget = count( $rekkefolge );
add_filter( 'samlab_kunnskap_tidsbudsjett', $tomt_budsjett );
$knapt = samlab_assistent_bygg_kunnskap();
remove_filter( 'samlab_kunnskap_tidsbudsjett', $tomt_budsjett );
sjekk( 'brukt budsjett stopper kildehentingen', $for_bygget === count( $rekkefolge ) );
sjekk( 'kildeteksten fra forrige bygg er beholdt', 1 === $knapt['kilder_ok'] && false !== strpos( $knapt['tekst'], '40 kontorplasser' ) );
sjekk( 'portalinnholdet er med når kildene droppes', false !== strpos( $knapt['tekst'], 'Brygga Design' ) );

// --- Kaldstart uten cache og uten tid: ingenting, men ærlig status ---
delete_option( 'samlab_kunnskap_kilder' );
add_filter( 'samlab_kunnskap_tidsbudsjett', $tomt_budsjett );
$kaldt = samlab_assistent_bygg_kunnskap();
remove_filter( 'samlab_kunnskap_tidsbudsjett', $tomt_budsjett );
sjekk( 'uten cache og uten tid står kildene som feilet', 0 === $kaldt['kilder_ok'] && 2 === count( $kaldt['kilder_feilet'] ) );
sjekk( 'portalinnholdet er likevel lagret', false !== strpos( $kaldt['tekst'], 'Brygga Design' ) );

// --- Rydd ---
wp_delete_post( $hemmelig_side, true );
wp_delete_post( $laast_handbok, true );
update_option( 'samlab_settings', $orig );
delete_option( 'samlab_kunnskap' );
delete_option( 'samlab_kunnskap_kilder' );
exit( $fail );
