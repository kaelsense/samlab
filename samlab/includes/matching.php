<?php
/**
 * Regelbasert matching: daglig cron som matcher åpne behov mot
 * bedriftenes intensjonsfelter og tjenester, og legger foreslåtte
 * koblinger i kontrollpanelets kø. Aldri automatiske introduksjoner
 * - forslag krever alltid moderatorens godkjenning (E3).
 *
 * Algoritme: tekstlig overlapp av lett stemmede ord (terskel 2
 * felles stammer). «Trenger»-behov matches mot det bedriftene
 * leverer; «tilbyr»-behov mot det bedriftene kjøper/trenger.
 * LLM-assistert scoring er en senere utvidelse (egen oppgave etter
 * fase F).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Norske småord som ikke skal telle som match-signal.
 *
 * @return string[]
 */
function samlab_match_stoppord() {
	return array( 'og', 'i', 'på', 'til', 'for', 'med', 'av', 'en', 'et', 'den', 'det', 'de', 'som', 'å', 'vi', 'er', 'har', 'kan', 'om', 'ny', 'nye', 'små', 'store', 'mer', 'hos', 'fra', 'ikke', 'eller', 'trenger', 'tilbyr', 'gratis', 'egen', 'eget', 'våre', 'vår' );
}

/**
 * Tokeniserer og stemmer tekst lett (fjerner vanlige endelser).
 *
 * @param string $tekst Teksten.
 * @return string[] Unike stammer.
 */
function samlab_match_stammer( $tekst ) {
	$tekst = mb_strtolower( wp_strip_all_tags( (string) $tekst ) );
	$ord   = preg_split( '/[^a-z0-9æøå]+/u', $tekst, -1, PREG_SPLIT_NO_EMPTY );

	$stammer = array();
	foreach ( $ord as $o ) {
		if ( mb_strlen( $o ) < 3 || in_array( $o, samlab_match_stoppord(), true ) ) {
			continue;
		}
		foreach ( array( 'ene', 'er', 'en', 'et', 'e' ) as $endelse ) {
			if ( mb_strlen( $o ) - mb_strlen( $endelse ) >= 4 && str_ends_with( $o, $endelse ) ) {
				$o = mb_substr( $o, 0, mb_strlen( $o ) - mb_strlen( $endelse ) );
				break;
			}
		}
		$stammer[ $o ] = true;
	}
	return array_keys( $stammer );
}

/**
 * Behovets match-korpus: tittel + kompetanse.
 *
 * @param WP_Post $behov Behovet.
 * @return string[]
 */
function samlab_match_behov_stammer( $behov ) {
	$komp   = get_post_meta( $behov->ID, '_samlab_kompetanse', true );
	$tekst  = $behov->post_title . ' ';
	$tekst .= is_array( $komp ) ? implode( ' ', $komp ) : '';
	return samlab_match_stammer( $tekst );
}

/**
 * Bedriftens match-korpus for en behovsretning.
 *
 * @param int    $bedrift_id Bedriften.
 * @param string $retning    «trenger» (match mot leverer/tjenester)
 *                           eller «tilbyr» (match mot kjøper/trenger nå).
 * @return string[]
 */
function samlab_match_bedrift_stammer( $bedrift_id, $retning ) {
	if ( 'tilbyr' === $retning ) {
		$tekst = get_post_meta( $bedrift_id, '_samlab_kjoper', true ) . ' ' . get_post_meta( $bedrift_id, '_samlab_trenger_na', true );
		return samlab_match_stammer( $tekst );
	}

	$tekst = get_post_meta( $bedrift_id, '_samlab_leverer', true ) . ' ' . get_post_meta( $bedrift_id, '_samlab_kort', true ) . ' ';
	$tjen  = get_post_meta( $bedrift_id, '_samlab_tjenester', true );
	if ( is_array( $tjen ) ) {
		foreach ( $tjen as $t ) {
			$tekst .= ( isset( $t['tittel'] ) ? $t['tittel'] : '' ) . ' ';
			$tekst .= isset( $t['punkter'] ) && is_array( $t['punkter'] ) ? implode( ' ', $t['punkter'] ) : '';
			$tekst .= ' ';
		}
	}
	return samlab_match_stammer( $tekst );
}

