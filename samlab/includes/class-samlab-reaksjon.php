<?php
/**
 * Modellklasse for reaksjoner (egen tabell, prepared statements).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for reaksjoner på innlegg (og senere andre objekttyper).
 */
class Samlab_Reaksjon {

	/**
	 * Vasker objekttypen til et kjent sett.
	 *
	 * @param string $type Objekttype.
	 * @return string
	 */
	private static function object_type( $type ) {
		return in_array( $type, array( 'innlegg', 'kommentar' ), true ) ? $type : 'innlegg';
	}

	/**
	 * Legger til en reaksjon. Idempotent per (objekt, bruker, reaksjon).
	 *
	 * @param string $type     Objekttype, f.eks. «innlegg».
	 * @param int    $obj_id   Objektets ID.
	 * @param int    $user_id  Brukeren som reagerer.
	 * @param string $reaction Reaksjonsnøkkel, f.eks. «like».
	 * @return bool
	 */
	public static function add( $type, $obj_id, $user_id, $reaction = 'like' ) {
		global $wpdb;

		$obj_id  = absint( $obj_id );
		$user_id = absint( $user_id );
		if ( ! $obj_id || ! $user_id ) {
			return false;
		}

		if ( self::user_has( $type, $obj_id, $user_id, $reaction ) ) {
			return true;
		}

		return (bool) $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Egen tabell, wpdb::insert preparerer selv.
			samlab_table( 'reaksjoner' ),
			array(
				'object_type' => self::object_type( $type ),
				'object_id'   => $obj_id,
				'user_id'     => $user_id,
				'reaction'    => sanitize_key( $reaction ),
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Fjerner en brukers reaksjon.
	 *
	 * @param string $type     Objekttype.
	 * @param int    $obj_id   Objektets ID.
	 * @param int    $user_id  Brukeren.
	 * @param string $reaction Reaksjonsnøkkel.
	 * @return bool
	 */
	public static function remove( $type, $obj_id, $user_id, $reaction = 'like' ) {
		global $wpdb;

		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell, wpdb::delete preparerer selv.
			samlab_table( 'reaksjoner' ),
			array(
				'object_type' => self::object_type( $type ),
				'object_id'   => absint( $obj_id ),
				'user_id'     => absint( $user_id ),
				'reaction'    => sanitize_key( $reaction ),
			),
			array( '%s', '%d', '%d', '%s' )
		);
	}

	/**
	 * Fjerner alle reaksjoner på et objekt (ved sletting av objektet).
	 *
	 * @param string $type   Objekttype.
	 * @param int    $obj_id Objektets ID.
	 * @return void
	 */
	public static function remove_all( $type, $obj_id ) {
		global $wpdb;

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell, wpdb::delete preparerer selv.
			samlab_table( 'reaksjoner' ),
			array(
				'object_type' => self::object_type( $type ),
				'object_id'   => absint( $obj_id ),
			),
			array( '%s', '%d' )
		);
	}

	/**
	 * Om brukeren allerede har gitt denne reaksjonen.
	 *
	 * @param string $type     Objekttype.
	 * @param int    $obj_id   Objektets ID.
	 * @param int    $user_id  Brukeren.
	 * @param string $reaction Reaksjonsnøkkel.
	 * @return bool
	 */
	public static function user_has( $type, $obj_id, $user_id, $reaction = 'like' ) {
		global $wpdb;
		$tabell = samlab_table( 'reaksjoner' );

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell; caching kommer med REST-laget.
			$wpdb->prepare(
				"SELECT id FROM {$tabell} WHERE object_type = %s AND object_id = %d AND user_id = %d AND reaction = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table(), ikke brukerdata.
				self::object_type( $type ),
				absint( $obj_id ),
				absint( $user_id ),
				sanitize_key( $reaction )
			)
		);
	}

	/**
	 * Antall reaksjoner per reaksjonsnøkkel for et objekt.
	 *
	 * @param string $type   Objekttype.
	 * @param int    $obj_id Objektets ID.
	 * @return array<string, int>
	 */
	public static function counts( $type, $obj_id ) {
		global $wpdb;
		$tabell = samlab_table( 'reaksjoner' );

		$rader = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell; caching kommer med REST-laget.
			$wpdb->prepare(
				"SELECT reaction, COUNT(*) AS antall FROM {$tabell} WHERE object_type = %s AND object_id = %d GROUP BY reaction", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table(), ikke brukerdata.
				self::object_type( $type ),
				absint( $obj_id )
			)
		);

		$antall = array();
		foreach ( $rader as $rad ) {
			$antall[ $rad->reaction ] = (int) $rad->antall;
		}
		return $antall;
	}

	/**
	 * Brukerne som har en gitt reaksjon på et objekt (brukes av
	 * lesebekreftelsene, nøkkel «lest»).
	 *
	 * @param string $type     Objekttype.
	 * @param int    $obj_id   Objektets ID.
	 * @param string $reaction Reaksjonsnøkkel.
	 * @return int[] Bruker-ID-er.
	 */
	public static function users( $type, $obj_id, $reaction = 'like' ) {
		global $wpdb;
		$tabell = samlab_table( 'reaksjoner' );

		return array_map(
			'intval',
			$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell.
				$wpdb->prepare(
					"SELECT user_id FROM {$tabell} WHERE object_type = %s AND object_id = %d AND reaction = %s ORDER BY created_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table(), ikke brukerdata.
					self::object_type( $type ),
					absint( $obj_id ),
					sanitize_key( $reaction )
				)
			)
		);
	}
}
