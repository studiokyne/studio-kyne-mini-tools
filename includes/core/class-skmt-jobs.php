<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Jobs {
	public function acquire_lock( $key, $ttl = 120 ) {
		$transient_key = 'skmt_lock_' . sanitize_key( $key );
		if ( get_transient( $transient_key ) ) {
			return false;
		}
		return set_transient( $transient_key, 1, max( 30, absint( $ttl ) ) );
	}

	public function release_lock( $key ) {
		delete_transient( 'skmt_lock_' . sanitize_key( $key ) );
	}

	public function is_locked( $key ) {
		return (bool) get_transient( 'skmt_lock_' . sanitize_key( $key ) );
	}
}