/**
 * Om det allerede finnes en kobling (uansett status, også avvist)
 * for dette behov/bedrift-paret - avviste forslag gjenoppstår ikke.
 *
 * @param int $behov_id   Behovet.
 * @param int $bedrift_id Den matchede bedriften.
 * @return bool
 */
function samlab_match_finnes( $behov_id, $bedrift_id ) {
	$treff = get_posts(
		array(
			'post_type'      => 'samlab_kobling',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Dedup-oppslag i cron.
				array(
					'key'   => '_samlab_match_behov',
					'value' => $behov_id,
				),
				array(
					'key'   => '_samlab_match_bedrift',
					'value' => $bedrift_id,
				),
			),
		)
	);
	return array() !== $treff;
}

/**
 * Kjører matchingen: åpne behov mot bedrifter, terskel 2 felles
 * stammer, foreslåtte koblinger i køen. Idempotent per par.
 *
 * @return int[] ID-ene til koblingene som ble opprettet.
 */
function samlab_kjor_matching() {
	$opprettet = array();

	$behov_liste = get_posts(
		array(
			'post_type'      => 'samlab_behov',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
		)
	);
	$bedrifter   = get_posts(
		array(
			'post_type'      => 'samlab_bedrift',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
		)
	);

	foreach ( $behov_liste as $behov ) {
		$retning_terms = get_the_terms( $behov->ID, 'samlab_retning' );
		$retning       = $retning_terms && ! is_wp_error( $retning_terms ) ? $retning_terms[0]->slug : 'trenger';
		$behov_stammer = samlab_match_behov_stammer( $behov );
		if ( array() === $behov_stammer ) {
			continue;
		}

		$egen_bedrift = (int) get_post_meta( $behov->ID, '_samlab_bedrift', true );
		$egen_kontakt = $egen_bedrift ? (int) get_post_meta( $egen_bedrift, '_samlab_kontaktperson', true ) : (int) $behov->post_author;

		foreach ( $bedrifter as $bedrift ) {
			if ( $bedrift->ID === $egen_bedrift ) {
				continue;
			}
			$kontakt = (int) get_post_meta( $bedrift->ID, '_samlab_kontaktperson', true );
			if ( $kontakt && $kontakt === $egen_kontakt ) {
				continue;
			}

			$felles = array_intersect( $behov_stammer, samlab_match_bedrift_stammer( $bedrift->ID, $retning ) );
			if ( count( $felles ) < 2 || samlab_match_finnes( $behov->ID, $bedrift->ID ) ) {
				continue;
			}

			$part_b = $egen_bedrift
				? array(
					'type' => 'bedrift',
					'id'   => $egen_bedrift,
				)
				: array(
					'type' => 'bruker',
					'id'   => (int) $behov->post_author,
				);

			$kobling = samlab_opprett_kobling(
				array(
					'tittel'      => get_the_title( $bedrift ) . ' ↔ ' . ( $egen_bedrift ? get_the_title( $egen_bedrift ) : get_the_author_meta( 'display_name', $behov->post_author ) ),
					/* translators: 1: behovets tittel, 2: bedriftens navn, 3: felles nøkkelord. */
					'begrunnelse' => sprintf( __( 'Automatisk forslag: behovet «%1$s» matcher %2$s (felles: %3$s).', 'samlab' ), get_the_title( $behov ), get_the_title( $bedrift ), implode( ', ', $felles ) ),
					'kilde'       => 'matching',
					'part_a'      => array(
						'type' => 'bedrift',
						'id'   => $bedrift->ID,
					),
					'part_b'      => $part_b,
				)
			);
			if ( ! is_wp_error( $kobling ) ) {
				update_post_meta( $kobling, '_samlab_match_behov', $behov->ID );
				update_post_meta( $kobling, '_samlab_match_bedrift', $bedrift->ID );
				$opprettet[] = (int) $kobling;
			}
		}
	}

	/**
	 * Kjøres etter en matching-runde.
	 *
	 * @since 0.2.0
	 *
	 * @param int[] $opprettet Koblingene som ble opprettet.
	 */
	do_action( 'samlab_matching_kjort', $opprettet );

	return $opprettet;
}
add_action( 'samlab_matching', 'samlab_kjor_matching' );
