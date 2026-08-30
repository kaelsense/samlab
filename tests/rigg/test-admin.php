<?php
// Røyk-test for admin-laget: skjermregister, screen-gating, enqueue og
// kroppsklasse. Ingen fasebokstav i navnet - designarbeidet er ikke en
// egen fase i BACKLOG.md ennå.
//
// Testen bruker de faktisk registrerte hook-suffiksene fra
// samlab_admin_skjermer(), ikke gjettede navn som
// «settings_page_samlab» - hele poenget med registeret er at navnene
// ikke skal gjettes.

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

require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
set_current_screen( 'dashboard' );
do_action( 'admin_menu' );

$skjermer = samlab_admin_skjermer();
sjekk( 'de tre egne sidene er registrert', 3 === count( $skjermer ) );
sjekk( 'kontrollpanelet er med', in_array( 'toplevel_page_samlab-kontrollpanel', $skjermer, true ) );
sjekk( 'registeret tar ikke imot false fra manglende capability', ! in_array( false, $skjermer, true ) && ! in_array( '', $skjermer, true ) );

/**
 * Hjelper: sett skjerm, kjør enqueue-kroken, og rapporter tilstanden.
 *
 * @param string $id        Skjerm-ID.
 * @param string $post_type Post-type å tvinge på skjermen, om noen.
 * @return array{flate: string, lastet: bool, kropp: string}
 */
function samlab_test_admin_skjerm( $id, $post_type = '' ) {
	set_current_screen( $id );
	if ( '' !== $post_type ) {
		get_current_screen()->post_type = $post_type;
	}
	wp_styles()->queue = array();
	do_action( 'admin_enqueue_scripts', $id );
	return array(
		'flate'  => samlab_admin_flate(),
		'lastet' => wp_style_is( 'samlab-admin', 'enqueued' ),
		'kropp'  => trim( apply_filters( 'admin_body_class', '' ) ),
	);
}

// --- Egne sider ---
$alle_sider = true;
foreach ( $skjermer as $samlab_id ) {
	$res = samlab_test_admin_skjerm( $samlab_id );
	if ( 'side' !== $res['flate'] || ! $res['lastet'] || 'samlab-admin samlab-admin-side' !== $res['kropp'] ) {
		$alle_sider = false;
	}
}
sjekk( 'alle tre sidene laster stilarket og får kroppsklassen', $alle_sider );

// --- Listetabell og metaboks ---
$liste = samlab_test_admin_skjerm( 'edit-samlab_bedrift', 'samlab_bedrift' );
sjekk( 'listetabellen får flate og stilark', 'liste' === $liste['flate'] && $liste['lastet'] );
sjekk( 'listetabellens kroppsklasse', 'samlab-admin samlab-admin-liste' === $liste['kropp'] );

$boks = samlab_test_admin_skjerm( 'samlab_kobling', 'samlab_kobling' );
sjekk( 'editoren får flate og stilark', 'metaboks' === $boks['flate'] && $boks['lastet'] );

// Sideeditoren er med fordi håndbok-metaboksen sitter der.
$side = samlab_test_admin_skjerm( 'page', 'page' );
sjekk( 'sideeditoren regnes som metaboks-flate', 'metaboks' === $side['flate'] );

// --- Utenfor Samlab skal ingenting lastes ---
$dash = samlab_test_admin_skjerm( 'dashboard' );
sjekk( 'dashbordet laster ikke stilarket', '' === $dash['flate'] && ! $dash['lastet'] && '' === $dash['kropp'] );

$innlegg = samlab_test_admin_skjerm( 'edit-post', 'post' );
sjekk( 'innleggslisten laster ikke stilarket', '' === $innlegg['flate'] && ! $innlegg['lastet'] );

// --- Avhengigheten på core sine designsystem-tokens ---
$reg = wp_styles()->registered['samlab-admin'];
sjekk( 'stilarket er versjonert', SAMLAB_VERSION === $reg->ver );
sjekk(
	'wp-theme brukes som avhengighet når den finnes',
	wp_style_is( 'wp-theme', 'registered' )
		? array( 'wp-theme' ) === $reg->deps
		: array() === $reg->deps
);

