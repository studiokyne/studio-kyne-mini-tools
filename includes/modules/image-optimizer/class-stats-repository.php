<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Image_Stats_Repository {
	protected $table_name;
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'skmt_image_stats';
	}
	public static function create_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'skmt_image_stats';
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL,
			module varchar(100) NOT NULL,
			original_size bigint(20) unsigned NULL,
			final_size bigint(20) unsigned NULL,
			bytes_saved bigint(20) unsigned NULL,
			percent_saved decimal(8,2) NULL,
			original_mime varchar(100) NULL,
			final_mime varchar(100) NULL,
			original_width int(11) NULL,
			original_height int(11) NULL,
			final_width int(11) NULL,
			final_height int(11) NULL,
			editor varchar(50) NULL,
			status varchar(20) NOT NULL,
			message text NULL,
			processed_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY attachment_id (attachment_id),
			KEY module (module),
			KEY status (status),
			KEY processed_at (processed_at)
		) {$charset_collate};";
		dbDelta( $sql );
	}
	public function save( $attachment_id, $data ) {
		global $wpdb;
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id < 1 ) { return; }
		$payload = array(
			'attachment_id'   => $attachment_id,
			'module'          => 'image-optimizer',
			'original_size'   => isset( $data['original_size'] ) ? absint( $data['original_size'] ) : null,
			'final_size'      => isset( $data['final_size'] ) ? absint( $data['final_size'] ) : null,
			'bytes_saved'     => isset( $data['bytes_saved'] ) ? absint( $data['bytes_saved'] ) : null,
			'percent_saved'   => isset( $data['percent_saved'] ) ? (float) $data['percent_saved'] : null,
			'original_mime'   => isset( $data['original_mime'] ) ? sanitize_mime_type( $data['original_mime'] ) : null,
			'final_mime'      => isset( $data['final_mime'] ) ? sanitize_mime_type( $data['final_mime'] ) : null,
			'original_width'  => isset( $data['original_width'] ) ? absint( $data['original_width'] ) : null,
			'original_height' => isset( $data['original_height'] ) ? absint( $data['original_height'] ) : null,
			'final_width'     => isset( $data['final_width'] ) ? absint( $data['final_width'] ) : null,
			'final_height'    => isset( $data['final_height'] ) ? absint( $data['final_height'] ) : null,
			'editor'          => isset( $data['editor'] ) ? sanitize_text_field( $data['editor'] ) : null,
			'status'          => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'success',
			'message'         => isset( $data['message'] ) ? sanitize_text_field( $data['message'] ) : '',
			'processed_at'    => current_time( 'mysql' ),
		);
		$formats = array( '%d', '%s', '%d', '%d', '%d', '%f', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' );
		$exists = $this->get( $attachment_id );
		if ( $exists ) {
			$wpdb->update( $this->table_name, $payload, array( 'attachment_id' => $attachment_id ), $formats, array( '%d' ) );
		} else {
			$wpdb->insert( $this->table_name, $payload, $formats );
		}
	}
	public function get( $attachment_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE attachment_id = %d LIMIT 1", absint( $attachment_id ) ), ARRAY_A );
		return $row ?: null;
	}
	public function get_recent( $limit = 20 ) {
		global $wpdb;
		$limit = max( 1, absint( $limit ) );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table_name} ORDER BY processed_at DESC LIMIT %d", $limit ), ARRAY_A );
	}
	public function count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
	public function get_aggregate() {
		global $wpdb;
		$row = $wpdb->get_row( "SELECT COUNT(*) as total_images, COALESCE(SUM(original_size), 0) as original_bytes, COALESCE(SUM(final_size), 0) as final_bytes, COALESCE(SUM(bytes_saved), 0) as saved_bytes FROM {$this->table_name} WHERE status = 'success'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = is_array( $row ) ? $row : array();
		$original_bytes = isset( $row['original_bytes'] ) ? (int) $row['original_bytes'] : 0;
		$final_bytes    = isset( $row['final_bytes'] ) ? (int) $row['final_bytes'] : 0;
		$row['percent_saved'] = $original_bytes > 0 ? round( ( ( $original_bytes - $final_bytes ) / $original_bytes ) * 100, 2 ) : 0;
		return $row;
	}
}
