<?php
/**
 * Plugin Name:       Samlab
 * Plugin URI:        https://digitelle.no/samlab
 * Description:       Intern community-portal for coworking-hus og kontorfellesskap - katalog, behov og tilbud, vegg og håndbok.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Digitelle AS
 * Author URI:        https://digitelle.no
 * License:           TODO - lisensvalg tas i fase 0 (se AVKLARINGER.md)
 * Text Domain:       samlab
 * Domain Path:       /languages
 *
 * @package Samlab
 */

// Direkte kall skal ikke gi output.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAMLAB_VERSION', '0.1.0' );
define( 'SAMLAB_PLUGIN_FILE', __FILE__ );
define( 'SAMLAB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAMLAB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Laster oversettelser for pluginen.
 *
 * @return void
 */
function samlab_load_textdomain() {
	load_plugin_textdomain( 'samlab', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'samlab_load_textdomain' );

/*
 * Modulene i includes/ registreres her etter hvert som de bygges
 * (post-types, roles, rewrites, rest-api, access - se planens kap. 3).
 */
