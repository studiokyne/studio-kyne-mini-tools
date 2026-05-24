<?php
namespace StudioKyne\MiniTools\Modules\ImageOptimizer;

use StudioKyne\MiniTools\Core\AbstractModule;

/**
 * Module Image Optimizer.
 *
 * Optimise automatiquement les images lors de l'upload :
 * - Conversion AVIF (fallback WebP)
 * - Compression configurable
 * - Redimensionnement
 * - Suppression EXIF
 * - Génération alt text
 * - Optimisation en masse
 */
class Module extends AbstractModule {
	private const BULK_BATCH_SIZE = 5;

	/**
	 * Clé d'option pour les réglages du module.
	 */
	private string $option_key = 'skmt_module_image_optimizer';

	/**
	 * Clé d'option pour les stats.
	 */
	private string $stats_key = 'skmt_image_optimizer_stats';

	/**
	 * Clé d'option pour l'etat du bulk.
	 */
	private string $bulk_state_key = 'skmt_image_optimizer_bulk_state';

	/**
	 * Réglages du module.
	 */
	private array $settings = [];

	/**
	 * Capacités détectées du serveur.
	 */
	private ?array $capabilities = null;

	/**
	 * Fichiers deja optimises sur cette requete.
	 */
	private array $optimized_paths = [];

