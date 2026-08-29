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

// --- Versjonering og status ---
sjekk( 'første bygg er versjon 1', 1 === $grunnlag['versjon'] && $grunnlag['bygget'] > 0 && strlen( $tekst ) === $grunnlag['storrelse'] );
$andre = samlab_assistent_bygg_kunnskap();
sjekk( 'nytt bygg teller opp versjonen', 2 === $andre['versjon'] );
sjekk( 'statusteksten viser versjon og størrelse', false !== strpos( samlab_assistent_kunnskap_status(), 'Versjon 2' ) );
sjekk( 'statusteksten navngir feilede kilder', false !== strpos( samlab_assistent_kunnskap_status(), 'finnes-ikke' ) );

// --- Cron er planlagt når modulen er på ---
samlab_kunnskap_planlegg();
sjekk( 'kunnskaps-cronen er planlagt daglig', false !== wp_next_scheduled( 'samlab_assistent_kunnskap' ) );

// --- Rydd ---
wp_delete_post( $hemmelig_side, true );
wp_delete_post( $laast_handbok, true );
update_option( 'samlab_settings', $orig );
delete_option( 'samlab_kunnskap' );
exit( $fail );
