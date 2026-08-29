<?php
/**
 * Roller og capabilities per planens kap. 3.2.
 *
 * - Medlem: lese portalinnhold, poste på veggen, opprette behov.
 * - Bedriftsredaktør: i tillegg redigere egen bedrift (selve
 *   eierskapssjekken håndheves med map_meta_cap i B4).
 * - Moderator: i tillegg godkjenne medlemmer, skjule innhold og
 *   feste oppslag.
 * - Administrator og redaktør får alle samlab-capabilities.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pluginens capabilities gruppert per rolle.
 *
 * @return array<string, array{name: string, caps: array<string, bool>}>
 */
function samlab_get_roles() {
	$member_caps = array(
		'read'                => true,
		'upload_files'        => true,
		'samlab_read_portal'  => true,
		'samlab_post_wall'    => true,
		'samlab_create_behov' => true,
	);

	$company_editor_caps = array_merge(
		$member_caps,
		array(
			'samlab_edit_bedrift' => true,
		)
	);

	$moderator_caps = array_merge(
		$company_editor_caps,
		array(
			'samlab_approve_members' => true,
			'samlab_hide_content'    => true,
			'samlab_pin_posts'       => true,
		)
	);

	return array(
		'samlab_member'         => array(
			'name' => __( 'Medlem', 'samlab' ),
			'caps' => $member_caps,
		),
		'samlab_company_editor' => array(
			'name' => __( 'Bedriftsredaktør', 'samlab' ),
			'caps' => $company_editor_caps,
		),
		'samlab_moderator'      => array(
			'name' => __( 'Moderator', 'samlab' ),
			'caps' => $moderator_caps,
		),
	);
}

/**
 * Alle samlab-prefiksede capabilities (til admin-tildeling og opprydding).
 *
 * @return string[]
 */
function samlab_get_all_caps() {
	$caps = array();
	foreach ( samlab_get_roles() as $role ) {
		foreach ( array_keys( $role['caps'] ) as $cap ) {
			if ( str_starts_with( $cap, 'samlab_' ) ) {
				$caps[ $cap ] = true;
			}
		}
	}
	return array_keys( $caps );
}

/**
 * Registrerer rollene og gir administrator og redaktør alle
 * samlab-capabilities. Kalles ved aktivering.
 *
 * @return void
 */
function samlab_add_roles() {
	foreach ( samlab_get_roles() as $slug => $role ) {
		remove_role( $slug );
		add_role( $slug, $role['name'], $role['caps'] );
	}

	foreach ( array( 'administrator', 'editor' ) as $builtin ) {
		$role = get_role( $builtin );
		if ( ! $role ) {
			continue;
		}
		foreach ( samlab_get_all_caps() as $cap ) {
			$role->add_cap( $cap );
		}
	}
}

/**
 * Fjerner rollene og samlab-capabilities fra innebygde roller.
 * Kalles fra uninstall.php.
 *
 * @return void
 */
function samlab_remove_roles() {
	foreach ( array_keys( samlab_get_roles() ) as $slug ) {
		remove_role( $slug );
	}

	foreach ( array( 'administrator', 'editor' ) as $builtin ) {
		$role = get_role( $builtin );
		if ( ! $role ) {
			continue;
		}
		foreach ( samlab_get_all_caps() as $cap ) {
			$role->remove_cap( $cap );
		}
	}
}
