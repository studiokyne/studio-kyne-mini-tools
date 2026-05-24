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
			plugins_url( 'assets/admin/images/menu-icon.svg', SKMT_PLUGIN_FILE ),
			99
		);

		$this->add_submenus();
	}

	/**
	 * Charge les assets CSS/JS.
	 */
	public function enqueue_assets( string $hook ): void {
		$is_plugin_screen = strpos( $hook, $this->slug ) !== false;

		if ( $is_plugin_screen ) {
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

			wp_enqueue_script(
				'skmt-admin-js',
				SKMT_ASSETS_URL . 'admin/js/admin.js',
				[],
				SKMT_VERSION,
				true
			);

			$this->localize_admin_script( 'skmt-admin-js' );

			$this->enqueue_module_assets();
		}
	}

	/**
	 * Localise les données communes pour un script admin.
	 */
	private function localize_admin_script( string $handle ): void {
		wp_localize_script( $handle, 'skmtAdmin', [
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
				'singleRunning'  => __( 'Optimisation…', 'studio-kyne-mini-tools' ),
				'singleDone'     => __( 'Optimisée', 'studio-kyne-mini-tools' ),
				'singleError'    => __( 'Erreur', 'studio-kyne-mini-tools' ),
			],
		] );
	}

	/**
	 * Retourne le SVG inline d'une icône.
	 */
	public function render_icon( string $icon, string $size = 'md', string $extra_class = '' ): string {
		$paths = $this->get_icon_paths();
		$path  = $paths[ $icon ] ?? $paths['package'];
		$class = trim( 'skmt-icon skmt-icon--' . $size . ' ' . $extra_class );

		return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}

	/**
	 * Dictionnaire minimal des icônes utilisées par l'admin.
	 *
	 * @return array<string, string>
	 */
	private function get_icon_paths(): array {
		return [
			'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
			'package'           => '<path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path>',
			'settings'          => '<path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/>',
			'image'             => '<rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"></path>',
			'check-circle'      => '<circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>',
			'info'              => '<circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path>',
		];
	}

	/**
	 * Injecte le style du separateur dans le menu admin.
	 */
	public function output_menu_separator_css(): void {
		echo '<style>' . $this->get_menu_separator_css() . '</style>';
	}

	/**
	 * Force le parent actif sur les pages du module.
	 */
	public function filter_parent_file( ?string $parent_file ): string {
		$tab = $this->get_current_tab();

		if ( strpos( $tab, 'module_' ) === 0 || in_array( $tab, [ 'dashboard', 'modules', 'settings' ], true ) ) {
			return $this->slug;
		}

		return $parent_file ?? '';
	}

	/**
	 * Force le sous-menu actif sur les pages du module.
	 */
	public function filter_submenu_file( ?string $submenu_file, ?string $parent_file ): string {
		$tab = $this->get_current_tab();

		if ( in_array( $tab, [ 'dashboard', 'modules', 'settings' ], true ) ) {
			return $this->slug . '&tab=' . $tab;
		}

		if ( strpos( $tab, 'module_' ) === 0 ) {
			return $this->slug . '&tab=' . $tab;
		}

		return $submenu_file ?? '';
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
		$scripts = method_exists( $instance, 'get_admin_js' ) ? $instance->get_admin_js() : [];

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

		foreach ( $scripts as $index => $script_url ) {
			if ( empty( $script_url ) ) {
				continue;
			}

			$handle = 'skmt-module-' . $module_id . '-js-' . $index;
			wp_enqueue_script( $handle, $script_url, [], SKMT_VERSION, true );
			$this->localize_admin_script( $handle );
		}
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
		$seen = [];
		$filtered = [];

		foreach ( $submenu[ $this->slug ] as $item ) {
			$label = isset( $item[0] ) ? (string) $item[0] : '';
			$slug  = isset( $item[2] ) ? (string) $item[2] : '';

			if ( $slug === $this->slug || $label === $top_label ) {
				continue;
			}

			$key   = $label . '|' . $slug;

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$filtered[] = $item;
		}

		$submenu[ $this->slug ] = $filtered;
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
	 * Retourne l'ID du module courant si on est sur une page module.
	 */
	private function get_current_module_id(): string {
		$tab = $this->get_current_tab();

		if ( strpos( $tab, 'module_' ) !== 0 ) {
			return '';
		}

		$module_id = substr( $tab, 7 );
		return $this->modules->get( $module_id ) ? $module_id : '';
	}

	/**
	 * Retourne l'onglet courant.
	 */
	private function get_current_tab(): string {
		return isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
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
				'modules_updated'  => __( 'Modules mis à jour.', 'studio-kyne-mini-tools' ),
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
				'auto_updates'   => ! empty( $_POST['skmt_global']['auto_updates'] ),
			];
			$this->settings->set( 'global', $global );
		}

		// Traiter les réglages d'un module
		if ( strpos( $tab, 'module_' ) === 0 ) {
			$module_id = substr( $tab, 7 );
			$instance  = $this->modules->get_active_instances()[ $module_id ] ?? null;

			if ( $instance && isset( $_POST['skmt_module_settings'] ) && is_array( $_POST['skmt_module_settings'] ) ) {
			$instance->save_settings( wp_unslash( $_POST['skmt_module_settings'] ) );
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
		$global = $this->settings->get( 'global', [] );
		$enabled = ! empty( $global['auto_updates'] );

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

		$auto_updates = array_values( array_diff( $auto_updates, [ $plugin_file ] ) );
		update_site_option( 'auto_update_plugins', $auto_updates );
	}

	/**
	 * Gère la mise à jour en masse des modules.
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
				continue;
			}

			$this->modules->deactivate( $module_id );
		}

		$redirect = add_query_arg( [
			'page'             => $this->slug,
			'tab'              => 'modules',
			'skmt_notice'      => 'modules_updated',
			'skmt_notice_type' => 'success',
		], admin_url( 'admin.php' ) );

		wp_safe_redirect( $redirect );
		exit;
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
	 * Retourne le slug de la page.
	 */
	public function get_slug(): string {
		return $this->slug;
	}
}
