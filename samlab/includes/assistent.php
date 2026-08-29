<?php
/**
 * Assistenten (fase F) - bootstrap og innstillinger. Denne filen
 * lastes alltid (den eier av/på-bryteren og innstillingene), men
 * selve modulen (includes/assistent/modul.php med cron, REST og
 * widget) lastes KUN når modulen er slått på. Portalen fungerer
 * fullt ut uten assistenten.
 *
 * API-nøkkelen leses utelukkende fra konstanten
 * SAMLAB_CLAUDE_API_KEY i wp-config.php - aldri fra databasen, og
 * verdien vises aldri i admin (kun funnet/ikke funnet).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Om assistent-modulen er slått på (standard av).
 *
 * @return bool
 */
function samlab_assistent_aktiv() {
	return '1' === samlab_get_setting( 'assistent_aktiv' );
}

/**
 * Assistentens visningsnavn, standard «Assistenten».
 *
 * @return string
 */
function samlab_assistent_navn() {
	return samlab_get_setting( 'assistent_navn', __( 'Assistenten', 'samlab' ) );
}

/**
 * Velkomstmeldingen i chat-widgeten.
 *
 * @return string
 */
function samlab_assistent_velkomst() {
	return samlab_get_setting( 'assistent_velkomst', __( 'Hei! Spør meg om huset, medlemmene eller det praktiske.', 'samlab' ) );
}

/**
 * Toneinstruksen som legges i systemprompten (F3).
 *
 * @return string
 */
function samlab_assistent_tone() {
	return samlab_get_setting( 'assistent_tone', '' );
}

/**
 * Modellen kall går mot, standard claude-opus-5.
 *
 * @return string
 */
function samlab_assistent_modell() {
	return samlab_get_setting( 'assistent_modell', 'claude-opus-5' );
}

/**
 * Eksterne kunnskapskilder (URL-er) for kunnskaps-cronen (F2).
 *
 * @return string[]
 */
function samlab_assistent_kilder() {
	$linjer = explode( "\n", samlab_get_setting( 'assistent_kilder', '' ) );
	return array_values( array_filter( array_map( 'trim', $linjer ) ) );
}

/**
 * Om API-nøkkelen finnes i wp-config.php. Verdien leses aldri ut
 * her - kun om konstanten er satt og ikke-tom.
 *
 * @return bool
 */
function samlab_assistent_har_nokkel() {
	return defined( 'SAMLAB_CLAUDE_API_KEY' ) && '' !== (string) SAMLAB_CLAUDE_API_KEY;
}

/**
 * Statustekst for nøkkelen til innstillingssiden - aldri verdien.
 *
 * @return string
 */
function samlab_assistent_nokkel_status() {
	if ( samlab_assistent_har_nokkel() ) {
		return __( 'Funnet i wp-config.php (SAMLAB_CLAUDE_API_KEY er satt).', 'samlab' );
	}
	return __( 'Ikke funnet. Legg til define( \'SAMLAB_CLAUDE_API_KEY\', \'…\' ); i wp-config.php - nøkkelen lagres aldri i databasen.', 'samlab' );
}

/*
 * Selve modulen lastes kun når den er slått på - ingen
 * assistent-kode (cron, REST, widget) ellers.
 */
if ( samlab_assistent_aktiv() ) {
	require_once SAMLAB_PLUGIN_DIR . 'includes/assistent/modul.php';
}
