<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Capabilities {
	public static function admin_capability() {
		return (string) apply_filters( 'skmt/admin_capability', 'manage_options' );
	}

	public static function current_user_can_manage() {
		return current_user_can( self::admin_capability() );
	}
}
