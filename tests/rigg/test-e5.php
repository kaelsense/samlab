<?php
// Røyk-test for E5: ukesbrevet mot seed-data, med mail-mock.
// wp_mail fanges med pre_wp_mail-filteret så ingenting sendes.
// Kjøres med: wp eval-file test-e5.php

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

// --- Mail-mock: fang alle wp_mail-kall ---
$mails = array();
add_filter(
	'pre_wp_mail',
	function ( $ignorert, $atts ) use ( &$mails ) {
		$mails[] = $atts;
		return true;
	},
	10,
	2
);

// --- Oppsett: innstillinger, testdata, reservasjon ---
$orig_settings = get_option( 'samlab_settings', array() );
$idag          = (int) wp_date( 'N' );
update_option(
	'samlab_settings',
	array_merge(
		$orig_settings,
		array(
			'ukesbrev_aktiv'    => '1',
			'ukesbrev_ukedag'   => (string) $idag,
			'ukesbrev_avsender' => 'Verten i huset',
		)
	)
);
delete_option( 'samlab_ukesbrev_sist' );

$kari    = get_user_by( 'login', 'kari.demo' );
$jonas   = get_user_by( 'login', 'jonas.demo' );
$medlem  = get_user_by( 'login', 'testmedlem' );
$innlegg = Samlab_Innlegg::create(
	array(
		'user_id' => $kari->ID,
		'content' => 'Ukens testinnlegg fra riggen om kaffemaskinen',
	)
);
$fersk   = wp_insert_user(
	array(
		'user_login'   => 'fersk.testbruker',
		'user_email'   => 'fersk@example.com',
		'user_pass'    => wp_generate_password(),
		'display_name' => 'Fersk Testbruker',
		'role'         => 'samlab_member',
	)
);
update_user_meta( $medlem->ID, 'samlab_ukesbrev_reservert', '1' );

// --- Innholdet ---
sjekk( 'avsendernavnet leses fra innstillingen', 'Verten i huset' === samlab_ukesbrev_avsendernavn() );
sjekk( 'ukedagen leses fra innstillingen', $idag === samlab_ukesbrev_ukedag() );

$antall = samlab_send_ukesbrev();
sjekk( 'brevet ble sendt til minst to mottakere', $antall >= 2 && count( $mails ) === $antall );

$mottakere = wp_list_pluck( $mails, 'to' );
sjekk( 'medlem uten reservasjon får brevet', in_array( $jonas->user_email, $mottakere, true ) );
sjekk( 'reservert medlem får IKKE brevet', ! in_array( $medlem->user_email, $mottakere, true ) );

$emne  = $mails[0]['subject'];
$tekst = $mails[0]['message'];
sjekk( 'emnet nevner portalnavnet', false !== strpos( $emne, samlab_portal_name() ) );
sjekk( 'brevet har seed-behovet', false !== strpos( $tekst, 'Fotograf til kundecaser' ) );
sjekk( 'brevet har det nye innlegget', false !== strpos( $tekst, 'kaffemaskinen' ) && false !== strpos( $tekst, $kari->display_name ) );
sjekk( 'brevet har det nye medlemmet', false !== strpos( $tekst, 'Fersk Testbruker' ) );
sjekk( 'brevet lenker til portalen', false !== strpos( $tekst, samlab_portal_url() ) );
sjekk( 'brevet forklarer reservasjon', false !== strpos( $tekst, 'Reserver deg' ) );
sjekk( 'brevet er ren tekst uten HTML', wp_strip_all_tags( $tekst ) === trim( $tekst ) );
sjekk( 'avsenderfilteret ryddes etter sending', false === has_filter( 'wp_mail_from_name', 'samlab_ukesbrev_avsendernavn' ) );
sjekk( 'sist-sendt ble registrert', (int) get_option( 'samlab_ukesbrev_sist' ) > 0 );

// --- Tick-vaktene ---
$mails = array();
samlab_ukesbrev_tick();
sjekk( 'tick sender ikke igjen samme uke', array() === $mails );

update_option( 'samlab_ukesbrev_sist', time() - 8 * DAY_IN_SECONDS, false );
$s                    = get_option( 'samlab_settings' );
$s['ukesbrev_ukedag'] = (string) ( ( $idag % 7 ) + 1 );
update_option( 'samlab_settings', $s );
samlab_ukesbrev_tick();
sjekk( 'tick sender ikke på feil ukedag', array() === $mails );

$s['ukesbrev_ukedag'] = (string) $idag;
unset( $s['ukesbrev_aktiv'] );
update_option( 'samlab_settings', $s );
samlab_ukesbrev_tick();
sjekk( 'tick sender ikke når brevet er av', array() === $mails );

$s['ukesbrev_aktiv'] = '1';
update_option( 'samlab_settings', $s );
samlab_ukesbrev_tick();
sjekk( 'tick sender på riktig dag når uken er omme', count( $mails ) >= 2 );

// --- Tomt brev sendes ikke ---
$mails = array();
add_filter( 'samlab_ukesbrev_seksjoner', '__return_empty_array', 99 );
sjekk( 'tomt brev sendes ikke', 0 === samlab_send_ukesbrev() && array() === $mails );
remove_filter( 'samlab_ukesbrev_seksjoner', '__return_empty_array', 99 );

// --- Planlegging/avplanlegging ---
sjekk( 'samlab_ukesbrev er planlagt daglig', false !== wp_next_scheduled( 'samlab_ukesbrev' ) );
samlab_deactivate();
sjekk( 'deaktivering avplanlegger jobben', false === wp_next_scheduled( 'samlab_ukesbrev' ) );
samlab_activate();
sjekk( 'aktivering planlegger jobben igjen', false !== wp_next_scheduled( 'samlab_ukesbrev' ) );

// --- Rydd ---
update_option( 'samlab_settings', $orig_settings );
delete_option( 'samlab_ukesbrev_sist' );
delete_user_meta( $medlem->ID, 'samlab_ukesbrev_reservert' );
wp_delete_user( $fersk );
Samlab_Innlegg::delete( $innlegg );
exit( $fail );
