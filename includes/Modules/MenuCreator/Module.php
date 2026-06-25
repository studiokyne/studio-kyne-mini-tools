<?php
namespace StudioKyne\MiniTools\Modules\MenuCreator;

use StudioKyne\MiniTools\Core\AbstractModule;
use StudioKyne\MiniTools\Modules\WhiteLabel\MenuProfileManager;

/**
 * Module Créateur de menu — gestion des profils de navigation et application aux utilisateurs.
 */
class Module extends AbstractModule {

	public function init(): void {
		// Moteur d'application des menus personnalisés
		$has_active = ! empty( array_filter(
			MenuProfileManager::get_all(),
			fn( $p ) => ( $p['status'] ?? '' ) === 'active'
		) );

		if ( $has_active ) {
			add_filter( 'custom_menu_order', '__return_true' );
			add_filter( 'menu_order',  [ $this, 'apply_menu_order' ],      9999 );
			add_action( 'admin_menu',  [ $this, 'apply_menu_visibility' ], 9999 );
			add_action( 'admin_head',  [ $this, 'inject_menu_icon_overrides' ] );
		}

		// AJAX endpoints
		add_action( 'wp_ajax_skmt_wl_get_wp_menu',       [ $this, 'ajax_get_wp_menu' ] );
		add_action( 'wp_ajax_skmt_wl_save_profile',      [ $this, 'ajax_save_profile' ] );
		add_action( 'wp_ajax_skmt_wl_delete_profile',    [ $this, 'ajax_delete_profile' ] );
		add_action( 'wp_ajax_skmt_wl_duplicate_profile', [ $this, 'ajax_duplicate_profile' ] );
		add_action( 'wp_ajax_skmt_wl_search_users',      [ $this, 'ajax_search_users' ] );

		// Médiathèque WP pour le picker d'icônes
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_media' ] );
	}

	/* ================================================================
	 * MOTEUR DE MENU
	 * ================================================================ */

	public function apply_menu_order( array $menu_order ): array {
		$profile = MenuProfileManager::get_active_for_user( get_current_user_id() );
		if ( ! $profile || empty( $profile['items'] ) ) {
			return $menu_order;
		}

		$slugs     = array_filter( array_map( fn( $item ) => $item['slug'] ?? '', $profile['items'] ) );
		$remaining = array_values( array_diff( $menu_order, $slugs ) );
		return array_values( array_merge( array_values( $slugs ), $remaining ) );
	}

	public function apply_menu_visibility(): void {
		$profile = MenuProfileManager::get_active_for_user( get_current_user_id() );
		if ( ! $profile || empty( $profile['items'] ) ) {
			return;
		}

		global $menu;

		foreach ( $profile['items'] as $item ) {
			$type = $item['type'] ?? 'wp_item';
			$slug = $item['slug'] ?? '';

			if ( 'separator' === $type ) {
				continue;
			}

			if ( ! ( $item['visible'] ?? true ) && ! empty( $slug ) ) {
				remove_menu_page( $slug );
				continue;
			}

			if ( 'custom_link' === $type && ! empty( $item['url'] ) ) {
				$label    = sanitize_text_field( $item['label'] ?? __( 'Lien', 'studio-kyne-mini-tools' ) );
				$icon_url = sanitize_text_field( $item['icon'] ?? 'dashicons-admin-links' );
				add_menu_page( $label, $label, 'read', esc_url_raw( $item['url'] ), '', $icon_url, 999 );
				continue;
			}

			if ( 'wp_item' === $type && ! empty( $slug ) && is_array( $menu ) ) {
				foreach ( $menu as $key => $menu_item ) {
					if ( ! is_array( $menu_item ) || ( $menu_item[2] ?? '' ) !== $slug ) {
						continue;
					}
					if ( isset( $item['label'] ) && $item['label'] !== null ) {
						$menu[ $key ][0] = esc_html( $item['label'] );
					}
					if ( isset( $item['icon'] ) && $item['icon'] !== null && strpos( $item['icon'], 'dashicons-' ) === 0 ) {
						$menu[ $key ][6] = esc_attr( $item['icon'] );
					}
					break;
				}
			}
		}
	}

