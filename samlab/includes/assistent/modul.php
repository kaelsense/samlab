<?php
/**
 * Assistent-modulen: lastes KUN når modulen er slått på i
 * innstillingene (se includes/assistent.php). Kunnskaps-cronen
 * (F2), REST-endepunktet (F3) og chat-widgeten (F4) kobles inn
 * her etter hvert som de bygges.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/kunnskap.php';
require_once __DIR__ . '/api.php';

/**
 * Markør for at modulen er lastet - brukes av røyk-testene for å
 * verifisere at av/på-bryteren faktisk styrer lastingen.
 *
 * @return bool
 */
function samlab_assistent_modul_lastet() {
	return true;
}
