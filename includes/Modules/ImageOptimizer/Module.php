<?php
namespace StudioKyne\MiniTools\Modules\ImageOptimizer;

use StudioKyne\MiniTools\Core\AbstractModule;
use StudioKyne\MiniTools\Admin\Admin;

/**
 * Module Image Optimizer — orchestrateur.
 *
 * Délègue le traitement des fichiers à ImageProcessor,
 * le workflow bulk à BulkProcessor,
 * et l'UI médiathèque à MediaLibrary.
 */
class Module extends AbstractModule {

	private const BATCH_SIZE = 5;

	private const STATS_SUFFIX      = '_stats';
	private const BULK_STATE_SUFFIX = '_bulk_state';

	/* ================================================================
	 * SOUS-OBJETS (initialisés dans init())
	 * ================================================================ */

	private ImageProcessor $processor;
	private BulkProcessor $bulk;
	private MediaLibrary $media_library;

	/**
	 * Réglages actifs du module (cache mémoire).
	 */
	private array $settings = [];

	/* ================================================================
	 * INITIALISATION
	 * ================================================================ */

	/**
	 * Charge les réglages, crée les sous-objets et enregistre les hooks.
	 */
	public function init(): void {
		$this->settings = $this->get_settings();

		// Sous-objets
		$this->processor = new ImageProcessor( $this->settings );

		$this->bulk = new BulkProcessor(
			$this->get_module_option_key() . self::BULK_STATE_SUFFIX,
			fn( int $id ) => $this->process_and_update_attachment( $id, true ),
			fn(): array   => $this->get_stats(),
			fn( int $user_id ) => $this->notify_bulk_complete( $user_id )
		);

		$this->media_library = new MediaLibrary( $this, $this->processor );
		$this->media_library->init();

		// Hooks d'upload
		add_filter( 'wp_handle_upload', [ $this, 'handle_upload' ] );
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'optimize_attachment_sizes' ], 10, 2 );

		// Alt text automatique
		add_action( 'add_attachment', [ $this, 'generate_alt_text' ] );

		// Bulk AJAX
		add_action( 'wp_ajax_skmt_image_optimizer_bulk', [ $this, 'ajax_bulk_start' ] );
		add_action( 'wp_ajax_skmt_image_optimizer_bulk_status', [ $this, 'ajax_bulk_status' ] );

		// Cron
		add_action( 'skmt_image_optimizer_cron', [ $this, 'run_cron_batch' ] );
	}

	/* ================================================================
	 * RÉGLAGES
	 * ================================================================ */

	public function get_settings(): array {
		return $this->get_module_settings( [
			'optimize_on_upload' => true,
			'format_mode'        => 'auto',
			'quality'            => 75,
			'max_width'          => 2560,
			'max_height'         => 2560,
			'strip_exif'         => true,
			'generate_alt'       => true,
			'keep_original'      => false,
		] );
	}

	public function save_settings( array $settings ): bool {
		$sanitized = [
			'optimize_on_upload' => isset( $settings['optimize_on_upload'] ),
			'format_mode'        => isset( $settings['format_mode'] ) && in_array( $settings['format_mode'], [ 'auto', 'avif', 'webp' ], true )
				? sanitize_key( $settings['format_mode'] )
				: 'auto',
			'quality'            => isset( $settings['quality'] ) ? min( 100, max( 1, absint( $settings['quality'] ) ) ) : 75,
			'max_width'          => isset( $settings['max_width'] ) ? max( 100, absint( $settings['max_width'] ) ) : 2560,
			'max_height'         => isset( $settings['max_height'] ) ? max( 100, absint( $settings['max_height'] ) ) : 2560,
			'strip_exif'         => isset( $settings['strip_exif'] ),
			'generate_alt'       => isset( $settings['generate_alt'] ),
			'keep_original'      => isset( $settings['keep_original'] ),
		];

		$this->settings = $sanitized;

		return $this->save_module_settings( $sanitized );
	}

	/* ================================================================
	 * ASSETS ADMIN
	 * ================================================================ */

	public function get_admin_css(): array {
		return [
			SKMT_ASSETS_URL . 'admin/css/modules/image-optimizer.css',
		];
	}

	public function get_admin_js(): array {
		return [
			SKMT_ASSETS_URL . 'admin/js/modules/image-optimizer.js',
		];
	}

	public function get_admin_js_data(): array {
		return [
			'bulkState' => $this->bulk->get_state(),
			'i18n' => [
				'bulkRunning'   => __( 'Optimisation en cours…', 'studio-kyne-mini-tools' ),
				'bulkProcessed' => __( 'Traité :', 'studio-kyne-mini-tools' ),
				'bulkRemaining' => __( 'Restant :', 'studio-kyne-mini-tools' ),
				'bulkDone'      => __( 'Optimisation terminée', 'studio-kyne-mini-tools' ),
				'bulkComplete'  => __( 'Toutes les images ont été optimisées.', 'studio-kyne-mini-tools' ),
				'bulkRetry'     => __( 'Réessayer', 'studio-kyne-mini-tools' ),
				'singleRunning' => __( 'Optimisation…', 'studio-kyne-mini-tools' ),
				'singleDone'    => __( 'Optimisée', 'studio-kyne-mini-tools' ),
				'singleError'   => __( 'Erreur', 'studio-kyne-mini-tools' ),
			],
		];
	}

	/**
	 * Ajoute une notice persistante à l'utilisateur qui a lancé le bulk,
	 * pour qu'il soit informé même si le lot s'est terminé pendant qu'il
	 * avait quitté la page (ou via une reprise cron en arrière-plan).
	 */
	private function notify_bulk_complete( int $user_id ): void {
		if ( ! $user_id ) {
			return;
		}
		Admin::add_persistent_notice(
			'image_optimizer_bulk_done',
			__( 'Optimisation en masse des images terminée.', 'studio-kyne-mini-tools' ),
			'success',
			$user_id
		);
	}

	/* ================================================================
	 * LIFECYCLE
	 * ================================================================ */

	public function on_deactivate(): void {
		// Supprimer les crons en attente.
		$timestamp = wp_next_scheduled( 'skmt_image_optimizer_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'skmt_image_optimizer_cron' );
		}
	}

	/* ================================================================
	 * STATIC : INSTALL / UNINSTALL
	 * ================================================================ */

	public static function get_uninstall_keys(): array {
		return [
			'options' => [
				'skmt_module_image_optimizer',
				'skmt_module_image_optimizer' . self::STATS_SUFFIX,
				'skmt_module_image_optimizer' . self::BULK_STATE_SUFFIX,
			],
			'meta' => [
				'_skmt_optimized',
				'_skmt_original_bytes',
				'_skmt_optimized_bytes',
				'_skmt_bytes_saved',
				'_skmt_main_original_bytes',
				'_skmt_main_optimized_bytes',
				'_skmt_main_bytes_saved',
				'_skmt_optimized_format',
				'_skmt_optimized_mime',
			],
		];
	}

	/* ================================================================
	 * HOOKS D'UPLOAD
	 * ================================================================ */

	/**
	 * Hook wp_handle_upload : optimise le fichier original immédiatement après upload.
	 */
	public function handle_upload( array $upload ): array {
		if ( ! $this->settings['optimize_on_upload'] ) {
			return $upload;
		}

		$file_path = $upload['file'] ?? '';
		$file_type = $upload['type'] ?? '';

		if ( empty( $file_path ) || ! $this->processor->is_supported_mime( $file_type ) ) {
			return $upload;
		}

		if ( $this->processor->is_animated( $file_path, $file_type ) ) {
			return $upload;
		}

		$this->processor->optimize( $file_path );

		return $upload;
	}

	/**
	 * Hook wp_generate_attachment_metadata : optimise + convertit original et miniatures.
	 */
	public function optimize_attachment_sizes( array $metadata, int $attachment_id ): array {
		if ( ! $this->settings['optimize_on_upload'] ) {
			return $metadata;
		}

		return $this->process_attachment_metadata( $metadata, $attachment_id, false );
	}

	/* ================================================================
	 * TRAITEMENT D'UN ATTACHMENT
	 * ================================================================ */

	/**
	 * Point d'entrée public : traite un attachment et met à jour ses métadonnées WP.
	 * Utilisé par MediaLibrary (single) et BulkProcessor (batch).
	 */
	public function process_and_update_attachment( int $attachment_id, bool $force = true ): void {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( $metadata ) {
			$metadata = $this->process_attachment_metadata( $metadata, $attachment_id, $force );
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		$this->generate_alt_text( $attachment_id );
	}

	/**
	 * Traite toutes les tailles d'un attachment (optimisation + conversion).
	 *
	 * @param bool $force Ignore le flag "déjà optimisé".
	 */
	public function process_attachment_metadata( array $metadata, int $attachment_id, bool $force ): array {
		$mime_type = $this->processor->get_mime_type( '', $attachment_id );

		if ( empty( $mime_type ) || ! $this->processor->is_supported_mime( $mime_type ) ) {
			return $metadata;
		}

		$attached_file = get_attached_file( $attachment_id );

		if ( $this->processor->is_animated( (string) $attached_file, $mime_type ) ) {
			return $metadata;
		}

		if ( ! $force && $this->is_already_optimized( $attachment_id ) ) {
			return $metadata;
		}

		if ( empty( $metadata['file'] ) ) {
			return $metadata;
		}

		$upload_dir = wp_upload_dir();
		$base_path  = trailingslashit( $upload_dir['basedir'] );
		$subdir     = dirname( $metadata['file'] );
		$sizes_path = trailingslashit( $base_path . $subdir );

		$total_before = 0;
		$total_after  = 0;
		$main_before  = 0;
		$main_after   = 0;
		$size_updates = [];

		// --- Miniatures ---
		if ( ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size => $size_data ) {
				if ( empty( $size_data['file'] ) ) {
					continue;
				}

				$size_file = $sizes_path . $size_data['file'];
				if ( ! file_exists( $size_file ) ) {
					continue;
				}

				$before        = (int) filesize( $size_file );
				$total_before += $before;

				$this->processor->optimize( $size_file );
				$converted  = $this->processor->convert( $size_file, $mime_type );
				$final_file = $converted ?: $size_file;

				$after        = file_exists( $final_file ) ? (int) filesize( $final_file ) : $before;
				$total_after += $after;

				if ( $converted && $converted !== $size_file ) {
					$size_updates[ $size ] = [
						'file' => $converted,
						'mime' => $this->processor->get_mime_type( $converted ),
					];
				}
			}
		}

		// --- Fichier original ---
		$original_file     = $base_path . $metadata['file'];
		$original_converted = false;
		$original_new_file  = '';

		if ( file_exists( $original_file ) ) {
			$before        = (int) filesize( $original_file );
			$main_before   = $before;
			$total_before += $before;

			$this->processor->optimize( $original_file );
			$converted  = $this->processor->convert( $original_file, $mime_type, $attachment_id );
			$final_file = $converted ?: $original_file;

			$after        = file_exists( $final_file ) ? (int) filesize( $final_file ) : $before;
			$main_after   = $after;
			$total_after += $after;

			if ( $converted && $converted !== $original_file ) {
				$original_converted = true;
				$original_new_file  = $converted;
				$this->update_attachment_database_refs( $attachment_id, $original_file, $converted );
			}
		}

		// --- Mise à jour des métadonnées WP ---
		if ( $original_converted ) {
			$metadata = $this->update_metadata_after_conversion( $metadata, $original_file, $original_new_file, $size_updates );
		} elseif ( ! empty( $size_updates ) ) {
			$metadata = $this->update_metadata_after_conversion( $metadata, '', '', $size_updates );
		}

		$metadata = $this->refresh_metadata_filesizes( $metadata, $base_path );

		// --- Stats et marquage ---
		if ( $total_before > 0 ) {
			$bytes_saved = max( $total_before - $total_after, 0 );
			$this->update_stats( $bytes_saved, $total_before );

			$final_mime = $original_converted
				? $this->processor->get_mime_type( $original_new_file )
				: $mime_type;

			$final_path = $original_converted ? $original_new_file : $original_file;
			$this->mark_attachment_optimized(
				$attachment_id,
				$total_before, $total_after,
				$final_path, $final_mime,
				$main_before, $main_after
			);
		}

		return $metadata;
	}

	/* ================================================================
	 * ALT TEXT
	 * ================================================================ */

	/**
	 * Génère automatiquement le texte alternatif depuis le nom de fichier.
	 */
	public function generate_alt_text( int $attachment_id ): void {
		if ( ! $this->settings['generate_alt'] ) {
			return;
		}

		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( ! empty( $alt ) ) {
			return;
		}

		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return;
		}

		$filename = pathinfo( $file, PATHINFO_FILENAME );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $this->processor->filename_to_alt( $filename ) );
	}

	/* ================================================================
	 * STATS
	 * ================================================================ */

	private function get_stats_key(): string {
		return $this->get_module_option_key() . self::STATS_SUFFIX;
	}

	private function update_stats( int $bytes_saved, int $original_bytes ): void {
		$stats = wp_parse_args( get_option( $this->get_stats_key(), [] ), [
			'optimized'      => 0,
			'bytes_saved'    => 0,
			'original_bytes' => 0,
		] );

		$stats['optimized']++;
		$stats['bytes_saved']    += max( $bytes_saved, 0 );
		$stats['original_bytes'] += max( $original_bytes, 0 );

		update_option( $this->get_stats_key(), $stats, false );
	}

	public function get_stats(): array {
		$defaults = [
			'optimized'      => 0,
			'bytes_saved'    => 0,
			'original_bytes' => 0,
		];
		$stats = wp_parse_args( get_option( $this->get_stats_key(), [] ), $defaults );

		return array_merge( $stats, [
			'capabilities' => $this->processor->get_capabilities(),
		] );
	}

	/**
	 * Estimation des gains pour le bulk (utilisée par le template de réglages).
	 */
	public function get_bulk_preview(): array {
		return $this->bulk->get_preview();
	}

	/* ================================================================
	 * META ATTACHMENTS
	 * ================================================================ */

	public function is_already_optimized( int $attachment_id ): bool {
		return (bool) get_post_meta( $attachment_id, '_skmt_optimized', true );
	}

	private function mark_attachment_optimized(
		int $attachment_id,
		int $original_bytes,
		int $optimized_bytes,
		string $final_file,
		string $final_mime,
		int $main_original = 0,
		int $main_optimized = 0
	): void {
		$bytes_saved      = max( $original_bytes - $optimized_bytes, 0 );
		$main_bytes_saved = max( $main_original - $main_optimized, 0 );
		$format           = strtolower( pathinfo( $final_file, PATHINFO_EXTENSION ) );

		update_post_meta( $attachment_id, '_skmt_optimized', time() );
		update_post_meta( $attachment_id, '_skmt_original_bytes', $original_bytes );
		update_post_meta( $attachment_id, '_skmt_optimized_bytes', $optimized_bytes );
		update_post_meta( $attachment_id, '_skmt_bytes_saved', $bytes_saved );
		update_post_meta( $attachment_id, '_skmt_main_original_bytes', $main_original );
		update_post_meta( $attachment_id, '_skmt_main_optimized_bytes', $main_optimized );
		update_post_meta( $attachment_id, '_skmt_main_bytes_saved', $main_bytes_saved );
		update_post_meta( $attachment_id, '_skmt_optimized_format', $format );
		update_post_meta( $attachment_id, '_skmt_optimized_mime', $final_mime );
	}

	/* ================================================================
	 * MÉTADONNÉES WP
	 * ================================================================ */

	private function update_attachment_database_refs( int $attachment_id, string $old_file, string $new_file ): void {
		update_attached_file( $attachment_id, $new_file );

		$mime = $this->processor->get_mime_type( $new_file );
		if ( $mime ) {
			wp_update_post( [
				'ID'             => $attachment_id,
				'post_mime_type' => $mime,
			] );
		}
	}

	private function update_metadata_after_conversion( array $metadata, string $old_file, string $new_file, array $size_updates ): array {
		if ( $old_file && $new_file && ! empty( $metadata['file'] ) ) {
			$old_info = pathinfo( $old_file );
			$new_info = pathinfo( $new_file );
			$metadata['file'] = str_replace( $old_info['basename'], $new_info['basename'], $metadata['file'] );
		}

		if ( ! empty( $metadata['sizes'] ) && ! empty( $size_updates ) ) {
			foreach ( $size_updates as $size => $update ) {
				if ( empty( $metadata['sizes'][ $size ] ) ) {
					continue;
				}
				$metadata['sizes'][ $size ]['file']      = basename( $update['file'] );
				$metadata['sizes'][ $size ]['mime-type']  = $update['mime'] ?? $metadata['sizes'][ $size ]['mime-type'];
			}
		}

		return $metadata;
	}

	private function refresh_metadata_filesizes( array $metadata, string $base_path ): array {
		if ( ! empty( $metadata['file'] ) ) {
			$original_path = $base_path . $metadata['file'];
			if ( file_exists( $original_path ) ) {
				$metadata['filesize'] = (int) filesize( $original_path );
			}
		}

		if ( empty( $metadata['sizes'] ) || empty( $metadata['file'] ) ) {
			return $metadata;
		}

		$subdir          = dirname( $metadata['file'] );
		$sizes_base_path = trailingslashit( $base_path . $subdir );

		foreach ( $metadata['sizes'] as $size => $size_data ) {
			if ( empty( $size_data['file'] ) ) {
				continue;
			}
			$size_path = $sizes_base_path . $size_data['file'];
			if ( file_exists( $size_path ) ) {
				$metadata['sizes'][ $size ]['filesize'] = (int) filesize( $size_path );
			}
		}

		return $metadata;
	}

	/* ================================================================
	 * DÉLÉGATION BULK (hooks cron + AJAX)
	 * ================================================================ */

	public function ajax_bulk_start(): void {
		$this->bulk->ajax_start( self::BATCH_SIZE );
	}

	public function ajax_bulk_status(): void {
		$this->bulk->ajax_status( self::BATCH_SIZE );
	}

	public function run_cron_batch(): void {
		$this->bulk->run_batch( self::BATCH_SIZE );
	}

	/* ================================================================
	 * COMPATIBILITÉ : capacités serveur (utilisées dans le template réglages)
	 * ================================================================ */

	public function get_capabilities(): array {
		return $this->processor->get_capabilities();
	}
}