	/**
	 * Initialise le module.
	 */
	public function init(): void {
		$this->settings = $this->get_settings();

		// Optimisation à l'upload
		add_filter( 'wp_handle_upload', [ $this, 'handle_upload' ] );
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'optimize_attachment_sizes' ], 10, 2 );

		// Génération du alt text
		add_action( 'add_attachment', [ $this, 'generate_alt_text' ] );

		// Colonne dans la médiathèque
		add_filter( 'manage_media_columns', [ $this, 'add_media_column' ] );
		add_action( 'manage_media_custom_column', [ $this, 'render_media_column' ], 10, 2 );

		// Bulk optimization AJAX
		add_action( 'wp_ajax_skmt_image_optimizer_bulk', [ $this, 'ajax_bulk_optimize' ] );
		add_action( 'wp_ajax_skmt_image_optimizer_bulk_status', [ $this, 'ajax_bulk_status' ] );

		// Traitement async via cron
		add_action( 'skmt_image_optimizer_cron', [ $this, 'run_cron_batch' ], 10, 1 );

		// Assets et media modal
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_media_assets' ] );
		add_filter( 'attachment_fields_to_edit', [ $this, 'add_media_optimizer_fields' ], 10, 2 );
		add_action( 'wp_ajax_skmt_optimize_single', [ $this, 'ajax_optimize_single' ] );
	}

	/* ================================================================
	 * RÉGLAGES
	 * ================================================================ */

	/**
	 * Retourne les réglages du module.
	 */
	public function get_settings(): array {
		$defaults = [
			'optimize_on_upload' => true,
			'format_mode'        => 'auto',   // auto | avif | webp
			'quality'            => 75,
			'max_width'          => 2560,
			'max_height'         => 2560,
			'strip_exif'         => true,
			'generate_alt'       => true,
			'keep_original'      => false,
		];

		$stored = $this->get_option_value( $this->option_key, [] );

		return wp_parse_args( $stored, $defaults );
	}

	/**
	 * Enregistre les réglages du module.
	 */
	public function save_settings( array $settings ): bool {
		$sanitized = [
			'optimize_on_upload' => isset( $settings['optimize_on_upload'] ),
			'format_mode'        => isset( $settings['format_mode'] ) && in_array( $settings['format_mode'], [ 'auto', 'avif', 'webp' ], true )
				? sanitize_key( $settings['format_mode'] )
				: 'auto',
			'quality'            => isset( $settings['quality'] ) ? absint( $settings['quality'] ) : 75,
			'max_width'          => isset( $settings['max_width'] ) ? absint( $settings['max_width'] ) : 2560,
			'max_height'         => isset( $settings['max_height'] ) ? absint( $settings['max_height'] ) : 2560,
			'strip_exif'         => isset( $settings['strip_exif'] ),
			'generate_alt'       => isset( $settings['generate_alt'] ),
			'keep_original'      => isset( $settings['keep_original'] ),
		];

		$this->settings = $sanitized;

		return $this->update_option_value( $this->option_key, $sanitized );
	}

	/**
	 * Retourne les styles admin du module.
	 */
	public function get_admin_css(): array {
		return [
			SKMT_ASSETS_URL . 'admin/css/modules/image-optimizer.css',
		];
	}

	/**
	 * Retourne les scripts admin du module.
	 */
	public function get_admin_js(): array {
		return [
			SKMT_ASSETS_URL . 'admin/js/modules/image-optimizer.js',
		];
	}

	/* ================================================================
	 * CAPACITÉS SERVEUR
	 * ================================================================ */

	/**
	 * Détecte et retourne les capacités du serveur.
	 */
	public function get_capabilities(): array {
		if ( null !== $this->capabilities ) {
			return $this->capabilities;
		}

		$has_imagick = extension_loaded( 'imagick' );
		$has_gd      = extension_loaded( 'gd' );

		$can_avif = false;
		$can_webp = false;

		if ( $has_imagick ) {
			$formats = \Imagick::queryFormats();
			$can_avif = in_array( 'AVIF', $formats, true );
			$can_webp = in_array( 'WEBP', $formats, true );
		}

		if ( $has_gd ) {
			$gd_info  = gd_info();
			$can_avif = $can_avif || ( $gd_info['AVIF Support'] ?? false );
			$can_webp = $can_webp || ( $gd_info['WebP Support'] ?? false );
		}

		$this->capabilities = [
			'imagick'     => $has_imagick,
			'gd'          => $has_gd,
			'avif'        => $can_avif,
			'webp'        => $can_webp,
			'editor'      => $has_imagick ? 'imagick' : ( $has_gd ? 'gd' : 'none' ),
		];

		return $this->capabilities;
	}

	/**
	 * Détermine le format cible selon les réglages et capacités.
	 */
	private function get_target_format(): string {
		$cap = $this->get_capabilities();
		$mode = $this->settings['format_mode'];

		if ( 'avif' === $mode && $cap['avif'] ) {
			return 'avif';
		}

		if ( 'webp' === $mode && $cap['webp'] ) {
			return 'webp';
		}

		// Mode auto : AVIF > WebP
		if ( $cap['avif'] ) {
			return 'avif';
		}

		if ( $cap['webp'] ) {
			return 'webp';
		}

		return '';
	}

	/* ================================================================
	 * OPTIMISATION UPLOAD
	 * ================================================================ */

	/**
	 * Gère l'upload d'un fichier.
	 *
	 * @param array $upload Données de l'upload.
	 */
	public function handle_upload( array $upload ): array {
		if ( ! $this->settings['optimize_on_upload'] ) {
			return $upload;
		}

		$file_path = $upload['file'] ?? '';
		$file_type = $upload['type'] ?? '';

		if ( empty( $file_path ) || ! $this->is_image( $file_type ) ) {
			return $upload;
		}

		if ( $this->is_animated_image( $file_path, $file_type ) ) {
			return $upload;
		}

		$this->optimize_file( $file_path );

		return $upload;
	}

	/**
	 * Optimise les tailles générées d'un attachment.
	 *
	 * @param array $metadata      Métadonnées.
	 * @param int   $attachment_id ID de l'attachment.
	 */
	public function optimize_attachment_sizes( array $metadata, int $attachment_id ): array {
		if ( ! $this->settings['optimize_on_upload'] ) {
			return $metadata;
		}

		return $this->process_attachment_metadata( $metadata, $attachment_id, false );
	}

	/**
	 * Traite un attachment pour optimisation et conversion.
	 */
	private function process_attachment_metadata( array $metadata, int $attachment_id, bool $force ): array {
		$mime_type = $this->get_file_mime_type( '', $attachment_id );
		if ( empty( $mime_type ) || ! $this->is_supported_image_mime( $mime_type ) ) {
			return $metadata;
		}

		$attached_file = get_attached_file( $attachment_id );
		if ( $this->is_animated_image( $attached_file, $mime_type ) ) {
			return $metadata;
		}

		if ( ! $force && $this->is_already_optimized( $attachment_id ) ) {
			return $metadata;
		}

		$upload_dir = wp_upload_dir();
		$base_path  = trailingslashit( $upload_dir['basedir'] );
		
		if ( empty( $metadata['file'] ) ) {
			return $metadata;
		}

		$subdir     = dirname( $metadata['file'] );
		$sizes_path = trailingslashit( $base_path . $subdir );

		$total_before = 0;
		$total_after  = 0;
		$main_before  = 0;
		$main_after   = 0;
		$size_updates = [];
		$original_converted = false;
		$original_new_file = '';

		if ( ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size => $size_data ) {
				if ( empty( $size_data['file'] ) ) {
					continue;
				}

				$size_file = $sizes_path . $size_data['file'];
				if ( ! file_exists( $size_file ) ) {
					continue;
				}

				$before = filesize( $size_file );
				$total_before += $before;
				$this->optimize_file( $size_file );

				$converted = $this->convert_file( $size_file, $attachment_id, $mime_type );
				$final_file = $converted ?: $size_file;

				$after = file_exists( $final_file ) ? filesize( $final_file ) : $before;
				$total_after += $after;

				if ( $converted && $converted !== $size_file ) {
					$size_updates[ $size ] = [
						'file' => $converted,
						'mime' => $this->get_file_mime_type( $converted, 0 ),
					];
				}
			}
		}

		$original_file = $base_path . $metadata['file'];
		if ( file_exists( $original_file ) ) {
			$before = filesize( $original_file );
			$main_before = $before;
			$total_before += $before;
			$this->optimize_file( $original_file );

			$converted = $this->convert_file( $original_file, $attachment_id, $mime_type );
			$final_file = $converted ?: $original_file;
			$after = file_exists( $final_file ) ? filesize( $final_file ) : $before;
			$main_after = $after;
			$total_after += $after;

			if ( $converted && $converted !== $original_file ) {
				$original_converted = true;
				$original_new_file = $converted;
				$this->update_attachment_database_refs( $attachment_id, $original_file, $converted );
			}
		}

		if ( $original_converted ) {
			$metadata = $this->update_metadata_after_conversion( $metadata, $original_file, $original_new_file, $size_updates );
		} elseif ( ! empty( $size_updates ) ) {
			$metadata = $this->update_metadata_after_conversion( $metadata, '', '', $size_updates );
		}

		// Synchroniser les tailles de fichiers réelles dans les metadata WP.
		$metadata = $this->refresh_metadata_filesizes( $metadata, $base_path );

		if ( $total_before > 0 ) {
			$bytes_saved = max( $total_before - $total_after, 0 );
			$this->update_stats( $bytes_saved, $total_before );

			$final_mime = $original_converted ? $this->get_file_mime_type( $original_new_file, 0 ) : $mime_type;
			$final_file = $original_converted ? $original_new_file : $original_file;
			$this->mark_attachment_optimized( $attachment_id, $total_before, $total_after, $final_file, $final_mime, $main_before, $main_after );
		}

		return $metadata;
	}

	/**
	 * Recalcule les tailles réelles des fichiers (original + tailles).
	 */
	private function refresh_metadata_filesizes( array $metadata, string $base_path ): array {
		if ( ! empty( $metadata['file'] ) ) {
			$original_path = $base_path . $metadata['file'];
			if ( file_exists( $original_path ) ) {
				$metadata['filesize'] = filesize( $original_path );
			}
		}

		if ( empty( $metadata['sizes'] ) || empty( $metadata['file'] ) ) {
			return $metadata;
		}

		$subdir = dirname( $metadata['file'] );
		$sizes_base_path = trailingslashit( $base_path . $subdir );

		foreach ( $metadata['sizes'] as $size => $size_data ) {
			if ( empty( $size_data['file'] ) ) {
				continue;
			}

			$size_file_path = $sizes_base_path . $size_data['file'];
			if ( file_exists( $size_file_path ) ) {
				$metadata['sizes'][ $size ]['filesize'] = filesize( $size_file_path );
			}
		}

		return $metadata;
	}

	/**
	 * Génère le texte alternatif depuis le nom du fichier.
	 *
	 * @param int $attachment_id ID de l'attachment.
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
		$alt_text = $this->filename_to_alt( $filename );

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
	}

	/* ================================================================
	 * OPTIMISATION FICHIER
	 * ================================================================ */

	/**
	 * Optimise un fichier image (redimensionnement, compression, strip EXIF).
	 *
	 * @param string $file_path Chemin du fichier.
	 */
	private function optimize_file( string $file_path ): void {
		if ( ! file_exists( $file_path ) ) {
			return;
		}

		if ( isset( $this->optimized_paths[ $file_path ] ) ) {
			return;
		}

		$cap = $this->get_capabilities();

		// Utiliser Imagick si disponible (meilleur contrôle)
		if ( $cap['imagick'] ) {
			$this->optimize_with_imagick( $file_path );
		} elseif ( $cap['gd'] ) {
			$this->optimize_with_gd( $file_path );
		}

		$this->optimized_paths[ $file_path ] = true;
	}

	/**
	 * Optimise avec Imagick.
	 */
	private function optimize_with_imagick( string $file_path ): void {
		try {
			$imagick = new \Imagick( $file_path );

			// Redimensionnement
			$width  = $imagick->getImageWidth();
			$height = $imagick->getImageHeight();

			if ( $width > $this->settings['max_width'] || $height > $this->settings['max_height'] ) {
				$imagick->resizeImage(
					$this->settings['max_width'],
					$this->settings['max_height'],
					\Imagick::FILTER_LANCZOS,
					1,
					true
				);
			}

			// Strip EXIF
			if ( $this->settings['strip_exif'] ) {
				$imagick->stripImage();
			}

			// Compression
			$imagick->setImageCompressionQuality( $this->settings['quality'] );

			// Sauvegarder
			$imagick->writeImage( $file_path );
			$imagick->clear();
			$imagick->destroy();
		} catch ( \Exception $e ) {
			// Silencieux : ne pas bloquer l'upload
		}
	}

	/**
	 * Optimise avec GD.
	 */
	private function optimize_with_gd( string $file_path ): void {
		$editor = wp_get_image_editor( $file_path );

		if ( is_wp_error( $editor ) ) {
			return;
		}

		// Redimensionnement
		$size = $editor->get_size();
		if ( $size['width'] > $this->settings['max_width'] || $size['height'] > $this->settings['max_height'] ) {
			$editor->resize( $this->settings['max_width'], $this->settings['max_height'], false );
		}

		// Qualité
		$editor->set_quality( $this->settings['quality'] );
		$editor->save( $file_path );
	}

	/* ================================================================
	 * CONVERSION FORMAT (AVIF / WebP)
	 * ================================================================ */

	/**
	 * Convertit un fichier vers le format cible.
	 *
	 * @param string $file_path Chemin du fichier source.
	 * @return string|false Chemin du fichier converti ou false.
	 */
	private function convert_file( string $file_path, int $attachment_id = 0, string $mime_type = '' ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$target_format = $this->get_target_format();

		if ( empty( $target_format ) ) {
			return false;
		}

		$mime_type = $mime_type ?: $this->get_file_mime_type( $file_path, $attachment_id );
		if ( empty( $mime_type ) || ! $this->is_supported_image_mime( $mime_type ) ) {
			return false;
		}

		if ( $this->is_animated_image( $file_path, $mime_type ) ) {
			return false;
		}

		$info = pathinfo( $file_path );

		// Déjà dans le bon format
		if ( strtolower( $info['extension'] ?? '' ) === $target_format ) {
			return $file_path;
		}

		$output_path = $info['dirname'] . '/' . $info['filename'] . '.' . $target_format;
		$before = file_exists( $file_path ) ? filesize( $file_path ) : 0;

		$cap = $this->get_capabilities();

		if ( $cap['imagick'] ) {
			$result = $this->convert_with_imagick( $file_path, $output_path, $target_format );
		} elseif ( $cap['gd'] ) {
			$result = $this->convert_with_gd( $file_path, $output_path, $target_format );
		} else {
			return false;
		}

		if ( ! $result || ! file_exists( $output_path ) ) {
			return false;
		}

		$after = filesize( $output_path );
		if ( $before > 0 && $after >= $before ) {
			wp_delete_file( $output_path );
			return false;
		}

		// Supprimer l'original si demandé
		if ( ! $this->settings['keep_original'] && file_exists( $output_path ) ) {
			wp_delete_file( $file_path );
		}

		return $output_path;
	}

	/**
	 * Convertit avec Imagick.
	 */
	private function convert_with_imagick( string $source, string $output, string $format ): bool {
		try {
			$imagick = new \Imagick( $source );

			if ( $this->settings['strip_exif'] ) {
				$imagick->stripImage();
			}

			$imagick->setImageCompressionQuality( $this->settings['quality'] );

			if ( 'avif' === $format ) {
				$imagick->setImageFormat( 'avif' );
			} else {
				$imagick->setImageFormat( 'webp' );
			}

			$imagick->writeImage( $output );
			$imagick->clear();
			$imagick->destroy();

			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Convertit avec GD.
	 */
	private function convert_with_gd( string $source, string $output, string $format ): bool {
		$editor = wp_get_image_editor( $source );

		if ( is_wp_error( $editor ) ) {
			return false;
		}

		$editor->set_quality( $this->settings['quality'] );

		$mime = 'webp' === $format ? 'image/webp' : 'image/avif';
		$result = $editor->save( $output, $mime );

		return ! is_wp_error( $result );
	}

	/**
	 * Met à jour les métadonnées après conversion de format.
	 */
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

				$metadata['sizes'][ $size ]['file'] = basename( $update['file'] );
				$metadata['sizes'][ $size ]['mime-type'] = $update['mime'] ?? $metadata['sizes'][ $size ]['mime-type'];
			}
		}

		return $metadata;
	}

	/* ================================================================
	 * UTILITAIRES
	 * ================================================================ */

	/**
	 * Vérifie si le type MIME est une image.
	 */
	private function is_image( string $mime_type ): bool {
		return $this->is_supported_image_mime( $mime_type );
	}

	/**
	 * Detecte le type MIME d'un fichier.
	 */
	private function get_file_mime_type( string $file_path, int $attachment_id = 0 ): string {
		if ( $attachment_id > 0 ) {
			$mime = get_post_mime_type( $attachment_id );
			if ( is_string( $mime ) && $mime !== '' ) {
				return $mime;
			}
		}

		if ( '' === $file_path ) {
			return '';
		}

		$info = wp_check_filetype( $file_path );
		if ( ! empty( $info['type'] ) ) {
			return $info['type'];
		}

		if ( function_exists( 'mime_content_type' ) ) {
			$mime = mime_content_type( $file_path );
			if ( is_string( $mime ) ) {
				return $mime;
			}
		}

		return '';
	}

	/**
	 * Verifie si un MIME est pris en charge pour optimisation.
	 */
	private function is_supported_image_mime( string $mime_type ): bool {
		if ( strpos( $mime_type, 'image/' ) !== 0 ) {
			return false;
		}

		$excluded = [
			'image/svg+xml',
			'image/x-icon',
			'image/vnd.microsoft.icon',
		];

		if ( in_array( $mime_type, $excluded, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Detecte si une image est animee.
	 */
	private function is_animated_image( string $file_path, string $mime_type ): bool {
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return false;
		}

		$cap = $this->get_capabilities();

		if ( $cap['imagick'] ) {
			try {
				$imagick = new \Imagick( $file_path );
				$is_animated = $imagick->getNumberImages() > 1;
				$imagick->clear();
				$imagick->destroy();
				return $is_animated;
			} catch ( \Exception $e ) {
				return false;
			}
		}

		if ( 'image/gif' === $mime_type ) {
			return $this->is_animated_gif( $file_path );
		}

		return false;
	}

	/**
	 * Detection simple d'un GIF anime.
	 */
	private function is_animated_gif( string $file_path ): bool {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$handle = fopen( $file_path, 'rb' );
		if ( ! $handle ) {
			return false;
		}

		$frames = 0;
		while ( ! feof( $handle ) && $frames < 2 ) {
			$chunk = fread( $handle, 1024 * 100 );
			if ( false === $chunk ) {
				break;
			}
			$frames += preg_match_all( '/\x00\x21\xF9\x04.{4}\x00\x2C/s', $chunk );
		}
		fclose( $handle );

		return $frames > 1;
	}

	/**
	 * Convertit un nom de fichier en texte alternatif lisible.
	 */
	private function filename_to_alt( string $filename ): string {
		// Retirer les dimensions (ex: image-1920x1080)
		$alt = preg_replace( '/-\d+x\d+$/', '', $filename );
		// Remplacer les séparateurs par des espaces
		$alt = str_replace( [ '-', '_', '.' ], ' ', $alt );
		// Capitaliser
		$alt = ucfirst( trim( $alt ) );

		return $alt;
	}

	/* ================================================================
	 * STATS
	 * ================================================================ */

	/**
	 * Met a jour les statistiques d'optimisation.
	 */
	private function update_stats( int $bytes_saved, int $original_bytes ): void {
		$defaults = [
			'optimized'      => 0,
			'bytes_saved'    => 0,
			'original_bytes' => 0,
		];
		$stats = wp_parse_args( get_option( $this->stats_key, [] ), $defaults );

		$stats['optimized']++;
		$stats['bytes_saved'] += max( $bytes_saved, 0 );
		$stats['original_bytes'] += max( $original_bytes, 0 );

		update_option( $this->stats_key, $stats );
	}

	/**
	 * Retourne les statistiques.
	 */
	public function get_stats(): array {
		$defaults = [
			'optimized'      => 0,
			'bytes_saved'    => 0,
			'original_bytes' => 0,
		];
		$stats = wp_parse_args( get_option( $this->stats_key, [] ), $defaults );

		return array_merge( $stats, [
			'capabilities' => $this->get_capabilities(),
		] );
	}

	/**
	 * Retourne une estimation de gain pour le bulk.
	 */
	public function get_bulk_preview(): array {
		$stats = $this->get_stats();
		$state = $this->get_bulk_state();
		$remaining = (int) $state['remaining'];

		if ( 0 === $remaining && ! $state['running'] ) {
			$remaining = $this->count_unoptimized_images();
		}

		$avg_saved = 0;
		if ( ! empty( $stats['optimized'] ) ) {
			$avg_saved = (int) floor( $stats['bytes_saved'] / max( 1, (int) $stats['optimized'] ) );
		}

		$estimated = $avg_saved * $remaining;

		return [
			'remaining'             => $remaining,
			'estimated_bytes_saved' => max( 0, $estimated ),
			'avg_saved_per_image'   => $avg_saved,
		];
	}

	/**
	 * Marque un attachment comme optimise.
	 */
	private function mark_attachment_optimized( int $attachment_id, int $original_bytes, int $optimized_bytes, string $final_file, string $final_mime, int $main_original_bytes = 0, int $main_optimized_bytes = 0 ): void {
		$bytes_saved = max( $original_bytes - $optimized_bytes, 0 );
		$main_bytes_saved = max( $main_original_bytes - $main_optimized_bytes, 0 );
		$format = strtolower( pathinfo( $final_file, PATHINFO_EXTENSION ) );

		update_post_meta( $attachment_id, '_skmt_optimized', time() );
		update_post_meta( $attachment_id, '_skmt_original_bytes', $original_bytes );
		update_post_meta( $attachment_id, '_skmt_optimized_bytes', $optimized_bytes );
		update_post_meta( $attachment_id, '_skmt_bytes_saved', $bytes_saved );
		update_post_meta( $attachment_id, '_skmt_main_original_bytes', $main_original_bytes );
		update_post_meta( $attachment_id, '_skmt_main_optimized_bytes', $main_optimized_bytes );
		update_post_meta( $attachment_id, '_skmt_main_bytes_saved', $main_bytes_saved );
		update_post_meta( $attachment_id, '_skmt_optimized_format', $format );
		update_post_meta( $attachment_id, '_skmt_optimized_mime', $final_mime );
	}

	/**
	 * Verifie si un attachment est deja optimise.
	 */
	private function is_already_optimized( int $attachment_id ): bool {
		return (bool) get_post_meta( $attachment_id, '_skmt_optimized', true );
	}

	/**
	 * Met a jour les references en base apres conversion.
	 */
	private function update_attachment_database_refs( int $attachment_id, string $old_file, string $new_file ): void {
		update_attached_file( $attachment_id, $new_file );

		$mime = $this->get_file_mime_type( $new_file, 0 );
		if ( $mime ) {
			wp_update_post( [
				'ID'             => $attachment_id,
				'post_mime_type' => $mime,
			] );
		}
	}

	/* ================================================================
	 * MÉDIATHÈQUE
	 * ================================================================ */

	/**
	 * Ajoute une colonne dans la médiathèque.
	 */
	public function add_media_column( array $columns ): array {
		$new_columns = [];
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['skmt_format'] = __( 'Format', 'studio-kyne-mini-tools' );
			}
		}
		return $new_columns;
	}

	/**
	 * Affiche le contenu de la colonne.
	 */
	public function render_media_column( string $column, int $post_id ): void {
		if ( 'skmt_format' !== $column ) {
			return;
		}

		$metadata = wp_get_attachment_metadata( $post_id );
		$mime     = get_post_mime_type( $post_id );

		if ( strpos( $mime, 'image/' ) !== 0 ) {
			echo '<span class="skmt-badge skmt-badge--inactive">—</span>';
			return;
		}

		$format = 'webp';
		if ( strpos( $mime, 'avif' ) !== false ) {
			$format = 'avif';
		} elseif ( strpos( $mime, 'webp' ) !== false ) {
			$format = 'webp';
		} else {
			$format = str_replace( 'image/', '', $mime );
		}

		$label = strtoupper( $format );
		$class = in_array( $format, [ 'avif', 'webp' ], true ) ? 'skmt-badge--success' : 'skmt-badge--inactive';

		echo '<span class="skmt-badge ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	/* ================================================================
	 * BULK OPTIMIZATION
	 * ================================================================ */

	/**
	 * AJAX : optimise un lot d'images.
	 */
	public function ajax_bulk_optimize(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		$state = $this->get_bulk_state();
		if ( ! $state['running'] ) {
			$total = $this->count_unoptimized_images();
			$state = [
				'running'   => $total > 0,
				'total'     => $total,
				'processed' => 0,
				'remaining' => $total,
				'updated_at'=> time(),
			];
			$this->set_bulk_state( $state );
		}

		if ( $state['running'] ) {
			$this->run_cron_batch( self::BULK_BATCH_SIZE );
			$this->schedule_bulk_event( self::BULK_BATCH_SIZE );
			$state = $this->get_bulk_state();
		}

		$preview = $this->get_bulk_preview();

		wp_send_json_success( [
			'processed' => $state['processed'],
			'remaining' => $state['remaining'],
			'done'      => 0 === $state['remaining'] && ! $state['running'],
			'running'   => $state['running'],
			'total'     => $state['total'],
			'estimated_bytes_saved' => (int) ( $preview['estimated_bytes_saved'] ?? 0 ),
		] );
	}

	/**
	 * AJAX : retourne le statut du bulk.
	 */
	public function ajax_bulk_status(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		$state = $this->get_bulk_state();
		if ( $state['running'] && $state['updated_at'] && ( time() - (int) $state['updated_at'] ) > 20 ) {
			$this->run_cron_batch( self::BULK_BATCH_SIZE );
			$state = $this->get_bulk_state();
		}
		$preview = $this->get_bulk_preview();
		wp_send_json_success( [
			'remaining' => $state['remaining'],
			'processed' => $state['processed'],
			'done'      => 0 === $state['remaining'] && ! $state['running'],
			'running'   => $state['running'],
			'total'     => $state['total'],
			'estimated_bytes_saved' => (int) ( $preview['estimated_bytes_saved'] ?? 0 ),
		] );
	}

	/**
	 * Traitement cron du batch.
	 */
	public function run_cron_batch( int $batch_size = 5 ): void {
		$state = $this->get_bulk_state();
		if ( ! $state['running'] ) {
			return;
		}

		$ids = $this->get_unoptimized_attachment_ids( $batch_size );
		if ( empty( $ids ) ) {
			$state['running'] = false;
			$state['remaining'] = 0;
			$state['updated_at'] = time();
			$this->set_bulk_state( $state );
			return;
		}

		$processed_now = 0;
		foreach ( $ids as $attachment_id ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( $metadata ) {
				$metadata = $this->process_attachment_metadata( $metadata, $attachment_id, true );
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}

			$this->generate_alt_text( $attachment_id );
			$processed_now++;
		}

		$state['processed'] += $processed_now;
		$state['remaining'] = max( $state['remaining'] - $processed_now, 0 );
		$state['updated_at'] = time();

		if ( 0 === $state['remaining'] ) {
			$state['running'] = false;
			$this->set_bulk_state( $state );
			return;
		}

		$this->set_bulk_state( $state );
		$this->schedule_bulk_event( $batch_size );
	}

	/**
	 * Enqueue les assets pour la mediathèque.
	 */
	public function enqueue_media_assets( string $hook ): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( 'upload' !== $screen->base && 'post' !== $screen->base ) {
			return;
		}

		if ( 'post' === $screen->base && 'attachment' !== $screen->post_type ) {
			return;
		}

		foreach ( $this->get_admin_js() as $index => $script_url ) {
			if ( empty( $script_url ) ) {
				continue;
			}

			$handle = 'skmt-image-optimizer-js-' . $index;
			wp_enqueue_script( $handle, $script_url, [], SKMT_VERSION, true );
			wp_localize_script( $handle, 'skmtAdmin', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'skmt_admin_nonce' ),
				'i18n'    => [
					'bulkRunning'    => __( 'Optimisation en cours…', 'studio-kyne-mini-tools' ),
					'bulkProcessed'  => __( 'Traité :', 'studio-kyne-mini-tools' ),
					'bulkRemaining'  => __( 'Restant :', 'studio-kyne-mini-tools' ),
					'bulkDone'       => __( 'Optimisation terminée', 'studio-kyne-mini-tools' ),
					'bulkComplete'   => __( 'Toutes les images ont été optimisées.', 'studio-kyne-mini-tools' ),
					'bulkRetry'      => __( 'Réessayer', 'studio-kyne-mini-tools' ),
					'singleRunning'  => __( 'Optimisation…', 'studio-kyne-mini-tools' ),
					'singleDone'     => __( 'Optimisée', 'studio-kyne-mini-tools' ),
					'singleError'    => __( 'Erreur', 'studio-kyne-mini-tools' ),
				],
			] );
		}
	}

	/**
	 * Ajoute une section Image Optimizer dans l'edition media.
	 */
	public function add_media_optimizer_fields( array $form_fields, \WP_Post $post ): array {
		$mime = get_post_mime_type( $post->ID );
		if ( empty( $mime ) || ! $this->is_supported_image_mime( $mime ) ) {
			return $form_fields;
		}

		$file = get_attached_file( $post->ID );
		if ( empty( $file ) || ! file_exists( $file ) ) {
			return $form_fields;
		}

		$is_animated = $this->is_animated_image( $file, $mime );
		$is_optimized = $this->is_already_optimized( $post->ID );
		$original_bytes = (int) get_post_meta( $post->ID, '_skmt_original_bytes', true );
		$optimized_bytes = (int) get_post_meta( $post->ID, '_skmt_optimized_bytes', true );
		$bytes_saved = (int) get_post_meta( $post->ID, '_skmt_bytes_saved', true );
		$main_original_bytes = (int) get_post_meta( $post->ID, '_skmt_main_original_bytes', true );
		$main_optimized_bytes = (int) get_post_meta( $post->ID, '_skmt_main_optimized_bytes', true );
		$main_bytes_saved = (int) get_post_meta( $post->ID, '_skmt_main_bytes_saved', true );

		// Fallback pour les anciens médias optimisés avant l'ajout du détail principal.
		if ( $is_optimized && 0 === $main_optimized_bytes ) {
			$main_optimized_bytes = file_exists( $file ) ? (int) filesize( $file ) : 0;
		}

		if ( $is_optimized && 0 === $main_original_bytes && $main_optimized_bytes > 0 ) {
			$main_original_bytes = max( $main_optimized_bytes + $main_bytes_saved, $main_optimized_bytes );
		}

		if ( $is_optimized && 0 === $main_bytes_saved && $main_original_bytes > 0 && $main_optimized_bytes > 0 ) {
			$main_bytes_saved = max( $main_original_bytes - $main_optimized_bytes, 0 );
		}

		$estimated = 0;
		if ( ! $is_optimized ) {
			$stats = $this->get_stats();
			$avg_ratio = 0;
			if ( ! empty( $stats['original_bytes'] ) ) {
				$avg_ratio = (float) $stats['bytes_saved'] / max( 1, (float) $stats['original_bytes'] );
			}
			$estimated = (int) floor( filesize( $file ) * $avg_ratio );
		}

		$action_html = '';
		if ( $is_animated ) {
			$action_html = '<p>' . esc_html__( 'Image animée : optimisation automatique désactivée.', 'studio-kyne-mini-tools' ) . '</p>';
		} elseif ( ! $is_optimized ) {
			$action_html = '<button type="button" class="button skmt-optimize-single" data-attachment="' . esc_attr( $post->ID ) . '">'
				. esc_html__( 'Optimiser cette image', 'studio-kyne-mini-tools' ) . '</button>';
		} else {
			$action_html = '<span class="skmt-optimized-status">' . esc_html__( 'Déjà optimisée', 'studio-kyne-mini-tools' ) . '</span>';
		}

		$current_size = filesize( $file );
		$potential_style = $is_optimized ? 'style="display:none;"' : '';
		$result_style = $is_optimized ? '' : 'style="display:none;"';

		$details = '<div class="skmt-gain-potential" ' . $potential_style . '>'
			. '<p>'
			. esc_html__( 'Gain potentiel :', 'studio-kyne-mini-tools' )
			. ' <strong class="skmt-bytes-estimated">' . esc_html( size_format( $estimated, 2 ) ) . '</strong>'
			. '</p>'
			. '<p>' . esc_html__( 'Taille actuelle :', 'studio-kyne-mini-tools' ) . ' <strong class="skmt-bytes-current">' . esc_html( size_format( $current_size, 2 ) ) . '</strong></p>'
			. '</div>'
			. '<div class="skmt-gain-result" ' . $result_style . '>'
			. '<p style="margin-bottom:4px;"><strong>' . esc_html__( 'Fichier principal', 'studio-kyne-mini-tools' ) . '</strong></p>'
			. '<p>'
			. esc_html__( 'Gain obtenu :', 'studio-kyne-mini-tools' )
			. ' <strong class="skmt-main-bytes-saved">' . esc_html( size_format( $main_bytes_saved, 2 ) ) . '</strong>'
			. '</p>'
			. '<p>' . esc_html__( 'Taille avant :', 'studio-kyne-mini-tools' ) . ' <strong class="skmt-main-bytes-original">' . esc_html( size_format( $main_original_bytes, 2 ) ) . '</strong></p>'
			. '<p>' . esc_html__( 'Taille après :', 'studio-kyne-mini-tools' ) . ' <strong class="skmt-main-bytes-final">' . esc_html( size_format( $main_optimized_bytes, 2 ) ) . '</strong></p>'
			. '<p style="margin:10px 0 4px;"><strong>' . esc_html__( 'Total (principal + miniatures)', 'studio-kyne-mini-tools' ) . '</strong></p>'
			. '<p>'
			. esc_html__( 'Gain obtenu :', 'studio-kyne-mini-tools' )
			. ' <strong class="skmt-bytes-saved">' . esc_html( size_format( $bytes_saved, 2 ) ) . '</strong>'
			. '</p>'
			. '<p>' . esc_html__( 'Taille avant :', 'studio-kyne-mini-tools' ) . ' <strong class="skmt-bytes-original">' . esc_html( size_format( $original_bytes, 2 ) ) . '</strong></p>'
			. '<p>' . esc_html__( 'Taille après :', 'studio-kyne-mini-tools' ) . ' <strong class="skmt-bytes-final">' . esc_html( size_format( $optimized_bytes, 2 ) ) . '</strong></p>'
			. '</div>';

		$form_fields['skmt_image_optimizer'] = [
			'label' => __( 'Image Optimizer', 'studio-kyne-mini-tools' ),
			'input' => 'html',
			'html'  => '<div class="skmt-media-optimizer" data-attachment="' . esc_attr( $post->ID ) . '">'
				. $details
				. '<div class="skmt-media-optimizer__actions">' . $action_html . '</div>'
				. '<div class="skmt-media-optimizer__message" style="margin-top:6px;"></div>'
				. '</div>',
		];

		return $form_fields;
	}

	/**
	 * AJAX : optimisation d'une seule image.
	 */
	public function ajax_optimize_single(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'ID invalide.', 'studio-kyne-mini-tools' ) );
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( empty( $mime ) || ! $this->is_supported_image_mime( $mime ) ) {
			wp_send_json_error( __( 'Format non pris en charge.', 'studio-kyne-mini-tools' ) );
		}

		$file = get_attached_file( $attachment_id );
		if ( empty( $file ) || ! file_exists( $file ) ) {
			wp_send_json_error( __( 'Fichier introuvable.', 'studio-kyne-mini-tools' ) );
		}

		if ( $this->is_animated_image( $file, $mime ) ) {
			wp_send_json_error( __( 'Image animée non prise en charge.', 'studio-kyne-mini-tools' ) );
		}

		if ( ! $this->is_already_optimized( $attachment_id ) ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( $metadata ) {
				$metadata = $this->process_attachment_metadata( $metadata, $attachment_id, true );
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}
		}

		$original_bytes = (int) get_post_meta( $attachment_id, '_skmt_original_bytes', true );
		$optimized_bytes = (int) get_post_meta( $attachment_id, '_skmt_optimized_bytes', true );
		$bytes_saved = (int) get_post_meta( $attachment_id, '_skmt_bytes_saved', true );
		$main_original_bytes = (int) get_post_meta( $attachment_id, '_skmt_main_original_bytes', true );
		$main_optimized_bytes = (int) get_post_meta( $attachment_id, '_skmt_main_optimized_bytes', true );
		$main_bytes_saved = (int) get_post_meta( $attachment_id, '_skmt_main_bytes_saved', true );

		wp_send_json_success( [
			'original_bytes'  => $original_bytes,
			'optimized_bytes' => $optimized_bytes,
			'bytes_saved'     => $bytes_saved,
			'main_original_bytes'  => $main_original_bytes,
			'main_optimized_bytes' => $main_optimized_bytes,
			'main_bytes_saved'     => $main_bytes_saved,
		] );
	}

	/**
	 * Recuperation de l'etat du bulk.
	 */
	private function get_bulk_state(): array {
		$state = get_option( $this->bulk_state_key, [] );
		$defaults = [
			'running'   => false,
			'total'     => 0,
			'processed' => 0,
			'remaining' => 0,
			'updated_at'=> 0,
		];

		return wp_parse_args( $state, $defaults );
	}

	/**
	 * Sauvegarde l'etat du bulk.
	 */
	private function set_bulk_state( array $state ): void {
		update_option( $this->bulk_state_key, $state );
	}

	/**
	 * Programme un batch cron si necessaire.
	 */
	private function schedule_bulk_event( int $batch_size ): void {
		if ( ! wp_next_scheduled( 'skmt_image_optimizer_cron', [ $batch_size ] ) ) {
			wp_schedule_single_event( time() + 2, 'skmt_image_optimizer_cron', [ $batch_size ] );
		}
	}

	/**
	 * Compte le nombre d'images non optimisees.
	 */
	private function count_unoptimized_images(): int {
		$args = [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_skmt_optimized',
					'compare' => 'NOT EXISTS',
				],
			],
		];

		$query = new \WP_Query( $args );
		return (int) $query->found_posts;
	}

	/**
	 * Recupere un lot d'IDs non optimises.
	 */
	private function get_unoptimized_attachment_ids( int $limit ): array {
		$args = [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'meta_query'     => [
				[
					'key'     => '_skmt_optimized',
					'compare' => 'NOT EXISTS',
				],
			],
		];

		$query = new \WP_Query( $args );
		return array_map( 'intval', $query->posts );
	}
}
