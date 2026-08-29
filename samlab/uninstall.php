<?php
/**
 * Avinstallering: fjerner roller, capabilities og pluginens options.
 *
 * Innhold (bedrifter, behov, vegginnlegg) beholdes bevisst - sletting
 * av data er en menneskelig beslutning, ikke en bieffekt av å fjerne
 * pluginen.
 *
 * @package Samlab
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/roles.php';

samlab_remove_roles();

delete_option( 'samlab_version' );
