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
	 * Constructeur.
	 */
	public function __construct( Modules $modules, Settings $settings ) {
		$this->modules  = $modules;
		$this->settings = $settings;

		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init', [ $this, 'ensure_auto_updates_enabled' ] );
		add_action( 'admin_post_skmt_save_settings', [ $this, 'handle_save_settings' ] );
		add_action( 'admin_post_skmt_toggle_module', [ $this, 'handle_toggle_module' ] );
		add_action( 'admin_post_skmt_check_updates', [ $this, 'handle_check_updates' ] );
		add_action( 'admin_head', [ $this, 'output_menu_separator_css' ] );
		add_filter( 'admin_footer_text', [ $this, 'filter_admin_footer_text' ] );
		add_filter( 'update_footer', [ $this, 'filter_update_footer' ], 11 );
	}

	/**
	 * Ajoute la page admin.
	 */
	public function add_menu_page(): void {
		add_menu_page(
			__( 'Studio Kyne Mini Tools', 'studio-kyne-mini-tools' ),
			__( 'SKMT', 'studio-kyne-mini-tools' ),
			'manage_options',
			$this->slug,
			[ $this, 'render_page' ],
			$this->get_menu_icon(),
			80
		);

		$this->add_submenus();
	}

	/**
	 * Charge les assets CSS/JS.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, $this->slug ) === false ) {
			return;
		}

		wp_enqueue_style(
			'skmt-reset-css',
			SKMT_ASSETS_URL . 'admin/css/reset.css',
			[],
			SKMT_VERSION
		);

		wp_enqueue_style(
			'skmt-layout-css',
			SKMT_ASSETS_URL . 'admin/css/layout.css',
			[ 'skmt-reset-css' ],
			SKMT_VERSION
		);

		wp_enqueue_style(
			'skmt-sidebar-css',
			SKMT_ASSETS_URL . 'admin/css/sidebar.css',
			[ 'skmt-layout-css' ],
			SKMT_VERSION
		);

		wp_enqueue_style(
			'skmt-components-css',
			SKMT_ASSETS_URL . 'admin/css/components.css',
			[ 'skmt-reset-css' ],
			SKMT_VERSION
		);

		wp_enqueue_style(
			'skmt-buttons-css',
			SKMT_ASSETS_URL . 'admin/css/buttons.css',
			[ 'skmt-components-css' ],
			SKMT_VERSION
		);

		// Charger Lucide Icons
		wp_enqueue_script(
			'lucide-icons',
			'https://unpkg.com/lucide@latest',
			[],
			null,
			false
		);

		wp_enqueue_script(
			'skmt-admin-js',
			SKMT_ASSETS_URL . 'admin/js/admin.js',
			[ 'lucide-icons' ],
			SKMT_VERSION,
			true
		);

		$this->enqueue_module_assets();

		wp_localize_script( 'skmt-admin-js', 'skmtAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'skmt_admin_nonce' ),
			'i18n'    => [
				'saveSuccess'    => __( 'Réglages enregistrés avec succès.', 'studio-kyne-mini-tools' ),
				'saveError'      => __( 'Une erreur est survenue.', 'studio-kyne-mini-tools' ),
				'bulkRunning'    => __( 'Optimisation en cours…', 'studio-kyne-mini-tools' ),
				'bulkProcessed'  => __( 'Traité :', 'studio-kyne-mini-tools' ),
				'bulkRemaining'  => __( 'Restant :', 'studio-kyne-mini-tools' ),
				'bulkDone'       => __( 'Optimisation terminée', 'studio-kyne-mini-tools' ),
				'bulkComplete'   => __( 'Toutes les images ont été optimisées.', 'studio-kyne-mini-tools' ),
				'bulkRetry'      => __( 'Réessayer', 'studio-kyne-mini-tools' ),
			],
		] );
	}

	/**
	 * Injecte le style du separateur dans le menu admin.
	 */
	public function output_menu_separator_css(): void {
		echo '<style>' . $this->get_menu_separator_css() . '</style>';
	}

	/**
	 * Enqueue les styles des modules actifs.
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

		$styles = $instance->get_admin_css();

		foreach ( $styles as $index => $style_url ) {
			if ( empty( $style_url ) ) {
				continue;
			}

			wp_enqueue_style(
				'skmt-module-' . $module_id . '-' . $index,
				$style_url,
				[ 'skmt-components-css', 'skmt-buttons-css', 'skmt-layout-css', 'skmt-sidebar-css' ],
				SKMT_VERSION
			);
		}
	}

	/**
	 * Retourne l'icone du menu principal.
	 */
	private function get_menu_icon(): string {
		$icon_path = SKMT_PLUGIN_DIR . 'assets/admin/images/menu-icon.svg';
		if ( file_exists( $icon_path ) ) {
			$svg = file_get_contents( $icon_path );
			if ( $svg ) {
				return 'data:image/svg+xml;base64,' . base64_encode( $svg );
			}
		}

		return 'dashicons-admin-tools';
	}

	/**
	 * Ajoute les sous-menus dynamiques.
	 */
	private function add_submenus(): void {
		remove_submenu_page( $this->slug, $this->slug );

		add_submenu_page(
			$this->slug,
			__( 'Vue d\'ensemble', 'studio-kyne-mini-tools' ),
			__( 'Vue d\'ensemble', 'studio-kyne-mini-tools' ),
			'manage_options',
			$this->slug . '&tab=dashboard',
			[ $this, 'render_page' ]
		);

		add_submenu_page(
			$this->slug,
			__( 'Modules', 'studio-kyne-mini-tools' ),
			__( 'Modules', 'studio-kyne-mini-tools' ),
			'manage_options',
			$this->slug . '&tab=modules',
			[ $this, 'render_page' ]
		);

		add_submenu_page(
			$this->slug,
			__( 'Réglages', 'studio-kyne-mini-tools' ),
			__( 'Réglages', 'studio-kyne-mini-tools' ),
			'manage_options',
			$this->slug . '&tab=settings',
			[ $this, 'render_page' ]
		);

		global $submenu;
		if ( isset( $submenu[ $this->slug ] ) ) {
			$submenu[ $this->slug ][] = [
				'',
				'manage_options',
				'skmt-separator',
				'',
				'skmt-menu-separator',
			];
		}

		$modules = $this->modules->get_all();
		foreach ( $modules as $module_id => $module ) {
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
	}

	/**
	 * Supprime le texte de footer WordPress sur les pages du plugin.
	 */
	public function filter_admin_footer_text( string $text ): string {
		return $this->is_plugin_screen() ? '' : $text;
	}

	/**
	 * Supprime le numero de version WordPress sur les pages du plugin.
	 */
	public function filter_update_footer( string $text ): string {
		return $this->is_plugin_screen() ? '' : $text;
	}

	/**
	 * Verifie si la page admin courante appartient au plugin.
	 */
	private function is_plugin_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return strpos( $screen->id, $this->slug ) !== false;
	}

	/**
	 * Styles inline pour le separateur du menu.
	 */
	private function get_menu_separator_css(): string {
		return '#adminmenu .skmt-menu-separator { pointer-events: none; }'
			. '#adminmenu .skmt-menu-separator a {'
			. 'height:1px;min-height:1px;margin:6px 12px;padding:0!important;'
			. 'background:#c3c4c7;box-shadow:none;text-indent:-9999px;'
			. '}'
			. '#adminmenu .skmt-menu-separator a:hover { background:#c3c4c7; }';
	}

	/**
	 * Rendu de la page admin.
	 */
	public function render_page(): void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

		// Vérifier les capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires.', 'studio-kyne-mini-tools' ) );
		}

		// Afficher les notices
		$this->display_notices();

		// Inclure le layout principal
		include SKMT_TEMPLATES_DIR . 'admin/layout.php';
	}

	/**
	 * Affiche les notices de feedback.
	 */
	private function display_notices(): void {
		if ( isset( $_GET['skmt_notice'] ) ) {
			$notice = sanitize_key( $_GET['skmt_notice'] );
			$type   = isset( $_GET['skmt_notice_type'] ) ? sanitize_key( $_GET['skmt_notice_type'] ) : 'success';

			$messages = [
				'settings_saved'   => __( 'Réglages enregistrés avec succès.', 'studio-kyne-mini-tools' ),
				'module_activated' => __( 'Module activé.', 'studio-kyne-mini-tools' ),
				'module_deactivated' => __( 'Module désactivé.', 'studio-kyne-mini-tools' ),
				'updates_checked'  => __( 'Vérification des mises à jour effectuée.', 'studio-kyne-mini-tools' ),
			];

			if ( isset( $messages[ $notice ] ) ) {
				echo '<div class="skmt-notice skmt-notice--' . esc_attr( $type ) . '">';
				echo '<p>' . esc_html( $messages[ $notice ] ) . '</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Gère la sauvegarde des réglages.
	 */
	public function handle_save_settings(): void {
		// Vérifier le nonce
		if ( ! isset( $_POST['skmt_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['skmt_nonce'] ) ), 'skmt_save_settings' ) ) {
			wp_die( esc_html__( 'Nonce invalide.', 'studio-kyne-mini-tools' ) );
		}

		// Vérifier les capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		$tab = isset( $_POST['skmt_tab'] ) ? sanitize_key( $_POST['skmt_tab'] ) : 'settings';

		// Traiter les réglages globaux
		if ( 'settings' === $tab && isset( $_POST['skmt_global'] ) ) {
			$global = [
				'update_channel' => isset( $_POST['skmt_global']['update_channel'] ) ? sanitize_key( $_POST['skmt_global']['update_channel'] ) : 'stable',
			];
			$this->settings->set( 'global', $global );
		}

		// Traiter les réglages d'un module
		if ( strpos( $tab, 'module_' ) === 0 ) {
			$module_id = substr( $tab, 7 );
			$instance  = $this->modules->get_active_instances()[ $module_id ] ?? null;

			if ( $instance && isset( $_POST['skmt_module_settings'] ) && is_array( $_POST['skmt_module_settings'] ) ) {
				$module_settings = $this->sanitize_module_settings( wp_unslash( $_POST['skmt_module_settings'] ) );
				$instance->save_settings( $module_settings );
			}
		}

		// Redirection avec notice
		$redirect = add_query_arg( [
			'page'             => $this->slug,
			'tab'              => $tab,
			'skmt_notice'      => 'settings_saved',
			'skmt_notice_type' => 'success',
		], admin_url( 'admin.php' ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Gère la verification manuelle des mises a jour.
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

		$redirect = add_query_arg( [
			'page'             => $this->slug,
			'tab'              => 'settings',
			'skmt_notice'      => 'updates_checked',
			'skmt_notice_type' => 'success',
		], admin_url( 'admin.php' ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Active les mises a jour auto pour le plugin.
	 */
	public function ensure_auto_updates_enabled(): void {
		$plugin_file = plugin_basename( SKMT_PLUGIN_FILE );
		$auto_updates = (array) get_site_option( 'auto_update_plugins', [] );

		if ( in_array( $plugin_file, $auto_updates, true ) ) {
			return;
		}

		$auto_updates[] = $plugin_file;
		update_site_option( 'auto_update_plugins', array_values( array_unique( $auto_updates ) ) );
	}

	/**
	 * Gère l'activation/désactivation d'un module.
	 */
	public function handle_toggle_module(): void {
		// Vérifier le nonce
		if ( ! isset( $_GET['skmt_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['skmt_nonce'] ) ), 'skmt_toggle_module' ) ) {
			wp_die( esc_html__( 'Nonce invalide.', 'studio-kyne-mini-tools' ) );
		}

		// Vérifier les capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		$module_id = isset( $_GET['module'] ) ? sanitize_key( $_GET['module'] ) : '';
		$action    = isset( $_GET['skmt_action'] ) ? sanitize_key( $_GET['skmt_action'] ) : '';

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

		$redirect = add_query_arg( [
			'page'             => $this->slug,
			'tab'              => 'modules',
			'skmt_notice'      => $notice,
			'skmt_notice_type' => 'success',
		], admin_url( 'admin.php' ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Sanitize les réglages d'un module.
	 */
	private function sanitize_module_settings( array $settings ): array {
		$sanitized = [];
		foreach ( $settings as $key => $value ) {
			if ( is_bool( $value ) || in_array( $value, [ 'true', 'false', '1', '0', 'on', 'off' ], true ) ) {
				$sanitized[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = intval( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}
		return $sanitized;
	}

	/**
	 * Retourne le slug de la page.
	 */
	public function get_slug(): string {
		return $this->slug;
	}
}