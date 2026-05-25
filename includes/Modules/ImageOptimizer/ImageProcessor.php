<?php
namespace StudioKyne\MiniTools\Modules\ImageOptimizer;

/**
 * Traitement pur des fichiers image : optimisation, conversion de format,
 * détection des capacités serveur.
 *
 * Pas de hooks WordPress — reçoit ses réglages à la construction.
 */
class ImageProcessor {

	private array $settings;

	/**
	 * Cache des capacités serveur (Imagick/GD, AVIF/WebP).
	 */
	private ?array $capabilities = null;

	/**
	 * Fichiers déjà optimisés sur cette requête (déduplication).
	 */
	private array $optimized_paths = [];

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/* ================================================================
	 * CAPACITÉS SERVEUR
	 * ================================================================ */

	/**
	 * Détecte et met en cache les capacités image du serveur.
	 */
	public function get_capabilities(): array {
		if ( null !== $this->capabilities ) {
			return $this->capabilities;
		}

		$has_imagick = extension_loaded( 'imagick' );
		$has_gd      = extension_loaded( 'gd' );
		$can_avif    = false;
		$can_webp    = false;

		if ( $has_imagick ) {
			$formats  = \Imagick::queryFormats();
			$can_avif = in_array( 'AVIF', $formats, true );
			$can_webp = in_array( 'WEBP', $formats, true );
		}

		if ( $has_gd ) {
			$gd_info  = gd_info();
			$can_avif = $can_avif || ( $gd_info['AVIF Support'] ?? false );
			$can_webp = $can_webp || ( $gd_info['WebP Support'] ?? false );
		}

		$this->capabilities = [
			'imagick' => $has_imagick,
			'gd'      => $has_gd,
			'avif'    => $can_avif,
			'webp'    => $can_webp,
			'editor'  => $has_imagick ? 'imagick' : ( $has_gd ? 'gd' : 'none' ),
		];

		return $this->capabilities;
	}

	/**
	 * Détermine le format de conversion cible selon les réglages et capacités.
	 * Retourne '' si aucune conversion possible/souhaitée.
	 */
	public function get_target_format(): string {
		$cap  = $this->get_capabilities();
		$mode = $this->settings['format_mode'] ?? 'auto';

		if ( 'avif' === $mode && $cap['avif'] ) {
			return 'avif';
		}

		if ( 'webp' === $mode && $cap['webp'] ) {
			return 'webp';
		}

		if ( 'auto' === $mode || '' === $mode ) {
			if ( $cap['avif'] ) {
				return 'avif';
			}
			if ( $cap['webp'] ) {
				return 'webp';
			}
		}

		return '';
	}

	/* ================================================================
	 * OPTIMISATION (compression + redimensionnement + strip EXIF)
	 * ================================================================ */

	/**
	 * Optimise un fichier image en place.
	 * Idempotent sur cette requête (les fichiers déjà traités sont ignorés).
	 */
	public function optimize( string $file_path ): void {
		if ( ! file_exists( $file_path ) || isset( $this->optimized_paths[ $file_path ] ) ) {
			return;
		}

		$cap = $this->get_capabilities();

		if ( $cap['imagick'] ) {
			$this->optimize_with_imagick( $file_path );
		} elseif ( $cap['gd'] ) {
			$this->optimize_with_gd( $file_path );
		}

		$this->optimized_paths[ $file_path ] = true;
	}

	private function optimize_with_imagick( string $file_path ): void {
		$imagick = null;

		try {
			$imagick = new \Imagick( $file_path );

			$width  = $imagick->getImageWidth();
			$height = $imagick->getImageHeight();

			$max_w = (int) ( $this->settings['max_width'] ?? 2560 );
			$max_h = (int) ( $this->settings['max_height'] ?? 2560 );

			if ( $width > $max_w || $height > $max_h ) {
				$imagick->resizeImage( $max_w, $max_h, \Imagick::FILTER_LANCZOS, 1, true );
			}

			if ( $this->settings['strip_exif'] ?? true ) {
				$imagick->stripImage();
			}

			$imagick->setImageCompressionQuality( (int) ( $this->settings['quality'] ?? 75 ) );
			$imagick->writeImage( $file_path );
		} catch ( \Exception $e ) {
			// Ne pas bloquer l'upload
		} finally {
			if ( $imagick instanceof \Imagick ) {
				$imagick->clear();
				$imagick->destroy();
			}
		}
	}

	private function optimize_with_gd( string $file_path ): void {
		$editor = wp_get_image_editor( $file_path );

		if ( is_wp_error( $editor ) ) {
			return;
		}

		$max_w = (int) ( $this->settings['max_width'] ?? 2560 );
		$max_h = (int) ( $this->settings['max_height'] ?? 2560 );
		$size  = $editor->get_size();

		if ( $size['width'] > $max_w || $size['height'] > $max_h ) {
			$editor->resize( $max_w, $max_h, false );
		}

		$editor->set_quality( (int) ( $this->settings['quality'] ?? 75 ) );
		$editor->save( $file_path );
	}

	/* ================================================================
	 * CONVERSION DE FORMAT (AVIF / WebP)
	 * ================================================================ */

