<?php
/**
 * REST-navnerommet samlab/v1.
 *
 * Frontend-kallene bruker WordPress' innebygde cookie-autentisering
 * med X-WP-Nonce (wp_rest); uten gyldig nonce er brukeren anonym og
 * avvises av permission-callbacken.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrerer rutene i samlab/v1.
 *
 * @return void
 */
function samlab_register_rest_routes() {
	register_rest_route(
		'samlab/v1',
		'/reaksjoner',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'samlab_rest_toggle_reaksjon',
			'permission_callback' => 'samlab_rest_can_react',
			'args'                => array(
				'object_type' => array(
					'type'    => 'string',
					'default' => 'innlegg',
					'enum'    => array( 'innlegg', 'kommentar' ),
				),
				'object_id'   => array(
					'type'     => 'integer',
					'required' => true,
					'minimum'  => 1,
				),
				'reaction'    => array(
					'type'              => 'string',
					'default'           => 'like',
					'sanitize_callback' => 'sanitize_key',
				),
			),
		)
	);
	register_rest_route(
		'samlab/v1',
		'/brukere',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'samlab_rest_finn_brukere',
			'permission_callback' => 'samlab_rest_can_react',
			'args'                => array(
				'sok' => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'samlab_register_rest_routes' );

/**
 * Brukerforslag til @-mentions.
 *
 * @param WP_REST_Request $request Forespørselen.
 * @return WP_REST_Response
 */
function samlab_rest_finn_brukere( $request ) {
	$sok = (string) $request['sok'];

	$brukere = get_users(
		array(
			'search'         => '*' . $sok . '*',
			'search_columns' => array( 'user_login', 'display_name', 'user_nicename' ),
			'number'         => 8,
			'orderby'        => 'display_name',
		)
	);

	$svar = array();
	foreach ( $brukere as $bruker ) {
		$svar[] = array(
			'login' => $bruker->user_login,
			'navn'  => $bruker->display_name,
		);
	}
	return rest_ensure_response( $svar );
}

/**
 * Reagere krever innlogging og portaltilgang.
 *
 * @return true|WP_Error
 */
function samlab_rest_can_react() {
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'samlab_ikke_innlogget', __( 'Du må være innlogget.', 'samlab' ), array( 'status' => 401 ) );
	}
	if ( ! current_user_can( 'samlab_read_portal' ) ) {
		return new WP_Error( 'samlab_ingen_tilgang', __( 'Du har ikke tilgang til portalen.', 'samlab' ), array( 'status' => 403 ) );
	}
	return true;
}

/**
 * Slår en reaksjon av/på for innlogget bruker og returnerer ny status.
 *
 * @param WP_REST_Request $request Forespørselen.
 * @return WP_REST_Response|WP_Error
 */
function samlab_rest_toggle_reaksjon( $request ) {
	$type     = (string) $request['object_type'];
	$obj_id   = (int) $request['object_id'];
	$reaction = (string) $request['reaction'];
	$user_id  = get_current_user_id();

	if ( 'innlegg' === $type ) {
		$innlegg = Samlab_Innlegg::get( $obj_id );
		if ( ! $innlegg || 'publish' !== $innlegg->status ) {
			return new WP_Error( 'samlab_ukjent_objekt', __( 'Fant ikke innlegget.', 'samlab' ), array( 'status' => 404 ) );
		}
	} elseif ( ! get_comment( $obj_id ) ) {
		return new WP_Error( 'samlab_ukjent_objekt', __( 'Fant ikke kommentaren.', 'samlab' ), array( 'status' => 404 ) );
	}

	if ( Samlab_Reaksjon::user_has( $type, $obj_id, $user_id, $reaction ) ) {
		Samlab_Reaksjon::remove( $type, $obj_id, $user_id, $reaction );
		$reacted = false;
	} else {
		Samlab_Reaksjon::add( $type, $obj_id, $user_id, $reaction );
		$reacted = true;
	}

	/**
	 * Kjøres når en reaksjon er lagt til eller fjernet via REST.
	 *
	 * @since 0.1.0
	 *
	 * @param string $type     Objekttype («innlegg» eller «kommentar»).
	 * @param int    $obj_id   Objektets ID.
	 * @param int    $user_id  Brukeren som reagerte.
	 * @param string $reaction Reaksjonsnøkkel, f.eks. «like».
	 * @param bool   $reacted  True når reaksjonen ble lagt til, false ved fjerning.
	 */
	do_action( 'samlab_reaksjon_endret', $type, $obj_id, $user_id, $reaction, $reacted );

	return rest_ensure_response(
		array(
			'object_type' => $type,
			'object_id'   => $obj_id,
			'reaction'    => $reaction,
			'reacted'     => $reacted,
			'counts'      => Samlab_Reaksjon::counts( $type, $obj_id ),
		)
	);
}
