<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'skmt_settings', array() );
$cleanup  = ! empty( $settings['cleanup_on_uninstall'] );

if ( ! $cleanup ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'skmt_logs',
	$wpdb->prefix . 'skmt_image_stats',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

$options = array(
	'skmt_settings',
	'skmt_active_modules',
	'skmt_image_optimizer_settings',
	'skmt_image_bulk_state',
	'skmt_notifications_history',
	'skmt_feedback_settings',
	'skmt_feedback_items',
);

foreach ( $options as $option_name ) {
	delete_option( $option_name );
}

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_skmt_' ) . '%'
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_timeout_skmt_' ) . '%'
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_skmt_' ) . '%'
	)
);
