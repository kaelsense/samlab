<?php
/**
 * Modellklasse for vegginnlegg (egen tabell, prepared statements).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for vegginnlegg.
 */
class Samlab_Innlegg {

	/**
	 * Oppretter et innlegg.
	 *
	 * @param array $args user_id (påkrevd), content (påkrevd),
	 *                    bedrift_id, image_id, pinned, status.
	 * @return int|false Innleggets ID, eller false ved feil.
	 */
	public static function create( $args ) {
		global $wpdb;

		$user_id = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;
		$content = isset( $args['content'] ) ? trim( wp_kses_post( $args['content'] ) ) : '';
		if ( ! $user_id || '' === $content ) {
			return false;
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Egen tabell, wpdb::insert preparerer selv.
			samlab_table( 'innlegg' ),
			array(
				'user_id'    => $user_id,
				'bedrift_id' => isset( $args['bedrift_id'] ) ? absint( $args['bedrift_id'] ) : 0,
				'content'    => $content,
				'image_id'   => isset( $args['image_id'] ) ? absint( $args['image_id'] ) : 0,
				'pinned'     => empty( $args['pinned'] ) ? 0 : 1,
				'status'     => isset( $args['status'] ) && 'hidden' === $args['status'] ? 'hidden' : 'publish',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Henter ett innlegg.
	 *
	 * @param int $id Innleggets ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$tabell = samlab_table( 'innlegg' );

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell; caching kommer med REST-laget.
			$wpdb->prepare( "SELECT * FROM {$tabell} WHERE id = %d", absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table(), ikke brukerdata.
		);
	}

	/**
	 * Oppdaterer et innlegg (content, pinned, status, image_id).
	 *
	 * @param int   $id   Innleggets ID.
	 * @param array $args Felter som skal endres.
	 * @return bool
	 */
	public static function update( $id, $args ) {
		global $wpdb;

		$data    = array();
		$formats = array();
		if ( isset( $args['content'] ) ) {
			$content = trim( wp_kses_post( $args['content'] ) );
			if ( '' === $content ) {
				return false;
			}
			$data['content'] = $content;
			$formats[]       = '%s';
		}
		if ( isset( $args['pinned'] ) ) {
			$data['pinned'] = empty( $args['pinned'] ) ? 0 : 1;
			$formats[]      = '%d';
		}
		if ( isset( $args['status'] ) ) {
			$data['status'] = 'hidden' === $args['status'] ? 'hidden' : 'publish';
			$formats[]      = '%s';
		}
		if ( isset( $args['image_id'] ) ) {
			$data['image_id'] = absint( $args['image_id'] );
			$formats[]        = '%d';
		}
		if ( array() === $data ) {
			return false;
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$formats[]          = '%s';

		$rader = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell, wpdb::update preparerer selv.
			samlab_table( 'innlegg' ),
			$data,
			array( 'id' => absint( $id ) ),
			$formats,
			array( '%d' )
		);

		return false !== $rader;
	}

	/**
	 * Sletter et innlegg og reaksjonene på det.
	 *
	 * @param int $id Innleggets ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$rader = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell, wpdb::delete preparerer selv.
			samlab_table( 'innlegg' ),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
		if ( $rader ) {
			Samlab_Reaksjon::remove_all( 'innlegg', $id );
		}

		return (bool) $rader;
	}

	/**
	 * Lister innlegg for veggen: festede først, deretter nyeste.
	 *
	 * @param array $args status (default publish), limit, offset.
	 * @return object[]
	 */
	public static function get_list( $args = array() ) {
		global $wpdb;
		$tabell = samlab_table( 'innlegg' );

		$status = isset( $args['status'] ) && 'hidden' === $args['status'] ? 'hidden' : 'publish';
		$limit  = isset( $args['limit'] ) ? min( 100, max( 1, absint( $args['limit'] ) ) ) : 20;
		$offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell; caching kommer med REST-laget.
			$wpdb->prepare(
				"SELECT * FROM {$tabell} WHERE status = %s ORDER BY pinned DESC, created_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table(), ikke brukerdata.
				$status,
				$limit,
				$offset
			)
		);
	}
}
