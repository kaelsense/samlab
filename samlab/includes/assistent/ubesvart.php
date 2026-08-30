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

/**
 * Fjerner et spørsmål fra køen (håndtert, G7). Treffer på
 * normalisert tekst - samme nøkkel som dedupen.
 *
 * @param string $sporsmal Spørsmålet.
 * @return bool Om noe ble fjernet.
 */
function samlab_ubesvart_fjern( $sporsmal ) {
	$norm = samlab_ubesvart_normaliser( $sporsmal );
	if ( '' === $norm ) {
		return false;
	}
	$liste = samlab_ubesvart_liste();
	foreach ( $liste as $indeks => $rad ) {
		if ( samlab_ubesvart_normaliser( $rad['sporsmal'] ) === $norm ) {
			unset( $liste[ $indeks ] );
			samlab_ubesvart_lagre( $liste );
			return true;
		}
	}
	return false;
}

/**
 * Oppretter et håndbok-utkast fra et ubesvart spørsmål og fjerner
 * det fra køen (G7). Løkken fra deckets slide 10 lukkes av F2:
 * neste kunnskapsbygg tar med siden når den publiseres, og
 * assistenten kan svare.
 *
 * @param string $sporsmal Spørsmålet (blir sidens tittel).
 * @param int    $user_id  Forfatteren av utkastet.
 * @return int|WP_Error Sidens post-ID.
 */
function samlab_ubesvart_til_handbok( $sporsmal, $user_id ) {
	$tittel = sanitize_text_field( (string) $sporsmal );
	if ( '' === $tittel ) {
		return new WP_Error( 'samlab_tomt_sporsmal', __( 'Spørsmålet er tomt.', 'samlab' ) );
	}

	$side_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'draft',
			'post_title'  => $tittel,
			'post_author' => absint( $user_id ),
		),
		true
	);
	if ( is_wp_error( $side_id ) ) {
		return $side_id;
	}
	update_post_meta( $side_id, '_samlab_handbok', '1' );
	samlab_ubesvart_fjern( $sporsmal );
	return $side_id;
}

/**
 * Mottak fra admin-post.php: «håndtert» fjerner spørsmålet fra
 * køen. Bak nonce + koblings-capability (kontrollpanelets flate).
 *
 * @return void
 */
function samlab_ubesvart_handtert_post() {
	$nonce = isset( $_POST['samlab_ubesvart_nonce'] ) ? sanitize_key( wp_unslash( $_POST['samlab_ubesvart_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'samlab_ubesvart_handling' ) || ! current_user_can( 'edit_samlab_koblinger' ) ) {
		wp_die( esc_html__( 'Ugyldig eller utløpt skjema - gå tilbake og prøv igjen.', 'samlab' ), '', 403 );
	}

	$sporsmal = isset( $_POST['samlab_sporsmal'] ) ? sanitize_textarea_field( wp_unslash( $_POST['samlab_sporsmal'] ) ) : '';
	samlab_ubesvart_fjern( $sporsmal );

	wp_safe_redirect( add_query_arg( 'samlab_ubesvart', 'handtert', admin_url( 'admin.php?page=samlab-kontrollpanel' ) ) );
	exit;
}
add_action( 'admin_post_samlab_ubesvart_handtert', 'samlab_ubesvart_handtert_post' );

/**
 * Mottak fra admin-post.php: «legg i håndboken» oppretter utkastet
 * og sender verten rett til redigeringen. Krever i tillegg
 * edit_pages - utkastet skal kunne redigeres av den som lager det.
 *
 * @return void
 */
function samlab_ubesvart_handbok_post() {
	$nonce = isset( $_POST['samlab_ubesvart_nonce'] ) ? sanitize_key( wp_unslash( $_POST['samlab_ubesvart_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'samlab_ubesvart_handling' ) || ! current_user_can( 'edit_samlab_koblinger' ) || ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'Ugyldig eller utløpt skjema - gå tilbake og prøv igjen.', 'samlab' ), '', 403 );
	}

	$sporsmal = isset( $_POST['samlab_sporsmal'] ) ? sanitize_textarea_field( wp_unslash( $_POST['samlab_sporsmal'] ) ) : '';
	$side_id  = samlab_ubesvart_til_handbok( $sporsmal, get_current_user_id() );
	if ( is_wp_error( $side_id ) ) {
		wp_die( esc_html( $side_id->get_error_message() ), '', 400 );
	}

	wp_safe_redirect( admin_url( 'post.php?post=' . (int) $side_id . '&action=edit' ) );
	exit;
}
add_action( 'admin_post_samlab_ubesvart_handbok', 'samlab_ubesvart_handbok_post' );