// --- Hygienen fra fase 1 ---
set_current_screen( 'toplevel_page_samlab-kontrollpanel' );
ob_start();
samlab_render_kontrollpanel();
$html = ob_get_clean();
sjekk( 'kontrollpanelet har wp-header-end for varselplassering', false !== strpos( $html, 'wp-header-end' ) );
sjekk( 'kolonneoverskriftene har scope', false === strpos( $html, '<th>' ) );
sjekk( 'seksjonene har id-er å hoppe til', false !== strpos( $html, 'id="samlab-forslag"' ) && false !== strpos( $html, 'id="samlab-oppmerksomhet"' ) );
sjekk( 'ingen inline style igjen i kontrollpanelet', false === strpos( $html, 'style="' ) );

ob_start();
samlab_render_rapport();
$html = ob_get_clean();
sjekk( 'rapporten har wp-header-end', false !== strpos( $html, 'wp-header-end' ) );
sjekk( 'ingen inline style igjen i rapporten', false === strpos( $html, 'style="' ) );

// --- Fase 3: kontrollpanelet som kort med sammendrag ---
set_current_screen( 'toplevel_page_samlab-kontrollpanel' );
ob_start();
samlab_render_kontrollpanel();
$html = ob_get_clean();

$dom = new DOMDocument();
libxml_use_internal_errors( true );
$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="rot">' . $html . '</div>' );
libxml_clear_errors();
$xpath = new DOMXPath( $dom );

$kort = $xpath->query( '//div[contains(@class, "postbox")]/div[contains(@class, "inside")]/h2' );
sjekk( 'hver seksjon er sitt eget kort', $kort->length >= 6 );
sjekk( 'ingen h2 ligger utenfor et kort', $xpath->query( '//h2' )->length === $kort->length );

$lenker = $xpath->query( '//ul[contains(@class, "samlab-sammendrag")]/li/a' );
sjekk( 'sammendraget har fire tall', 4 === $lenker->length );
$ankere = array();
foreach ( $lenker as $samlab_a ) {
	$ankere[] = ltrim( $samlab_a->getAttribute( 'href' ), '#' );
}
$mal = array();
foreach ( $ankere as $samlab_anker ) {
	$mal[] = $xpath->query( '//*[@id="' . $samlab_anker . '"]' )->length;
}
sjekk( 'hvert sammendragstall peker på en seksjon som finnes', array( 1, 1, 1, 1 ) === $mal );
sjekk( 'sammendraget er lenker, ikke knapper', 0 === $xpath->query( '//ul[contains(@class, "samlab-sammendrag")]//button' )->length );

sjekk( 'oppmerksomhet er delt i fire grupper', 4 === $xpath->query( '//div[contains(@class, "samlab-oppmerksomhet")]/div[contains(@class, "samlab-oppmerksomhet-gruppe")]' )->length );

// Sammendraget skal gjenbruke listene seksjonene henter, ikke spørre på
// nytt. Hver statusgruppe skal hentes nøyaktig én gang; legger noen
// senere en count( samlab_kp_koblinger(...) ) i sammendraget, blir de
// hentet to ganger, og denne testen sier fra.
//
// get_posts() setter suppress_filters, så the_posts fyrer aldri -
// pre_get_posts er den som virker her.
$kobling_kall  = array();
$samlab_teller = function ( $q ) use ( &$kobling_kall ) {
	if ( 'samlab_kobling' !== $q->get( 'post_type' ) ) {
		return;
	}
	$mq             = $q->get( 'meta_query' );
	$kobling_kall[] = isset( $mq[0]['value'] ) ? implode( '+', (array) $mq[0]['value'] ) : 'uten';
};
add_action( 'pre_get_posts', $samlab_teller );
ob_start();
samlab_render_kontrollpanel();
ob_get_clean();
remove_action( 'pre_get_posts', $samlab_teller );

$tellinger = array_count_values( $kobling_kall );
$ventet    = array(
	'foreslatt'            => 1,
	'forespurt'            => 1,
	'godkjent+introdusert' => 1,
	'fulgt_opp'            => 1,
);
$faktisk = array_intersect_key( $tellinger, $ventet );
ksort( $faktisk );
ksort( $ventet );
sjekk( 'hver statusgruppe hentes nøyaktig én gang', $ventet === $faktisk );
// Den femte er samlab_kp_brukere_med_kobling(), som henter alle
// statuser for å finne nye uten introduksjon. Den er kjent og egen sak.
sjekk( 'ingen uventede koblingsspørringer', 5 === count( $kobling_kall ) );

