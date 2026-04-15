<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Image_Bulk_Runner {
	protected $module;
	protected $state_option_name = 'skmt_image_bulk_state';
	protected $cron_hook = 'skmt_image_bulk_cron_tick';

	public function __construct( $module ) {
		$this->module = $module;
	}

	public function register() {
		add_action( 'wp_ajax_skmt_image_bulk_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_skmt_image_bulk_stop', array( $this, 'ajax_stop' ) );
		add_action( 'wp_ajax_skmt_image_bulk_status', array( $this, 'ajax_status' ) );
		add_action( 'wp_ajax_skmt_image_bulk_process', array( $this, 'ajax_status' ) );
		add_action( $this->cron_hook, array( $this, 'cron_process' ) );
	}

	public function ajax_start() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'studio-kyne-mini-tools' ) ), 403 );
		}

		check_ajax_referer( 'skmt_image_bulk' );

		$state = $this->get_state();
		if ( ! empty( $state['running'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Une conversion planifiée est déjà en cours.', 'studio-kyne-mini-tools' ),
					'state'   => $this->prepare_state_for_response( $state ),
				),
				409
			);
		}

		$total_candidates = $this->count_candidates_after( 0 );
		$state            = $this->get_default_state();
		$state['total']   = $total_candidates;
		$state['running'] = $total_candidates > 0;
		$state['completed'] = 0 === $total_candidates;
		$state['stopped'] = false;
		$state['started_at'] = time();
		$state['updated_at'] = time();
		$state['last_message'] = $total_candidates > 0
			? __( 'Conversion planifiée démarrée.', 'studio-kyne-mini-tools' )
			: __( 'Aucune image éligible à traiter.', 'studio-kyne-mini-tools' );

		if ( ! $state['running'] ) {
			$state['finished_at'] = time();
			SKMT_Notifications::add( 'info', __( 'Aucune image éligible pour la conversion planifiée.', 'studio-kyne-mini-tools' ) );
		} else {
			$this->schedule_next_tick( 3 );
			SKMT_Notifications::add( 'info', __( 'Conversion planifiée lancée en arrière-plan.', 'studio-kyne-mini-tools' ) );
		}

		$this->save_state( $state );

		wp_send_json_success(
			array(
				'message' => $state['last_message'],
				'state'   => $this->prepare_state_for_response( $state ),
			)
		);
	}

	public function ajax_stop() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'studio-kyne-mini-tools' ) ), 403 );
		}

		check_ajax_referer( 'skmt_image_bulk' );

		$state = $this->get_state();
		if ( empty( $state['running'] ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Aucune conversion planifiée en cours.', 'studio-kyne-mini-tools' ),
					'state'   => $this->prepare_state_for_response( $state ),
				)
			);
		}

		$state['running']      = false;
		$state['stopped']      = true;
		$state['completed']    = false;
		$state['updated_at']   = time();
		$state['finished_at']  = time();
		$state['last_message'] = __( 'Conversion planifiée arrêtée.', 'studio-kyne-mini-tools' );

		$this->save_state( $state );
		wp_clear_scheduled_hook( $this->cron_hook );
		$this->module->plugin()->jobs()->release_lock( 'image_bulk' );

		SKMT_Notifications::add( 'warning', __( 'Conversion planifiée arrêtée manuellement.', 'studio-kyne-mini-tools' ) );

		wp_send_json_success(
			array(
				'message' => $state['last_message'],
				'state'   => $this->prepare_state_for_response( $state ),
			)
		);
	}

	public function ajax_status() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'studio-kyne-mini-tools' ) ), 403 );
		}

		check_ajax_referer( 'skmt_image_bulk' );

		$state = $this->get_state();

		wp_send_json_success(
			array(
				'message' => ! empty( $state['last_message'] ) ? $state['last_message'] : __( 'Statut récupéré.', 'studio-kyne-mini-tools' ),
				'state'   => $this->prepare_state_for_response( $state ),
			)
		);
	}

	public function cron_process() {
		$state = $this->get_state();
		if ( empty( $state['running'] ) ) {
			return;
		}

		if ( ! $this->module->plugin()->jobs()->acquire_lock( 'image_bulk', 60 ) ) {
			$this->schedule_next_tick( 15 );
			return;
		}

		$batch_processed = 0;
		$batch_skipped   = 0;
		$batch_failed    = 0;

		try {
			$cursor     = absint( $state['cursor'] ?? 0 );
			$batch_size = max( 1, min( 50, absint( $state['batch_size'] ?? 10 ) ) );
			$ids        = $this->get_candidate_ids( $cursor, $batch_size );

			if ( empty( $ids ) ) {
				$this->complete_state( $state );
				return;
			}

			$last_id = $cursor;
			foreach ( $ids as $attachment_id ) {
				$last_id = max( $last_id, (int) $attachment_id );
				$status  = $this->module->optimize_attachment( (int) $attachment_id, false, true );

				if ( 'success' === $status ) {
					++$batch_processed;
				} elseif ( 'skipped' === $status ) {
					++$batch_skipped;
				} else {
					++$batch_failed;
				}
			}

			$state['cursor']      = $last_id;
			$state['processed']   = absint( $state['processed'] ?? 0 ) + $batch_processed;
			$state['skipped']     = absint( $state['skipped'] ?? 0 ) + $batch_skipped;
			$state['failed']      = absint( $state['failed'] ?? 0 ) + $batch_failed;
			$state['updated_at']  = time();
			$state['last_message'] = sprintf(
				/* translators: 1: processed images, 2: skipped images, 3: failed images. */
				__( 'Lot planifié: %1$d optimisées, %2$d ignorées, %3$d erreurs.', 'studio-kyne-mini-tools' ),
				$batch_processed,
				$batch_skipped,
				$batch_failed
			);

			if ( count( $ids ) < $batch_size ) {
				$this->complete_state( $state );
				return;
			}

			$this->save_state( $state );
			$this->schedule_next_tick( 8 );
		} catch ( Exception $exception ) {
			$state['running']      = false;
			$state['completed']    = false;
			$state['stopped']      = true;
			$state['updated_at']   = time();
			$state['finished_at']  = time();
			$state['last_message'] = __( 'Erreur pendant la conversion planifiée.', 'studio-kyne-mini-tools' );
			$this->save_state( $state );
			SKMT_Notifications::add( 'error', __( 'Erreur pendant la conversion planifiée des images.', 'studio-kyne-mini-tools' ), array( 'message' => $exception->getMessage() ) );
		} finally {
			$this->module->plugin()->jobs()->release_lock( 'image_bulk' );
		}
	}

	public function stop_scheduled_runner() {
		wp_clear_scheduled_hook( $this->cron_hook );
		$this->module->plugin()->jobs()->release_lock( 'image_bulk' );

		$state = $this->get_state();
		if ( ! empty( $state['running'] ) ) {
			$state['running'] = false;
			$state['stopped'] = true;
			$state['updated_at'] = time();
			$state['finished_at'] = time();
			$state['last_message'] = __( 'Conversion planifiée arrêtée.', 'studio-kyne-mini-tools' );
			$this->save_state( $state );
		}
	}

	protected function complete_state( $state ) {
		$state['running']      = false;
		$state['completed']    = true;
		$state['stopped']      = false;
		$state['updated_at']   = time();
		$state['finished_at']  = time();
		$state['last_message'] = sprintf(
			/* translators: 1: processed images, 2: skipped images, 3: failed images. */
			__( 'Conversion planifiée terminée: %1$d optimisées, %2$d ignorées, %3$d erreurs.', 'studio-kyne-mini-tools' ),
			absint( $state['processed'] ?? 0 ),
			absint( $state['skipped'] ?? 0 ),
			absint( $state['failed'] ?? 0 )
		);

		$this->save_state( $state );
		wp_clear_scheduled_hook( $this->cron_hook );

		SKMT_Notifications::add( 'success', $state['last_message'] );
	}

	protected function schedule_next_tick( $delay_seconds = 8 ) {
		$delay_seconds = max( 2, absint( $delay_seconds ) );
		if ( ! wp_next_scheduled( $this->cron_hook ) ) {
			wp_schedule_single_event( time() + $delay_seconds, $this->cron_hook );
		}
	}

	protected function get_default_state() {
		return array(
			'running'      => false,
			'completed'    => false,
			'stopped'      => false,
			'cursor'       => 0,
			'total'        => 0,
			'processed'    => 0,
			'skipped'      => 0,
			'failed'       => 0,
			'batch_size'   => 10,
			'started_at'   => 0,
			'updated_at'   => 0,
			'finished_at'  => 0,
			'last_message' => '',
		);
	}

	protected function get_state() {
		$state = get_option( $this->state_option_name, array() );
		$state = is_array( $state ) ? $state : array();
		return wp_parse_args( $state, $this->get_default_state() );
	}

	protected function save_state( $state ) {
		$state = is_array( $state ) ? $state : array();
		update_option( $this->state_option_name, wp_parse_args( $state, $this->get_default_state() ), false );
	}

	protected function prepare_state_for_response( $state ) {
		$state = wp_parse_args( is_array( $state ) ? $state : array(), $this->get_default_state() );

		$done = absint( $state['processed'] ) + absint( $state['skipped'] ) + absint( $state['failed'] );
		$total = absint( $state['total'] );
		$progress = $total > 0 ? min( 100, (int) round( ( $done / $total ) * 100 ) ) : 0;

		$state['done'] = $done;
		$state['progress_percent'] = $progress;

		return $state;
	}

	protected function count_candidates_after( $cursor ) {
		global $wpdb;

		$mime_types   = array( 'image/jpeg', 'image/png', 'image/gif' );
		$placeholders = implode( ', ', array_fill( 0, count( $mime_types ), '%s' ) );

		$sql    = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND ID > %d AND post_mime_type IN ({$placeholders})";
		$params = array_merge( array( absint( $cursor ) ), $mime_types );

		$query = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
