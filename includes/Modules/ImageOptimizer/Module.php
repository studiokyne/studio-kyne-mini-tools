<?php
namespace StudioKyne\MiniTools\Modules\ImageOptimizer;

use StudioKyne\MiniTools\Core\ModuleInterface;

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
class Module implements ModuleInterface {

	/**
	 * Clé d'option pour les réglages du module.
	 */
	private string $option_key = 'skmt_module_image_optimizer';

	/**
	 * Clé d'option pour les stats.
	 */
	private string $stats_key = 'skmt_image_optimizer_stats';

	/**
	 * Réglages du module.
	 */
	private array $settings = [];

	/**
	 * Capacités détectées du serveur.
	 */
	private ?array $capabilities = null;

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

		$stored = get_option( $this->option_key, [] );

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

		return update_option( $this->option_key, $sanitized );
	}

	/**
	 * Retourne les styles admin du module.
	 */
	public function get_admin_css(): array {
		return [
			SKMT_ASSETS_URL . 'admin/css/modules/image-optimizer.css',
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

		$upload_dir = wp_upload_dir();
		$base_path  = trailingslashit( $upload_dir['basedir'] );
		
		if ( empty( $metadata['file'] ) ) {
			return $metadata;
		}

		$subdir     = dirname( $metadata['file'] );
		$sizes_path = trailingslashit( $base_path . $subdir );

		// 1. Optimiser chaque taille générée sur place d'abord
		if ( ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size => $size_data ) {
				if ( ! empty( $size_data['file'] ) ) {
					$size_file = $sizes_path . $size_data['file'];
					if ( file_exists( $size_file ) ) {
						$this->optimize_file( $size_file );
					}
				}
			}
		}

		// 2. Convertir le fichier principal
		$original_file = $base_path . $metadata['file'];
		if ( file_exists( $original_file ) ) {
			$converted = $this->convert_file( $original_file );
			if ( $converted && $converted !== $original_file ) {
				// Mettre à jour le chemin du fichier attaché dans WordPress
				update_attached_file( $attachment_id, $converted );

				// Mettre à jour le type MIME du post d'attachement
				$info = pathinfo( $converted );
				$ext  = strtolower( $info['extension'] ?? '' );
				$mime = 'avif' === $ext ? 'image/avif' : 'image/webp';
				
				wp_update_post( [
					'ID'             => $attachment_id,
					'post_mime_type' => $mime,
				] );

				// 3. Convertir également physiquement toutes les miniatures générées !
				if ( ! empty( $metadata['sizes'] ) ) {
					foreach ( $metadata['sizes'] as $size => $size_data ) {
						if ( ! empty( $size_data['file'] ) ) {
							$size_file = $sizes_path . $size_data['file'];
							if ( file_exists( $size_file ) ) {
								$this->convert_file( $size_file );
							}
						}
					}
				}

				// Mettre à jour les métadonnées pour refléter le nouveau format
				$metadata = $this->update_metadata_format( $metadata, $original_file, $converted );
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

		$cap = $this->get_capabilities();

		// Utiliser Imagick si disponible (meilleur contrôle)
		if ( $cap['imagick'] ) {
			$this->optimize_with_imagick( $file_path );
		} elseif ( $cap['gd'] ) {
			$this->optimize_with_gd( $file_path );
		}
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
	private function convert_file( string $file_path ) {
		$target_format = $this->get_target_format();

		if ( empty( $target_format ) ) {
			return false;
		}

		$info = pathinfo( $file_path );

		// Déjà dans le bon format
		if ( strtolower( $info['extension'] ?? '' ) === $target_format ) {
			return $file_path;
		}

		$output_path = $info['dirname'] . '/' . $info['filename'] . '.' . $target_format;

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

		// Supprimer l'original si demandé
		if ( ! $this->settings['keep_original'] && file_exists( $output_path ) ) {
			wp_delete_file( $file_path );
		}

		// Incrémenter les stats
		$this->increment_stats();

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
	private function update_metadata_format( array $metadata, string $old_file, string $new_file ): array {
		$old_info = pathinfo( $old_file );
		$new_info = pathinfo( $new_file );

		// Mettre à jour le fichier principal
		if ( ! empty( $metadata['file'] ) ) {
			$metadata['file'] = str_replace( $old_info['basename'], $new_info['basename'], $metadata['file'] );
		}

		// Mettre à jour les tailles
		if ( ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size => $size_data ) {
				if ( ! empty( $size_data['file'] ) ) {
					$old_size_info = pathinfo( $size_data['file'] );
					$metadata['sizes'][ $size ]['file'] = $old_size_info['filename'] . '.' . $new_info['extension'];
					$metadata['sizes'][ $size ]['mime-type'] = 'avif' === $new_info['extension'] ? 'image/avif' : 'image/webp';
				}
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
		return strpos( $mime_type, 'image/' ) === 0;
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
	 * Incrémente le compteur d'images optimisées.
	 */
	private function increment_stats(): void {
		$stats = get_option( $this->stats_key, [ 'optimized' => 0, 'bytes_saved' => 0 ] );
		$stats['optimized']++;
		update_option( $this->stats_key, $stats );
	}

	/**
	 * Retourne les statistiques.
	 */
	public function get_stats(): array {
		$stats = get_option( $this->stats_key, [ 'optimized' => 0, 'bytes_saved' => 0 ] );

		return array_merge( $stats, [
			'capabilities' => $this->get_capabilities(),
		] );
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

		$batch_size = 5;

		$args = [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $batch_size,
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
		$processed = 0;
		$remaining = 0;

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $attachment_id ) {
				$file = get_attached_file( $attachment_id );
				if ( $file && file_exists( $file ) ) {
					$this->optimize_file( $file );
					$converted = $this->convert_file( $file );

					if ( $converted && $converted !== $file ) {
						// Mettre à jour le fichier attaché
						update_attached_file( $attachment_id, $converted );

						// Mettre à jour les métadonnées
						$metadata = wp_get_attachment_metadata( $attachment_id );
						if ( $metadata ) {
							$metadata = $this->update_metadata_format( $metadata, $file, $converted );
							wp_update_attachment_metadata( $attachment_id, $metadata );
						}

						// Mettre à jour le mime type
						$info = pathinfo( $converted );
						$mime = 'avif' === strtolower( $info['extension'] ?? '' ) ? 'image/avif' : 'image/webp';
						wp_update_post( [
							'ID'             => $attachment_id,
							'post_mime_type' => $mime,
						] );
					}

					// Alt text
					$this->generate_alt_text( $attachment_id );
				}

				update_post_meta( $attachment_id, '_skmt_optimized', time() );
				$processed++;
			}
		}

		// Compter le reste
		$remaining_args = [
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
		$remaining_query = new \WP_Query( $remaining_args );
		$remaining = $remaining_query->found_posts;

		wp_send_json_success( [
			'processed' => $processed,
			'remaining' => $remaining,
			'done'      => 0 === $remaining,
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

		wp_send_json_success( [
			'remaining' => $query->found_posts,
			'done'      => 0 === $query->found_posts,
		] );
	}
}