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
		add_filter( 'parent_file', [ $this, 'filter_parent_file' ] );
		add_filter( 'submenu_file', [ $this, 'filter_submenu_file' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init', [ $this, 'ensure_auto_updates_enabled' ] );
		add_action( 'admin_post_skmt_save_settings', [ $this, 'handle_save_settings' ] );
		add_action( 'admin_post_skmt_toggle_module', [ $this, 'handle_toggle_module' ] );
		add_action( 'admin_post_skmt_update_modules', [ $this, 'handle_update_modules' ] );
		add_action( 'admin_post_skmt_check_updates', [ $this, 'handle_check_updates' ] );
		add_action( 'admin_head', [ $this, 'output_menu_separator_css' ] );
		add_filter( 'admin_footer_text', [ $this, 'filter_admin_footer_text' ] );
		add_filter( 'update_footer', [ $this, 'filter_update_footer' ], 11 );
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

		wp_enqueue_style( 'skmt-reset-css',      SKMT_ASSETS_URL . 'admin/css/reset.css',      [],                                                              SKMT_VERSION );
		wp_enqueue_style( 'skmt-layout-css',     SKMT_ASSETS_URL . 'admin/css/layout.css',     [ 'skmt-reset-css' ],                                            SKMT_VERSION );
		wp_enqueue_style( 'skmt-sidebar-css',    SKMT_ASSETS_URL . 'admin/css/sidebar.css',    [ 'skmt-layout-css' ],                                           SKMT_VERSION );
		wp_enqueue_style( 'skmt-components-css', SKMT_ASSETS_URL . 'admin/css/components.css', [ 'skmt-reset-css' ],                                            SKMT_VERSION );
		wp_enqueue_style( 'skmt-buttons-css',    SKMT_ASSETS_URL . 'admin/css/buttons.css',    [ 'skmt-components-css' ],                                       SKMT_VERSION );

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

			// Injection des données JS spécifiques au module (i18n, etc.)
			$js_data = $instance->get_admin_js_data();
			if ( ! empty( $js_data['i18n'] ) ) {
				$i18n_json = wp_json_encode( $js_data['i18n'] );
				wp_add_inline_script(
					$handle,
					'window.skmtAdmin=window.skmtAdmin||{};window.skmtAdmin.i18n=Object.assign(window.skmtAdmin.i18n||{},' . $i18n_json . ');',
					'before'
				);
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
	 * Affiche les notices de feedback (via query string).
	 */
	private function display_notices(): void {
		if ( ! isset( $_GET['skmt_notice'] ) ) {
			return;
		}

		$notice = sanitize_key( $_GET['skmt_notice'] );
		$type   = isset( $_GET['skmt_notice_type'] ) ? sanitize_key( $_GET['skmt_notice_type'] ) : 'success';

		$messages = [
			'settings_saved'     => __( 'Réglages enregistrés avec succès.', 'studio-kyne-mini-tools' ),
			'module_activated'   => __( 'Module activé.', 'studio-kyne-mini-tools' ),
			'module_deactivated' => __( 'Module désactivé.', 'studio-kyne-mini-tools' ),
			'modules_updated'    => __( 'Modules mis à jour.', 'studio-kyne-mini-tools' ),
			'updates_checked'    => __( 'Vérification des mises à jour effectuée.', 'studio-kyne-mini-tools' ),
		];

		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="skmt-notice skmt-notice--' . esc_attr( $type ) . '">';
			echo '<p>' . esc_html( $messages[ $notice ] ) . '</p>';
			echo '</div>';
		}
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
		$tab = $this->get_current_tab();

		if ( strpos( $tab, 'module_' ) === 0 || in_array( $tab, [ 'dashboard', 'modules', 'settings' ], true ) ) {
			return $this->slug;
		}

		return $parent_file ?? '';
	}

	public function filter_submenu_file( ?string $submenu_file, ?string $parent_file ): string {
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
		];
	}

	/* ================================================================
	 * HELPERS PRIVÉS
	 * ================================================================ */

	private function get_current_tab(): string {
		return isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
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