	/**
	 * Convertit un fichier vers le format cible.
	 *
	 * Retourne le chemin du fichier converti, ou false si la conversion
	 * n'est pas possible ou ne réduit pas la taille.
	 *
	 * @param string $file_path     Chemin du fichier source.
	 * @param string $mime_type     MIME type connu (évite une détection inutile).
	 * @param int    $attachment_id Pour la détection MIME via WP si mime_type vide.
	 * @return string|false
	 */
	public function convert( string $file_path, string $mime_type = '', int $attachment_id = 0 ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$target_format = $this->get_target_format();
		if ( '' === $target_format ) {
			return false;
		}

		$mime = $mime_type ?: $this->get_mime_type( $file_path, $attachment_id );
		if ( ! $this->is_supported_mime( $mime ) ) {
			return false;
		}

		if ( $this->is_animated( $file_path, $mime ) ) {
			return false;
		}

		$info = pathinfo( $file_path );

		// Déjà dans le bon format
		if ( strtolower( $info['extension'] ?? '' ) === $target_format ) {
			return $file_path;
		}

		$output_path = $info['dirname'] . '/' . $info['filename'] . '.' . $target_format;
		$before      = filesize( $file_path );

		$cap = $this->get_capabilities();

		if ( $cap['imagick'] ) {
			$ok = $this->convert_with_imagick( $file_path, $output_path, $target_format );
		} elseif ( $cap['gd'] ) {
			$ok = $this->convert_with_gd( $file_path, $output_path, $target_format );
		} else {
			return false;
		}

		if ( ! $ok || ! file_exists( $output_path ) ) {
			return false;
		}

		// Ne conserver la conversion que si elle réduit la taille.
		$after = filesize( $output_path );
		if ( $before > 0 && $after >= $before ) {
			wp_delete_file( $output_path );
			return false;
		}

		if ( ! ( $this->settings['keep_original'] ?? false ) ) {
			wp_delete_file( $file_path );
		}

		return $output_path;
	}

	private function convert_with_imagick( string $source, string $output, string $format ): bool {
		$imagick = null;

		try {
			$imagick = new \Imagick( $source );

			if ( $this->settings['strip_exif'] ?? true ) {
				$imagick->stripImage();
			}

			$imagick->setImageCompressionQuality( (int) ( $this->settings['quality'] ?? 75 ) );
			$imagick->setImageFormat( $format );
			$imagick->writeImage( $output );

			return true;
		} catch ( \Exception $e ) {
			return false;
		} finally {
			if ( $imagick instanceof \Imagick ) {
				$imagick->clear();
				$imagick->destroy();
			}
		}
	}

	private function convert_with_gd( string $source, string $output, string $format ): bool {
		$editor = wp_get_image_editor( $source );

		if ( is_wp_error( $editor ) ) {
			return false;
		}

		$editor->set_quality( (int) ( $this->settings['quality'] ?? 75 ) );
		$mime   = 'webp' === $format ? 'image/webp' : 'image/avif';
		$result = $editor->save( $output, $mime );

		return ! is_wp_error( $result );
	}

	/* ================================================================
	 * UTILITAIRES
	 * ================================================================ */

	/**
	 * Vérifie si un type MIME est pris en charge pour optimisation/conversion.
	 */
	public function is_supported_mime( string $mime_type ): bool {
		if ( strpos( $mime_type, 'image/' ) !== 0 ) {
			return false;
		}

		$excluded = [
			'image/svg+xml',
			'image/x-icon',
			'image/vnd.microsoft.icon',
		];

		return ! in_array( $mime_type, $excluded, true );
	}

	/**
	 * Détecte si une image est animée (GIF animé, WebP animé, APNG…).
	 */
	public function is_animated( string $file_path, string $mime_type ): bool {
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return false;
		}

		$cap = $this->get_capabilities();

		if ( $cap['imagick'] ) {
			$imagick = null;
			try {
				$imagick     = new \Imagick( $file_path );
				$is_animated = $imagick->getNumberImages() > 1;
				return $is_animated;
			} catch ( \Exception $e ) {
				return false;
			} finally {
				if ( $imagick instanceof \Imagick ) {
					$imagick->clear();
					$imagick->destroy();
				}
			}
		}

		if ( 'image/gif' === $mime_type ) {
			return $this->is_animated_gif( $file_path );
		}

		return false;
	}

	private function is_animated_gif( string $file_path ): bool {
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
			$frames += preg_match_all( '/\x00\x21\xF9\x04.{4}\x00[\x2C\x21]/s', $chunk );
		}
		fclose( $handle );

		return $frames > 1;
	}

	/**
	 * Détecte le type MIME d'un fichier.
	 * Préfère le MIME enregistré en base WP si un attachment_id est fourni.
	 */
	public function get_mime_type( string $file_path, int $attachment_id = 0 ): string {
		if ( $attachment_id > 0 ) {
			$mime = get_post_mime_type( $attachment_id );
			if ( is_string( $mime ) && '' !== $mime ) {
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
			$detected = mime_content_type( $file_path );
			if ( is_string( $detected ) && '' !== $detected ) {
				return $detected;
			}
		}

		return '';
	}

	/**
	 * Convertit un nom de fichier en texte alternatif lisible.
	 * Ex: "mon-image-1920x1080" → "Mon image"
	 */
	public function filename_to_alt( string $filename ): string {
		$alt = preg_replace( '/-\d+x\d+$/', '', $filename );
		$alt = str_replace( [ '-', '_', '.' ], ' ', (string) $alt );
		return ucfirst( trim( $alt ) );
	}
}
