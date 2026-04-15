<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Module_Image_Optimizer implements SKMT_Module_Interface {
	protected $plugin;
	protected $stats;
	protected $bulk_runner;
	protected $option_name = 'skmt_image_optimizer_settings';
	protected $defaults = array(
		'enabled'                 => 1,
		'target_format'           => 'auto',
		'quality'                 => 82,
		'max_width'               => 2560,
		'max_height'              => 2560,
		'keep_original'           => 0,
		'enable_upload_optimizer' => 1,
		'enable_bulk_tools'       => 1,
		'strip_exif_metadata'     => 1,
		'auto_fill_alt_text'      => 1,
	);

	public function __construct( $plugin ) {
		$this->plugin      = $plugin;
		$this->stats       = new SKMT_Image_Stats_Repository();
		$this->bulk_runner = new SKMT_Image_Bulk_Runner( $this );
	}

	public function get_id() { return 'image-optimizer'; }
	public function get_name() { return __( 'Image Optimizer', 'studio-kyne-mini-tools' ); }
	public function get_description() { return __( 'Optimise automatiquement les images à l’upload et permet une conversion de masse.', 'studio-kyne-mini-tools' ); }
	public function get_icon() { return 'image'; }
	public function is_default_active() { return false; }
	public function is_configurable() { return true; }
	public function plugin() { return $this->plugin; }
	public function activate() { $this->maybe_seed_defaults(); SKMT_Image_Stats_Repository::create_table(); }
	public function deactivate() {
		$this->bulk_runner->stop_scheduled_runner();
	}

	public function get_settings() {
		$stored = get_option( $this->option_name, array() );
		$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), $this->defaults );
		$settings['enabled'] = 1;
		return $settings;
	}

	public function is_enabled() {
		return true;
	}

	public function maybe_seed_defaults() {
		if ( false === get_option( $this->option_name, false ) ) {
			add_option( $this->option_name, $this->defaults );
		}
	}

	public function register_settings() {
		register_setting(
			'skmt_image_optimizer_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->defaults,
			)
		);
	}

	public function sanitize_settings( $input ) {
		$current = $this->get_settings();
		$input   = is_array( $input ) ? $input : array();

		$current['enabled']                 = 1;
		$current['keep_original']           = empty( $input['keep_original'] ) ? 0 : 1;
		$current['enable_upload_optimizer'] = empty( $input['enable_upload_optimizer'] ) ? 0 : 1;
		$current['enable_bulk_tools']       = empty( $input['enable_bulk_tools'] ) ? 0 : 1;
		$current['strip_exif_metadata']     = empty( $input['strip_exif_metadata'] ) ? 0 : 1;
		$current['auto_fill_alt_text']      = empty( $input['auto_fill_alt_text'] ) ? 0 : 1;
		$current['quality']                 = max( 35, min( 100, absint( $input['quality'] ?? $current['quality'] ) ) );
		$current['max_width']               = max( 256, min( 6000, absint( $input['max_width'] ?? $current['max_width'] ) ) );
		$current['max_height']              = max( 256, min( 6000, absint( $input['max_height'] ?? $current['max_height'] ) ) );

		$format = isset( $input['target_format'] ) ? sanitize_key( $input['target_format'] ) : $current['target_format'];
		$current['target_format'] = in_array( $format, array( 'auto', 'avif', 'webp' ), true ) ? $format : 'auto';

		if ( empty( $current['enable_bulk_tools'] ) ) {
			$this->bulk_runner->stop_scheduled_runner();
		}

		return $current;
	}

	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_filter( 'big_image_size_threshold', array( $this, 'filter_big_image_threshold' ), 10, 1 );
		add_filter( 'image_editor_output_format', array( $this, 'filter_output_format' ), 10, 3 );
		add_filter( 'wp_handle_upload', array( $this, 'filter_uploaded_file' ), 10, 2 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'capture_generated_metadata' ), 10, 2 );
		add_action( 'add_attachment', array( $this, 'maybe_set_alt_text_from_filename' ) );

		$this->bulk_runner->register();
	}

	public function register_admin_pages( $parent_slug ) {
		add_submenu_page(
			$parent_slug,
			__( 'Image Optimizer', 'studio-kyne-mini-tools' ),
			__( 'Image Optimizer', 'studio-kyne-mini-tools' ),
			SKMT_Capabilities::admin_capability(),
			'skmt-image-optimizer',
			array( $this, 'render_admin_page' )
		);
	}

	public function filter_big_image_threshold( $threshold ) {
		$settings = $this->get_settings();
		if ( empty( $settings['enable_upload_optimizer'] ) ) {
			return $threshold;
		}
		return max( absint( $settings['max_width'] ), absint( $settings['max_height'] ) );
	}

	public function filter_output_format( $formats, $filename, $mime_type ) {
		$settings = $this->get_settings();
		if ( empty( $settings['enable_upload_optimizer'] ) ) {
			return $formats;
		}

		$processor = new SKMT_Image_Processor( $settings, $this->plugin->logger() );
		$target    = $processor->resolve_target_mime();
		if ( empty( $target ) ) {
			return $formats;
		}

		$processable = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/avif' );
		if ( in_array( $mime_type, $processable, true ) ) {
			$formats[ $mime_type ] = $target;
		}

		return $formats;
	}

	public function filter_uploaded_file( $upload, $context ) {
		$settings = $this->get_settings();
		if ( empty( $settings['enable_upload_optimizer'] ) || empty( $upload['file'] ) ) {
			return $upload;
		}

		$processor = new SKMT_Image_Processor( $settings, $this->plugin->logger() );
		$result    = $processor->process_file( $upload['file'], ! empty( $settings['keep_original'] ) );
		if ( ! empty( $result['skipped'] ) || empty( $result['success'] ) ) {
			return $upload;
		}

		$hash = md5( wp_normalize_path( $result['final_path'] ) );
		set_transient( 'skmt_upload_' . $hash, $result, 15 * MINUTE_IN_SECONDS );

		$new_filetype  = wp_check_filetype( $result['final_path'] );
		$upload['file'] = $result['final_path'];
		$upload['type'] = $new_filetype['type'] ?? $upload['type'];

		if ( ! empty( $upload['url'] ) ) {
			$upload['url'] = preg_replace(
				'#' . preg_quote( basename( $upload['url'] ), '#' ) . '$#',
				basename( $result['final_path'] ),
				$upload['url']
			);
		}

		return $upload;
	}

	public function capture_generated_metadata( $metadata, $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( empty( $file_path ) ) {
			return $metadata;
		}

		$hash   = md5( wp_normalize_path( $file_path ) );
		$result = get_transient( 'skmt_upload_' . $hash );
		if ( empty( $result ) || ! is_array( $result ) ) {
			return $metadata;
		}

		$this->stats->save(
			$attachment_id,
			array(
				'original_size'   => $result['original_size'] ?? 0,
				'final_size'      => $result['final_size'] ?? 0,
				'bytes_saved'     => $result['bytes_saved'] ?? 0,
				'percent_saved'   => $result['percent_saved'] ?? 0,
				'original_mime'   => $result['original_mime'] ?? '',
				'final_mime'      => $result['final_mime'] ?? '',
				'original_width'  => $result['original_width'] ?? 0,
				'original_height' => $result['original_height'] ?? 0,
				'final_width'     => $result['final_width'] ?? 0,
				'final_height'    => $result['final_height'] ?? 0,
				'editor'          => $result['editor'] ?? '',
				'status'          => 'success',
				'message'         => $result['message'] ?? '',
			)
		);

		update_post_meta( $attachment_id, '_skmt_image_optimizer', wp_json_encode( $result ) );
		delete_transient( 'skmt_upload_' . $hash );

		return $metadata;
	}

	public function optimize_attachment( $attachment_id, $force = false, $bulk_context = false ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id < 1 || ! wp_attachment_is_image( $attachment_id ) ) {
			return 'skipped';
		}

		if ( $bulk_context ) {
			$current_mime = get_post_mime_type( $attachment_id );
			if ( in_array( $current_mime, array( 'image/webp', 'image/avif' ), true ) ) {
				$this->stats->save(
					$attachment_id,
					array(
						'status'        => 'skipped',
						'message'       => __( 'Image déjà compressée (WebP/AVIF) : ignorée en conversion de masse.', 'studio-kyne-mini-tools' ),
						'original_mime' => $current_mime,
						'final_mime'    => $current_mime,
					)
				);
				return 'skipped';
			}
		}

		$existing = $this->stats->get( $attachment_id );
		if ( ! $force && $existing && 'success' === $existing['status'] ) {
			return 'skipped';
		}

		$file_path = get_attached_file( $attachment_id );
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			$this->stats->save( $attachment_id, array( 'status' => 'error', 'message' => __( 'Fichier source introuvable.', 'studio-kyne-mini-tools' ) ) );
			return 'error';
		}

		$settings  = $this->get_settings();
		$processor = new SKMT_Image_Processor( $settings, $this->plugin->logger() );
		$result    = $processor->process_file( $file_path, ! empty( $settings['keep_original'] ) );
		if ( ! empty( $result['skipped'] ) ) {
			$this->stats->save(
				$attachment_id,
				array(
					'status'        => 'skipped',
					'message'       => $result['message'],
					'original_mime' => $result['original_mime'] ?? '',
					'final_mime'    => $result['final_mime'] ?? '',
				)
			);
			return 'skipped';
		}
		if ( empty( $result['success'] ) ) {
			$this->stats->save( $attachment_id, array( 'status' => 'error', 'message' => $result['message'] ?? __( 'Erreur inconnue.', 'studio-kyne-mini-tools' ) ) );
			return 'error';
		}

		$current_file = get_attached_file( $attachment_id );
		if ( $result['final_path'] !== $current_file ) {
			$uploads = wp_get_upload_dir();
			if ( ! empty( $uploads['basedir'] ) ) {
				$relative_path = ltrim( str_replace( wp_normalize_path( $uploads['basedir'] ), '', wp_normalize_path( $result['final_path'] ) ), '/' );
				update_attached_file( $attachment_id, $relative_path );
			}
		}

		wp_update_post(
			array(
				'ID'             => $attachment_id,
				'post_mime_type' => $result['final_mime'],
			)
		);

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $result['final_path'] );
		if ( ! is_wp_error( $metadata ) && is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		$this->stats->save(
			$attachment_id,
			array(
				'original_size'   => $result['original_size'],
				'final_size'      => $result['final_size'],
				'bytes_saved'     => $result['bytes_saved'],
				'percent_saved'   => $result['percent_saved'],
				'original_mime'   => $result['original_mime'],
				'final_mime'      => $result['final_mime'],
				'original_width'  => $result['original_width'],
				'original_height' => $result['original_height'],
				'final_width'     => $result['final_width'],
				'final_height'    => $result['final_height'],
				'editor'          => $result['editor'],
				'status'          => 'success',
				'message'         => $result['message'],
			)
		);

		update_post_meta( $attachment_id, '_skmt_image_optimizer', wp_json_encode( $result ) );

		return 'success';
	}

	public function maybe_set_alt_text_from_filename( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id < 1 || ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['auto_fill_alt_text'] ) ) {
			return;
		}

		$existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( ! empty( $existing_alt ) ) {
			return;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( empty( $file_path ) ) {
			return;
		}

		$alt_text = $this->build_alt_text_from_filename( $file_path );
		if ( '' === $alt_text ) {
			$alt_text = sanitize_text_field( get_the_title( $attachment_id ) );
		}

		if ( '' !== $alt_text ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}
	}

	protected function build_alt_text_from_filename( $file_path ) {
		$filename = wp_basename( (string) $file_path );
		$name     = pathinfo( $filename, PATHINFO_FILENAME );
		$name     = rawurldecode( (string) $name );

		if ( function_exists( 'mb_check_encoding' ) && function_exists( 'mb_convert_encoding' ) && ! mb_check_encoding( $name, 'UTF-8' ) ) {
			$name = mb_convert_encoding( $name, 'UTF-8', 'auto' );
		}

		$name = str_replace( array( '-', '_', '.' ), ' ', $name );
		$name = preg_replace( '/\s+/u', ' ', trim( $name ) );
		$name = sanitize_text_field( wp_strip_all_tags( (string) $name ) );

		return trim( $name );
	}

	public function render_admin_page() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Vous n’avez pas l’autorisation d’accéder à cette page.', 'studio-kyne-mini-tools' ) );
		}

		$settings     = $this->get_settings();
		$recent_stats = $this->stats->get_recent( 50 );
		$aggregate    = $this->stats->get_aggregate();
		$processor    = new SKMT_Image_Processor( $settings, $this->plugin->logger() );
		$editor_name  = class_exists( 'Imagick' ) ? 'Imagick' : 'GD / WP_Image_Editor';
		$memory_limit = function_exists( 'ini_get' ) ? (string) ini_get( 'memory_limit' ) : '';
		$upload_limit = size_format( wp_max_upload_size(), 2 );
		$imagick_on   = class_exists( 'Imagick' );
		$gd_on        = extension_loaded( 'gd' );
		$upload_dir   = wp_get_upload_dir();
		$upload_path  = ! empty( $upload_dir['basedir'] ) ? wp_normalize_path( (string) $upload_dir['basedir'] ) : '';
		$disk_free    = ( '' !== $upload_path && is_dir( $upload_path ) ) ? @disk_free_space( $upload_path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$disk_free_ui = false !== $disk_free ? size_format( (int) $disk_free, 2 ) : __( 'Indisponible', 'studio-kyne-mini-tools' );
		$bulk_enabled = ! empty( $settings['enable_bulk_tools'] );
		?>
		<div class="wrap skmt-wrap">
			<div class="skmt-shell">
				<header class="skmt-page-head">
					<div>
						<h1><?php echo esc_html__( 'Image Optimizer', 'studio-kyne-mini-tools' ); ?></h1>
					</div>
					<span class="skmt-badge skmt-badge--<?php echo $this->is_enabled() ? 'success' : 'muted'; ?>"><?php echo esc_html( $this->is_enabled() ? __( 'Actif', 'studio-kyne-mini-tools' ) : __( 'Désactivé', 'studio-kyne-mini-tools' ) ); ?></span>
				</header>

				<div class="skmt-grid skmt-grid--4">
					<div class="skmt-card">
						<h2><?php echo esc_html__( 'Images optimisées', 'studio-kyne-mini-tools' ); ?></h2>
						<div class="skmt-stat"><?php echo esc_html( (string) ( $aggregate['total_images'] ?? 0 ) ); ?></div>
					</div>
					<div class="skmt-card">
						<h2><?php echo esc_html__( 'Poids initial', 'studio-kyne-mini-tools' ); ?></h2>
						<div class="skmt-stat"><?php echo esc_html( size_format( (int) ( $aggregate['original_bytes'] ?? 0 ), 2 ) ); ?></div>
					</div>
					<div class="skmt-card">
						<h2><?php echo esc_html__( 'Poids final', 'studio-kyne-mini-tools' ); ?></h2>
						<div class="skmt-stat"><?php echo esc_html( size_format( (int) ( $aggregate['final_bytes'] ?? 0 ), 2 ) ); ?></div>
					</div>
					<div class="skmt-card">
						<h2><?php echo esc_html__( 'Gain moyen', 'studio-kyne-mini-tools' ); ?></h2>
						<div class="skmt-stat"><?php echo esc_html( (string) ( $aggregate['percent_saved'] ?? 0 ) ); ?>%</div>
					</div>
				</div>

				<div class="skmt-grid skmt-grid--2">
					<div class="skmt-card">
						<div class="skmt-card-head">
							<h2><?php echo esc_html__( 'Réglages du module', 'studio-kyne-mini-tools' ); ?></h2>
						</div>
						<form action="options.php" method="post">
							<?php settings_fields( 'skmt_image_optimizer_group' ); ?>

							<div class="skmt-settings-group">
								<h3><?php echo esc_html__( 'Comportement', 'studio-kyne-mini-tools' ); ?></h3>
								<div class="skmt-form-grid">
									<div class="skmt-field"><label class="skmt-toggle"><input type="checkbox" name="skmt_image_optimizer_settings[enable_upload_optimizer]" value="1" <?php checked( ! empty( $settings['enable_upload_optimizer'] ) ); ?> /><span></span><strong><?php echo esc_html__( 'Optimiser à l’upload', 'studio-kyne-mini-tools' ); ?></strong></label></div>
									<div class="skmt-field"><label class="skmt-toggle"><input type="checkbox" name="skmt_image_optimizer_settings[enable_bulk_tools]" value="1" <?php checked( ! empty( $settings['enable_bulk_tools'] ) ); ?> /><span></span><strong><?php echo esc_html__( 'Activer la conversion de masse', 'studio-kyne-mini-tools' ); ?></strong></label></div>
									<div class="skmt-field"><label class="skmt-toggle"><input type="checkbox" name="skmt_image_optimizer_settings[keep_original]" value="1" <?php checked( ! empty( $settings['keep_original'] ) ); ?> /><span></span><strong><?php echo esc_html__( 'Conserver l’original', 'studio-kyne-mini-tools' ); ?></strong></label></div>
								</div>
							</div>

							<hr class="skmt-separator" />

							<div class="skmt-settings-group">
								<h3><?php echo esc_html__( 'Qualité et dimensions', 'studio-kyne-mini-tools' ); ?></h3>
								<div class="skmt-form-grid">
									<div class="skmt-field"><label for="skmt-image-target-format"><strong><?php echo esc_html__( 'Format cible', 'studio-kyne-mini-tools' ); ?></strong></label><select id="skmt-image-target-format" name="skmt_image_optimizer_settings[target_format]"><option value="auto" <?php selected( 'auto', $settings['target_format'] ); ?>><?php echo esc_html__( 'Auto', 'studio-kyne-mini-tools' ); ?></option><option value="avif" <?php selected( 'avif', $settings['target_format'] ); ?>>AVIF</option><option value="webp" <?php selected( 'webp', $settings['target_format'] ); ?>>WebP</option></select></div>
									<div class="skmt-field"><label for="skmt-image-quality-range"><strong><?php echo esc_html__( 'Qualité', 'studio-kyne-mini-tools' ); ?></strong></label><div class="skmt-range"><input id="skmt-image-quality-range" type="range" min="35" max="100" step="1" name="skmt_image_optimizer_settings[quality]" value="<?php echo esc_attr( (string) $settings['quality'] ); ?>" data-range-target="skmt-image-quality-input" /><input id="skmt-image-quality-input" class="skmt-input-number" type="number" min="35" max="100" value="<?php echo esc_attr( (string) $settings['quality'] ); ?>" /></div></div>
								</div>
								<div class="skmt-dimensions-row">
									<div class="skmt-field skmt-field--dimension"><label for="skmt-image-max-width"><strong><?php echo esc_html__( 'Largeur max', 'studio-kyne-mini-tools' ); ?></strong></label><input id="skmt-image-max-width" type="number" min="256" max="6000" name="skmt_image_optimizer_settings[max_width]" value="<?php echo esc_attr( (string) $settings['max_width'] ); ?>" /></div>
									<div class="skmt-field skmt-field--dimension"><label for="skmt-image-max-height"><strong><?php echo esc_html__( 'Hauteur max', 'studio-kyne-mini-tools' ); ?></strong></label><input id="skmt-image-max-height" type="number" min="256" max="6000" name="skmt_image_optimizer_settings[max_height]" value="<?php echo esc_attr( (string) $settings['max_height'] ); ?>" /></div>
								</div>
							</div>

							<hr class="skmt-separator" />

							<div class="skmt-settings-group">
								<h3><?php echo esc_html__( 'Métadonnées et accessibilité', 'studio-kyne-mini-tools' ); ?></h3>
								<div class="skmt-form-grid">
									<div class="skmt-field"><label class="skmt-toggle"><input type="checkbox" name="skmt_image_optimizer_settings[strip_exif_metadata]" value="1" <?php checked( ! empty( $settings['strip_exif_metadata'] ) ); ?> /><span></span><strong><?php echo esc_html__( 'Supprimer les métadonnées EXIF', 'studio-kyne-mini-tools' ); ?></strong></label><p class="description"><?php echo esc_html__( 'Activé par défaut pour réduire le poids des images et limiter les données techniques conservées.', 'studio-kyne-mini-tools' ); ?></p></div>
									<div class="skmt-field"><label class="skmt-toggle"><input type="checkbox" name="skmt_image_optimizer_settings[auto_fill_alt_text]" value="1" <?php checked( ! empty( $settings['auto_fill_alt_text'] ) ); ?> /><span></span><strong><?php echo esc_html__( 'Renseigner automatiquement le texte alternatif à l’upload', 'studio-kyne-mini-tools' ); ?></strong></label><p class="description"><?php echo esc_html__( 'Le texte alternatif est généré à partir du nom de fichier si le champ ALT est vide.', 'studio-kyne-mini-tools' ); ?></p></div>
								</div>
							</div>

							<?php submit_button( __( 'Enregistrer les réglages', 'studio-kyne-mini-tools' ) ); ?>
						</form>
					</div>

					<div class="skmt-card">
						<div class="skmt-card-head">
							<h2><?php echo esc_html__( 'Statut du serveur', 'studio-kyne-mini-tools' ); ?></h2>
						</div>
						<ul class="skmt-status-list">
							<li><span><?php echo esc_html__( 'Support WebP', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge <?php echo $processor->supports_webp() ? 'skmt-badge--success' : 'skmt-badge--error'; ?>"><?php echo esc_html( $processor->supports_webp() ? __( 'Oui', 'studio-kyne-mini-tools' ) : __( 'Non', 'studio-kyne-mini-tools' ) ); ?></span></li>
							<li><span><?php echo esc_html__( 'Support AVIF', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge <?php echo $processor->supports_avif() ? 'skmt-badge--success' : 'skmt-badge--warning'; ?>"><?php echo esc_html( $processor->supports_avif() ? __( 'Oui', 'studio-kyne-mini-tools' ) : __( 'Non', 'studio-kyne-mini-tools' ) ); ?></span></li>
							<li><span><?php echo esc_html__( 'Extension Imagick', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge <?php echo $imagick_on ? 'skmt-badge--success' : 'skmt-badge--warning'; ?>"><?php echo esc_html( $imagick_on ? __( 'Activée', 'studio-kyne-mini-tools' ) : __( 'Absente', 'studio-kyne-mini-tools' ) ); ?></span></li>
							<li><span><?php echo esc_html__( 'Extension GD', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge <?php echo $gd_on ? 'skmt-badge--success' : 'skmt-badge--warning'; ?>"><?php echo esc_html( $gd_on ? __( 'Activée', 'studio-kyne-mini-tools' ) : __( 'Absente', 'studio-kyne-mini-tools' ) ); ?></span></li>
							<li><span><?php echo esc_html__( 'Éditeur détecté', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted"><?php echo esc_html( $editor_name ); ?></span></li>
							<li><span><?php echo esc_html__( 'Version PHP', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted"><?php echo esc_html( PHP_VERSION ); ?></span></li>
							<li><span><?php echo esc_html__( 'Limite mémoire PHP', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted"><?php echo esc_html( '' !== $memory_limit ? $memory_limit : __( 'Indisponible', 'studio-kyne-mini-tools' ) ); ?></span></li>
							<li><span><?php echo esc_html__( 'Upload max WordPress', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted"><?php echo esc_html( $upload_limit ); ?></span></li>
							<li><span><?php echo esc_html__( 'Espace libre (uploads)', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted"><?php echo esc_html( $disk_free_ui ); ?></span></li>
							<li><span><?php echo esc_html__( 'Format final résolu', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--primary"><?php echo esc_html( strtoupper( str_replace( 'image/', '', $processor->resolve_target_mime() ?: 'none' ) ) ); ?></span></li>
						</ul>
						<?php if ( ! $processor->supports_avif() ) : ?>
							<p class="notice inline notice-warning"><span><?php echo esc_html__( 'AVIF n’est pas supporté côté serveur. Le module utilisera automatiquement WebP si possible.', 'studio-kyne-mini-tools' ); ?></span></p>
						<?php endif; ?>
					</div>
				</div>

				<div class="skmt-grid skmt-grid--2">
					<div class="skmt-card">
						<div class="skmt-card-head">
							<h2><?php echo esc_html__( 'Conversion de masse', 'studio-kyne-mini-tools' ); ?></h2>
						</div>
						<?php if ( ! $bulk_enabled ) : ?>
							<p class="description"><?php echo esc_html__( 'Activez la conversion de masse dans les réglages du module pour utiliser cet outil.', 'studio-kyne-mini-tools' ); ?></p>
						<?php else : ?>
							<div class="skmt-bulk-box" id="skmt-bulk-box" data-nonce="<?php echo esc_attr( wp_create_nonce( 'skmt_image_bulk' ) ); ?>" data-batch-size="10" data-poll-interval="4000">
								<p><?php echo esc_html__( 'Le traitement tourne en arrière-plan via des tâches planifiées. Vous pouvez quitter cette page, la conversion continue.', 'studio-kyne-mini-tools' ); ?></p>
								<div class="skmt-actions">
									<button type="button" class="button button-primary" id="skmt-bulk-start"><?php echo esc_html__( 'Lancer la conversion planifiée', 'studio-kyne-mini-tools' ); ?></button>
									<button type="button" class="button skmt-button-danger" id="skmt-bulk-stop" disabled><?php echo esc_html__( 'Arrêter la conversion', 'studio-kyne-mini-tools' ); ?></button>
								</div>
								<div class="skmt-progress"><div class="skmt-progress__bar" id="skmt-bulk-progress-bar"></div></div>
								<p id="skmt-bulk-status" class="description"><?php echo esc_html__( 'En attente.', 'studio-kyne-mini-tools' ); ?></p>
								<div id="skmt-bulk-log" class="skmt-bulk-log"></div>
							</div>
						<?php endif; ?>
					</div>

					<div class="skmt-card">
						<div class="skmt-card-head">
							<h2><?php echo esc_html__( 'Historique par image', 'studio-kyne-mini-tools' ); ?></h2>
						</div>
						<?php if ( empty( $recent_stats ) ) : ?>
							<p class="description"><?php echo esc_html__( 'Aucune image traitée pour le moment.', 'studio-kyne-mini-tools' ); ?></p>
						<?php else : ?>
							<div class="skmt-table-wrap skmt-table-wrap--history">
								<table class="widefat fixed striped skmt-table">
									<thead>
										<tr><th><?php echo esc_html__( 'Image', 'studio-kyne-mini-tools' ); ?></th><th><?php echo esc_html__( 'Statut', 'studio-kyne-mini-tools' ); ?></th><th><?php echo esc_html__( 'Avant', 'studio-kyne-mini-tools' ); ?></th><th><?php echo esc_html__( 'Après', 'studio-kyne-mini-tools' ); ?></th><th><?php echo esc_html__( 'Gain', 'studio-kyne-mini-tools' ); ?></th><th><?php echo esc_html__( 'Message', 'studio-kyne-mini-tools' ); ?></th></tr>
									</thead>
									<tbody>
										<?php foreach ( $recent_stats as $row ) : ?>
											<?php
											$attachment_id = (int) $row['attachment_id'];
											$status        = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'warning';
											$status_class  = 'success' === $status ? 'success' : ( 'error' === $status ? 'error' : 'warning' );
											$title         = get_the_title( $attachment_id );
											$title         = $title ? $title : '#' . $attachment_id;
											$edit_link     = get_edit_post_link( $attachment_id, '' );
											?>
											<tr>
												<td><?php if ( $edit_link ) : ?><a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $title ); ?></a><?php else : ?><?php echo esc_html( $title ); ?><?php endif; ?><div class="description"><?php echo esc_html( $row['processed_at'] ); ?></div></td>
												<td><span class="skmt-badge skmt-badge--<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( strtoupper( $status ) ); ?></span></td>
												<td><?php echo esc_html( size_format( (int) ( $row['original_size'] ?? 0 ), 2 ) ); ?></td>
												<td><?php echo esc_html( size_format( (int) ( $row['final_size'] ?? 0 ), 2 ) ); ?></td>
												<td><?php echo esc_html( size_format( (int) ( $row['bytes_saved'] ?? 0 ), 2 ) ); ?><div class="description"><?php echo esc_html( (string) ( $row['percent_saved'] ?? 0 ) ); ?>%</div></td>
												<td><?php echo esc_html( $row['message'] ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
