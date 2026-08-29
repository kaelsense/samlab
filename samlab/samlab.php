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
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
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
 * (post-types, rewrites, rest-api, access - se planens kap. 3).
 */
require_once SAMLAB_PLUGIN_DIR . 'includes/roles.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/post-types.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/rewrites.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/access.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/database.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/class-samlab-innlegg.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/class-samlab-reaksjon.php';

/**
 * Aktivering: registrer pluginens rewrite-regler og flush dem én gang.
 *
 * Flush er dyrt og skal kun skje her - aldri på vanlige requests.
 *
 * @return void
 */
function samlab_activate() {
	// Rewrite-modulen kommer i B7; kalles her slik at reglene er
	// registrert før flushen når modulen finnes.
	if ( function_exists( 'samlab_register_rewrites' ) ) {
		samlab_register_rewrites();
	}
	// Aktivering skjer før init - registrer innholdstypene eksplisitt
	// så term-seeding og flush ser dem.
	samlab_register_bedrift();
	samlab_register_behov();
	samlab_ensure_retning_terms();
	samlab_create_tables();
	samlab_add_roles();
	flush_rewrite_rules();
	update_option( 'samlab_version', SAMLAB_VERSION );
}
register_activation_hook( __FILE__, 'samlab_activate' );

/**
 * Deaktivering: fjern pluginens rewrite-regler fra cachen.
 *
 * Innhold og innstillinger beholdes - opprydding av data hører til
 * avinstallering (uninstall.php, B2).
 *
 * @return void
 */
function samlab_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'samlab_deactivate' );
