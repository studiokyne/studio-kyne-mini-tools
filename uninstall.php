<?php
/**
 * Nettoyage des donnees lors de la desinstallation.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$option_keys = [
	'skmt_settings',
	'skmt_module_image_optimizer',
	'skmt_image_optimizer_stats',
	'skmt_image_optimizer_bulk_state',
];

foreach ( $option_keys as $key ) {
	delete_option( $key );
	delete_site_option( $key );
}

$post_meta_keys = [
	'_skmt_optimized',
	'_skmt_original_bytes',
	'_skmt_optimized_bytes',
	'_skmt_bytes_saved',
	'_skmt_main_original_bytes',
	'_skmt_main_optimized_bytes',
	'_skmt_main_bytes_saved',
	'_skmt_optimized_format',
	'_skmt_optimized_mime',
];

foreach ( $post_meta_keys as $meta_key ) {
	delete_post_meta_by_key( $meta_key );
}
