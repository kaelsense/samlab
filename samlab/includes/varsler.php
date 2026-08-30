<?php
/**
 * In-app-varsler: utløsere, tekstrendring og REST-ruter.
 *
 * Utløsere: mention i vegginnlegg, kommentar og reaksjon på eget
 * innlegg, og koblingsflyten (G2): forespørsel til partene ved
 * forespurt (med begrunnelsen, aldri motpartens kontaktinfo),
 * statusvarsler fra godkjent og utover, nøytralt «ble ikke noe
 * av»-varsel til motparten ved nei (avklaring 5: aldri hvem som
 * takket nei), og varsel til moderatorene når begge parter har
 * svart. Foreslått er fortsatt kun moderatorens arbeidsflate.
 * Varsel ved svar på behov aktiveres når en svar-funksjon finnes
 * (ikke i MVP).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mention-varsler når et vegginnlegg opprettes.
 *
 * @param int $innlegg_id Innleggets ID.
 * @param int $forfatter  Forfatteren.
 * @return void
 */
function samlab_varsle_mentions( $innlegg_id, $forfatter ) {
	$innlegg = Samlab_Innlegg::get( $innlegg_id );
	if ( ! $innlegg ) {
		return;
	}
	preg_match_all( '/(?:^|[\s>(])@([A-Za-z0-9._\-]+)/u', (string) $innlegg->content, $treff );
	foreach ( array_unique( $treff[1] ) as $login ) {
		$bruker = get_user_by( 'login', $login );
		if ( $bruker ) {
			Samlab_Varsel::create(
				array(
					'user_id'     => $bruker->ID,
					'type'        => 'mention',
					'object_type' => 'innlegg',
					'object_id'   => $innlegg_id,
					'actor_id'    => $forfatter,
				)
			);
		}
	}
}
add_action( 'samlab_innlegg_opprettet', 'samlab_varsle_mentions', 10, 2 );

/**
 * Varsel til innleggsforfatteren ved ny kommentar på veggen.
 *
 * @param int        $comment_id Kommentarens ID.
 * @param WP_Comment $comment    Kommentaren.
 * @return void
 */
function samlab_varsle_kommentar( $comment_id, $comment ) {
	if ( 'samlab_innlegg' !== $comment->comment_type ) {
		return;
	}
	$innlegg_id = (int) get_comment_meta( $comment_id, '_samlab_innlegg', true );
	$innlegg    = $innlegg_id ? Samlab_Innlegg::get( $innlegg_id ) : null;
	if ( ! $innlegg ) {
		return;
	}
	Samlab_Varsel::create(
		array(
			'user_id'     => (int) $innlegg->user_id,
			'type'        => 'kommentar',
			'object_type' => 'innlegg',
			'object_id'   => $innlegg_id,
			'actor_id'    => (int) $comment->user_id,
		)
	);
}
add_action( 'wp_insert_comment', 'samlab_varsle_kommentar', 10, 2 );

/**
 * Varsel til innleggsforfatteren når noen reagerer.
 *
 * @param string $type     Objekttype.
 * @param int    $obj_id   Objektets ID.
 * @param int    $user_id  Den som reagerte.
 * @param string $reaction Reaksjonsnøkkel.
 * @param bool   $reacted  True når reaksjonen ble lagt til.
 * @return void
 */
function samlab_varsle_reaksjon( $type, $obj_id, $user_id, $reaction, $reacted ) {
	if ( ! $reacted || 'innlegg' !== $type ) {
		return;
	}
	$innlegg = Samlab_Innlegg::get( $obj_id );
	if ( ! $innlegg ) {
		return;
	}
	Samlab_Varsel::create(
		array(
			'user_id'     => (int) $innlegg->user_id,
			'type'        => 'reaksjon',
			'object_type' => 'innlegg',
			'object_id'   => (int) $obj_id,
			'actor_id'    => (int) $user_id,
		)
	);
}
add_action( 'samlab_reaksjon_endret', 'samlab_varsle_reaksjon', 10, 5 );

/**
 * Brukerne som skal varsles for en koblings parter.
 *
 * @param int $kobling_id Koblingen.
 * @return int[] Bruker-ID-er.
 */
