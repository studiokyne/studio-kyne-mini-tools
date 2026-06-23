<?php
namespace StudioKyne\MiniTools\Modules\Files;

/**
 * Moteur des opérations fichier, strictement limité à un répertoire racine.
 * Toute tentative de sortir de la racine lève une InvalidArgumentException.
 */
class FileManager {

	private string $root;

	public function __construct( string $root ) {
		$real = realpath( $root );
		if ( $real === false ) {
			throw new \InvalidArgumentException( 'Root path does not exist: ' . $root );
		}
		$this->root = rtrim( $real, DIRECTORY_SEPARATOR );
	}

	public function get_root(): string {
		return $this->root;
	}

	/* ================================================================
	 * PATH RESOLUTION
	 * ================================================================ */

	/**
	 * Résout un chemin relatif en absolu validé (le fichier/dossier doit exister).
	 */
	public function resolve( string $rel ): string {
		$rel = str_replace( "\0", '', $rel );

		if ( $rel === '' || $rel === '.' || $rel === '/' ) {
			return $this->root;
		}

		$norm = $this->normalize( $rel );
		$abs  = realpath( $this->root . DIRECTORY_SEPARATOR . $norm );

		if ( $abs === false ) {
			throw new \InvalidArgumentException( 'Path does not exist.' );
		}

		if ( $abs !== $this->root && strpos( $abs, $this->root . DIRECTORY_SEPARATOR ) !== 0 ) {
			throw new \InvalidArgumentException( 'Path is outside root.' );
		}

		return $abs;
	}

	/**
	 * Résout un chemin pour un fichier qui n'existe pas encore (création/upload).
	 */
	private function resolve_new( string $rel ): string {
		$rel  = str_replace( "\0", '', $rel );
		$norm = $this->normalize( $rel );

		if ( $norm === '' ) {
			throw new \InvalidArgumentException( 'Invalid path.' );
		}

		$abs = $this->root . DIRECTORY_SEPARATOR . $norm;

		// Vérifier que le parent existe et est dans la racine.
		$parent = realpath( dirname( $abs ) );
		if ( $parent === false || ( $parent !== $this->root && strpos( $parent, $this->root . DIRECTORY_SEPARATOR ) !== 0 ) ) {
			throw new \InvalidArgumentException( 'Parent directory is outside root or does not exist.' );
		}

		return $abs;
	}

	/**
	 * Normalise un chemin relatif : supprime les segments vides, '.', '..'.
	 */
	private function normalize( string $rel ): string {
		$parts = preg_split( '#[/\\\\]#', $rel );
		$clean = [];
		foreach ( $parts as $p ) {
			if ( $p === '' || $p === '.' ) {
				continue;
			}
			if ( $p === '..' ) {
				array_pop( $clean );
				continue;
			}
			$clean[] = $p;
		}
		return implode( DIRECTORY_SEPARATOR, $clean );
	}

	/**
	 * Retourne le chemin relatif à partir d'un chemin absolu.
	 */
	public function to_relative( string $abs ): string {
		if ( $abs === $this->root ) {
			return '';
		}
		if ( strpos( $abs, $this->root . DIRECTORY_SEPARATOR ) !== 0 ) {
			throw new \InvalidArgumentException( 'Path is not within root.' );
		}
		return ltrim( str_replace( '\\', '/', substr( $abs, strlen( $this->root ) ) ), '/' );
	}

	/* ================================================================
	 * LISTING
	 * ================================================================ */

	/**
	 * Liste le contenu d'un répertoire. Retourne les dossiers en premier.
	 */
	public function list_directory( string $rel ): array {
		$abs = $this->resolve( $rel );

		if ( ! is_dir( $abs ) ) {
			throw new \InvalidArgumentException( 'Not a directory.' );
		}

		$handle = opendir( $abs );
		if ( $handle === false ) {
			throw new \RuntimeException( 'Cannot open directory.' );
		}

		$items = [];
		while ( false !== ( $entry = readdir( $handle ) ) ) {
			if ( $entry === '.' || $entry === '..' ) {
				continue;
			}

			$full     = $abs . DIRECTORY_SEPARATOR . $entry;
			$is_dir   = is_dir( $full );
			$ext      = $is_dir ? '' : strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
			$rel_item = ( $rel === '' || $rel === '/' )
				? $entry
				: rtrim( str_replace( '\\', '/', $rel ), '/' ) . '/' . $entry;

			$items[] = [
				'name'     => $entry,
				'path'     => $rel_item,
				'type'     => $is_dir ? 'dir' : 'file',
				'ext'      => $ext,
				'size'     => $is_dir ? null : @filesize( $full ),
				'modified' => @filemtime( $full ),
				'perms'    => substr( sprintf( '%o', @fileperms( $full ) ), -4 ),
				'owner'    => $this->get_owner( $full ),
				'writable' => is_writable( $full ),
			];
		}
		closedir( $handle );

		usort( $items, function ( $a, $b ) {
			if ( $a['type'] !== $b['type'] ) {
				return $a['type'] === 'dir' ? -1 : 1;
			}
			return strcasecmp( $a['name'], $b['name'] );
		} );

		return $items;
	}

	/* ================================================================
	 * OPÉRATIONS
	 * ================================================================ */

	public function delete( string $rel ): bool {
		$abs = $this->resolve( $rel );
		if ( is_dir( $abs ) ) {
			return $this->delete_dir( $abs );
		}
		return unlink( $abs );
	}

	private function delete_dir( string $dir ): bool {
		foreach ( array_diff( (array) scandir( $dir ), [ '.', '..' ] ) as $item ) {
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			is_dir( $path ) ? $this->delete_dir( $path ) : unlink( $path );
		}
		return rmdir( $dir );
	}