	public function inject_menu_icon_overrides(): void {
		$profile = MenuProfileManager::get_active_for_user( get_current_user_id() );
		if ( ! $profile || empty( $profile['items'] ) ) {
			return;
		}

		$css = '';
		foreach ( $profile['items'] as $item ) {
			if ( empty( $item['slug'] ) || empty( $item['icon'] ) ) {
				continue;
			}
			$icon     = $item['icon'];
			$css_slug = esc_attr( sanitize_text_field( $item['slug'] ) );

			if ( strpos( $icon, 'svg:' ) === 0 ) {
				$svg_b64 = substr( $icon, 4 );
				if ( base64_decode( $svg_b64, true ) === false ) {
					continue;
				}
				$img_src = 'data:image/svg+xml;base64,' . esc_attr( $svg_b64 );
			} elseif ( strpos( $icon, 'http' ) === 0 ) {
				$img_src = esc_url( $icon );
			} else {
				continue;
			}

			$css .= '#adminmenu a[href*="' . $css_slug . '"] .wp-menu-image{'
				. 'background-image:url("' . $img_src . '")!important;'
				. 'background-position:center center!important;background-repeat:no-repeat!important;background-size:20px!important}'
				. '#adminmenu a[href*="' . $css_slug . '"] .wp-menu-image::before{content:""!important}';
		}

		if ( $css ) {
			echo '<style>' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/* ================================================================
	 * AJAX
	 * ================================================================ */

	public function ajax_get_wp_menu(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) ] );
		}

		if ( ! did_action( 'admin_menu' ) ) {
			do_action( 'admin_menu', '' );
		}

		global $menu, $submenu;
		$clean_menu = [];
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( ! is_array( $item ) ) continue;
				$clean_menu[] = [
					'label' => wp_strip_all_tags( $item[0] ?? '' ),
					'cap'   => $item[1] ?? 'read',
					'slug'  => $item[2] ?? '',
					'icon'  => $item[6] ?? '',
				];
			}
		}
		$clean_submenu = [];
		if ( is_array( $submenu ) ) {
			foreach ( $submenu as $parent_slug => $sub_items ) {
				$clean_submenu[ $parent_slug ] = [];
				foreach ( (array) $sub_items as $item ) {
					if ( ! is_array( $item ) ) continue;
					$clean_submenu[ $parent_slug ][] = [
						'label' => wp_strip_all_tags( $item[0] ?? '' ),
						'cap'   => $item[1] ?? 'read',
						'slug'  => $item[2] ?? '',
					];
				}
			}
		}
		wp_send_json_success( [ 'menu' => $clean_menu, 'submenu' => $clean_submenu ] );
	}

	public function ajax_save_profile(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) ] );
		}

		$raw = isset( $_POST['profile'] ) ? wp_unslash( $_POST['profile'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			wp_send_json_error( [ 'message' => __( 'Données manquantes.', 'studio-kyne-mini-tools' ) ] );
		}

		$profile = json_decode( $raw, true );
		if ( ! is_array( $profile ) ) {
			wp_send_json_error( [ 'message' => __( 'JSON invalide.', 'studio-kyne-mini-tools' ) ] );
		}

		$sanitized = $this->sanitize_profile( $profile );
		MenuProfileManager::save( $sanitized );
		wp_send_json_success( [ 'profile' => $sanitized ] );
	}

	public function ajax_delete_profile(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) ] );
		}

		$profile_id = isset( $_POST['profile_id'] ) ? sanitize_text_field( wp_unslash( $_POST['profile_id'] ) ) : '';
		if ( empty( $profile_id ) ) {
			wp_send_json_error( [ 'message' => __( 'ID manquant.', 'studio-kyne-mini-tools' ) ] );
		}

		MenuProfileManager::delete( $profile_id );
		wp_send_json_success();
	}

	public function ajax_duplicate_profile(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) ] );
		}

		$profile_id = isset( $_POST['profile_id'] ) ? sanitize_text_field( wp_unslash( $_POST['profile_id'] ) ) : '';
		$original   = MenuProfileManager::get( $profile_id );
		if ( ! $original ) {
			wp_send_json_error( [ 'message' => __( 'Profil introuvable.', 'studio-kyne-mini-tools' ) ] );
		}

		$copy               = $original;
		$copy['id']         = wp_generate_uuid4();
		$copy['name']       = $original['name'] . ' ' . __( '(copie)', 'studio-kyne-mini-tools' );
		$copy['status']     = 'draft';
		$copy['updated_at'] = time();

		MenuProfileManager::save( $copy );
		wp_send_json_success( [ 'profile' => $copy ] );
	}

	public function ajax_search_users(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$query = sanitize_text_field( $_GET['q'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$users = get_users( [ 'search' => '*' . $query . '*', 'number' => 20 ] );

		$result = array_map(
			fn( \WP_User $u ) => [
				'id'    => $u->ID,
				'label' => $u->display_name . ' (' . $u->user_login . ')',
			],
			$users
		);
		wp_send_json_success( $result );
	}

	/* ================================================================
	 * HELPERS
	 * ================================================================ */

	private function sanitize_profile( array $profile ): array {
		return [
			'id'            => ! empty( $profile['id'] ) ? sanitize_text_field( $profile['id'] ) : wp_generate_uuid4(),
			'name'          => sanitize_text_field( $profile['name'] ?? '' ),
			'status'        => in_array( $profile['status'] ?? '', [ 'draft', 'active' ], true ) ? $profile['status'] : 'draft',
			'apply_to_all'  => ! empty( $profile['apply_to_all'] ),
			'include_roles' => array_map( 'sanitize_key', (array) ( $profile['include_roles'] ?? [] ) ),
			'include_users' => array_map( 'absint', (array) ( $profile['include_users'] ?? [] ) ),
			'exclude_roles' => array_map( 'sanitize_key', (array) ( $profile['exclude_roles'] ?? [] ) ),
			'exclude_users' => array_map( 'absint', (array) ( $profile['exclude_users'] ?? [] ) ),
			'items'         => $this->sanitize_menu_items( (array) ( $profile['items'] ?? [] ) ),
			'updated_at'    => time(),
		];
	}

	private function sanitize_menu_items( array $items, int $depth = 0 ): array {
		$sanitized = [];
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) continue;
			$type     = in_array( $item['type'] ?? '', [ 'wp_item', 'custom_link', 'separator' ], true )
				? $item['type'] : 'wp_item';
			$children = [];
			if ( 0 === $depth && ! empty( $item['children'] ) ) {
				$children = $this->sanitize_menu_items( (array) $item['children'], 1 );
			}
			$sanitized[] = [
				'type'         => $type,
				'slug'         => sanitize_text_field( $item['slug'] ?? '' ),
				'label'        => isset( $item['label'] ) && $item['label'] !== null ? sanitize_text_field( $item['label'] ) : null,
				'icon'         => isset( $item['icon'] ) && $item['icon'] !== null ? sanitize_text_field( $item['icon'] ) : null,
				'visible'      => isset( $item['visible'] ) ? (bool) $item['visible'] : true,
				'target_blank' => ! empty( $item['target_blank'] ),
				'url'          => 'custom_link' === $type ? esc_url_raw( $item['url'] ?? '' ) : '',
				'children'     => $children,
			];
		}
		return $sanitized;
	}

	/* ================================================================
	 * SETTINGS
	 * ================================================================ */

	public function get_settings(): array {
		return [];
	}

	public function save_settings( array $settings ): bool {
		return true;
	}

	public static function get_defaults(): array {
		return [];
	}

	public static function get_uninstall_keys(): array {
		return [
			'options' => [ MenuProfileManager::OPTION_KEY ],
			'meta'    => [],
		];
	}

	/* ================================================================
	 * ASSETS
	 * ================================================================ */

	public function get_admin_css(): array {
		return [ SKMT_ASSETS_URL . 'admin/css/modules/menu-creator.css' ];
	}

	public function get_admin_js(): array {
		return [ SKMT_ASSETS_URL . 'admin/js/modules/menu-creator.js' ];
	}

	public function get_admin_js_data(): array {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data = [
			'mcProfiles' => MenuProfileManager::get_all(),
			'i18n'       => [
				'newMenu'          => __( 'Nouveau menu', 'studio-kyne-mini-tools' ),
				'draft'            => __( 'Brouillon', 'studio-kyne-mini-tools' ),
				'active'           => __( 'Actif', 'studio-kyne-mini-tools' ),
				'unsavedChanges'   => __( 'Modifications non sauvegardées', 'studio-kyne-mini-tools' ),
				'leaveConfirm'     => __( 'Vos modifications seront perdues. Continuer ?', 'studio-kyne-mini-tools' ),
				'deleteConfirmMsg' => __( 'Cette action est irréversible.', 'studio-kyne-mini-tools' ),
			],
		];

		if ( 'module_menu_creator' === $tab && is_admin() && current_user_can( 'manage_options' ) ) {
			global $menu, $submenu;
			$wp_menu = [];
			if ( is_array( $menu ) ) {
				foreach ( $menu as $item ) {
					if ( ! is_array( $item ) ) continue;
					$wp_menu[] = [
						'label' => wp_strip_all_tags( $item[0] ?? '' ),
						'cap'   => $item[1] ?? 'read',
						'slug'  => $item[2] ?? '',
						'icon'  => $item[6] ?? '',
					];
				}
			}
			$wp_submenu = [];
			if ( is_array( $submenu ) ) {
				foreach ( $submenu as $parent => $subs ) {
					$wp_submenu[ $parent ] = [];
					foreach ( (array) $subs as $item ) {
						if ( ! is_array( $item ) ) continue;
						$wp_submenu[ $parent ][] = [
							'label' => wp_strip_all_tags( $item[0] ?? '' ),
							'cap'   => $item[1] ?? 'read',
							'slug'  => $item[2] ?? '',
						];
					}
				}
			}
			$recent_users = get_users( [ 'number' => 30, 'orderby' => 'registered', 'order' => 'DESC' ] );
			$data['wpMenu']        = $wp_menu;
			$data['wpSubmenu']     = $wp_submenu;
			$data['wpRoles']       = wp_roles()->get_names();
			$data['wpRecentUsers'] = array_map( function ( \WP_User $u ) {
				return [
					'id'    => (int) $u->ID,
					'label' => $u->display_name . ' (' . $u->user_login . ')',
				];
			}, $recent_users );
			$data['iconLibrary'] = $this->get_lucide_icons();
		}

		return $data;
	}

	/**
	 * Bibliothèque d'icônes Lucide embarquées (MIT).
	 * Retourne un tableau associatif slug → contenu SVG (sans le wrapper <svg>).
	 *
	 * @return array<string, string>
	 */
	private function get_lucide_icons(): array {
		$w = 'xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
		$icons = [
			'home'           => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
			'layout'         => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><line x1="3" x2="21" y1="9" y2="9"/><line x1="9" x2="9" y1="21" y2="9"/>',
			'settings'       => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
			'user'           => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
			'users'          => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
			'shield'         => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
			'lock'           => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
			'key'            => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
			'mail'           => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
			'bell'           => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
			'calendar'       => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
			'file-text'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
			'folder'         => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
			'image'          => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
			'link'           => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
			'pen'            => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
			'star'           => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
			'bar-chart'      => '<line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/>',
			'download'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
			'upload'         => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
			'clock'          => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
			'info'           => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
			'phone'          => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.15 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3 1.19h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.83a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
			'globe'          => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
			'tag'            => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
			'book-open'      => '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',
			'message'        => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
			'package'        => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7"/>',
			'grid'           => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
			'list'           => '<line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/>',
			'wrench'         => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
			'code'           => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
			'database'       => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>',
			'cloud'          => '<path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>',
			'layers'         => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
			'zap'            => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
			'heart'          => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
			'bookmark'       => '<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>',
			'help-circle'    => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
			'alert-circle'   => '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
			'check-circle'   => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
			'palette'        => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
			'shopping-cart'  => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
			'credit-card'    => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
			'map-pin'        => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
			'truck'          => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
			'chart-pie'      => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
			'activity'       => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
			'search'         => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
			'filter'         => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
			'sliders'        => '<line x1="4" x2="4" y1="21" y2="14"/><line x1="4" x2="4" y1="6" y2="3"/><line x1="12" x2="12" y1="21" y2="12"/><line x1="12" x2="12" y1="6" y2="3"/><line x1="20" x2="20" y1="21" y2="16"/><line x1="20" x2="20" y1="8" y2="3"/><line x1="1" x2="7" y1="14" y2="14"/><line x1="9" x2="15" y1="12" y2="12"/><line x1="17" x2="23" y1="16" y2="16"/>',
			'server'         => '<rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/><line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/>',
			'flag'           => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/>',
		];

		$result = [];
		foreach ( $icons as $name => $paths ) {
			$result[ $name ] = '<svg ' . $w . '>' . $paths . '</svg>';
		}
		return $result;
	}

	public function maybe_enqueue_media( string $hook ): void {
		if ( false === strpos( $hook, 'studio-kyne-mini-tools' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'module_menu_creator' === $tab ) {
			wp_enqueue_media();
		}
	}
}