function samlab_kobling_part_brukere( $kobling_id ) {
	$brukere = array();
	foreach ( array( 'a', 'b' ) as $part ) {
		$type = get_post_meta( $kobling_id, '_samlab_part_' . $part . '_type', true );
		$id   = (int) get_post_meta( $kobling_id, '_samlab_part_' . $part . '_id', true );
		if ( 'bruker' === $type && $id ) {
			$brukere[] = $id;
		} elseif ( 'bedrift' === $type && $id ) {
			$kontakt = (int) get_post_meta( $id, '_samlab_kontaktperson', true );
			if ( $kontakt ) {
				$brukere[] = $kontakt;
			}
		}
	}
	return array_values( array_unique( $brukere ) );
}

/**
 * Varsler partene når en kobling når forespurt (G2) eller
 * godkjent/introdusert/fulgt opp.
 *
 * Forespørselen sendes med aktør 0 (system) så begge parter alltid
 * får den - også en kontaktperson som selv er moderator og trykket
 * «Godkjenn og spør partene».
 *
 * @param int    $kobling_id Koblingen.
 * @param string $status     Ny status.
 * @param string $gammel     Forrige status.
 * @param int    $user_id    Hvem som endret.
 * @return void
 */
function samlab_varsle_kobling( $kobling_id, $status, $gammel, $user_id ) {
	if ( ! in_array( $status, array( 'forespurt', 'godkjent', 'introdusert', 'fulgt_opp' ), true ) ) {
		return;
	}
	foreach ( samlab_kobling_part_brukere( $kobling_id ) as $mottaker ) {
		Samlab_Varsel::create(
			array(
				'user_id'     => $mottaker,
				'type'        => 'kobling_' . $status,
				'object_type' => 'kobling',
				'object_id'   => $kobling_id,
				'actor_id'    => 'forespurt' === $status ? 0 : $user_id,
			)
		);
	}
}
add_action( 'samlab_kobling_status_endret', 'samlab_varsle_kobling', 10, 4 );

/**
 * Varsler når en part har svart på en forespurt kobling (G2):
 * nøytralt varsel til motparten ved nei (aldri hvem som takket nei
 * - avklaring 5), og varsel til moderatorene når begge har svart.
 *
 * Kjøres før statusløftet i samlab_kobling_svar(), så «begge har
 * svart» leses fra samtykke-metaene: et nei avslutter alltid, et ja
 * avslutter når motparten alt har sagt ja.
 *
 * @param int    $kobling_id Koblingen.
 * @param string $part       Parten som svarte («a» eller «b»).
 * @param string $svar       «ja» eller «nei».
 * @return void
 */
function samlab_varsle_kobling_besvart( $kobling_id, $part, $svar ) {
	$motpart = 'a' === $part ? 'b' : 'a';

	if ( 'nei' === $svar ) {
		$mottaker = samlab_kobling_part_bruker( $kobling_id, $motpart );
		if ( $mottaker ) {
			Samlab_Varsel::create(
				array(
					'user_id'     => $mottaker->ID,
					'type'        => 'kobling_ikke_noe',
					'object_type' => 'kobling',
					'object_id'   => $kobling_id,
					'actor_id'    => 0,
				)
			);
		}
	}

	$komplett = 'nei' === $svar || 'ja' === samlab_kobling_samtykke( $kobling_id, $motpart );
	if ( ! $komplett ) {
		return;
	}
	$moderatorer = get_users( array( 'capability' => 'edit_samlab_koblinger' ) );
	foreach ( $moderatorer as $moderator ) {
		Samlab_Varsel::create(
			array(
				'user_id'     => $moderator->ID,
				'type'        => 'kobling_besvart',
				'object_type' => 'kobling',
				'object_id'   => $kobling_id,
				'actor_id'    => 0,
			)
		);
	}
}
add_action( 'samlab_kobling_besvart', 'samlab_varsle_kobling_besvart', 10, 3 );

/**
 * Menneskelig tekst og lenke for et varsel.
 *
 * @param object $varsel Rad fra varseltabellen.
 * @return array{tekst: string, lenke: string}
 */
