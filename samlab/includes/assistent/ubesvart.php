<?php
/**
 * Ubesvart-køen (G6): spørsmålene assistenten ikke fant svar på,
 * samlet anonymt til verten - løkken fra deckets slide 10, som
 * mater håndboken og FAQ-en.
 *
 * Personvernlinjen (avklaring 7): kun spørsmålstekst, dato og
 * teller lagres - aldri bruker-ID og aldri svaret. Samtaler logges
 * fortsatt aldri. Innstillingen assistent_ubesvart (standard på)
 * stopper all lagring når den er av.
 *
 * Lastes kun via modul.php (modulen på).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taket på køen - eldste innslag ryker først (FIFO).
 */
const SAMLAB_UBESVART_MAKS = 200;

/**
 * Maks lengde på ett lagret spørsmål, i tegn.
 */
const SAMLAB_UBESVART_TEKST_MAKS = 500;

/**
 * Køen, nyeste sist. Option med autoload av - køen trengs kun i
 * admin og ved registrering.
 *
 * @return array<int, array{sporsmal: string, dato: string, antall: int}>
 */
function samlab_ubesvart_liste() {
	$liste = get_option( 'samlab_ubesvart', array() );
	return is_array( $liste ) ? array_values( $liste ) : array();
}

/**
 * Lagrer køen med autoload av.
 *
 * @param array $liste Køen.
 * @return void
 */
function samlab_ubesvart_lagre( $liste ) {
	if ( false === get_option( 'samlab_ubesvart', false ) ) {
		add_option( 'samlab_ubesvart', array_values( $liste ), '', false );
		return;
	}
	update_option( 'samlab_ubesvart', array_values( $liste ), false );
}

/**
 * Normalisert form av et spørsmål, til dedupe: små bokstaver,
 * sammenslått blank og uten avsluttende tegnsetting.
 *
 * @param string $sporsmal Spørsmålet.
 * @return string
 */
function samlab_ubesvart_normaliser( $sporsmal ) {
	$tekst = mb_strtolower( trim( preg_replace( '/\s+/u', ' ', (string) $sporsmal ) ) );
	return rtrim( $tekst, " \t?.!" );
}

/**
 * Registrerer et ubesvart spørsmål i køen - anonymt: kun teksten,
 * dagens dato og en teller. Duplikater (normalisert) øker telleren
 * og flytter datoen; taket håndheves FIFO.
 *
 * @param string $sporsmal Medlemmets spørsmål.
 * @return bool Om noe ble lagret (false når innstillingen er av
 *              eller teksten er tom).
 */
function samlab_ubesvart_registrer( $sporsmal ) {
	if ( ! samlab_assistent_ubesvart_aktiv() ) {
		return false;
	}
	$sporsmal = mb_substr( sanitize_textarea_field( (string) $sporsmal ), 0, SAMLAB_UBESVART_TEKST_MAKS );
	$norm     = samlab_ubesvart_normaliser( $sporsmal );
	if ( '' === $norm ) {
		return false;
	}

	$liste = samlab_ubesvart_liste();
	foreach ( $liste as $indeks => $rad ) {
		if ( samlab_ubesvart_normaliser( $rad['sporsmal'] ) === $norm ) {
			$liste[ $indeks ]['antall'] = (int) $rad['antall'] + 1;
			$liste[ $indeks ]['dato']   = gmdate( 'Y-m-d' );
			samlab_ubesvart_lagre( $liste );
			return true;
		}
	}

	$liste[] = array(
		'sporsmal' => $sporsmal,
		'dato'     => gmdate( 'Y-m-d' ),
		'antall'   => 1,
	);
	if ( count( $liste ) > SAMLAB_UBESVART_MAKS ) {
		$liste = array_slice( $liste, - SAMLAB_UBESVART_MAKS );
	}
	samlab_ubesvart_lagre( $liste );
	return true;
}
