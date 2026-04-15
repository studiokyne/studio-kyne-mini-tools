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
		wp_enqueue_script( 'skmt-lucide', 'https://unpkg.com/lucide@latest', array(), null, true );
		wp_enqueue_script( 'skmt-admin', SKMT_PLUGIN_URL . 'assets/admin/admin.js', array( 'skmt-lucide' ), SKMT_VERSION, true );
		wp_localize_script(
			'skmt-admin',
			'skmtAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'bulkRunning'     => __( 'Conversion en cours…', 'studio-kyne-mini-tools' ),
					'bulkCompleted'   => __( 'Conversion terminée.', 'studio-kyne-mini-tools' ),
					'bulkError'       => __( 'Une erreur est survenue pendant la conversion.', 'studio-kyne-mini-tools' ),
					'bulkStarting'    => __( 'Démarrage de la conversion…', 'studio-kyne-mini-tools' ),
					'bulkStopping'    => __( 'Arrêt de la conversion…', 'studio-kyne-mini-tools' ),
					'bulkStopped'     => __( 'Conversion arrêtée.', 'studio-kyne-mini-tools' ),
					'bulkIdle'        => __( 'En attente.', 'studio-kyne-mini-tools' ),
					'bulkStatusError' => __( 'Impossible de récupérer le statut de conversion.', 'studio-kyne-mini-tools' ),
					'bulkStart'       => __( 'Démarrage de la conversion de masse…', 'studio-kyne-mini-tools' ),
					'bulkPaused'      => __( 'Conversion mise en pause.', 'studio-kyne-mini-tools' ),
					'genericError'    => __( 'Erreur', 'studio-kyne-mini-tools' ),
					'importingConfig' => __( 'Import en cours…', 'studio-kyne-mini-tools' ),
					'selectedFile'    => __( 'Fichier sélectionné :', 'studio-kyne-mini-tools' ),
					'closeNotification' => __( 'Fermer la notification', 'studio-kyne-mini-tools' ),
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
		$notifications = SKMT_Notifications::get_recent( 12 );
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
							<a class="button" href="<?php echo esc_url( add_query_arg( 'force-check', '1', self_admin_url( 'update-core.php' ) ) ); ?>"><?php echo esc_html__( 'Vérifier les mises à jour', 'studio-kyne-mini-tools' ); ?></a>
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

				<div class="skmt-card">
					<div class="skmt-card-head">
						<h2 class="skmt-title-inline"><?php echo $this->render_icon( 'bell' ); ?><?php echo esc_html__( 'Centre de notifications', 'studio-kyne-mini-tools' ); ?></h2>
					</div>
					<p class="description"><?php echo esc_html__( 'Retrouvez ici les derniers événements importants: imports, modules et conversions planifiées.', 'studio-kyne-mini-tools' ); ?></p>
					<?php if ( empty( $notifications ) ) : ?>
						<p class="description"><?php echo esc_html__( 'Aucun événement récent.', 'studio-kyne-mini-tools' ); ?></p>
					<?php else : ?>
						<ul class="skmt-notification-list">
							<?php foreach ( $notifications as $entry ) : ?>
								<?php
								$level   = isset( $entry['level'] ) ? sanitize_key( $entry['level'] ) : 'info';
								$message = isset( $entry['message'] ) ? sanitize_text_field( (string) $entry['message'] ) : '';
								$created = isset( $entry['created_at'] ) ? sanitize_text_field( (string) $entry['created_at'] ) : '';
								?>
								<li>
									<div class="skmt-notification-list__meta">
										<span class="skmt-badge skmt-badge--<?php echo esc_attr( $this->map_notification_badge( $level ) ); ?>"><?php echo esc_html( $this->get_notification_level_label( $level ) ); ?></span>
										<span class="description"><?php echo esc_html( $this->format_notification_time( $created ) ); ?></span>
									</div>
									<p><?php echo esc_html( $message ); ?></p>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
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

		$module_instance = $this->plugin->modules()->get_modules()[ $module ] ?? null;
		$module_name     = $module_instance ? $module_instance->get_name() : $module;

		if ( 'enable' === $state ) {
			$this->plugin->modules()->activate_module( $module );
			SKMT_Notifications::add(
				'success',
				sprintf(
					/* translators: %s: module name. */
					__( 'Module activé: %s', 'studio-kyne-mini-tools' ),
					$module_name
				)
			);
		} elseif ( 'disable' === $state ) {
			$this->plugin->modules()->deactivate_module( $module );
			SKMT_Notifications::add(
				'warning',
				sprintf(
					/* translators: %s: module name. */
					__( 'Module désactivé: %s', 'studio-kyne-mini-tools' ),
					$module_name
				)
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=skmt-modules' ) );
		exit;
	}

	public function handle_export_config() {
		$this->assert_capability();
		check_admin_referer( 'skmt_export_config' );

		SKMT_Notifications::add( 'info', __( 'Configuration SKMT exportée.', 'studio-kyne-mini-tools' ) );

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
			SKMT_Notifications::add( 'error', __( 'Import impossible: fichier manquant ou invalide.', 'studio-kyne-mini-tools' ) );
			$this->redirect_settings_notice( 'import-invalid' );
		}

		$raw = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			SKMT_Notifications::add( 'error', __( 'Import impossible: lecture du fichier échouée.', 'studio-kyne-mini-tools' ) );
			$this->redirect_settings_notice( 'import-invalid' );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded['options'] ) || ! is_array( $decoded['options'] ) ) {
			SKMT_Notifications::add( 'error', __( 'Import impossible: structure JSON invalide.', 'studio-kyne-mini-tools' ) );
			$this->redirect_settings_notice( 'import-invalid' );
		}

		$applied = $this->apply_import_options( $decoded['options'] );
		if ( $applied ) {
			SKMT_Notifications::add( 'success', __( 'Configuration SKMT importée avec succès.', 'studio-kyne-mini-tools' ) );
		} else {
			SKMT_Notifications::add( 'warning', __( 'Import effectué, aucune option applicable trouvée.', 'studio-kyne-mini-tools' ) );
		}
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

		$map = array(
			'import-success' => array(
				'type'    => 'success',
				'message' => __( 'Configuration importée avec succès.', 'studio-kyne-mini-tools' ),
			),
			'import-empty'   => array(
				'type'    => 'warning',
				'message' => __( 'Aucune configuration applicable trouvée dans le fichier.', 'studio-kyne-mini-tools' ),
			),
			'import-invalid' => array(
				'type'    => 'error',
				'message' => __( 'Le fichier importé est invalide.', 'studio-kyne-mini-tools' ),
			),
		);

		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}

		$payload = $map[ $notice ];
		$role    = 'error' === $payload['type'] ? 'alert' : 'status';

		echo '<div class="skmt-toast-stack" data-skmt-toast-stack>';
		echo '<div class="skmt-toast skmt-toast--' . esc_attr( $payload['type'] ) . '" role="' . esc_attr( $role ) . '" aria-live="polite" data-skmt-toast>';
		echo '<div class="skmt-toast__message">' . esc_html( $payload['message'] ) . '</div>';
		echo '<button type="button" class="skmt-toast__close" aria-label="' . esc_attr__( 'Fermer la notification', 'studio-kyne-mini-tools' ) . '" data-skmt-toast-close>&times;</button>';
		echo '</div>';
		echo '</div>';
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

	protected function map_notification_badge( $level ) {
		$level = sanitize_key( (string) $level );
		if ( in_array( $level, array( 'success', 'warning', 'error' ), true ) ) {
			return $level;
		}
		return 'info';
	}

	protected function get_notification_level_label( $level ) {
		$level = sanitize_key( (string) $level );

		$labels = array(
			'success' => __( 'Succès', 'studio-kyne-mini-tools' ),
			'warning' => __( 'Alerte', 'studio-kyne-mini-tools' ),
			'error'   => __( 'Erreur', 'studio-kyne-mini-tools' ),
			'info'    => __( 'Info', 'studio-kyne-mini-tools' ),
		);

		return $labels[ $level ] ?? $labels['info'];
	}

	protected function format_notification_time( $created_at ) {
		$timestamp = strtotime( (string) $created_at );
		if ( false === $timestamp ) {
			return __( 'Date inconnue', 'studio-kyne-mini-tools' );
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
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
		$icon_name = str_replace( '_', '-', sanitize_key( (string) $icon_name ) );
		if ( '' === $icon_name ) {
			$icon_name = 'circle';
		}

		return '<span class="skmt-icon" aria-hidden="true"><i class="skmt-lucide" data-lucide="' . esc_attr( $icon_name ) . '"></i></span>';
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