function samlab_varsel_visning( $varsel ) {
	$aktor = get_userdata( (int) $varsel->actor_id );
	$navn  = $aktor ? $aktor->display_name : __( 'Noen', 'samlab' );
	$lenke = '';

	switch ( $varsel->type ) {
		case 'mention':
			/* translators: %s: navnet på den som nevnte deg. */
			$tekst = sprintf( __( '%s nevnte deg på veggen', 'samlab' ), $navn );
			break;
		case 'kommentar':
			/* translators: %s: navnet på den som kommenterte. */
			$tekst = sprintf( __( '%s kommenterte innlegget ditt', 'samlab' ), $navn );
			break;
		case 'reaksjon':
			/* translators: %s: navnet på den som reagerte. */
			$tekst = sprintf( __( '%s likte innlegget ditt', 'samlab' ), $navn );
			break;
		case 'kobling_forespurt':
			// Med begrunnelsen, aldri motpartens kontaktinfo -
			// kontakt deles først fra godkjent (G2).
			/* translators: 1: koblingens tittel, 2: begrunnelsen. */
			$tekst = sprintf( __( 'Du er foreslått en kobling: «%1$s». Begrunnelse: %2$s Svarer du ja?', 'samlab' ), get_the_title( (int) $varsel->object_id ), wp_trim_words( (string) get_post_field( 'post_content', (int) $varsel->object_id ), 25 ) );
			$lenke = samlab_portal_url( 'koblinger' );
			break;
		case 'kobling_ikke_noe':
			// Nøytralt, uten hvem som takket nei (avklaring 5).
			/* translators: %s: koblingens tittel. */
			$tekst = sprintf( __( 'Koblingen «%s» ble ikke noe av denne gangen', 'samlab' ), get_the_title( (int) $varsel->object_id ) );
			$lenke = samlab_portal_url( 'koblinger' );
			break;
		case 'kobling_besvart':
			/* translators: %s: koblingens tittel. */
			$tekst = sprintf( __( 'Begge parter har svart på koblingen «%s» - se kontrollpanelet', 'samlab' ), get_the_title( (int) $varsel->object_id ) );
			$lenke = admin_url( 'admin.php?page=samlab-kontrollpanel' );
			break;
		case 'kobling_godkjent':
		case 'kobling_introdusert':
		case 'kobling_fulgt_opp':
			$statuser = samlab_kobling_statuser();
			$slug     = str_replace( 'kobling_', '', $varsel->type );
			/* translators: 1: koblingens tittel, 2: ny status. */
			$tekst = sprintf( __( 'Koblingen «%1$s» er %2$s', 'samlab' ), get_the_title( (int) $varsel->object_id ), strtolower( isset( $statuser[ $slug ] ) ? $statuser[ $slug ] : $slug ) );
			$lenke = samlab_portal_url( 'koblinger' );
			break;
		default:
			$tekst = __( 'Ny hendelse i portalen', 'samlab' );
	}

	if ( 'innlegg' === $varsel->object_type ) {
		$lenke = samlab_portal_url( 'vegg' ) . '#innlegg-' . (int) $varsel->object_id;
	}

	return array(
		'tekst' => $tekst,
		'lenke' => $lenke,
	);
}

/**
 * Registrerer varsel-rutene i samlab/v1.
 *
 * @return void
 */
function samlab_register_varsel_routes() {
	register_rest_route(
		'samlab/v1',
		'/varsler',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'samlab_rest_hent_varsler',
			'permission_callback' => 'samlab_rest_can_react',
		)
	);
	register_rest_route(
		'samlab/v1',
		'/varsler/lest',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'samlab_rest_marker_varsler_lest',
			'permission_callback' => 'samlab_rest_can_react',
		)
	);
}
add_action( 'rest_api_init', 'samlab_register_varsel_routes' );

/**
 * Innlogget brukers varsler med uleste-teller.
 *
 * @return WP_REST_Response
 */
function samlab_rest_hent_varsler() {
	$bruker  = get_current_user_id();
	$varsler = array();
	foreach ( Samlab_Varsel::for_user( $bruker ) as $varsel ) {
		$visning   = samlab_varsel_visning( $varsel );
		$varsler[] = array(
			'id'    => (int) $varsel->id,
			'tekst' => $visning['tekst'],
			'lenke' => $visning['lenke'],
			'tid'   => human_time_diff( strtotime( $varsel->created_at . ' UTC' ) ),
			'lest'  => null !== $varsel->read_at,
		);
	}

	return rest_ensure_response(
		array(
			'varsler' => $varsler,
			'uleste'  => Samlab_Varsel::unread_count( $bruker ),
		)
	);
}

/**
 * Markerer alle innlogget brukers varsler som lest.
 *
 * @return WP_REST_Response
 */
function samlab_rest_marker_varsler_lest() {
	Samlab_Varsel::mark_all_read( get_current_user_id() );
	return rest_ensure_response( array( 'uleste' => 0 ) );
}
