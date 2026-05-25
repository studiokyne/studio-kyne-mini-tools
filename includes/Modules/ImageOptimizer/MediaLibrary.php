<?php
namespace StudioKyne\MiniTools\Modules\ImageOptimizer;

/**
 * Intégration UI de la médiathèque WordPress pour l'Image Optimizer :
 * colonne Format, champs dans l'éditeur media, optimisation single via AJAX.
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
		add_action( 'wp_ajax_skmt_optimize_single', [ $this, 'ajax_optimize_single' ] );
	}

	/* ================================================================
	 * COLONNE MÉDIATHÈQUE
	 * ================================================================ */

	/**
	 * Ajoute une colonne "Format" dans la liste des médias.
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
	public function enqueue_assets( string $hook ): void {
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

		foreach ( $this->module->get_admin_js() as $index => $script_url ) {
			if ( empty( $script_url ) ) {
				continue;
			}

			$handle = 'skmt-image-optimizer-media-' . $index;

			wp_enqueue_script( $handle, $script_url, [], SKMT_VERSION, true );

			// Données globales + i18n spécifiques au module.
			$js_data  = $this->module->get_admin_js_data();
			$i18n     = $js_data['i18n'] ?? [];

			wp_localize_script( $handle, 'skmtAdmin', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'skmt_admin_nonce' ),
				'i18n'    => $i18n,
			] );
		}
	}

	/* ================================================================
	 * ÉDITEUR D'ATTACHMENT
	 * ================================================================ */

	/**
	 * Injecte la section Image Optimizer dans le formulaire d'édition d'un média.
	 */
	public function add_optimizer_fields( array $form_fields, \WP_Post $post ): array {
		$mime = get_post_mime_type( $post->ID );

		if ( empty( $mime ) || ! $this->processor->is_supported_mime( $mime ) ) {
			return $form_fields;
		}

		$file = get_attached_file( $post->ID );
		if ( empty( $file ) || ! file_exists( $file ) ) {
			return $form_fields;
		}

		$is_animated  = $this->processor->is_animated( $file, $mime );
		$is_optimized = $this->module->is_already_optimized( $post->ID );

		$original_bytes      = (int) get_post_meta( $post->ID, '_skmt_original_bytes', true );
		$optimized_bytes     = (int) get_post_meta( $post->ID, '_skmt_optimized_bytes', true );
		$bytes_saved         = (int) get_post_meta( $post->ID, '_skmt_bytes_saved', true );
		$main_original_bytes = (int) get_post_meta( $post->ID, '_skmt_main_original_bytes', true );
		$main_optimized_bytes= (int) get_post_meta( $post->ID, '_skmt_main_optimized_bytes', true );
		$main_bytes_saved    = (int) get_post_meta( $post->ID, '_skmt_main_bytes_saved', true );

		// Fallbacks pour les médias optimisés avant l'ajout du détail main.
		if ( $is_optimized && 0 === $main_optimized_bytes ) {
			$main_optimized_bytes = file_exists( $file ) ? (int) filesize( $file ) : 0;
		}
		if ( $is_optimized && 0 === $main_original_bytes && $main_optimized_bytes > 0 ) {
			$main_original_bytes = max( $main_optimized_bytes + $main_bytes_saved, $main_optimized_bytes );
		}
		if ( $is_optimized && 0 === $main_bytes_saved && $main_original_bytes > 0 && $main_optimized_bytes > 0 ) {
			$main_bytes_saved = max( $main_original_bytes - $main_optimized_bytes, 0 );
		}

		// Estimation pour les images non encore optimisées.
		$estimated = 0;
		if ( ! $is_optimized ) {
			$stats     = $this->module->get_stats();
			$avg_ratio = 0.0;
			if ( ! empty( $stats['original_bytes'] ) ) {
				$avg_ratio = (float) $stats['bytes_saved'] / max( 1.0, (float) $stats['original_bytes'] );
			}
			$estimated = (int) floor( filesize( $file ) * $avg_ratio );
		}

		// Bouton / statut d'action.
		if ( $is_animated ) {
			$action_html = '<p>' . esc_html__( 'Image animée : optimisation automatique désactivée.', 'studio-kyne-mini-tools' ) . '</p>';
		} elseif ( ! $is_optimized ) {
			$action_html = '<button type="button" class="button skmt-optimize-single" data-attachment="' . esc_attr( (string) $post->ID ) . '">'
				. esc_html__( 'Optimiser cette image', 'studio-kyne-mini-tools' )
				. '</button>';
		} else {
			$action_html = '<span class="skmt-optimized-status">' . esc_html__( 'Déjà optimisée', 'studio-kyne-mini-tools' ) . '</span>';
		}

		$current_size    = filesize( $file );
		$potential_style = $is_optimized ? 'style="display:none;"' : '';
		$result_style    = $is_optimized ? '' : 'style="display:none;"';

		$details = '<div class="skmt-gain-potential" ' . $potential_style . '>'
			. '<p>'
			. esc_html__( 'Gain potentiel :', 'studio-kyne-mini-tools' )
			. ' <strong class="skmt-bytes-estimated">' . esc_html( size_format( $estimated, 2 ) ) . '</strong>'
			. '</p>'
			. '<p>' . esc_html__( 'Taille actuelle :', 'studio-kyne-mini-tools' ) . ' <strong class="skmt-bytes-current">' . esc_html( size_format( $current_size, 2 ) ) . '</strong></p>'
			. '</div>'
			. '<div class="skmt-gain-result" ' . $result_style . '>'
			. '<p style="margin-bottom:4px;"><strong>' . esc_html__( 'Fichier principal', 'studio-kyne-mini-tools' ) . '</strong></p>'
			. '<p>' . esc_html__( 'Gain obtenu :', 'studio-kyne-mini-tools' ) . ' <strong class="skmt-main-bytes-saved">' . esc_html( size_format( $main_bytes_saved, 2 ) ) . '</strong></p>'
			. '<p>' . esc_html__( 'Taille avant :', 'studio-kyne-mini-tools' ) . ' <strong class="skmt-main-bytes-original">' . esc_html( size_format( $main_original_bytes, 2 ) ) . '</strong></p>'
			. '<p>' . esc_html__( 'Taille après :', 'studio-kyne-mini-tools' ) . ' <strong class="skmt-main-bytes-final">' . esc_html( size_format( $main_optimized_bytes, 2 ) ) . '</strong></p>'
			. '<p style="margin:10px 0 4px;"><strong>' . esc_html__( 'Total (principal + miniatures)', 'studio-kyne-mini-tools' ) . '</strong></p>'
			. '<p>' . esc_html__( 'Gain obtenu :', 'studio-kyne-mini-tools' ) . ' <strong class="skmt-bytes-saved">' . esc_html( size_format( $bytes_saved, 2 ) ) . '</strong></p>'
			. '<p>' . esc_html__( 'Taille avant :', 'studio-kyne-mini-tools' ) . ' <strong class="skmt-bytes-original">' . esc_html( size_format( $original_bytes, 2 ) ) . '</strong></p>'
			. '<p>' . esc_html__( 'Taille après :', 'studio-kyne-mini-tools' ) . ' <strong class="skmt-bytes-final">' . esc_html( size_format( $optimized_bytes, 2 ) ) . '</strong></p>'
			. '</div>';

		$form_fields['skmt_image_optimizer'] = [
			'label' => __( 'Image Optimizer', 'studio-kyne-mini-tools' ),
			'input' => 'html',
			'html'  => '<div class="skmt-media-optimizer" data-attachment="' . esc_attr( (string) $post->ID ) . '">'
				. $details
				. '<div class="skmt-media-optimizer__actions">' . $action_html . '</div>'
				. '<div class="skmt-media-optimizer__message" style="margin-top:6px;"></div>'
				. '</div>',
		];

		return $form_fields;
	}

	/* ================================================================
	 * AJAX : OPTIMISATION D'UNE IMAGE
	 * ================================================================ */

	/**
	 * Optimise un seul attachment depuis la médiathèque.
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

		if ( empty( $mime ) || ! $this->processor->is_supported_mime( $mime ) ) {
			wp_send_json_error( __( 'Format non pris en charge.', 'studio-kyne-mini-tools' ) );
		}

		$file = get_attached_file( $attachment_id );

		if ( empty( $file ) || ! file_exists( $file ) ) {
			wp_send_json_error( __( 'Fichier introuvable.', 'studio-kyne-mini-tools' ) );
		}

		if ( $this->processor->is_animated( $file, $mime ) ) {
			wp_send_json_error( __( 'Image animée non prise en charge.', 'studio-kyne-mini-tools' ) );
		}

		if ( ! $this->module->is_already_optimized( $attachment_id ) ) {
			$this->module->process_and_update_attachment( $attachment_id, true );
		}

		wp_send_json_success( [
			'original_bytes'       => (int) get_post_meta( $attachment_id, '_skmt_original_bytes', true ),
			'optimized_bytes'      => (int) get_post_meta( $attachment_id, '_skmt_optimized_bytes', true ),
			'bytes_saved'          => (int) get_post_meta( $attachment_id, '_skmt_bytes_saved', true ),
			'main_original_bytes'  => (int) get_post_meta( $attachment_id, '_skmt_main_original_bytes', true ),
			'main_optimized_bytes' => (int) get_post_meta( $attachment_id, '_skmt_main_optimized_bytes', true ),
			'main_bytes_saved'     => (int) get_post_meta( $attachment_id, '_skmt_main_bytes_saved', true ),
		] );
	}
}
