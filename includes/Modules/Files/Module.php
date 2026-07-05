<?php
namespace StudioKyne\MiniTools\Modules\Files;

use StudioKyne\MiniTools\Core\AbstractModule;

/**
 * Module Fichiers — gestionnaire de fichiers WordPress.
 */
class Module extends AbstractModule {

	private FileManager $fm;

	/* ================================================================
	 * INIT
	 * ================================================================ */

	public function init(): void {
		$this->fm = new FileManager( ABSPATH );

		add_action( 'wp_ajax_skmt_files_list',         [ $this, 'ajax_list' ] );
		add_action( 'wp_ajax_skmt_files_delete',       [ $this, 'ajax_delete' ] );
		add_action( 'wp_ajax_skmt_files_rename',       [ $this, 'ajax_rename' ] );
		add_action( 'wp_ajax_skmt_files_move',         [ $this, 'ajax_move' ] );
		add_action( 'wp_ajax_skmt_files_mkdir',        [ $this, 'ajax_mkdir' ] );
		add_action( 'wp_ajax_skmt_files_zip',          [ $this, 'ajax_zip' ] );
		add_action( 'wp_ajax_skmt_files_extract',      [ $this, 'ajax_extract' ] );
		add_action( 'wp_ajax_skmt_files_get_content',  [ $this, 'ajax_get_content' ] );
		add_action( 'wp_ajax_skmt_files_save_content', [ $this, 'ajax_save_content' ] );
		add_action( 'wp_ajax_skmt_files_upload',       [ $this, 'ajax_upload' ] );
		add_action( 'admin_post_skmt_files_download',  [ $this, 'handle_download' ] );
	}

	/* ================================================================
	 * SÉCURITÉ
	 * ================================================================ */