	public function rename( string $rel, string $new_name ): bool {
		$new_name = sanitize_file_name( $new_name );
		if ( $new_name === '' ) {
			throw new \InvalidArgumentException( 'Invalid name.' );
		}

		$abs     = $this->resolve( $rel );
		$new_abs = dirname( $abs ) . DIRECTORY_SEPARATOR . $new_name;

		if ( strpos( $new_abs, $this->root ) !== 0 ) {
			throw new \InvalidArgumentException( 'Invalid path.' );
		}
		if ( file_exists( $new_abs ) ) {
			throw new \RuntimeException( 'A file with that name already exists.' );
		}

		return rename( $abs, $new_abs );
	}

	public function move( string $src_rel, string $dst_rel ): bool {
		$src     = $this->resolve( $src_rel );
		$dst_dir = $this->resolve( $dst_rel );

		if ( ! is_dir( $dst_dir ) ) {
			throw new \InvalidArgumentException( 'Destination is not a directory.' );
		}

		$dst = $dst_dir . DIRECTORY_SEPARATOR . basename( $src );

		if ( strpos( $dst, $this->root ) !== 0 ) {
			throw new \InvalidArgumentException( 'Invalid destination.' );
		}
		if ( file_exists( $dst ) ) {
			throw new \RuntimeException( 'A file with that name already exists at destination.' );
		}

		return rename( $src, $dst );
	}

	public function create_folder( string $rel ): bool {
		$abs = $this->resolve_new( $rel );
		if ( file_exists( $abs ) ) {
			throw new \RuntimeException( 'Already exists.' );
		}
		return wp_mkdir_p( $abs );
	}

	public function get_content( string $rel ): string {
		$abs = $this->resolve( $rel );
		if ( ! is_file( $abs ) ) {
			throw new \InvalidArgumentException( 'Not a file.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $abs );
		if ( $content === false ) {
			throw new \RuntimeException( 'Cannot read file.' );
		}
		return $content;
	}

	public function save_content( string $rel, string $content ): bool {
		$abs = $this->resolve( $rel );
		if ( ! is_file( $abs ) ) {
			throw new \InvalidArgumentException( 'Not a file.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		return file_put_contents( $abs, $content ) !== false;
	}

	/**
	 * Crée une archive ZIP. Retourne le chemin absolu du zip créé.
	 */
	public function create_zip( array $rel_paths, string $dest_rel ): string {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new \RuntimeException( 'ZipArchive non disponible sur ce serveur.' );
		}

		$dest = $this->resolve_new( $dest_rel );
		$zip  = new \ZipArchive();

		if ( $zip->open( $dest, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
			throw new \RuntimeException( 'Impossible de créer l\'archive.' );
		}

		foreach ( $rel_paths as $rel ) {
			$abs = $this->resolve( $rel );
			if ( is_dir( $abs ) ) {
				$this->zip_add_dir( $zip, $abs, basename( $abs ) );
			} else {
				$zip->addFile( $abs, basename( $abs ) );
			}
		}

		$zip->close();
		return $dest;
	}

	private function zip_add_dir( \ZipArchive $zip, string $dir, string $base ): void {
		$zip->addEmptyDir( $base );
		foreach ( array_diff( (array) scandir( $dir ), [ '.', '..' ] ) as $item ) {
			$abs = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $abs ) ) {
				$this->zip_add_dir( $zip, $abs, $base . '/' . $item );
			} else {
				$zip->addFile( $abs, $base . '/' . $item );
			}
		}
	}

	public function extract_zip( string $rel ): bool {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new \RuntimeException( 'ZipArchive non disponible sur ce serveur.' );
		}

		$abs  = $this->resolve( $rel );
		$dest = dirname( $abs );
		$zip  = new \ZipArchive();

		if ( $zip->open( $abs ) !== true ) {
			throw new \RuntimeException( 'Impossible d\'ouvrir l\'archive.' );
		}

		$zip->extractTo( $dest );
		$zip->close();
		return true;
	}

	public function upload( string $dir_rel, array $file ): string {
		$dir  = $this->resolve( $dir_rel );
		$name = sanitize_file_name( $file['name'] );
		$dest = $dir . DIRECTORY_SEPARATOR . $name;

		if ( strpos( $dest, $this->root ) !== 0 ) {
			throw new \InvalidArgumentException( 'Invalid destination.' );
		}

		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			throw new \RuntimeException( 'Upload failed.' );
		}

		return $this->to_relative( $dest );
	}

	/* ================================================================
	 * UTILITAIRES
	 * ================================================================ */

	private function get_owner( string $path ): string {
		if ( ! function_exists( 'posix_getpwuid' ) || ! function_exists( 'posix_getgrgid' ) ) {
			return '';
		}
		$uid   = @fileowner( $path );
		$gid   = @filegroup( $path );
		$uinfo = posix_getpwuid( $uid );
		$ginfo = posix_getgrgid( $gid );
		$u     = $uinfo ? $uinfo['name'] : (string) $uid;
		$g     = $ginfo ? $ginfo['name'] : (string) $gid;
		return $u . ':' . $g;
	}

	public static function format_size( int $bytes ): string {
		if ( $bytes < 1024 ) {
			return $bytes . ' B';
		}
		if ( $bytes < 1048576 ) {
			return round( $bytes / 1024, 1 ) . ' KB';
		}
		if ( $bytes < 1073741824 ) {
			return round( $bytes / 1048576, 1 ) . ' MB';
		}
		return round( $bytes / 1073741824, 2 ) . ' GB';
	}
}
