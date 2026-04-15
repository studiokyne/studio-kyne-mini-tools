<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Image_Processor {
	protected $settings;
	protected $logger;
	public function __construct( $settings, $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}
	public function process_file( $file_path, $keep_original = true ) {
		$file_path = wp_normalize_path( (string) $file_path );
		$result    = array( 'success' => false, 'skipped' => false, 'message' => '' );
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			$result['message'] = __( 'Fichier introuvable.', 'studio-kyne-mini-tools' );
			return $result;
		}
		$filetype = wp_check_filetype( $file_path );
		$mime     = $filetype['type'] ?? '';
		if ( ! $this->is_supported_input( $mime ) ) {
			return array( 'success' => false, 'skipped' => true, 'message' => __( 'Type MIME non pris en charge.', 'studio-kyne-mini-tools' ), 'original_path' => $file_path, 'final_path' => $file_path, 'original_mime' => $mime, 'final_mime' => $mime );
		}
		if ( 'image/gif' === $mime && $this->is_animated_gif( $file_path ) ) {
			return array( 'success' => false, 'skipped' => true, 'message' => __( 'GIF animé ignoré.', 'studio-kyne-mini-tools' ), 'original_path' => $file_path, 'final_path' => $file_path, 'original_mime' => $mime, 'final_mime' => $mime );
		}
		$original_size = @filesize( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$image_size    = @getimagesize( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( empty( $image_size[0] ) || empty( $image_size[1] ) ) {
			$result['message'] = __( 'Impossible de lire les dimensions de l’image.', 'studio-kyne-mini-tools' );
			return $result;
		}
		if ( ! $this->has_enough_free_space( $file_path, (int) $original_size ) ) {
			$result['message'] = __( 'Espace disque insuffisant pour optimiser cette image.', 'studio-kyne-mini-tools' );
			return $result;
		}
		$target_mime = $this->resolve_target_mime();
		if ( empty( $target_mime ) ) {
			$result['message'] = __( 'Aucun format cible moderne n’est supporté par le serveur.', 'studio-kyne-mini-tools' );
			return $result;
		}
		$editor = wp_get_image_editor( $file_path );
		if ( is_wp_error( $editor ) ) {
			$result['message'] = $editor->get_error_message();
			return $result;
		}
		if ( method_exists( $editor, 'supports_mime_type' ) && ! $editor->supports_mime_type( $target_mime ) ) {
			$result['message'] = __( 'L’éditeur d’image actif ne supporte pas le format cible.', 'studio-kyne-mini-tools' );
			return $result;
		}
		$editor_class = is_object( $editor ) ? get_class( $editor ) : 'unknown';
		if ( method_exists( $editor, 'set_quality' ) ) {
			$editor->set_quality( max( 35, min( 100, absint( $this->settings['quality'] ?? 82 ) ) ) );
		}
		$max_width  = max( 1, absint( $this->settings['max_width'] ?? 1920 ) );
		$max_height = max( 1, absint( $this->settings['max_height'] ?? 1920 ) );
		$resize_needed = ( $image_size[0] > $max_width || $image_size[1] > $max_height );
		if ( $resize_needed ) {
			$resized = $editor->resize( $max_width, $max_height, false );
			if ( is_wp_error( $resized ) ) {
				$this->logger->log( 'image-optimizer', 'warning', __( 'Le redimensionnement a échoué, poursuite avec les dimensions d’origine.', 'studio-kyne-mini-tools' ), array( 'message' => $resized->get_error_message() ) );
			}
		}
		$save_mime  = $target_mime;
		$save_path  = $this->build_target_path( $file_path, $target_mime );
		$used_fallback = false;
		if ( $keep_original && $save_path === $file_path ) {
			$backup_path = $this->build_backup_path( $file_path );
			@copy( $file_path, $backup_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$saved = $editor->save( $save_path, $target_mime );
		if ( is_wp_error( $saved ) && 'image/avif' === $target_mime && $this->supports_webp() ) {
			$save_mime     = 'image/webp';
			$save_path     = $this->build_target_path( $file_path, 'image/webp' );
			$used_fallback = true;
			$saved         = $editor->save( $save_path, 'image/webp' );
		}
		if ( is_wp_error( $saved ) ) {
			$result['message'] = $saved->get_error_message();
			return $result;
		}
		$final_path = wp_normalize_path( (string) $saved['path'] );
		$final_mime = (string) ( $saved['mime-type'] ?? $save_mime );
		$this->maybe_strip_exif_metadata( $final_path );
		$final_size = @filesize( $final_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$final_dims = @getimagesize( $final_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$is_already_modern = in_array( $mime, array( 'image/webp', 'image/avif' ), true );
		if ( $is_already_modern && (int) $original_size > 0 && (int) $final_size >= (int) $original_size ) {
			if ( $final_path !== $file_path && file_exists( $final_path ) ) {
				@unlink( $final_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}

			return array(
				'success'         => false,
				'skipped'         => true,
				'message'         => __( 'Image AVIF/WebP conservée: la recompression ne réduit pas le poids.', 'studio-kyne-mini-tools' ),
				'original_path'   => $file_path,
				'final_path'      => $file_path,
				'original_mime'   => $mime,
				'final_mime'      => $mime,
				'original_size'   => (int) $original_size,
				'final_size'      => (int) $original_size,
				'bytes_saved'     => 0,
				'percent_saved'   => 0,
				'original_width'  => (int) $image_size[0],
				'original_height' => (int) $image_size[1],
				'final_width'     => (int) $image_size[0],
				'final_height'    => (int) $image_size[1],
				'editor'          => $editor_class,
			);
		}

		if ( ! $keep_original && $final_path !== $file_path && file_exists( $file_path ) ) {
			@unlink( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		return array(
			'success'         => true,
			'skipped'         => false,
			'message'         => $used_fallback ? __( 'AVIF indisponible à l’enregistrement. Fallback WebP utilisé.', 'studio-kyne-mini-tools' ) : __( 'Image optimisée avec succès.', 'studio-kyne-mini-tools' ),
			'original_path'   => $file_path,
			'final_path'      => $final_path,
			'original_mime'   => $mime,
			'final_mime'      => $final_mime,
			'original_size'   => (int) $original_size,
			'final_size'      => (int) $final_size,
			'bytes_saved'     => max( 0, (int) $original_size - (int) $final_size ),
			'percent_saved'   => ( (int) $original_size > 0 ) ? round( ( ( (int) $original_size - (int) $final_size ) / (int) $original_size ) * 100, 2 ) : 0,
			'original_width'  => (int) $image_size[0],
			'original_height' => (int) $image_size[1],
			'final_width'     => ! empty( $final_dims[0] ) ? (int) $final_dims[0] : (int) $image_size[0],
			'final_height'    => ! empty( $final_dims[1] ) ? (int) $final_dims[1] : (int) $image_size[1],
			'editor'          => $editor_class,
		);
	}
	protected function is_supported_input( $mime ) {
		return in_array( $mime, array( 'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/avif', 'image/gif' ), true );
	}
	public function resolve_target_mime() {
		$format = sanitize_key( $this->settings['target_format'] ?? 'auto' );
		if ( 'avif' === $format ) {
			if ( $this->supports_avif() ) { return 'image/avif'; }
			if ( $this->supports_webp() ) { return 'image/webp'; }
			return '';
		}
		if ( 'webp' === $format ) {
			return $this->supports_webp() ? 'image/webp' : '';
		}
		if ( $this->supports_avif() ) { return 'image/avif'; }
		if ( $this->supports_webp() ) { return 'image/webp'; }
		return '';
	}
	public function supports_avif() { return wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ); }
	public function supports_webp() { return wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ); }
	protected function build_target_path( $original_path, $mime ) {
		$map = array( 'image/avif' => 'avif', 'image/webp' => 'webp', 'image/jpeg' => 'jpg', 'image/png' => 'png' );
		$extension = $map[ $mime ] ?? pathinfo( $original_path, PATHINFO_EXTENSION );
		$info = pathinfo( $original_path );
		return wp_normalize_path( trailingslashit( $info['dirname'] ) . $info['filename'] . '.' . $extension );
	}
	protected function build_backup_path( $file_path ) {
		$info = pathinfo( $file_path );
		return wp_normalize_path( trailingslashit( $info['dirname'] ) . $info['filename'] . '-original.' . $info['extension'] );
	}
	protected function is_animated_gif( $file_path ) {
		$contents = @file_get_contents( $file_path, false, null, 0, 2097152 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $contents ) {
			return false;
		}
		return preg_match_all( '#\x00\x21\xF9\x04#s', $contents ) > 1;
	}

	protected function has_enough_free_space( $file_path, $original_size ) {
		$directory = dirname( (string) $file_path );
		if ( ! is_dir( $directory ) ) {
			return true;
		}

		$free_space = @disk_free_space( $directory ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $free_space ) {
			return true;
		}

		$estimated_required = max( 25 * 1024 * 1024, (int) $original_size * 2 );
		return (int) $free_space > $estimated_required;
	}

	protected function maybe_strip_exif_metadata( $file_path ) {
		if ( empty( $this->settings['strip_exif_metadata'] ) ) {
			return;
		}

		if ( ! class_exists( 'Imagick' ) || ! file_exists( $file_path ) ) {
			return;
		}

		try {
			$imagick = new Imagick();
			$imagick->readImage( $file_path );
			$imagick->stripImage();
			$imagick->writeImage( $file_path );
			$imagick->clear();
			$imagick->destroy();
		} catch ( Exception $exception ) {
			$this->logger->log(
				'image-optimizer',
				'warning',
				__( 'Impossible de supprimer les métadonnées EXIF.', 'studio-kyne-mini-tools' ),
				array( 'message' => $exception->getMessage() )
			);
		}
	}
}