	private function check_nonce(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'skmt_admin_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission refusée.', 'studio-kyne-mini-tools' ) ], 403 );
		}
	}

	private function get_post_path( string $key = 'path' ): string {
		$raw = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		return rawurldecode( $raw );
	}

	/* ================================================================
	 * AJAX — LISTING
	 * ================================================================ */

	public function ajax_list(): void {
		$this->check_nonce();
		$path = $this->get_post_path();

		try {
			$items     = $this->fm->list_directory( $path );
			$date_fmt  = get_option( 'date_format' ) . ' H:i';
			$formatted = array_map( function ( $item ) use ( $date_fmt ) {
				$item['size_fmt']     = ( $item['size'] !== null ) ? FileManager::format_size( (int) $item['size'] ) : '';
				$item['modified_fmt'] = $item['modified'] ? date_i18n( $date_fmt, $item['modified'] ) : '';
				return $item;
			}, $items );

			wp_send_json_success( [ 'items' => $formatted, 'path' => $path ] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/* ================================================================
	 * AJAX — OPÉRATIONS
	 * ================================================================ */

	public function ajax_delete(): void {
		$this->check_nonce();
		$paths  = isset( $_POST['paths'] ) ? (array) wp_unslash( $_POST['paths'] ) : [];
		$errors = [];

		foreach ( $paths as $path ) {
			try {
				$this->fm->delete( sanitize_text_field( $path ) );
			} catch ( \Exception $e ) {
				$errors[] = $e->getMessage();
			}
		}

		if ( $errors && count( $errors ) === count( $paths ) ) {
			wp_send_json_error( [ 'message' => implode( ', ', $errors ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'Supprimé avec succès.', 'studio-kyne-mini-tools' ) ] );
	}

	public function ajax_rename(): void {
		$this->check_nonce();
		$path     = $this->get_post_path();
		$new_name = isset( $_POST['new_name'] ) ? sanitize_file_name( wp_unslash( $_POST['new_name'] ) ) : '';

		try {
			$this->fm->rename( $path, $new_name );
			wp_send_json_success( [ 'message' => __( 'Renommé avec succès.', 'studio-kyne-mini-tools' ) ] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public function ajax_move(): void {
		$this->check_nonce();
		$src = $this->get_post_path( 'src' );
		$dst = $this->get_post_path( 'dst' );

		try {
			$this->fm->move( $src, $dst );
			wp_send_json_success( [ 'message' => __( 'Déplacé avec succès.', 'studio-kyne-mini-tools' ) ] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public function ajax_mkdir(): void {
		$this->check_nonce();
		$parent = $this->get_post_path( 'parent' );
		$name   = isset( $_POST['name'] ) ? sanitize_file_name( wp_unslash( $_POST['name'] ) ) : '';
		$rel    = ( $parent !== '' ) ? rtrim( $parent, '/' ) . '/' . $name : $name;

		try {
			$this->fm->create_folder( $rel );
			wp_send_json_success( [ 'message' => __( 'Dossier créé.', 'studio-kyne-mini-tools' ) ] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public function ajax_zip(): void {
		$this->check_nonce();
		$paths  = isset( $_POST['paths'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['paths'] ) ) : [];
		$name   = isset( $_POST['name'] ) ? sanitize_file_name( wp_unslash( $_POST['name'] ) ) : 'archive.zip';
		$parent = $this->get_post_path( 'parent' );
		$dest   = ( $parent !== '' ) ? rtrim( $parent, '/' ) . '/' . $name : $name;

		try {
			$abs          = $this->fm->create_zip( $paths, $dest );
			$rel          = $this->fm->to_relative( $abs );
			$download_url = $this->build_download_url( $rel );
			wp_send_json_success( [
				'message'      => __( 'Archive créée.', 'studio-kyne-mini-tools' ),
				'path'         => $rel,
				'download_url' => $download_url,
			] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public function ajax_extract(): void {
		$this->check_nonce();
		$path = $this->get_post_path();

		try {
			$this->fm->extract_zip( $path );
			wp_send_json_success( [ 'message' => __( 'Archive extraite.', 'studio-kyne-mini-tools' ) ] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public function ajax_get_content(): void {
		$this->check_nonce();
		$path = $this->get_post_path();

		try {
			$content = $this->fm->get_content( $path );
			wp_send_json_success( [ 'content' => $content, 'path' => $path ] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public function ajax_save_content(): void {
		$this->check_nonce();
		$path    = $this->get_post_path();
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';

		try {
			$this->fm->save_content( $path, $content );
			wp_send_json_success( [ 'message' => __( 'Fichier enregistré.', 'studio-kyne-mini-tools' ) ] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public function ajax_upload(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'skmt_admin_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission refusée.', 'studio-kyne-mini-tools' ) ], 403 );
		}

		$dir   = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$files = $_FILES['files'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! $files ) {
			wp_send_json_error( [ 'message' => __( 'Aucun fichier reçu.', 'studio-kyne-mini-tools' ) ] );
		}

		$uploaded = [];
		$errors   = [];
		$count    = is_array( $files['name'] ) ? count( $files['name'] ) : 1;

		for ( $i = 0; $i < $count; $i++ ) {
			$file = is_array( $files['name'] )
				? [
					'name'     => $files['name'][ $i ],
					'tmp_name' => $files['tmp_name'][ $i ],
					'error'    => $files['error'][ $i ],
					'size'     => $files['size'][ $i ],
				]
				: $files;

			if ( $file['error'] !== UPLOAD_ERR_OK ) {
				$errors[] = sanitize_text_field( $file['name'] );
				continue;
			}

			try {
				$uploaded[] = $this->fm->upload( $dir, $file );
			} catch ( \Exception $e ) {
				$errors[] = sanitize_text_field( $file['name'] ) . ': ' . $e->getMessage();
			}
		}

		if ( $errors && ! $uploaded ) {
			wp_send_json_error( [ 'message' => implode( ', ', $errors ) ] );
		}

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: %d: number of files */
				_n( '%d fichier uploadé.', '%d fichiers uploadés.', count( $uploaded ), 'studio-kyne-mini-tools' ),
				count( $uploaded )
			),
			'paths'   => $uploaded,
		] );
	}

	/* ================================================================
	 * TÉLÉCHARGEMENT
	 * ================================================================ */

	public function handle_download(): void {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'skmt_files_download' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'studio-kyne-mini-tools' ) );
		}

		$path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '';

		try {
			$abs = $this->fm->resolve( $path );
		} catch ( \Exception $e ) {
			wp_die( esc_html__( 'Fichier introuvable.', 'studio-kyne-mini-tools' ) );
		}

		if ( is_dir( $abs ) ) {
			$this->stream_dir_as_zip( $abs );
			return;
		}

		if ( ! is_file( $abs ) ) {
			wp_die( esc_html__( 'Fichier introuvable.', 'studio-kyne-mini-tools' ) );
		}

		$mime = mime_content_type( $abs ) ?: 'application/octet-stream';
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . basename( $abs ) . '"' );
		header( 'Content-Length: ' . filesize( $abs ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		readfile( $abs );
		exit;
	}

	/**
	 * Zippe un dossier dans un fichier temp, le streame, puis le supprime.
	 */
	private function stream_dir_as_zip( string $abs ): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'ZipArchive non disponible sur ce serveur.', 'studio-kyne-mini-tools' ) );
		}

		// Fichier temporaire hors racine WP
		$tmp = tempnam( sys_get_temp_dir(), 'skmt_zip_' );
		if ( $tmp === false ) {
			wp_die( esc_html__( 'Impossible de créer le fichier temporaire.', 'studio-kyne-mini-tools' ) );
		}

		// tempnam crée un fichier vide — ZipArchive::CREATE l'écrase
		$zip = new \ZipArchive();
		if ( $zip->open( $tmp, \ZipArchive::OVERWRITE ) !== true ) {
			unlink( $tmp );
			wp_die( esc_html__( 'Impossible de créer l\'archive.', 'studio-kyne-mini-tools' ) );
		}

		$this->add_dir_to_zip( $zip, $abs, basename( $abs ) );
		$zip->close();

		$filename = basename( $abs ) . '.zip';
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		readfile( $tmp );
		unlink( $tmp );
		exit;
	}

	private function add_dir_to_zip( \ZipArchive $zip, string $dir, string $base ): void {
		$zip->addEmptyDir( $base );
		foreach ( array_diff( (array) scandir( $dir ), [ '.', '..' ] ) as $item ) {
			$abs = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $abs ) ) {
				$this->add_dir_to_zip( $zip, $abs, $base . '/' . $item );
			} else {
				$zip->addFile( $abs, $base . '/' . $item );
			}
		}
	}

	/* ================================================================
	 * ASSETS
	 * ================================================================ */

	public function get_admin_css(): array {
		return [ SKMT_ASSETS_URL . 'admin/css/modules/files.css' ];
	}

	public function get_admin_js(): array {
		return [ SKMT_ASSETS_URL . 'admin/js/modules/files.js' ];
	}

	public function get_admin_js_data(): array {
		return [
			'i18n' => [
				'confirmDelete' => __( 'Supprimer ce(s) élément(s) ? Cette action est irréversible.', 'studio-kyne-mini-tools' ),
				'emptyFolder'   => __( 'Ce dossier est vide.', 'studio-kyne-mini-tools' ),
				'loading'       => __( 'Chargement...', 'studio-kyne-mini-tools' ),
				'uploading'     => __( 'Upload en cours...', 'studio-kyne-mini-tools' ),
				'newFolderName' => __( 'Nom du nouveau dossier :', 'studio-kyne-mini-tools' ),
				'downloadUrl'   => admin_url( 'admin-post.php?action=skmt_files_download' ),
				'downloadNonce' => wp_create_nonce( 'skmt_files_download' ),
			],
		];
	}

	/* ================================================================
	 * MODULE INTERFACE
	 * ================================================================ */

	public function get_settings(): array {
		return $this->get_module_settings( self::get_defaults() );
	}

	public function save_settings( array $settings ): bool {
		return $this->save_module_settings( $this->get_module_settings( self::get_defaults() ) );
	}

	public static function get_defaults(): array {
		return [];
	}

	public static function get_uninstall_keys(): array {
		return [
			'options' => [ 'skmt_module_files' ],
			'meta'    => [],
		];
	}

	/* ================================================================
	 * HELPER
	 * ================================================================ */

	private function build_download_url( string $rel ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=skmt_files_download&path=' . rawurlencode( $rel ) ),
			'skmt_files_download'
		);
	}
}
