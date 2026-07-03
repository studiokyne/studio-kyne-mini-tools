<?php
namespace StudioKyne\MiniTools\Admin;

use StudioKyne\MiniTools\Core\Modules;
use StudioKyne\MiniTools\Core\Settings;

/**
 * Gère l'interface d'administration du plugin.
 */
class Admin {

	/**
	 * Slug de la page admin.
	 */
	private string $slug = 'studio-kyne-mini-tools';

	/**
	 * Modules manager.
	 */
	private Modules $modules;

	/**
	 * Settings manager.
	 */
	private Settings $settings;

	/**
	 * HTML des notices WP capturées via output buffering.
	 */
	private string $captured_wp_notices = '';

	/**
	 * Données du toast SKMT à afficher (message + type).
	 */
	private ?array $skmt_toast = null;

	/**
	 * Constructeur.
	 */
	public function __construct( Modules $modules, Settings $settings ) {
		$this->modules  = $modules;
		$this->settings = $settings;

		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_filter( 'parent_file', [ $this, 'filter_parent_file' ] );
		add_filter( 'submenu_file', [ $this, 'filter_submenu_file' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_skmt_save_settings', [ $this, 'handle_save_settings' ] );
		add_action( 'admin_post_skmt_toggle_module', [ $this, 'handle_toggle_module' ] );
		add_action( 'wp_ajax_skmt_ajax_toggle_module', [ $this, 'handle_ajax_toggle_module' ] );
		add_action( 'admin_post_skmt_update_modules', [ $this, 'handle_update_modules' ] );
		add_action( 'admin_post_skmt_check_updates', [ $this, 'handle_check_updates' ] );
		add_action( 'admin_post_skmt_reset_settings', [ $this, 'handle_reset_settings' ] );
		add_action( 'admin_post_skmt_export_settings', [ $this, 'handle_export_settings' ] );
		add_action( 'admin_post_skmt_import_settings', [ $this, 'handle_import_settings' ] );
		add_action( 'admin_head', [ $this, 'output_menu_separator_css' ] );
		add_action( 'admin_footer', [ $this, 'render_modal' ] );
		add_filter( 'admin_footer_text', [ $this, 'filter_admin_footer_text' ] );
		add_filter( 'update_footer', [ $this, 'filter_update_footer' ], 11 );
		add_action( 'admin_notices',         [ $this, 'capture_wp_notices_start' ], 0 );
		add_action( 'admin_notices',         [ $this, 'capture_wp_notices_end' ],   PHP_INT_MAX );
		add_action( 'admin_bar_menu',        [ $this, 'register_notification_center' ], 999 );
		add_action( 'admin_footer',          [ $this, 'render_notification_drawer' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_global_notification_assets' ] );
		add_action( 'wp_ajax_skmt_dismiss_notice', [ $this, 'handle_dismiss_notice' ] );
	}

	/* ================================================================
	 * MENU
	 * ================================================================ */

	/**
	 * Ajoute la page admin principale.
	 */
	public function add_menu_page(): void {
		add_menu_page(
			__( 'Studio Kyne Mini Tools', 'studio-kyne-mini-tools' ),
			__( 'SKMT', 'studio-kyne-mini-tools' ),
			'manage_options',
			$this->slug,
			[ $this, 'render_page' ],
			plugins_url( 'assets/admin/images/menu-icon.svg', SKMT_PLUGIN_FILE ),
			99
		);

		$this->add_submenus();
	}

	/**
	 * Ajoute les sous-menus dynamiques.
	 */
	private function add_submenus(): void {
		remove_submenu_page( $this->slug, $this->slug );

		add_submenu_page( $this->slug, __( 'Vue d\'ensemble', 'studio-kyne-mini-tools' ), __( 'Vue d\'ensemble', 'studio-kyne-mini-tools' ), 'manage_options', $this->slug . '&tab=dashboard', [ $this, 'render_page' ] );
		add_submenu_page( $this->slug, __( 'Modules', 'studio-kyne-mini-tools' ), __( 'Modules', 'studio-kyne-mini-tools' ), 'manage_options', $this->slug . '&tab=modules', [ $this, 'render_page' ] );
		add_submenu_page( $this->slug, __( 'Réglages', 'studio-kyne-mini-tools' ), __( 'Réglages', 'studio-kyne-mini-tools' ), 'manage_options', $this->slug . '&tab=settings', [ $this, 'render_page' ] );

		global $submenu;
		if ( isset( $submenu[ $this->slug ] ) ) {
			$submenu[ $this->slug ][] = [ '', 'manage_options', 'skmt-separator', '', 'skmt-menu-separator' ];
		}

		foreach ( $this->modules->get_all() as $module_id => $module ) {
			if ( ! $this->modules->is_active( $module_id ) ) {
				continue;
			}

			$label = ! empty( $module['menu_label'] ) ? $module['menu_label'] : $module['name'];
			add_submenu_page(
				$this->slug,
				esc_html( $label ),
				esc_html( $label ),
				'manage_options',
				$this->slug . '&tab=module_' . $module_id,
				[ $this, 'render_page' ]
			);
		}

		$this->deduplicate_submenus();
	}

	/**
	 * Retire les doublons de sous-menus du plugin.
	 */
	private function deduplicate_submenus(): void {
		global $submenu;

		if ( empty( $submenu[ $this->slug ] ) || ! is_array( $submenu[ $this->slug ] ) ) {
			return;
		}

		$top_label = __( 'SKMT', 'studio-kyne-mini-tools' );
		$seen      = [];
		$filtered  = [];

		foreach ( $submenu[ $this->slug ] as $item ) {
			$label = isset( $item[0] ) ? (string) $item[0] : '';
			$slug  = isset( $item[2] ) ? (string) $item[2] : '';

			if ( $slug === $this->slug || $label === $top_label ) {
				continue;
			}

			$key = $label . '|' . $slug;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$filtered[]   = $item;
		}

		$submenu[ $this->slug ] = $filtered;
	}

	/* ================================================================
	 * ASSETS
	 * ================================================================ */

	/**
	 * Charge les assets CSS/JS sur les pages du plugin.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, $this->slug ) === false ) {
			return;
		}

		wp_enqueue_style( 'skmt-reset-css',      SKMT_ASSETS_URL . 'admin/css/reset.css',      [],                    SKMT_VERSION );
		wp_enqueue_style( 'skmt-layout-css',     SKMT_ASSETS_URL . 'admin/css/layout.css',     [ 'skmt-reset-css' ],  SKMT_VERSION );
		wp_enqueue_style( 'skmt-sidebar-css',    SKMT_ASSETS_URL . 'admin/css/sidebar.css',    [ 'skmt-layout-css' ], SKMT_VERSION );
		wp_enqueue_style( 'skmt-components-css', SKMT_ASSETS_URL . 'admin/css/components.css', [ 'skmt-reset-css' ],  SKMT_VERSION );
		wp_enqueue_style( 'skmt-buttons-css',    SKMT_ASSETS_URL . 'admin/css/buttons.css',    [ 'skmt-components-css' ], SKMT_VERSION );

		wp_enqueue_script( 'skmt-admin-js', SKMT_ASSETS_URL . 'admin/js/admin.js', [], SKMT_VERSION, true );

		$this->localize_admin_script( 'skmt-admin-js' );

		$this->enqueue_module_assets();
	}

	/**
	 * Localise les données globales pour un script admin (sans i18n spécifiques aux modules).
	 */
	private function localize_admin_script( string $handle ): void {
		wp_localize_script( $handle, 'skmtAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'skmt_admin_nonce' ),
			'i18n'    => [
				'saveSuccess' => __( 'Réglages enregistrés avec succès.', 'studio-kyne-mini-tools' ),
				'saveError'   => __( 'Une erreur est survenue.', 'studio-kyne-mini-tools' ),
			],
		] );
	}

	/**
	 * Charge les assets du centre de notifications sur tout l'admin WP.
	 */
	public function enqueue_global_notification_assets(): void {
		wp_enqueue_style( 'skmt-notifications-css', SKMT_ASSETS_URL . 'admin/css/notifications.css', [], SKMT_VERSION );
		wp_enqueue_script( 'skmt-notifications-js', SKMT_ASSETS_URL . 'admin/js/notifications.js',   [], SKMT_VERSION, true );
	}

	/**
	 * Charge les assets des modules actifs sur leur page de réglages.
	 */
	private function enqueue_module_assets(): void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

		if ( strpos( $tab, 'module_' ) !== 0 ) {
			return;
		}

		$module_id = substr( $tab, 7 );
		$instance  = $this->modules->get_active_instances()[ $module_id ] ?? null;

		if ( ! $instance ) {
			return;
		}

		// CSS du module
		foreach ( $instance->get_admin_css() as $index => $style_url ) {
			if ( empty( $style_url ) ) {
				continue;
			}
			wp_enqueue_style(
				'skmt-module-' . $module_id . '-css-' . $index,
				$style_url,
				[ 'skmt-components-css', 'skmt-buttons-css', 'skmt-layout-css', 'skmt-sidebar-css' ],
				SKMT_VERSION
			);
		}

		// JS du module (avec skmt-admin-js comme dépendance pour que skmtAdmin soit défini)
		foreach ( $instance->get_admin_js() as $index => $script_url ) {
			if ( empty( $script_url ) ) {
				continue;
			}

			$handle = 'skmt-module-' . $module_id . '-js-' . $index;
			wp_enqueue_script( $handle, $script_url, [ 'skmt-admin-js' ], SKMT_VERSION, true );

			// Injection des données JS spécifiques au module dans skmtAdmin
			$js_data = $instance->get_admin_js_data();
			if ( ! empty( $js_data ) ) {
				$inline = 'window.skmtAdmin=window.skmtAdmin||{};';
				if ( ! empty( $js_data['i18n'] ) ) {
					$inline .= 'window.skmtAdmin.i18n=Object.assign(window.skmtAdmin.i18n||{},' . wp_json_encode( $js_data['i18n'] ) . ');';
				}
				foreach ( $js_data as $key => $value ) {
					if ( 'i18n' === $key ) {
						continue;
					}
					$inline .= 'window.skmtAdmin[' . wp_json_encode( $key ) . ']=' . wp_json_encode( $value ) . ';';
				}
				wp_add_inline_script( $handle, $inline, 'before' );
			}
		}
	}

	/* ================================================================
	 * RENDU
	 * ================================================================ */

	/**
	 * Rendu de la page admin.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires.', 'studio-kyne-mini-tools' ) );
		}

		$this->display_notices();

		include SKMT_TEMPLATES_DIR . 'admin/layout.php';
	}

	/**
	 * Prépare le toast de feedback SKMT (via query string) pour injection JS.
	 * N'affiche plus rien directement — les données sont consommées par render_notification_drawer().
	 */
	private function display_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['skmt_notice'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = sanitize_key( $_GET['skmt_notice'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = isset( $_GET['skmt_notice_type'] ) ? sanitize_key( $_GET['skmt_notice_type'] ) : 'success';

		$messages = [
			'settings_saved'     => __( 'Réglages enregistrés avec succès.', 'studio-kyne-mini-tools' ),
			'module_activated'   => __( 'Module activé.', 'studio-kyne-mini-tools' ),
			'module_deactivated' => __( 'Module désactivé.', 'studio-kyne-mini-tools' ),
			'modules_updated'    => __( 'Modules mis à jour.', 'studio-kyne-mini-tools' ),
			'updates_checked'    => __( 'Vérification des mises à jour effectuée.', 'studio-kyne-mini-tools' ),
			'settings_reset'      => __( 'Configuration réinitialisée aux valeurs par défaut.', 'studio-kyne-mini-tools' ),
			'settings_imported'   => __( 'Configuration importée avec succès.', 'studio-kyne-mini-tools' ),
			'import_error_file'   => __( 'Erreur lors du chargement du fichier.', 'studio-kyne-mini-tools' ),
			'import_error_invalid' => __( 'Le fichier JSON est invalide ou incompatible.', 'studio-kyne-mini-tools' ),
		];

		if ( isset( $messages[ $notice ] ) ) {
			$this->skmt_toast = [
				'message' => $messages[ $notice ],
				'type'    => $type,
			];
		}
	}

	/* ================================================================
	 * CENTRE DE NOTIFICATIONS
	 * ================================================================ */

	/**
	 * Démarre la capture des notices WP via output buffering (tout l'admin).
	 */
	public function capture_wp_notices_start(): void {
		ob_start();
	}

	/**
	 * Termine la capture et stocke le HTML des notices WP.
	 */
	public function capture_wp_notices_end(): void {
		$this->captured_wp_notices = ob_get_clean() ?: '';
	}

	/**
	 * Ajoute le bouton cloche "Notifications" dans la barre d'admin WP (tout l'admin).
	 */
	public function register_notification_center( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! is_admin() ) {
			return;
		}

		$bell = $this->render_icon( 'bell', 'sm', 'skmt-notif-bell-icon' );

		$wp_admin_bar->add_node( [
			'id'     => 'skmt-notif-center',
			'parent' => 'top-secondary',
			'title'  => '<span class="skmt-notif-btn-wrap">' . $bell . '<span class="skmt-notif-badge" id="skmt-notif-badge" style="display:none"></span></span>',
			'href'   => '#skmt-notif-drawer',
			'meta'   => [
				'class' => 'skmt-notif-trigger',
				'title' => esc_attr__( 'Notifications', 'studio-kyne-mini-tools' ),
			],
		] );
	}

	/**
	 * Rend la modal réutilisable sur les pages du plugin.
	 */
	public function render_modal(): void {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}
		?>
		<div id="skmt-modal-overlay" class="skmt-modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
			<div class="skmt-modal">
				<div class="skmt-modal__header">
					<h2 class="skmt-modal__title"></h2>
				</div>
				<div class="skmt-modal__body">
					<p class="skmt-modal__message"></p>
				</div>
				<div class="skmt-modal__footer">
					<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary skmt-modal__cancel">
						<?php esc_html_e( 'Annuler', 'studio-kyne-mini-tools' ); ?>
					</button>
					<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary skmt-modal__confirm">
						<?php esc_html_e( 'Confirmer', 'studio-kyne-mini-tools' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Rend le drawer de notifications + le conteneur de toasts + les données JSON pour JS.
	 * Appelé via admin_footer sur tout l'admin, après capture des notices WP.
	 */
	public function render_notification_drawer(): void {

		$close_icon    = $this->render_icon( 'x', 'sm' );
		$notices_json  = wp_json_encode( $this->captured_wp_notices );
		$toast_json    = wp_json_encode( $this->skmt_toast );

		$user_id         = get_current_user_id();
		$raw_notices     = $user_id ? get_user_meta( $user_id, 'skmt_notices', true ) : [];
		$raw_notices     = is_array( $raw_notices ) ? $raw_notices : [];
		$persistent_list = [];
		foreach ( $raw_notices as $notice_id => $notice ) {
			$persistent_list[] = [
				'id'      => $notice_id,
				'message' => $notice['message'] ?? '',
				'type'    => $notice['type'] ?? 'info',
			];
		}
		$persistent_json = wp_json_encode( $persistent_list );
		$notif_data_json = wp_json_encode( [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'skmt_admin_nonce' ),
		] );
		?>
		<div id="skmt-notif-drawer" class="skmt-notif-drawer" role="dialog" aria-label="<?php esc_attr_e( 'Centre de notifications', 'studio-kyne-mini-tools' ); ?>" aria-hidden="true">
			<div class="skmt-notif-drawer__header">
				<h2 class="skmt-notif-drawer__title"><?php esc_html_e( 'Notifications', 'studio-kyne-mini-tools' ); ?></h2>
				<button class="skmt-notif-drawer__close" id="skmt-notif-close" type="button" aria-label="<?php esc_attr_e( 'Fermer', 'studio-kyne-mini-tools' ); ?>">
					<?php echo $close_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
			</div>
			<div class="skmt-notif-drawer__body" id="skmt-notif-body"></div>
		</div>
		<div id="skmt-notif-overlay" class="skmt-notif-overlay" aria-hidden="true"></div>
		<div id="skmt-toast-container" class="skmt-toast-container" role="region" aria-live="polite" aria-label="<?php esc_attr_e( 'Notifications', 'studio-kyne-mini-tools' ); ?>"></div>
		<script>
		window.skmtWpNoticesHtml     = <?php echo $notices_json;    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
		window.skmtToastData         = <?php echo $toast_json;      // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
		window.skmtPersistentNotices = <?php echo $persistent_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
		window.skmtNotifData         = <?php echo $notif_data_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
		</script>
		<?php
	}

	/**
	 * Ajoute une notice persistante (survit aux rechargements).
	 * Sans $user_id, cible l'utilisateur courant ; utile pour cibler un
	 * utilisateur précis depuis un contexte sans utilisateur courant (cron).
	 */
	public static function add_persistent_notice( string $id, string $message, string $type = 'info', int $user_id = 0 ): void {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$notices        = get_user_meta( $user_id, 'skmt_notices', true );
		$notices        = is_array( $notices ) ? $notices : [];
		$notices[ $id ] = [
			'message'   => $message,
			'type'      => $type,
			'timestamp' => time(),
		];
		update_user_meta( $user_id, 'skmt_notices', $notices );
	}

	/**
	 * Supprime une notice persistante de l'utilisateur courant.
	 */
	public static function dismiss_persistent_notice( string $id ): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$notices = get_user_meta( $user_id, 'skmt_notices', true );
		if ( ! is_array( $notices ) ) {
			return;
		}
		unset( $notices[ $id ] );
		update_user_meta( $user_id, 'skmt_notices', $notices );
	}

	/**
	 * Endpoint AJAX : dismiss d'une notice persistante SKMT.
	 */
	public function handle_dismiss_notice(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) ] );
		}
		$id = isset( $_POST['notice_id'] ) ? sanitize_key( $_POST['notice_id'] ) : '';
		if ( empty( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'ID invalide.', 'studio-kyne-mini-tools' ) ] );
		}
		self::dismiss_persistent_notice( $id );
		wp_send_json_success();
	}

	/* ================================================================
	 * GESTION DES ACTIONS ADMIN
	 * ================================================================ */

	/**
	 * Sauvegarde des réglages globaux ou d'un module.
	 */
	public function handle_save_settings(): void {
		if ( ! isset( $_POST['skmt_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['skmt_nonce'] ) ), 'skmt_save_settings' ) ) {
			wp_die( esc_html__( 'Nonce invalide.', 'studio-kyne-mini-tools' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		$tab = isset( $_POST['skmt_tab'] ) ? sanitize_key( $_POST['skmt_tab'] ) : 'settings';

		// Réglages globaux
		if ( 'settings' === $tab && isset( $_POST['skmt_global'] ) ) {
			$global = [
				'update_channel' => isset( $_POST['skmt_global']['update_channel'] ) ? sanitize_key( $_POST['skmt_global']['update_channel'] ) : 'stable',
				'auto_updates'   => ! empty( $_POST['skmt_global']['auto_updates'] ),
			];
			$this->settings->set( 'global', $global );
			$this->ensure_auto_updates_enabled();
		}

		// Réglages d'un module
		if ( strpos( $tab, 'module_' ) === 0 ) {
			$module_id = substr( $tab, 7 );
			$instance  = $this->modules->get_active_instances()[ $module_id ] ?? null;

			if ( $instance && isset( $_POST['skmt_module_settings'] ) && is_array( $_POST['skmt_module_settings'] ) ) {
				$instance->save_settings( wp_unslash( $_POST['skmt_module_settings'] ) );
			}
		}

		wp_safe_redirect( add_query_arg( [
			'page'             => $this->slug,
			'tab'              => $tab,
			'skmt_notice'      => 'settings_saved',
			'skmt_notice_type' => 'success',
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Activation/désactivation d'un seul module (GET ou POST).
	 * Nonce lu depuis $_REQUEST pour supporter les deux méthodes HTTP.
	 */
	public function handle_toggle_module(): void {
		if ( ! isset( $_REQUEST['skmt_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['skmt_nonce'] ) ), 'skmt_toggle_module' ) ) {
			wp_die( esc_html__( 'Nonce invalide.', 'studio-kyne-mini-tools' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		$module_id = isset( $_REQUEST['module'] ) ? sanitize_key( $_REQUEST['module'] ) : '';
		$action    = isset( $_REQUEST['skmt_action'] ) ? sanitize_key( $_REQUEST['skmt_action'] ) : '';

		if ( empty( $module_id ) || ! in_array( $action, [ 'activate', 'deactivate' ], true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . $this->slug . '&tab=modules' ) );
			exit;
		}

		if ( 'activate' === $action ) {
			$this->modules->activate( $module_id );
			$notice = 'module_activated';
		} else {
			$this->modules->deactivate( $module_id );
			$notice = 'module_deactivated';
		}

		wp_safe_redirect( add_query_arg( [
			'page'             => $this->slug,
			'tab'              => 'modules',
			'skmt_notice'      => $notice,
			'skmt_notice_type' => 'success',
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Toggle AJAX d'un module (réponse JSON — pas de redirect).
	 */
	public function handle_ajax_toggle_module(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'skmt_admin_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce invalide.', 'studio-kyne-mini-tools' ) ], 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) ], 403 );
		}

		$module_id = isset( $_POST['module'] ) ? sanitize_key( $_POST['module'] ) : '';
		$action    = isset( $_POST['skmt_action'] ) ? sanitize_key( $_POST['skmt_action'] ) : '';

		if ( empty( $module_id ) || ! in_array( $action, [ 'activate', 'deactivate' ], true ) ) {
			wp_send_json_error( [ 'message' => __( 'Paramètres invalides.', 'studio-kyne-mini-tools' ) ], 400 );
		}

		if ( 'activate' === $action ) {
			$this->modules->activate( $module_id );
			$notice      = __( 'Module activé.', 'studio-kyne-mini-tools' );
			$new_state   = true;
		} else {
			$this->modules->deactivate( $module_id );
			$notice      = __( 'Module désactivé.', 'studio-kyne-mini-tools' );
			$new_state   = false;
		}

		$configure_url = add_query_arg( [
			'page' => $this->slug,
			'tab'  => 'module_' . $module_id,
		], admin_url( 'admin.php' ) );

		wp_send_json_success( [
			'notice'        => $notice,
			'active'        => $new_state,
			'configure_url' => esc_url( $configure_url ),
		] );
	}

	/**
	 * Activation/désactivation en masse des modules.
	 */
	public function handle_update_modules(): void {
		if ( ! isset( $_POST['skmt_modules_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['skmt_modules_nonce'] ) ), 'skmt_update_modules' ) ) {
			wp_die( esc_html__( 'Nonce invalide.', 'studio-kyne-mini-tools' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		$enabled_modules = [];
		if ( isset( $_POST['skmt_modules'] ) && is_array( $_POST['skmt_modules'] ) ) {
			$enabled_modules = array_map( 'sanitize_key', wp_unslash( $_POST['skmt_modules'] ) );
		}

		foreach ( $this->modules->get_all() as $module_id => $module ) {
			if ( in_array( $module_id, $enabled_modules, true ) ) {
				$this->modules->activate( $module_id );
			} else {
				$this->modules->deactivate( $module_id );
			}
		}

		wp_safe_redirect( add_query_arg( [
			'page'             => $this->slug,
			'tab'              => 'modules',
			'skmt_notice'      => 'modules_updated',
			'skmt_notice_type' => 'success',
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Vérification manuelle des mises à jour.
	 */
	public function handle_check_updates(): void {
		if ( ! isset( $_POST['skmt_check_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['skmt_check_nonce'] ) ), 'skmt_check_updates' ) ) {
			wp_die( esc_html__( 'Nonce invalide.', 'studio-kyne-mini-tools' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		delete_site_transient( 'update_plugins' );
		delete_transient( 'skmt_github_update_stable' );
		delete_transient( 'skmt_github_update_dev' );
		wp_update_plugins();

		wp_safe_redirect( add_query_arg( [
			'page'             => $this->slug,
			'tab'              => 'settings',
			'skmt_notice'      => 'updates_checked',
			'skmt_notice_type' => 'success',
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Réinitialisation de tous les réglages du plugin aux valeurs par défaut.
	 */
	public function handle_reset_settings(): void {
		if ( ! isset( $_POST['skmt_reset_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['skmt_reset_nonce'] ) ), 'skmt_reset_settings' ) ) {
			wp_die( esc_html__( 'Nonce invalide.', 'studio-kyne-mini-tools' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		// Reset réglages globaux
		$this->settings->set( 'global', [ 'update_channel' => 'stable', 'auto_updates' => false ] );

		// Reset les options de chaque module enregistré
		foreach ( $this->modules->get_all() as $module_id => $module ) {
			if ( ! empty( $module['class'] ) && class_exists( $module['class'] ) ) {
				$keys = $module['class']::get_uninstall_keys();
				foreach ( $keys['options'] ?? [] as $option_key ) {
					delete_option( $option_key );
				}
			}
		}

		wp_safe_redirect( add_query_arg( [
			'page'             => $this->slug,
			'tab'              => 'settings',
			'skmt_notice'      => 'settings_reset',
			'skmt_notice_type' => 'success',
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Export de tous les réglages du plugin en JSON.
	 */
	public function handle_export_settings(): void {
		if ( ! isset( $_POST['skmt_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['skmt_export_nonce'] ) ), 'skmt_export_settings' ) ) {
			wp_die( esc_html__( 'Nonce invalide.', 'studio-kyne-mini-tools' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		$data = [
			'version'  => SKMT_VERSION,
			'exported' => current_time( 'c' ),
			'global'   => get_option( 'skmt_settings', [] ),
			'modules'  => [],
		];

		foreach ( $this->modules->get_all() as $module_id => $module ) {
			$data['modules'][ $module_id ] = get_option( 'skmt_module_' . $module_id, [] );
		}

		$filename = 'skmt-settings-' . gmdate( 'Y-m-d' ) . '.json';
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Import des réglages depuis un fichier JSON.
	 */
	public function handle_import_settings(): void {
		if ( ! isset( $_POST['skmt_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['skmt_import_nonce'] ) ), 'skmt_import_settings' ) ) {
			wp_die( esc_html__( 'Nonce invalide.', 'studio-kyne-mini-tools' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		$file = $_FILES['skmt_import_file'] ?? null;
		if ( ! $file || empty( $file['tmp_name'] ) || $file['error'] !== UPLOAD_ERR_OK ) {
			wp_safe_redirect( add_query_arg( [
				'page'             => $this->slug,
				'tab'              => 'settings',
				'skmt_notice'      => 'import_error_file',
				'skmt_notice_type' => 'error',
			], admin_url( 'admin.php' ) ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw  = file_get_contents( $file['tmp_name'] );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || ! isset( $data['global'] ) ) {
			wp_safe_redirect( add_query_arg( [
				'page'             => $this->slug,
				'tab'              => 'settings',
				'skmt_notice'      => 'import_error_invalid',
				'skmt_notice_type' => 'error',
			], admin_url( 'admin.php' ) ) );
			exit;
		}

		update_option( 'skmt_settings', $data['global'] );

		if ( isset( $data['modules'] ) && is_array( $data['modules'] ) ) {
			$registered_modules = $this->modules->get_all();
			foreach ( $data['modules'] as $module_id => $module_settings ) {
				if ( isset( $registered_modules[ $module_id ] ) && is_array( $module_settings ) ) {
					update_option( 'skmt_module_' . $module_id, $module_settings );
				}
			}
		}

		wp_safe_redirect( add_query_arg( [
			'page'             => $this->slug,
			'tab'              => 'settings',
			'skmt_notice'      => 'settings_imported',
			'skmt_notice_type' => 'success',
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ================================================================
	 * AUTO-UPDATES
	 * ================================================================ */

	/**
	 * Synchronise l'option WordPress auto_update_plugins avec le réglage SKMT.
	 */
	public function ensure_auto_updates_enabled(): void {
		$plugin_file  = plugin_basename( SKMT_PLUGIN_FILE );
		$auto_updates = (array) get_site_option( 'auto_update_plugins', [] );
		$global       = $this->settings->get( 'global', [] );
		$enabled      = ! empty( $global['auto_updates'] );

		if ( $enabled ) {
			if ( in_array( $plugin_file, $auto_updates, true ) ) {
				return;
			}
			$auto_updates[] = $plugin_file;
			update_site_option( 'auto_update_plugins', array_values( array_unique( $auto_updates ) ) );
			return;
		}

		if ( ! in_array( $plugin_file, $auto_updates, true ) ) {
			return;
		}

		update_site_option( 'auto_update_plugins', array_values( array_diff( $auto_updates, [ $plugin_file ] ) ) );
	}

	/* ================================================================
	 * FILTRES MENU / FOOTER
	 * ================================================================ */

	public function filter_parent_file( ?string $parent_file ): string {
		if ( ! isset( $_GET['page'] ) || strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), $this->slug ) !== 0 ) {
			return $parent_file ?? '';
		}

		$tab = $this->get_current_tab();

		if ( strpos( $tab, 'module_' ) === 0 || in_array( $tab, [ 'dashboard', 'modules', 'settings' ], true ) ) {
			return $this->slug;
		}

		return $parent_file ?? '';
	}

	public function filter_submenu_file( ?string $submenu_file, ?string $parent_file ): string {
		if ( ! isset( $_GET['page'] ) || strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), $this->slug ) !== 0 ) {
			return $submenu_file ?? '';
		}

		$tab = $this->get_current_tab();

		if ( in_array( $tab, [ 'dashboard', 'modules', 'settings' ], true ) || strpos( $tab, 'module_' ) === 0 ) {
			return $this->slug . '&tab=' . $tab;
		}

		return $submenu_file ?? '';
	}

	public function filter_admin_footer_text( string $text ): string {
		return $this->is_plugin_screen() ? '' : $text;
	}

	public function filter_update_footer( string $text ): string {
		return $this->is_plugin_screen() ? '' : $text;
	}

	public function output_menu_separator_css(): void {
		echo '<style>' . $this->get_menu_separator_css() . '</style>';
	}

	/* ================================================================
	 * ICÔNES SVG
	 * ================================================================ */

	/**
	 * Retourne le SVG inline d'une icône.
	 */
	public function render_icon( string $icon, string $size = 'md', string $extra_class = '' ): string {
		$paths = $this->get_icon_paths();
		$path  = $paths[ $icon ] ?? $paths['package'];
		$class = trim( 'skmt-icon skmt-icon--' . $size . ' ' . $extra_class );

		return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}

	private function get_icon_paths(): array {
		return [
			'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
			'package'          => '<path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path>',
			'settings'         => '<path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/>',
			'image'            => '<rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"></path>',
			'check-circle'     => '<circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>',
			'info'             => '<circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path>',
			'shield'           => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
			'bell'             => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
			'x'                => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
			'log-in'           => '<path d="m10 17 5-5-5-5"/><path d="M15 12H3"/><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>',
			'folder'           => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
		'chevron-down'     => '<path d="m6 9 6 6 6-6"/>',
		'palette'          => '<path d="M12 22a1 1 0 0 1 0-20 10 9 0 0 1 10 9 5 5 0 0 1-5 5h-2.25a1.75 1.75 0 0 0-1.4 2.8l.3.4a1.75 1.75 0 0 1-1.4 2.8z"/><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>',
		'menu'             => '<path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>',
		'database'         => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>',
		];
	}

	/* ================================================================
	 * HELPERS PRIVÉS
	 * ================================================================ */

	private function get_current_tab(): string {
		return isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
	}

	/**
	 * Vérifie si la page courante est une page du plugin (via $_GET['page']).
	 * Utilisable tôt dans le cycle de vie WP, avant que get_current_screen() soit disponible.
	 */
	private function is_skmt_page(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) && sanitize_key( $_GET['page'] ) === $this->slug;
	}

	private function is_plugin_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && strpos( $screen->id, $this->slug ) !== false;
	}

	private function get_menu_separator_css(): string {
		return '#adminmenu .skmt-menu-separator{pointer-events:none}'
			. '#adminmenu .skmt-menu-separator a{'
			. 'height:1px;min-height:1px;margin:6px 12px;padding:0!important;'
			. 'background:#c3c4c7;box-shadow:none;text-indent:-9999px;'
			. '}'
			. '#adminmenu .skmt-menu-separator a:hover{background:#c3c4c7}';
	}

	public function get_slug(): string {
		return $this->slug;
	}
}
