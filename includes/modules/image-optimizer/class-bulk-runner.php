<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Image_Bulk_Runner {
	protected $module;
	public function __construct( $module ) {
		$this->module = $module;
	}
	public function register() {
		add_action( 'wp_ajax_skmt_image_bulk_process', array( $this, 'ajax_process' ) );
	}
	public function ajax_process() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'studio-kyne-mini-tools' ) ), 403 );
		}
		check_ajax_referer( 'skmt_image_bulk' );
		if ( ! $this->module->plugin()->jobs()->acquire_lock( 'image_bulk', 45 ) ) {
			wp_send_json_error( array( 'message' => __( 'Un autre lot est déjà en cours.', 'studio-kyne-mini-tools' ) ), 409 );
		}
		$cursor     = isset( $_POST['cursor'] ) ? absint( $_POST['cursor'] ) : 0;
		$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 10;
		$batch_size = max( 1, min( 50, $batch_size ) );
		$ids = $this->get_candidate_ids( $cursor, $batch_size );
		$processed = 0;
		$skipped   = 0;
		$failed    = 0;
		$last_id   = $cursor;
		foreach ( $ids as $attachment_id ) {
			$last_id = max( $last_id, (int) $attachment_id );
			$status  = $this->module->optimize_attachment( (int) $attachment_id, false, true );
			if ( 'success' === $status ) {
				++$processed;
			} elseif ( 'skipped' === $status ) {
				++$skipped;
			} else {
				++$failed;
			}
		}
		$completed = count( $ids ) < $batch_size;
		$total     = $this->count_candidates();
		$this->module->plugin()->jobs()->release_lock( 'image_bulk' );
		wp_send_json_success(
			array(
				'cursor'          => $last_id,
				'batch_processed' => $processed,
				'batch_skipped'   => $skipped,
				'batch_failed'    => $failed,
				'completed'       => $completed,
				'total'           => $total,
				'message'         => $completed ? __( 'Conversion de masse terminée.', 'studio-kyne-mini-tools' ) : __( 'Lot traité.', 'studio-kyne-mini-tools' ),
			)
		);
	}
	protected function count_candidates() {
		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => array( 'image/jpeg', 'image/png', 'image/gif' ),
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'no_found_rows'          => false,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return (int) $query->found_posts;
	}
	protected function get_candidate_ids( $cursor, $limit ) {
		global $wpdb;

		$mime_types = array( 'image/jpeg', 'image/png', 'image/gif' );
		$placeholders = implode( ', ', array_fill( 0, count( $mime_types ), '%s' ) );

		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND ID > %d AND post_mime_type IN ({$placeholders}) ORDER BY ID ASC LIMIT %d";
		$params = array_merge( array( absint( $cursor ) ), $mime_types, array( absint( $limit ) ) );

		$query = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$ids   = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_values( array_map( 'absint', (array) $ids ) );
	}
}
