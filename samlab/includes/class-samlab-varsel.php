<?php
/**
 * Modellklasse for in-app-varsler (egen tabell, prepared statements).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for varsler.
 */
class Samlab_Varsel {

	/**
	 * Oppretter et varsel. Varsler aldri en om egne handlinger, og
	 * dedupliserer mot identiske uleste varsler.
	 *
	 * @param array $args user_id (mottaker), type, object_type,
	 *                    object_id, actor_id.
	 * @return int|false Varselets ID, false ved hopp/feil.
	 */
	public static function create( $args ) {
		global $wpdb;

		$user_id  = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;
		$type     = isset( $args['type'] ) ? sanitize_key( $args['type'] ) : '';
		$obj_type = isset( $args['object_type'] ) ? sanitize_key( $args['object_type'] ) : 'innlegg';
		$obj_id   = isset( $args['object_id'] ) ? absint( $args['object_id'] ) : 0;
		$actor_id = isset( $args['actor_id'] ) ? absint( $args['actor_id'] ) : 0;

		if ( ! $user_id || '' === $type || $user_id === $actor_id ) {
			return false;
		}

		$tabell = samlab_table( 'varsler' );
		$finnes = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell.
			$wpdb->prepare(
				"SELECT id FROM {$tabell} WHERE user_id = %d AND type = %s AND object_type = %s AND object_id = %d AND actor_id = %d AND read_at IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table().
				$user_id,
				$type,
				$obj_type,
				$obj_id,
				$actor_id
			)
		);
		if ( $finnes ) {
			return false;
		}

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Egen tabell, wpdb::insert preparerer selv.
			$tabell,
			array(
				'user_id'     => $user_id,
				'type'        => $type,
				'object_type' => $obj_type,
				'object_id'   => $obj_id,
				'actor_id'    => $actor_id,
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Henter en brukers varsler, nyeste først.
	 *
	 * @param int $user_id Mottakeren.
	 * @param int $limit   Maks antall (1-100).
	 * @return object[]
	 */
	public static function for_user( $user_id, $limit = 20 ) {
		global $wpdb;
		$tabell = samlab_table( 'varsler' );

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell.
			$wpdb->prepare(
				"SELECT * FROM {$tabell} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table().
				absint( $user_id ),
				min( 100, max( 1, absint( $limit ) ) )
			)
		);
	}

	/**
	 * Antall uleste varsler for en bruker.
	 *
	 * @param int $user_id Mottakeren.
	 * @return int
	 */
	public static function unread_count( $user_id ) {
		global $wpdb;
		$tabell = samlab_table( 'varsler' );

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tabell} WHERE user_id = %d AND read_at IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table().
				absint( $user_id )
			)
		);
	}

	/**
	 * Markerer alle en brukers varsler som lest.
	 *
	 * @param int $user_id Mottakeren.
	 * @return int Antall som ble markert.
	 */
	public static function mark_all_read( $user_id ) {
		global $wpdb;
		$tabell = samlab_table( 'varsler' );

		$antall = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell.
			$wpdb->prepare(
				"UPDATE {$tabell} SET read_at = %s WHERE user_id = %d AND read_at IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table().
				gmdate( 'Y-m-d H:i:s' ),
				absint( $user_id )
			)
		);
		return (int) $antall;
	}

	/**
	 * Fjerner alle varsler knyttet til et objekt (ved sletting).
	 *
	 * @param string $obj_type Objekttype.
	 * @param int    $obj_id   Objektets ID.
	 * @return void
	 */
	public static function remove_for_object( $obj_type, $obj_id ) {
		global $wpdb;

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell, wpdb::delete preparerer selv.
			samlab_table( 'varsler' ),
			array(
				'object_type' => sanitize_key( $obj_type ),
				'object_id'   => absint( $obj_id ),
			),
			array( '%s', '%d' )
		);
	}
}
