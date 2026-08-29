<?php
// Røyk-test for F1: assistentens innstillinger, sanitering,
// nøkkelstatus og at modul-koden IKKE er lastet når modulen er av.
// Kjøres med assistenten AV: wp eval-file test-f1.php
// Av/på-lastingen verifiseres i tillegg med separate wp eval-kall
// (lastingen skjer ved plugin-innlasting) - se BACKLOG-notatet.

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

// --- Modulen er av: ingen assistent-kode lastet ---
sjekk( 'modulen er av som standard', ! samlab_assistent_aktiv() );
sjekk( 'ingen modul-kode lastet når av', ! function_exists( 'samlab_assistent_modul_lastet' ) );

// --- Feltene finnes på innstillingssiden ---
$felter = samlab_settings_fields();
foreach ( array( 'assistent_aktiv', 'assistent_navn', 'assistent_velkomst', 'assistent_tone', 'assistent_modell', 'assistent_kilder', 'assistent_nokkel' ) as $navn ) {
	if ( ! isset( $felter[ $navn ] ) ) {
		sjekk( "felt $navn finnes", false );
	}
}
sjekk( 'alle assistentfeltene er definert', isset( $felter['assistent_kilder'] ) );

// --- Sanitering ---
$ren = samlab_sanitize_settings(
	array(
		'assistent_aktiv'    => '1',
		'assistent_navn'     => '  Kompis <script>x</script> ',
		'assistent_velkomst' => "Hei!\n<em>Velkommen</em>",
		'assistent_modell'   => 'Claude-Opus-5!! ',
		'assistent_kilder'   => "https://example.no/om-oss\njavascript:alert(1)\n   \nftp://filer.example.no/x\nhttps://example.no/priser",
		'assistent_nokkel'   => 'forsok-pa-a-lagre-nokkel',
		'assistent_seksjon'  => 'forsok-pa-overskrift',
	)
);
sjekk( 'av/på saniteres', '1' === $ren['assistent_aktiv'] );
sjekk( 'navnet vaskes for HTML', 'Kompis' === $ren['assistent_navn'] );
sjekk( 'velkomstmeldingen vaskes', false === strpos( $ren['assistent_velkomst'], '<em>' ) );
sjekk( 'modell-ID-en vaskes til trygt tegnsett', 'claude-opus-5' === $ren['assistent_modell'] );
sjekk( 'kildelisten beholder kun http(s)-URL-er', "https://example.no/om-oss\nhttps://example.no/priser" === $ren['assistent_kilder'] );
sjekk( 'status-/overskriftsrader tar aldri imot verdier', ! isset( $ren['assistent_nokkel'] ) && ! isset( $ren['assistent_seksjon'] ) );

// --- Helpers: standarder uten innstillinger ---
sjekk( 'standardnavn', __( 'Assistenten', 'samlab' ) === samlab_assistent_navn() );
sjekk( 'standardmodell er claude-opus-5', 'claude-opus-5' === samlab_assistent_modell() );
sjekk( 'standardvelkomst finnes', '' !== samlab_assistent_velkomst() );
sjekk( 'ingen kilder som standard', array() === samlab_assistent_kilder() );

// --- Helpers: med lagrede innstillinger ---
$orig = get_option( 'samlab_settings', array() );
update_option( 'samlab_settings', array_merge( $orig, $ren ) );
sjekk( 'navnet leses fra innstillingen', 'Kompis' === samlab_assistent_navn() );
sjekk( 'kildene leses som liste', array( 'https://example.no/om-oss', 'https://example.no/priser' ) === samlab_assistent_kilder() );
sjekk( 'aktiv-bryteren leses', samlab_assistent_aktiv() );
update_option( 'samlab_settings', $orig );

// --- Nøkkelstatus: aldri selve nøkkelen ---
sjekk( 'ingen nøkkel i riggen', ! samlab_assistent_har_nokkel() );
sjekk( 'status sier ikke funnet', false !== strpos( samlab_assistent_nokkel_status(), 'Ikke funnet' ) );
define( 'SAMLAB_CLAUDE_API_KEY', 'sk-test-hemmelig-123' );
sjekk( 'nøkkelen oppdages fra konstanten', samlab_assistent_har_nokkel() );
sjekk( 'status sier funnet', false !== strpos( samlab_assistent_nokkel_status(), 'Funnet i wp-config.php' ) );
sjekk( 'statusteksten røper aldri nøkkelverdien', false === strpos( samlab_assistent_nokkel_status(), 'sk-test-hemmelig-123' ) );

exit( $fail );