// Fase 3b: hver brede tabell ligger i en merket, fokuserbar
// scroll-region. Uten den skyver firekolonners-tabellene hele siden ut
// i horisontal scroll ved 320 px.
//
// Testen lager sin egen kobling: uten data rendrer seksjonene «Ingen
// ...» og ingen tabeller, og da hadde testen bestått uten å teste noe.
$samlab_kobling = wp_insert_post(
	array(
		'post_type'   => 'samlab_kobling',
		'post_title'  => 'Testkobling for tabellramme',
		'post_status' => 'publish',
		'meta_input'  => array( '_samlab_status' => 'foreslatt' ),
	)
);
ob_start();
samlab_render_kontrollpanel();
$med_tabell = ob_get_clean();
wp_delete_post( $samlab_kobling, true );

$dom_t = new DOMDocument();
libxml_use_internal_errors( true );
$dom_t->loadHTML( '<?xml encoding="utf-8" ?><div id="rot">' . $med_tabell . '</div>' );
libxml_clear_errors();
$xt       = new DOMXPath( $dom_t );
$tabeller = $xt->query( '//table[contains(@class, "widefat")]' );
$rammet   = $xt->query( '//div[contains(@class, "samlab-tabellramme")]/table[contains(@class, "widefat")]' );
sjekk( 'det finnes en tabell å teste', $tabeller->length > 0 );
sjekk( 'alle brede tabeller ligger i en scroll-region', $tabeller->length === $rammet->length );

$rammer = $xt->query( '//div[contains(@class, "samlab-tabellramme")]' );
$merket = 0;
foreach ( $rammer as $samlab_r ) {
	if ( 'region' === $samlab_r->getAttribute( 'role' ) && '0' === $samlab_r->getAttribute( 'tabindex' ) && '' !== $samlab_r->getAttribute( 'aria-label' ) ) {
		++$merket;
	}
}
sjekk( 'scroll-regionene er navngitt og tastaturnåbare', $rammer->length > 0 && $rammer->length === $merket );

// Tekstene testene i G4/G5/G7 henger på skal være urørt.
sjekk( 'seksjonsoverskriftene er uendret', false !== strpos( $html, 'Foreslåtte koblinger' ) && false !== strpos( $html, 'Trenger oppmerksomhet' ) );

// --- Fase 4: rapporten ---
set_current_screen( 'kontrollpanel_page_samlab-rapport' );
ob_start();
samlab_render_rapport();
$html = ob_get_clean();

$dom_r = new DOMDocument();
libxml_use_internal_errors( true );
$dom_r->loadHTML( '<?xml encoding="utf-8" ?><div id="rot">' . $html . '</div>' );
libxml_clear_errors();
$xr = new DOMXPath( $dom_r );

$perioder = $xr->query( '//ul[contains(@class, "subsubsub")]/li/a' );
sjekk( 'periodevalget er core sin subsubsub', 3 === $perioder->length );
$aktive = $xr->query( '//ul[contains(@class, "subsubsub")]/li/a[@aria-current="page"]' );
sjekk( 'aktiv periode er merket med aria-current', 1 === $aktive->length && false !== strpos( $aktive->item( 0 )->getAttribute( 'class' ), 'current' ) );
sjekk( 'alle periodene er lenker, også den aktive', 0 === $xr->query( '//ul[contains(@class, "subsubsub")]//strong' )->length );

$ruter = $xr->query( '//ul[contains(@class, "samlab-sammendrag")]/li' );
sjekk( 'rapporten har en sammendragsrad', 4 === $ruter->length );
sjekk( 'rapportens sammendrag er ikke lenker - det er ingen seksjoner å hoppe til', 0 === $xr->query( '//ul[contains(@class, "samlab-sammendrag")]//a' )->length );

sjekk( 'tallkolonnen er merket', $xr->query( '//td[contains(@class, "samlab-tallkolonne")]' )->length > 10 );

