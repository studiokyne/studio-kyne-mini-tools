<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Logger {
	protected $table_name;
	protected $settings;

	public function __construct( SKMT_Settings $settings ) {
		global $wpdb;
		$this->settings   = $settings;
		$this->table_name = $wpdb->prefix . 'skmt_logs';
	}

	public static function create_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'skmt_logs';
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			module varchar(100) NOT NULL,
			level varchar(20) NOT NULL,
			message text NOT NULL,
			context longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY module (module),
			KEY level (level),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	public function log( $module, $level, $message, $context = array() ) {
		if ( ! $this->settings->get( 'logging_enabled' ) ) {
			return;
		}
		global $wpdb;
		$level = in_array( $level, array( 'info', 'warning', 'error' ), true ) ? $level : 'info';
		$wpdb->insert(
			$this->table_name,
			array(
				'module'     => sanitize_key( $module ),
				'level'      => $level,
				'message'    => wp_strip_all_tags( (string) $message ),
				'context'    => ! empty( $context ) ? wp_json_encode( $context ) : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[SKMT][%s][%s] %s', sanitize_key( $module ), $level, wp_strip_all_tags( (string) $message ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	public function get_logs( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'level'  => '',
			'module' => '',
			'search' => '',
			'limit'  => 50,
			'offset' => 0,
		);
		$args = wp_parse_args( $args, $defaults );
		$where = array( '1=1' );
		$values = array();
		if ( ! empty( $args['level'] ) ) {
			$where[] = 'level = %s';
			$values[] = sanitize_key( $args['level'] );
		}
		if ( ! empty( $args['module'] ) ) {
			$where[] = 'module = %s';
			$values[] = sanitize_key( $args['module'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$where[] = 'message LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}
		$values[] = max( 1, absint( $args['limit'] ) );
		$values[] = max( 0, absint( $args['offset'] ) );
		$sql = "SELECT * FROM {$this->table_name} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$query = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (array) $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function count_logs( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'level'  => '',
			'module' => '',
			'search' => '',
		);
		$args = wp_parse_args( $args, $defaults );
		$where = array( '1=1' );
		$values = array();
		if ( ! empty( $args['level'] ) ) {
			$where[] = 'level = %s';
			$values[] = sanitize_key( $args['level'] );
		}
		if ( ! empty( $args['module'] ) ) {
			$where[] = 'module = %s';
			$values[] = sanitize_key( $args['module'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$where[] = 'message LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}
		$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE " . implode( ' AND ', $where );
		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function clear() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$this->table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function purge_old_logs() {
		global $wpdb;
		$days = (int) $this->settings->get( 'log_retention_days' );
		if ( $days < 1 ) {
			return;
		}
		$threshold = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE created_at < %s",
				$threshold
			)
		);
	}
}
