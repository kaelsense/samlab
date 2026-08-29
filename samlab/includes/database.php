<?php
/**
 * Databaseskjema for pluginens egne tabeller (hybridmodellen):
 * vegginnlegg og reaksjoner er høyfrekvent innhold og bor i egne
 * tabeller, ikke som CPT-er.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SAMLAB_DB_VERSION = '2';

/**
 * Fullt tabellnavn med nettstedets prefiks.
 *
 * @param string $tabell Basisnavn uten prefiks, f.eks. «innlegg».
 * @return string
 */
function samlab_table( $tabell ) {
	global $wpdb;
	return $wpdb->prefix . 'samlab_' . $tabell;
}

/**
 * Oppretter/oppdaterer tabellene med dbDelta. Kalles ved aktivering,
 * og ved behov når SAMLAB_DB_VERSION økes.
 *
 * @return void
 */
function samlab_create_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset    = $wpdb->get_charset_collate();
	$innlegg    = samlab_table( 'innlegg' );
	$reaksjoner = samlab_table( 'reaksjoner' );
	$varsler    = samlab_table( 'varsler' );

	dbDelta(
		"CREATE TABLE {$innlegg} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			bedrift_id bigint(20) unsigned NOT NULL DEFAULT 0,
			content longtext NOT NULL,
			image_id bigint(20) unsigned NOT NULL DEFAULT 0,
			pinned tinyint(1) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'publish',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status_pinned_created (status,pinned,created_at)
		) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$reaksjoner} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_type varchar(20) NOT NULL DEFAULT 'innlegg',
			object_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			reaction varchar(32) NOT NULL DEFAULT 'like',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unik_reaksjon (object_type,object_id,user_id,reaction),
			KEY objekt (object_type,object_id)
		) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$varsler} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(32) NOT NULL,
			object_type varchar(20) NOT NULL DEFAULT 'innlegg',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			read_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY mottaker (user_id,read_at),
			KEY objekt (object_type,object_id)
		) {$charset};"
	);

	update_option( 'samlab_db_version', SAMLAB_DB_VERSION );
}

/**
 * Kjører skjemaoppdatering når databaseversjonen er bak koden
 * (f.eks. etter plugin-oppdatering uten reaktivering).
 *
 * @return void
 */
function samlab_maybe_upgrade_tables() {
	if ( get_option( 'samlab_db_version' ) !== SAMLAB_DB_VERSION ) {
		samlab_create_tables();
	}
}
add_action( 'admin_init', 'samlab_maybe_upgrade_tables' );
