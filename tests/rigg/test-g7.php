<?php
// Røyk-test for G7: ubesvart-køen i kontrollpanelet og hele løkken
// fra slide 10 - spørsmål i kø → håndbok-side publisert →
// kunnskapsbygg → grunnlaget inneholder svaret - og køen er tom
// etter håndtering. Krever modulen PÅ (kjøres i f2-f4-gruppen).
// Kjøres med: wp eval-file test-g7.php

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

$moderator = get_user_by( 'login', 'testmod' );
delete_option( 'samlab_ubesvart' );

function g7_render_kp() {
	ob_start();
	samlab_render_kontrollpanel();
	return ob_get_clean();
}

// 1) Spørsmål i kø vises i kontrollpanelet med handlinger.
samlab_ubesvart_registrer( 'Hvor leverer jeg pakkeretur?' );
samlab_ubesvart_registrer( 'hvor leverer jeg pakkeretur' );
wp_set_current_user( 1 );
$html = g7_render_kp();
sjekk( 'seksjonen vises med spørsmålet', false !== strpos( $html, 'Ubesvarte spørsmål til assistenten' ) && false !== strpos( $html, 'Hvor leverer jeg pakkeretur?' ) );
sjekk( 'telleren vises (dedupe fra G6)', false !== strpos( $html, '<td>2</td>' ) );
sjekk( 'admin ser begge knappene', false !== strpos( $html, 'Legg i håndboken' ) && false !== strpos( $html, 'Håndtert' ) );

// 2) Moderator uten edit_pages ser kun håndtert-knappen.
wp_set_current_user( $moderator->ID );
$html = g7_render_kp();
sjekk( 'moderator ser køen men ikke håndbok-knappen', false !== strpos( $html, 'Hvor leverer jeg pakkeretur?' ) && false === strpos( $html, 'Legg i håndboken' ) && false !== strpos( $html, 'Håndtert' ) );

// 3) «Legg i håndboken»: utkast med flagg, forfatter og tom kø.
wp_set_current_user( 1 );
$side_id = samlab_ubesvart_til_handbok( 'Hvor leverer jeg pakkeretur?', 1 );
sjekk( 'håndbok-utkast opprettet', ! is_wp_error( $side_id ) && $side_id > 0 );
$side = get_post( $side_id );
sjekk( 'utkastet er en side med spørsmålet som tittel', 'page' === $side->post_type && 'draft' === $side->post_status && 'Hvor leverer jeg pakkeretur?' === $side->post_title );
sjekk( 'utkastet er håndbok-merket med forfatter', '1' === get_post_meta( $side_id, '_samlab_handbok', true ) && 1 === (int) $side->post_author );
sjekk( 'spørsmålet er ute av køen', array() === samlab_ubesvart_liste() );
sjekk( 'tomt spørsmål avvises', is_wp_error( samlab_ubesvart_til_handbok( '   ', 1 ) ) );

// 4) Løkken lukkes: publiser svaret, bygg kunnskap, assistenten
// har grunnlaget.
wp_update_post(
	array(
		'ID'           => $side_id,
		'post_status'  => 'publish',
		'post_content' => 'Pakkereturer leveres i resepsjonen i 1. etasje, hverdager 08-16.',
	)
);
samlab_assistent_bygg_kunnskap();
$grunnlag = samlab_assistent_kunnskap();
sjekk( 'kunnskapsbygget tar med den nye siden', false !== strpos( $grunnlag['tekst'], 'Hvor leverer jeg pakkeretur?' ) && false !== strpos( $grunnlag['tekst'], 'Pakkereturer leveres i resepsjonen' ) );

// 5) Håndtert-veien: fjerner innslaget, idempotent.
samlab_ubesvart_registrer( 'Finnes det parkering?' );
sjekk( 'håndtert fjerner spørsmålet', true === samlab_ubesvart_fjern( 'finnes det PARKERING???' ) && array() === samlab_ubesvart_liste() );
sjekk( 'fjerning av ukjent spørsmål er nei', false === samlab_ubesvart_fjern( 'Finnes det parkering?' ) );

// 6) Tom kø gir rolig melding.
$html = g7_render_kp();
sjekk( 'tom kø sier at grunnlaget holder', false !== strpos( $html, 'Ingen ubesvarte spørsmål' ) );

// Rydd: siden vekk og grunnlaget bygget på nytt uten den.
wp_delete_post( $side_id, true );
samlab_assistent_bygg_kunnskap();
delete_option( 'samlab_ubesvart' );

exit( $fail );