// Sammendraget må stemme med tabellen - ellers forteller de to delene
// av samme side ulike historier.
$fra_tabell = array();
foreach ( $xr->query( '//table//tr' ) as $samlab_tr ) {
	$celler = $samlab_tr->getElementsByTagName( 'td' );
	if ( 2 === $celler->length ) {
		$fra_tabell[ trim( $celler->item( 0 )->textContent ) ] = trim( $celler->item( 1 )->textContent );
	}
}
$stemmer = true;
foreach ( $ruter as $samlab_rute ) {
	$t = trim( $xr->query( './/span[contains(@class, "samlab-sammendrag-tall")]', $samlab_rute )->item( 0 )->textContent );
	$e = trim( $xr->query( './/span[contains(@class, "samlab-sammendrag-etikett")]', $samlab_rute )->item( 0 )->textContent );
	if ( ! isset( $fra_tabell[ $e ] ) || $fra_tabell[ $e ] !== $t ) {
		$stemmer = false;
	}
}
sjekk( 'sammendragstallene stemmer med tabellraden de speiler', $stemmer );

// --- Fase 2: innstillingssiden som kortstabel ---
set_current_screen( 'admin_page_samlab' );
ob_start();
samlab_render_settings_page();
$html = ob_get_clean();

$felter     = samlab_settings_fields();
$overskrift = 0;
$verdifelt  = 0;
foreach ( $felter as $samlab_n => $samlab_f ) {
	if ( 'overskrift' === $samlab_f['type'] ) {
		++$overskrift;
		continue;
	}
	if ( 'status' === $samlab_f['type'] ) {
		continue;
	}
	++$verdifelt;
	if ( false === strpos( $html, 'name="samlab_settings[' . $samlab_n . ']"' ) ) {
		sjekk( "felt $samlab_n rendres fortsatt", false );
	}
}
sjekk( 'alle verdifeltene rendres', $verdifelt > 20 && false !== strpos( $html, 'name="samlab_settings[assistent_kilder]"' ) );
sjekk( 'siden er delt i seksjoner', $overskrift >= 5 );

// Markupen må være balansert - kortene åpnes og lukkes på tvers av
// PHP-blokker, og en glemt </div> ville dratt resten av siden inn i
// det siste kortet.
$dom = new DOMDocument();
libxml_use_internal_errors( true );
$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="rot">' . $html . '</div>' );
libxml_clear_errors();
$xpath  = new DOMXPath( $dom );
$kort   = $xpath->query( '//div[contains(@class, "postbox")]' );
$innsdt = $xpath->query( '//div[contains(@class, "postbox")]/div[contains(@class, "inside")]/table[contains(@class, "form-table")]' );
sjekk( 'hvert seksjonskort har sin egen form-table', $kort->length >= $overskrift && $innsdt->length === $overskrift );
sjekk( 'lagre-knappen ligger utenfor kortene', 1 === $xpath->query( '//form/p[contains(@class, "submit")]' )->length );
sjekk( 'skjemaet er ikke fanget inne i et kort', 0 === $xpath->query( '//div[contains(@class, "postbox")]//form[contains(@action, "options.php")]' )->length );

// --- Verdiene må fortsatt overleve en lagring ---
// Dette er grunnen til at siden IKKE fikk faner: saniteringen bygger
// fra tom array, så felt som ikke er med i innsendingen forsvinner.
$inn = array();
foreach ( $felter as $samlab_n => $samlab_f ) {
	if ( in_array( $samlab_f['type'], array( 'overskrift', 'status' ), true ) ) {
		continue;
	}
	switch ( $samlab_f['type'] ) {
		case 'avkryssing':
			$inn[ $samlab_n ] = '1';
			break;
		case 'farge':
			$inn[ $samlab_n ] = '#123456';
			break;
		case 'url':
		case 'urlliste':
			$inn[ $samlab_n ] = 'https://example.no/';
			break;
		case 'ukedag':
			$inn[ $samlab_n ] = '3';
			break;
		case 'valg':
			$inn[ $samlab_n ] = (string) array_key_first( $samlab_f['valg'] );
			break;
		case 'modell':
			$inn[ $samlab_n ] = 'claude-opus-5';
			break;
		default:
			$inn[ $samlab_n ] = 'testverdi';
	}
}
$ut      = samlab_sanitize_settings( $inn );
$mangler = array_diff( array_keys( $inn ), array_keys( $ut ) );
sjekk( 'alle innsendte felt overlever saniteringen', array() === $mangler );
sjekk( 'seksjonsradene tar ikke imot verdier', ! isset( $ut['portal_seksjon'] ) && ! isset( $ut['flater_seksjon'] ) );

exit( $fail );
