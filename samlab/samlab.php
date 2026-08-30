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
require_once SAMLAB_PLUGIN_DIR . 'includes/arrangementer.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/koblinger.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/rewrites.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/access.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/skjerm.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/forms.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/rest-api.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/search.php';
require_once SAMLAB_PLUGIN_DIR . 'admin/assets.php';
require_once SAMLAB_PLUGIN_DIR . 'admin/settings.php';
require_once SAMLAB_PLUGIN_DIR . 'admin/kontrollpanel.php';
require_once SAMLAB_PLUGIN_DIR . 'admin/rapport.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/database.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/class-samlab-innlegg.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/class-samlab-reaksjon.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/class-samlab-stemme.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/class-samlab-varsel.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/varsler.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/assistent.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/matching.php';
require_once SAMLAB_PLUGIN_DIR . 'includes/ukesbrev.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once SAMLAB_PLUGIN_DIR . 'includes/class-samlab-cli-command.php';
}

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
	samlab_register_arrangement();
	samlab_ensure_retning_terms();
	samlab_create_tables();
	samlab_add_roles();
	if ( ! wp_next_scheduled( 'samlab_matching' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'samlab_matching' );
	}
	if ( ! wp_next_scheduled( 'samlab_ukesbrev' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'samlab_ukesbrev' );
	}
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
	wp_clear_scheduled_hook( 'samlab_matching' );
	wp_clear_scheduled_hook( 'samlab_ukesbrev' );
	wp_clear_scheduled_hook( 'samlab_assistent_kunnskap' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'samlab_deactivate' );
