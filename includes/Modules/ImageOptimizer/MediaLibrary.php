<?php
namespace StudioKyne\MiniTools\Modules\ImageOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Intégration UI de la médiathèque WordPress pour l'Image Optimizer :
 * colonne Format, panneau de la fiche média et ses actions AJAX.
 */
class MediaLibrary {

	private Module $module;
	private ImageProcessor $processor;

	public function __construct( Module $module, ImageProcessor $processor ) {
		$this->module    = $module;
		$this->processor = $processor;
	}

	/**
	 * Enregistre tous les hooks médiathèque.
	 */
	public function init(): void {
		add_filter( 'manage_media_columns', [ $this, 'add_column' ] );
		add_action( 'manage_media_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'attachment_fields_to_edit', [ $this, 'add_optimizer_fields' ], 10, 2 );

		foreach ( [ 'optimize', 'reoptimize', 'convert', 'regenerate', 'restore' ] as $action ) {
			add_action( 'wp_ajax_skmt_image_optimizer_media_' . $action, [ $this, 'ajax_' . $action ] );
		}
	}

	/* ================================================================
	 * COLONNE MÉDIATHÈQUE
	 * ================================================================ */

	/**
	 * Ajoute une colonne "Format" dans la liste des médias.
	 *
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$result = [];
		foreach ( $columns as $key => $value ) {
			$result[ $key ] = $value;
			if ( 'title' === $key ) {
				$result['skmt_format'] = __( 'Format', 'studio-kyne-mini-tools' );
			}
		}
		return $result;
	}

	/**
	 * Affiche le badge de format dans la colonne.
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( 'skmt_format' !== $column ) {
			return;
		}

		$mime = get_post_mime_type( $post_id );

		if ( ! is_string( $mime ) || strpos( $mime, 'image/' ) !== 0 ) {
			echo '<span class="skmt-badge skmt-badge--inactive">—</span>';
			return;
		}

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
	 * ASSETS (upload.php + éditeur d'attachment)
	 * ================================================================ */

	/**
	 * Charge le JS de l'Image Optimizer sur les pages médiathèque et éditeur.
	 */
	public function enqueue_assets(): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_upload     = 'upload' === $screen->base;
		$is_attachment = 'post' === $screen->base && 'attachment' === $screen->post_type;

		if ( ! $is_upload && ! $is_attachment ) {
			return;
		}

		// Design system pour les boutons, la modale et les toasts du panneau :
		// tokens + composants seulement, comme le module Media (reset.css et
		// layout.css n'ont rien à faire sur un écran WordPress natif).
		wp_enqueue_style( 'skmt-tokens-css', SKMT_ASSETS_URL . 'admin/css/tokens.css', [], SKMT_VERSION );
		wp_enqueue_style( 'skmt-components-css', SKMT_ASSETS_URL . 'admin/css/components.css', [ 'skmt-tokens-css' ], SKMT_VERSION );
		wp_enqueue_style( 'skmt-buttons-css', SKMT_ASSETS_URL . 'admin/css/buttons.css', [ 'skmt-components-css' ], SKMT_VERSION );
		wp_enqueue_style( 'skmt-notifications-css', SKMT_ASSETS_URL . 'admin/css/notifications.css', [], SKMT_VERSION );
		wp_enqueue_script( 'skmt-admin-js', SKMT_ASSETS_URL . 'admin/js/admin.js', [], SKMT_VERSION, true );
		wp_enqueue_script( 'skmt-notifications-js', SKMT_ASSETS_URL . 'admin/js/notifications.js', [], SKMT_VERSION, true );

		foreach ( $this->module->get_admin_js() as $index => $script_url ) {
			if ( empty( $script_url ) ) {
				continue;
			}

			$handle = 'skmt-image-optimizer-media-' . $index;

			wp_enqueue_script( $handle, $script_url, [ 'skmt-admin-js', 'skmt-notifications-js' ], SKMT_VERSION, true );

			// Données globales + i18n spécifiques au module.
			$js_data = $this->module->get_admin_js_data();
			$i18n    = $js_data['i18n'] ?? [];

			wp_localize_script(
				$handle,
				'skmtAdmin',
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'skmt_admin_nonce' ),
					'i18n'    => $i18n,
				]
			);
		}
	}

	/* ================================================================
	 * ÉDITEUR D'ATTACHMENT
	 * ================================================================ */

	/**
	 * Injecte la section Image Optimizer dans le formulaire d'édition d'un média.
	 *
	 * @param array<string, mixed> $form_fields
	 * @return array<string, mixed>
	 */
	public function add_optimizer_fields( array $form_fields, \WP_Post $post ): array {
		$html = $this->render_panel( $post->ID );

		if ( '' !== $html ) {
			$form_fields['skmt_image_optimizer'] = [
				'label' => __( 'Image Optimizer', 'studio-kyne-mini-tools' ),
				'input' => 'html',
				'html'  => $html,
			];
		}

		return $form_fields;
	}

	/**
	 * Panneau complet d'un média (statistiques + actions), '' s'il ne
	 * s'applique pas. Renvoyé tel quel par chaque action AJAX : le JS
	 * remplace le panneau au lieu de recalculer l'affichage.
	 */
	public function render_panel( int $attachment_id ): string {
		$mime = (string) get_post_mime_type( $attachment_id );

		if ( '' === $mime || ! $this->processor->is_supported_mime( $mime ) ) {
			return '';
		}

		$file = (string) get_attached_file( $attachment_id );
		if ( '' === $file || ! file_exists( $file ) ) {
			return '';
		}

		$is_animated  = $this->processor->is_animated( $file, $mime );
		$is_optimized = $this->module->is_already_optimized( $attachment_id );

		$original_bytes       = (int) get_post_meta( $attachment_id, '_skmt_original_bytes', true );
		$optimized_bytes      = (int) get_post_meta( $attachment_id, '_skmt_optimized_bytes', true );
		$bytes_saved          = (int) get_post_meta( $attachment_id, '_skmt_bytes_saved', true );
		$main_original_bytes  = (int) get_post_meta( $attachment_id, '_skmt_main_original_bytes', true );
		$main_optimized_bytes = (int) get_post_meta( $attachment_id, '_skmt_main_optimized_bytes', true );
		$main_bytes_saved     = (int) get_post_meta( $attachment_id, '_skmt_main_bytes_saved', true );
		$current_size         = (int) filesize( $file );

		// Fallbacks pour les médias optimisés avant l'ajout du détail main.
		if ( $is_optimized && 0 === $main_optimized_bytes ) {
			$main_optimized_bytes = $current_size;
		}
		if ( $is_optimized && 0 === $main_original_bytes && $main_optimized_bytes > 0 ) {
			$main_original_bytes = max( $main_optimized_bytes + $main_bytes_saved, $main_optimized_bytes );
		}
		if ( $is_optimized && 0 === $main_bytes_saved && $main_original_bytes > 0 && $main_optimized_bytes > 0 ) {
			$main_bytes_saved = max( $main_original_bytes - $main_optimized_bytes, 0 );
		}

		if ( $is_optimized ) {
			$details = '<p style="margin-bottom:4px;"><strong>' . esc_html__( 'Fichier principal', 'studio-kyne-mini-tools' ) . '</strong></p>'
				. $this->render_sizes( $main_bytes_saved, $main_original_bytes, $main_optimized_bytes )
				. '<p style="margin:10px 0 4px;"><strong>' . esc_html__( 'Total (principal + miniatures)', 'studio-kyne-mini-tools' ) . '</strong></p>'
				. $this->render_sizes( $bytes_saved, $original_bytes, $optimized_bytes );
		} else {
			// Estimation d'après le ratio moyen obtenu sur la médiathèque.
			$stats     = $this->module->get_stats();
			$avg_ratio = empty( $stats['original_bytes'] ) ? 0.0 : (float) $stats['bytes_saved'] / max( 1.0, (float) $stats['original_bytes'] );

			$details = '<p>' . esc_html__( 'Gain potentiel :', 'studio-kyne-mini-tools' ) . ' <strong>' . esc_html( (string) size_format( (int) floor( $current_size * $avg_ratio ), 2 ) ) . '</strong></p>'
				. '<p>' . esc_html__( 'Taille actuelle :', 'studio-kyne-mini-tools' ) . ' <strong>' . esc_html( (string) size_format( $current_size, 2 ) ) . '</strong></p>';
		}

		if ( $is_animated ) {
			$actions = '<p>' . esc_html__( 'Image animée : optimisation automatique désactivée.', 'studio-kyne-mini-tools' ) . '</p>';
		} else {
			$actions = $this->render_actions( $attachment_id, $is_optimized, $file );
		}

		return '<div class="skmt-media-optimizer" data-attachment="' . esc_attr( (string) $attachment_id ) . '">'
			. $details
			. '<div class="skmt-media-optimizer__actions" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">' . $actions . '</div>'
			. '</div>';
	}

	/**
	 * Trois lignes gain / avant / après.
	 */
	private function render_sizes( int $saved, int $before, int $after ): string {
		return '<p>' . esc_html__( 'Gain obtenu :', 'studio-kyne-mini-tools' ) . ' <strong>' . esc_html( (string) size_format( $saved, 2 ) ) . '</strong></p>'
			. '<p>' . esc_html__( 'Taille avant :', 'studio-kyne-mini-tools' ) . ' <strong>' . esc_html( (string) size_format( $before, 2 ) ) . '</strong></p>'
			. '<p>' . esc_html__( 'Taille après :', 'studio-kyne-mini-tools' ) . ' <strong>' . esc_html( (string) size_format( $after, 2 ) ) . '</strong></p>';
	}

	/**
	 * Boutons d'action du panneau. Chaque bouton porte l'action AJAX qu'il
	 * déclenche ; le JS se charge des confirmations.
	 */
	private function render_actions( int $attachment_id, bool $is_optimized, string $file ): string {
		$has_backup = '' !== $this->module->get_backup_path( $attachment_id );

		// Formats proposés : ceux que le serveur sait encoder, sauf l'actuel.
		$cap     = $this->processor->get_capabilities();
		$current = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		$formats = array_values(
			array_filter(
				[ 'webp', 'avif' ],
				static fn( string $format ): bool => ! empty( $cap[ $format ] ) && $format !== $current
			)
		);

		$buttons = [];
		if ( $is_optimized ) {
			$buttons[] = $this->action_button( 'reoptimize', __( 'Ré-optimiser', 'studio-kyne-mini-tools' ), [ 'data-has-backup' => $has_backup ? '1' : '0' ] );
		} else {
			$buttons[] = $this->action_button( 'optimize', __( 'Optimiser cette image', 'studio-kyne-mini-tools' ), [], 'primary' );
		}
		if ( $formats ) {
			$buttons[] = $this->action_button( 'convert', __( 'Convertir…', 'studio-kyne-mini-tools' ), [ 'data-formats' => implode( ',', $formats ) ] );
		}
		$buttons[] = $this->action_button( 'regenerate', __( 'Régénérer les miniatures', 'studio-kyne-mini-tools' ) );
		if ( $has_backup ) {
			$buttons[] = $this->action_button( 'restore', __( "Restaurer l'original", 'studio-kyne-mini-tools' ), [], 'danger' );
		}

		return implode( '', $buttons );
	}

	/**
	 * @param array<string, string> $attributes
	 */
	private function action_button( string $action, string $label, array $attributes = [], string $variant = 'secondary' ): string {
		$html = '<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--' . esc_attr( $variant ) . '" data-skmt-io-action="' . esc_attr( $action ) . '"';
		foreach ( $attributes as $name => $value ) {
			$html .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}

		return $html . '>' . esc_html( $label ) . '</button>';
	}

	/* ================================================================
	 * AJAX : ACTIONS SUR UN MÉDIA
	 * ================================================================ */

	public function ajax_optimize(): void {
		$attachment_id = $this->get_request_attachment();

		if ( ! $this->module->is_already_optimized( $attachment_id ) ) {
			$this->module->process_and_update_attachment( $attachment_id, true );
		}

		$this->send_panel( $attachment_id, __( 'Image optimisée.', 'studio-kyne-mini-tools' ) );
	}

	public function ajax_reoptimize(): void {
		$attachment_id = $this->get_request_attachment();

		$this->send_result( $attachment_id, $this->module->reprocess_attachment( $attachment_id ), __( 'Image ré-optimisée.', 'studio-kyne-mini-tools' ) );
	}

	public function ajax_convert(): void {
		$attachment_id = $this->get_request_attachment();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié par get_request_attachment().
		$format = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : '';

		$error = $this->module->reprocess_attachment( $attachment_id, $format );
		if ( $error ) {
			wp_send_json_error( $error->get_error_message() );
		}

		// convert() ne garde un fichier converti que s'il est plus léger.
		$extension = strtolower( pathinfo( (string) get_attached_file( $attachment_id ), PATHINFO_EXTENSION ) );
		if ( $extension !== $format ) {
			$this->send_panel(
				$attachment_id,
				/* translators: %s : format demandé (WEBP, AVIF). */
				sprintf( __( "La conversion en %s n'allégeait pas l'image : le format actuel est conservé.", 'studio-kyne-mini-tools' ), strtoupper( $format ) ),
				'warning'
			);
		}

		/* translators: %s : format obtenu (WEBP, AVIF). */
		$this->send_panel( $attachment_id, sprintf( __( 'Image convertie en %s.', 'studio-kyne-mini-tools' ), strtoupper( $format ) ) );
	}

	public function ajax_regenerate(): void {
		$attachment_id = $this->get_request_attachment();

		$this->send_result( $attachment_id, $this->module->regenerate_thumbnails( $attachment_id ), __( 'Miniatures régénérées.', 'studio-kyne-mini-tools' ) );
	}

	public function ajax_restore(): void {
		$attachment_id = $this->get_request_attachment();

		$this->send_result( $attachment_id, $this->module->restore_original( $attachment_id ), __( 'Original restauré.', 'studio-kyne-mini-tools' ) );
	}

	/**
	 * Vérifie nonce, capacité et média ; renvoie l'ID ou répond en erreur.
	 */
	private function get_request_attachment(): int {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );

		if ( ! current_user_can( Module::get_required_capability() ) ) {
			wp_send_json_error( __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;

		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			wp_send_json_error( __( 'ID invalide.', 'studio-kyne-mini-tools' ) );
		}

		$mime = (string) get_post_mime_type( $attachment_id );
		if ( '' === $mime || ! $this->processor->is_supported_mime( $mime ) ) {
			wp_send_json_error( __( 'Format non pris en charge.', 'studio-kyne-mini-tools' ) );
		}

		$file = (string) get_attached_file( $attachment_id );
		if ( '' === $file || ! file_exists( $file ) ) {
			wp_send_json_error( __( 'Fichier introuvable.', 'studio-kyne-mini-tools' ) );
		}

		if ( $this->processor->is_animated( $file, $mime ) ) {
			wp_send_json_error( __( 'Image animée non prise en charge.', 'studio-kyne-mini-tools' ) );
		}

		return $attachment_id;
	}

	private function send_result( int $attachment_id, ?\WP_Error $error, string $message ): void {
		if ( $error ) {
			wp_send_json_error( $error->get_error_message() );
		}

		$this->send_panel( $attachment_id, $message );
	}

	private function send_panel( int $attachment_id, string $message, string $type = 'success' ): void {
		wp_send_json_success(
			[
				'html'    => $this->render_panel( $attachment_id ),
				'message' => $message,
				'type'    => $type,
			]
		);
	}
}
