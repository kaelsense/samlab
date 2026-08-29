<?php
/**
 * Flaten «behov» - plassholder til C-fasen.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$samlab_flate = samlab_portal_views()['behov'];
?>
<h1><?php echo esc_html( $samlab_flate['label'] ); ?></h1>
<p><?php esc_html_e( 'Denne flaten bygges ut i kommende versjoner.', 'samlab' ); ?></p>
