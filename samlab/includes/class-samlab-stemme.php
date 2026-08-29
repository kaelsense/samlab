<?php
/**
 * Stemmemodellen for avstemninger på vegginnlegg (E7): egen tabell
 * med én rad per medlem per avstemning - ny stemme på samme
 * avstemning oppdaterer raden (endring tillatt).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for samlab_stemmer-tabellen.
 */
class Samlab_Stemme {

	/**
	 * Avgir eller endrer en stemme.
	 *
	 * @param int $innlegg_id Innlegget med avstemningen.
	 * @param int $user_id    Brukeren.
	 * @param int $valg       Alternativindeks (0-basert).
	 * @return bool
	 */
	public static function vote( $innlegg_id, $user_id, $valg ) {
		global $wpdb;
		$tabell = samlab_table( 'stemmer' );
		$now    = gmdate( 'Y-m-d H:i:s' );

		if ( null !== self::user_choice( $innlegg_id, $user_id ) ) {
			$rader = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell, wpdb::update preparerer selv.
				$tabell,
				array(
					'valg'       => absint( $valg ),
					'updated_at' => $now,
				),
				array(
					'innlegg_id' => absint( $innlegg_id ),
					'user_id'    => absint( $user_id ),
				),
				array( '%d', '%s' ),
				array( '%d', '%d' )
			);
			return false !== $rader;
		}

		return (bool) $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Egen tabell, wpdb::insert preparerer selv.
			$tabell,
			array(
				'innlegg_id' => absint( $innlegg_id ),
				'user_id'    => absint( $user_id ),
				'valg'       => absint( $valg ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Innlogget brukers valg i en avstemning.
	 *
	 * @param int $innlegg_id Innlegget.
	 * @param int $user_id    Brukeren.
	 * @return int|null Alternativindeks, eller null uten stemme.
	 */
	public static function user_choice( $innlegg_id, $user_id ) {
		global $wpdb;
		$tabell = samlab_table( 'stemmer' );

		$valg = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell.
			$wpdb->prepare(
				"SELECT valg FROM {$tabell} WHERE innlegg_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table(), ikke brukerdata.
				$innlegg_id,
				$user_id
			)
		);
		return null === $valg ? null : (int) $valg;
	}

	/**
	 * Stemmetall per alternativ for en avstemning.
	 *
	 * @param int $innlegg_id Innlegget.
	 * @param int $antall_valg Antall alternativer (for nullfylte tall).
	 * @return array<int, int> Alternativindeks => antall stemmer.
	 */
	public static function counts( $innlegg_id, $antall_valg = 0 ) {
		global $wpdb;
		$tabell = samlab_table( 'stemmer' );

		$rader = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell.
			$wpdb->prepare(
				"SELECT valg, COUNT(*) AS antall FROM {$tabell} WHERE innlegg_id = %d GROUP BY valg", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellnavn fra samlab_table(), ikke brukerdata.
				$innlegg_id
			)
		);

		$tall = array_fill( 0, max( 0, (int) $antall_valg ), 0 );
		foreach ( $rader as $rad ) {
			$tall[ (int) $rad->valg ] = (int) $rad->antall;
		}
		ksort( $tall );
		return $tall;
	}

	/**
	 * Fjerner alle stemmer for et innlegg (kaskade ved sletting).
	 *
	 * @param int $innlegg_id Innlegget.
	 * @return void
	 */
	public static function remove_for_innlegg( $innlegg_id ) {
		global $wpdb;
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Egen tabell, wpdb::delete preparerer selv.
			samlab_table( 'stemmer' ),
			array( 'innlegg_id' => absint( $innlegg_id ) ),
			array( '%d' )
		);
	}
}
