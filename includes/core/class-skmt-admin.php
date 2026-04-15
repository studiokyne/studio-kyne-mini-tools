<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Admin {
	protected $plugin;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_skmt_toggle_module', array( $this, 'handle_module_toggle' ) );
		add_action( 'admin_post_skmt_export_config', array( $this, 'handle_export_config' ) );
		add_action( 'admin_post_skmt_import_config', array( $this, 'handle_import_config' ) );
		add_filter( 'admin_body_class', array( $this, 'filter_admin_body_class' ) );
	}

	public function register_menus() {
		$capability = SKMT_Capabilities::admin_capability();

		add_menu_page(
			__( 'Studio Kyne Mini Tools', 'studio-kyne-mini-tools' ),
			__( 'SKMT', 'studio-kyne-mini-tools' ),
			$capability,
			'skmt',
			array( $this, 'render_dashboard' ),
			$this->get_menu_icon_data_uri(),
			58
		);

		add_submenu_page( 'skmt', __( 'Vue d’ensemble', 'studio-kyne-mini-tools' ), __( 'Vue d’ensemble', 'studio-kyne-mini-tools' ), $capability, 'skmt', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'skmt', __( 'Modules', 'studio-kyne-mini-tools' ), __( 'Modules', 'studio-kyne-mini-tools' ), $capability, 'skmt-modules', array( $this, 'render_modules_page' ) );
		add_submenu_page( 'skmt', __( 'Réglages', 'studio-kyne-mini-tools' ), __( 'Réglages', 'studio-kyne-mini-tools' ), $capability, 'skmt-settings', array( $this, 'render_settings_page' ) );
		add_submenu_page( 'skmt', __( 'Modules actifs', 'studio-kyne-mini-tools' ), '────────', $capability, 'skmt-separator', array( $this, 'render_separator_page' ) );

		foreach ( $this->plugin->modules()->get_modules() as $module ) {
			if ( $this->plugin->modules()->is_module_active( $module->get_id() ) ) {
				$module->register_admin_pages( 'skmt' );
			}
		}
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'skmt' ) ) {
			return;
		}

		wp_enqueue_style( 'skmt-admin', SKMT_PLUGIN_URL . 'assets/admin/admin.css', array(), SKMT_VERSION );
		wp_enqueue_script( 'skmt-admin', SKMT_PLUGIN_URL . 'assets/admin/admin.js', array(), SKMT_VERSION, true );
		wp_localize_script(
			'skmt-admin',
			'skmtAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'bulkRunning'     => __( 'Conversion en cours…', 'studio-kyne-mini-tools' ),
					'bulkCompleted'   => __( 'Conversion terminée.', 'studio-kyne-mini-tools' ),
					'bulkError'       => __( 'Une erreur est survenue pendant la conversion.', 'studio-kyne-mini-tools' ),
					'bulkBatchLog'    => __( 'Lot: %1$d optimisées, %2$d ignorées, %3$d erreurs.', 'studio-kyne-mini-tools' ),
					'bulkStart'       => __( 'Démarrage de la conversion de masse…', 'studio-kyne-mini-tools' ),
					'bulkPaused'      => __( 'Conversion mise en pause.', 'studio-kyne-mini-tools' ),
					'genericError'    => __( 'Erreur', 'studio-kyne-mini-tools' ),
					'importingConfig' => __( 'Import en cours…', 'studio-kyne-mini-tools' ),
					'selectedFile'    => __( 'Fichier sélectionné :', 'studio-kyne-mini-tools' ),
				),
			)
		);
	}

	public function filter_admin_body_class( $classes ) {
		if ( ! $this->is_skmt_page_request() ) {
			return $classes;
		}

		$classes .= ' skmt-admin-page';
		return trim( $classes );
	}

	public function render_dashboard() {
		$this->assert_capability();
		$modules        = $this->plugin->modules()->get_modules();
		$active_modules = $this->plugin->modules()->get_active_module_ids();
		?>
		<div class="wrap skmt-wrap">
			<div class="skmt-shell">
				<header class="skmt-page-head">
					<div>
						<h1><?php echo esc_html__( 'Vue d’ensemble', 'studio-kyne-mini-tools' ); ?></h1>
					</div>
				</header>
				<div class="skmt-grid skmt-grid--2">
					<div class="skmt-card">
						<h2 class="skmt-title-inline"><?php echo $this->render_icon( 'boxes' ); ?><?php echo esc_html__( 'Modules actifs', 'studio-kyne-mini-tools' ); ?></h2>
						<div class="skmt-stat"><?php echo esc_html( count( $active_modules ) ); ?> / <?php echo esc_html( count( $modules ) ); ?></div>
						<p><?php echo esc_html__( 'Activez uniquement les briques dont vous avez besoin.', 'studio-kyne-mini-tools' ); ?></p>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=skmt-modules' ) ); ?>"><?php echo esc_html__( 'Gérer les modules', 'studio-kyne-mini-tools' ); ?></a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_modules_page() {
		$this->assert_capability();
		$modules = $this->plugin->modules()->get_modules();
		?>
		<div class="wrap skmt-wrap">
			<div class="skmt-shell">
				<header class="skmt-page-head">
					<div>
						<h1><?php echo esc_html__( 'Modules', 'studio-kyne-mini-tools' ); ?></h1>
					</div>
				</header>
				<?php if ( empty( $modules ) ) : ?>
					<div class="skmt-card"><p><?php echo esc_html__( 'Aucun module disponible.', 'studio-kyne-mini-tools' ); ?></p></div>
				<?php else : ?>
					<div class="skmt-grid skmt-grid--2">
						<?php foreach ( $modules as $module ) : ?>
							<?php
							$is_active    = $this->plugin->modules()->is_module_active( $module->get_id() );
							$toggle_url   = wp_nonce_url( admin_url( 'admin-post.php?action=skmt_toggle_module&module=' . rawurlencode( $module->get_id() ) . '&state=' . ( $is_active ? 'disable' : 'enable' ) ), 'skmt_toggle_module_' . $module->get_id() );
							$settings_url = admin_url( 'admin.php?page=skmt-' . $module->get_id() );
							?>
							<div class="skmt-card skmt-module-card">
								<div class="skmt-module-card__head">
									<div class="skmt-module-card__title">
										<span class="skmt-icon-box"><?php echo $this->render_icon( $this->map_module_icon( $module->get_icon() ) ); ?></span>
										<div>
											<h2><?php echo esc_html( $module->get_name() ); ?></h2>
											<p><?php echo esc_html( $module->get_description() ); ?></p>
										</div>
									</div>
									<span class="skmt-badge <?php echo $is_active ? 'skmt-badge--success' : 'skmt-badge--muted'; ?>"><?php echo esc_html( $is_active ? __( 'Actif', 'studio-kyne-mini-tools' ) : __( 'Inactif', 'studio-kyne-mini-tools' ) ); ?></span>
								</div>
								<div class="skmt-actions">
									<a class="button <?php echo $is_active ? 'skmt-button-danger' : 'button-primary'; ?>" href="<?php echo esc_url( $toggle_url ); ?>"><?php echo esc_html( $is_active ? __( 'Désactiver', 'studio-kyne-mini-tools' ) : __( 'Activer', 'studio-kyne-mini-tools' ) ); ?></a>
									<?php if ( $module->is_configurable() && $is_active ) : ?>
										<a class="button" href="<?php echo esc_url( $settings_url ); ?>"><?php echo esc_html__( 'Configurer', 'studio-kyne-mini-tools' ); ?></a>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function render_settings_page() {
		$this->assert_capability();
		$settings      = $this->plugin->settings()->get_all();
		$update_status = $this->get_update_status();
		$languages     = $this->get_language_options();
		?>
		<div class="wrap skmt-wrap">
			<div class="skmt-shell">
				<header class="skmt-page-head">
					<div>
						<h1><?php echo esc_html__( 'Réglages', 'studio-kyne-mini-tools' ); ?></h1>
					</div>
				</header>

				<?php $this->render_settings_notice(); ?>

				<div class="skmt-grid skmt-grid--2">
					<div class="skmt-card">
						<div class="skmt-card-head">
							<h2 class="skmt-title-inline"><?php echo $this->render_icon( 'settings' ); ?><?php echo esc_html__( 'Comportement global', 'studio-kyne-mini-tools' ); ?></h2>
						</div>
						<form action="options.php" method="post">
							<?php settings_fields( 'skmt_settings_group' ); ?>
							<div class="skmt-field">
								<label class="skmt-toggle"><input type="checkbox" name="skmt_settings[cleanup_on_uninstall]" value="1" <?php checked( ! empty( $settings['cleanup_on_uninstall'] ) ); ?> /><span></span><strong><?php echo esc_html__( 'Supprimer les données à la désinstallation', 'studio-kyne-mini-tools' ); ?></strong></label>
								<p class="description"><?php echo esc_html__( 'Activé par défaut: supprime tables, options et métadonnées SKMT à la suppression complète du plugin.', 'studio-kyne-mini-tools' ); ?></p>
							</div>
							<div class="skmt-field">
								<label for="skmt-plugin-locale"><strong><?php echo esc_html__( 'Langue du plugin', 'studio-kyne-mini-tools' ); ?></strong></label>
								<select id="skmt-plugin-locale" name="skmt_settings[plugin_locale]">
									<?php foreach ( $languages as $locale_code => $locale_label ) : ?>
										<option value="<?php echo esc_attr( $locale_code ); ?>" <?php selected( (string) ( $settings['plugin_locale'] ?? '' ), (string) $locale_code ); ?>><?php echo esc_html( $locale_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php echo esc_html__( 'Choisissez une langue spécifique pour SKMT ou laissez la langue du site.', 'studio-kyne-mini-tools' ); ?></p>
							</div>
							<?php submit_button( __( 'Enregistrer', 'studio-kyne-mini-tools' ) ); ?>
						</form>
					</div>

					<div class="skmt-card">
						<div class="skmt-card-head">
							<h2 class="skmt-title-inline"><?php echo $this->render_icon( 'activity' ); ?><?php echo esc_html__( 'Version et mises à jour', 'studio-kyne-mini-tools' ); ?></h2>
						</div>
						<ul class="skmt-status-list">
							<li><span><?php echo esc_html__( 'Repository GitHub', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted"><?php echo esc_html( $update_status['repository'] ); ?></span></li>
							<li><span><?php echo esc_html__( 'Version installée', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted">v<?php echo esc_html( $update_status['current_version'] ); ?></span></li>
							<li><span><?php echo esc_html__( 'Statut', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--<?php echo esc_attr( $update_status['badge'] ); ?>"><?php echo esc_html( $update_status['label'] ); ?></span></li>
							<?php if ( ! empty( $update_status['target_version'] ) ) : ?>
								<li><span><?php echo esc_html__( 'Version disponible', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--primary">v<?php echo esc_html( $update_status['target_version'] ); ?></span></li>
							<?php endif; ?>
						</ul>
						<div class="skmt-actions">
							<a class="button" href="<?php echo esc_url( self_admin_url( 'update-core.php' ) ); ?>"><?php echo esc_html__( 'Vérifier les mises à jour', 'studio-kyne-mini-tools' ); ?></a>
						</div>
					</div>
				</div>

				<div class="skmt-card">
					<div class="skmt-card-head">
						<h2 class="skmt-title-inline"><?php echo $this->render_icon( 'database' ); ?><?php echo esc_html__( 'Import / Export', 'studio-kyne-mini-tools' ); ?></h2>
					</div>
					<p class="description"><?php echo esc_html__( 'Déplacez votre configuration entre environnements en un clic.', 'studio-kyne-mini-tools' ); ?></p>
					<div class="skmt-import-export-layout">
						<form class="skmt-import-export-panel" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="skmt_export_config" />
							<?php wp_nonce_field( 'skmt_export_config' ); ?>
							<p class="description"><?php echo esc_html__( 'Télécharge un fichier JSON avec tous les réglages SKMT.', 'studio-kyne-mini-tools' ); ?></p>
							<button type="submit" class="button button-primary"><?php echo esc_html__( 'Exporter la configuration', 'studio-kyne-mini-tools' ); ?></button>
						</form>
						<form class="skmt-import-export-panel" id="skmt-import-config-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<input type="hidden" name="action" value="skmt_import_config" />
							<?php wp_nonce_field( 'skmt_import_config' ); ?>
							<input id="skmt-config-file" class="skmt-hidden-file-input" type="file" name="skmt_config_file" accept="application/json,.json" required />
							<p class="description"><?php echo esc_html__( 'Importe un fichier JSON SKMT et applique automatiquement les réglages compatibles.', 'studio-kyne-mini-tools' ); ?></p>
							<div class="skmt-actions"><button type="button" class="button" id="skmt-import-config-trigger"><?php echo esc_html__( 'Importer la configuration', 'studio-kyne-mini-tools' ); ?></button></div>
							<p id="skmt-import-config-name" class="description"></p>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	protected function get_language_options() {
		$options = array(
			'' => __( 'Langue du site (par défaut)', 'studio-kyne-mini-tools' ),
		);

		$locales = array();
		if ( function_exists( 'get_available_languages' ) ) {
			$locales = get_available_languages();
		}

		if ( function_exists( 'determine_locale' ) ) {
			$locales[] = determine_locale();
		}

		$locales[] = 'fr_FR';
		$locales[] = 'en_US';

		$mo_files = glob( SKMT_PLUGIN_DIR . 'languages/studio-kyne-mini-tools-*.mo' );
		if ( is_array( $mo_files ) ) {
			foreach ( $mo_files as $file_path ) {
				$file_name = wp_basename( $file_path );
				if ( preg_match( '/studio-kyne-mini-tools-([A-Za-z0-9_\-]+)\.mo$/', $file_name, $matches ) ) {
					$locales[] = $matches[1];
				}
			}
		}

		$locales = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $locales ) ) ) );
		sort( $locales, SORT_STRING );

		foreach ( $locales as $locale_code ) {
			$options[ $locale_code ] = $locale_code;
		}

		return $options;
	}

	public function render_separator_page() {
		wp_safe_redirect( admin_url( 'admin.php?page=skmt-settings' ) );
		exit;
	}

	public function handle_module_toggle() {
		$this->assert_capability();
		$module = isset( $_GET['module'] ) ? sanitize_key( wp_unslash( $_GET['module'] ) ) : '';
		$state  = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : '';
		check_admin_referer( 'skmt_toggle_module_' . $module );

		if ( 'enable' === $state ) {
			$this->plugin->modules()->activate_module( $module );
		} elseif ( 'disable' === $state ) {
			$this->plugin->modules()->deactivate_module( $module );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=skmt-modules' ) );
		exit;
	}

	public function handle_export_config() {
		$this->assert_capability();
		check_admin_referer( 'skmt_export_config' );

		$payload = array(
			'plugin'      => 'studio-kyne-mini-tools',
			'version'     => SKMT_VERSION,
			'exported_at' => gmdate( 'c' ),
			'options'     => $this->collect_export_options(),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename=skmt-config-' . gmdate( 'Ymd-His' ) . '.json' );

		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public function handle_import_config() {
		$this->assert_capability();
		check_admin_referer( 'skmt_import_config' );

		$file = isset( $_FILES['skmt_config_file'] ) ? $_FILES['skmt_config_file'] : null;
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->redirect_settings_notice( 'import-invalid' );
		}

		$raw = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			$this->redirect_settings_notice( 'import-invalid' );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded['options'] ) || ! is_array( $decoded['options'] ) ) {
			$this->redirect_settings_notice( 'import-invalid' );
		}

		$applied = $this->apply_import_options( $decoded['options'] );
		$this->redirect_settings_notice( $applied ? 'import-success' : 'import-empty' );
	}

	protected function collect_export_options() {
		$options = array(
			'skmt_settings'       => $this->plugin->settings()->get_all(),
			'skmt_active_modules' => $this->plugin->modules()->get_active_module_ids(),
		);

		foreach ( $this->plugin->modules()->get_modules() as $module ) {
			$option_name            = $this->get_module_option_name( $module->get_id() );
			$options[ $option_name ] = get_option( $option_name, array() );
		}

		return $options;
	}

	protected function apply_import_options( $options ) {
		$changed = false;

		if ( isset( $options['skmt_settings'] ) && is_array( $options['skmt_settings'] ) ) {
			update_option( 'skmt_settings', $this->plugin->settings()->sanitize( $options['skmt_settings'] ) );
			$changed = true;
		}

		if ( isset( $options['skmt_active_modules'] ) && is_array( $options['skmt_active_modules'] ) ) {
			$available_ids = array_keys( $this->plugin->modules()->get_modules() );
			$active_ids    = array_values(
				array_filter(
					array_map( 'sanitize_key', $options['skmt_active_modules'] ),
					static function ( $module_id ) use ( $available_ids ) {
						return in_array( $module_id, $available_ids, true );
					}
				)
			);
			update_option( 'skmt_active_modules', $active_ids );
			$changed = true;
		}

		foreach ( $this->plugin->modules()->get_modules() as $module ) {
			$option_name = $this->get_module_option_name( $module->get_id() );
			if ( ! isset( $options[ $option_name ] ) || ! is_array( $options[ $option_name ] ) ) {
				continue;
			}

			$module_settings = $options[ $option_name ];
			if ( is_callable( array( $module, 'sanitize_settings' ) ) ) {
				$module_settings = $module->sanitize_settings( $module_settings );
			}

			update_option( $option_name, $module_settings );
			$changed = true;
		}

		return $changed;
	}

	protected function render_settings_notice() {
		$notice = isset( $_GET['skmt_notice'] ) ? sanitize_key( wp_unslash( $_GET['skmt_notice'] ) ) : '';

		if ( '' === $notice ) {
			return;
		}

		if ( 'import-success' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Configuration importée avec succès.', 'studio-kyne-mini-tools' ) . '</p></div>';
			return;
		}

		if ( 'import-empty' === $notice ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Aucune configuration applicable trouvée dans le fichier.', 'studio-kyne-mini-tools' ) . '</p></div>';
			return;
		}

		if ( 'import-invalid' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Le fichier importé est invalide.', 'studio-kyne-mini-tools' ) . '</p></div>';
		}
	}

	protected function redirect_settings_notice( $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'skmt-settings',
					'skmt_notice' => sanitize_key( $notice ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	protected function get_update_status() {
		$status = array(
			'current_version'      => SKMT_VERSION,
			'target_version'       => '',
			'has_update'           => false,
			'repository_configured' => true,
			'repository'           => defined( 'SKMT_GITHUB_REPO' ) ? (string) SKMT_GITHUB_REPO : '',
			'label'                => __( 'À jour', 'studio-kyne-mini-tools' ),
			'badge'                => 'success',
		);

		$repository = trim( (string) $status['repository'] );
		$status['repository'] = '' !== $repository ? $repository : 'studiokyne/studio-kyne-mini-tools';
		if ( '' === $repository ) {
			$status['label'] = __( 'Repository source indisponible', 'studio-kyne-mini-tools' );
			$status['badge'] = 'warning';
			return $status;
		}
		$updates = get_site_transient( 'update_plugins' );

		if ( is_object( $updates ) && ! empty( $updates->response[ SKMT_PLUGIN_BASENAME ] ) ) {
			$update = $updates->response[ SKMT_PLUGIN_BASENAME ];
			$status['has_update']     = true;
			$status['target_version'] = ! empty( $update->new_version ) ? (string) $update->new_version : '';
			$status['label']          = __( 'Mise à jour disponible', 'studio-kyne-mini-tools' );
			$status['badge']          = 'warning';
			return $status;
		}

		return $status;
	}

	protected function get_module_option_name( $module_id ) {
		return 'skmt_' . str_replace( '-', '_', sanitize_key( $module_id ) ) . '_settings';
	}

	protected function map_module_icon( $icon ) {
		$icon = sanitize_key( str_replace( 'dashicons-', '', (string) $icon ) );
		if ( in_array( $icon, array( 'format-image', 'image' ), true ) ) {
			return 'image';
		}
		if ( in_array( $icon, array( 'admin-generic', 'settings' ), true ) ) {
			return 'settings';
		}
		return 'box';
	}

	protected function render_icon( $icon_name ) {
		$svg = $this->get_icon_svg( $icon_name );
		return '<span class="skmt-icon" aria-hidden="true">' . $svg . '</span>';
	}

	protected function get_icon_svg( $icon_name ) {
		$icons = array(
			'image'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="m21 15-5-5L5 21"></path></svg>',
			'boxes'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><path d="M3 10h18"></path><path d="M8 14h2"></path></svg>',
			'settings' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"></path><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33h.01a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51h.01a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
			'activity' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9-6-18-3 9H2"></path></svg>',
			'database' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"></path><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"></path></svg>',
			'box'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>',
		);

		return isset( $icons[ $icon_name ] ) ? $icons[ $icon_name ] : $icons['box'];
	}

	protected function get_menu_icon_data_uri() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="4" fill="#a3a3a3"/><path d="M7 8h10l-5 8H7l5-8Z" fill="#1f2937"/><circle cx="9" cy="19" r="1.5" fill="#a3a3a3"/><circle cx="15" cy="19" r="1.5" fill="#a3a3a3"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	protected function assert_capability() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Vous n’avez pas l’autorisation d’accéder à cette page.', 'studio-kyne-mini-tools' ) );
		}
	}

	protected function is_skmt_page_request() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' === $page ) {
			return false;
		}
		return 0 === strpos( $page, 'skmt' );
	}
}
