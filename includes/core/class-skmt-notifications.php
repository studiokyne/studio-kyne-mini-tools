<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Notifications {
	protected static $option_name = 'skmt_notifications_history';
	protected static $max_items = 100;

	public static function add( $level, $message, $context = array() ) {
		$level = sanitize_key( (string) $level );
		if ( ! in_array( $level, array( 'success', 'info', 'warning', 'error' ), true ) ) {
			$level = 'info';
		}

		$message = sanitize_text_field( (string) $message );
		if ( '' === $message ) {
			return;
		}

		$context = is_array( $context ) ? $context : array();
		$entry   = array(
			'level'      => $level,
			'message'    => $message,
			'context'    => self::sanitize_context( $context ),
			'created_at' => current_time( 'mysql' ),
		);

		$history = get_option( self::$option_name, array() );
		$history = is_array( $history ) ? $history : array();
		array_unshift( $history, $entry );

		if ( count( $history ) > self::$max_items ) {
			$history = array_slice( $history, 0, self::$max_items );
		}

		update_option( self::$option_name, $history, false );
	}

	public static function get_recent( $limit = 20 ) {
		$limit   = max( 1, absint( $limit ) );
		$history = get_option( self::$option_name, array() );
		$history = is_array( $history ) ? $history : array();

		if ( count( $history ) <= $limit ) {
			return $history;
		}

		return array_slice( $history, 0, $limit );
	}

	protected static function sanitize_context( $context ) {
		$sanitized = array();
		foreach ( $context as $key => $value ) {
			$sanitized_key = sanitize_key( (string) $key );
			if ( '' === $sanitized_key ) {
				continue;
			}

			if ( is_scalar( $value ) ) {
				$sanitized[ $sanitized_key ] = sanitize_text_field( (string) $value );
				continue;
			}

			$sanitized[ $sanitized_key ] = sanitize_text_field( wp_json_encode( $value ) );
		}

		return $sanitized;
	}
}
