<?php
namespace StudioKyne\MiniTools\Modules\WhiteLabel;

use StudioKyne\MiniTools\Core\AbstractModule;

/**
 * Module Marque Blanche — nettoyage de la barre d'administration WordPress
 * et personnalisation du footer admin.
 */
class Module extends AbstractModule {

	/**
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	public function __construct( string $id ) {
		parent::__construct( $id );
	}

	public function init(): void {
		$s  = $this->get_settings();
		$ab = $s['admin_bar'];

		if ( ! empty( $ab['hide_wp_logo'] ) ) {
			add_action( 'admin_bar_menu', [ $this, 'remove_wp_logo' ], 999 );
		}
		if ( ! empty( $ab['hide_site_menu'] ) ) {
			add_action( 'admin_bar_menu', [ $this, 'remove_site_menu' ], 999 );
		}
		if ( ! empty( $ab['hide_command_palette'] ) ) {
			add_action( 'admin_bar_menu', [ $this, 'remove_command_palette' ], 999 );
		}
		if ( ! empty( $ab['hide_updates_counter'] ) ) {
			add_action( 'admin_bar_menu', [ $this, 'remove_updates_menu' ], 999 );
		}
		if ( ! empty( $ab['hide_comments_counter'] ) ) {
			add_action( 'admin_bar_menu', [ $this, 'remove_comments_menu' ], 999 );
		}
		if ( ! empty( $ab['hide_new_content_menu'] ) ) {
			add_action( 'admin_bar_menu', [ $this, 'remove_new_content_menu' ], 999 );
		}
		if ( ! empty( $ab['hide_help_button'] ) ) {
			add_action( 'admin_head', [ $this, 'hide_help_button_css' ] );
		}
		if ( ! empty( $ab['hide_screen_options'] ) ) {
			add_action( 'admin_head', [ $this, 'hide_screen_options_css' ] );
		}
		if ( ! empty( $ab['remove_howdy'] ) ) {
			add_filter( 'gettext', [ $this, 'remove_howdy' ], 10, 2 );
		}
		if ( ! empty( $ab['hide_frontend'] ) ) {
			add_filter( 'show_admin_bar', [ $this, 'hide_admin_bar_frontend' ] );
		}

		$footer = $s['footer'];
		if ( ! empty( $footer['left_text'] ) ) {
			add_filter( 'admin_footer_text', [ $this, 'filter_footer_left' ], 20 );
		}
		if ( ! empty( $footer['hide_right_text'] ) || ! empty( $footer['right_text'] ) ) {
			add_filter( 'update_footer', [ $this, 'filter_footer_right' ], 20 );
		}
	}

	/* ================================================================
	 * ADMIN BAR
	 * ================================================================ */

	public function remove_wp_logo( \WP_Admin_Bar $bar ): void {
		$bar->remove_node( 'wp-logo' );
	}

	public function remove_site_menu( \WP_Admin_Bar $bar ): void {
		$bar->remove_node( 'site-name' );
		$bar->remove_node( 'wpadminbar-home' );
	}

	public function remove_command_palette( \WP_Admin_Bar $bar ): void {
		$bar->remove_node( 'command-palette' );
	}

	public function remove_updates_menu( \WP_Admin_Bar $bar ): void {
		$bar->remove_node( 'updates' );
	}

	public function remove_comments_menu( \WP_Admin_Bar $bar ): void {
		$bar->remove_node( 'comments' );
	}

	public function remove_new_content_menu( \WP_Admin_Bar $bar ): void {
		$bar->remove_node( 'new-content' );
	}

	public function hide_help_button_css(): void {
		echo '<style>#contextual-help-link-wrap{display:none!important}</style>';
	}

	public function hide_screen_options_css(): void {
		echo '<style>#screen-options-link-wrap{display:none!important}</style>';
	}

	public function remove_howdy( string $translation, string $text ): string {
		// $text est toujours la chaîne anglaise source, quelle que soit la locale installée.
		if ( 'Howdy, %s' === $text ) {
			return '%s';
		}
		return $translation;
	}

	public function hide_admin_bar_frontend( bool $show ): bool {
		return is_admin() ? $show : false;
	}

	public function filter_footer_left( string $text ): string {
		$custom = $this->settings['footer']['left_text'] ?? '';
		return $custom ? wp_kses_post( $custom ) : $text;
	}

	public function filter_footer_right( string $text ): string {
		if ( ! empty( $this->settings['footer']['hide_right_text'] ) ) {
			return '';
		}
		$custom = $this->settings['footer']['right_text'] ?? '';
		return $custom ? wp_kses_post( $custom ) : $text;
	}

	/* ================================================================
	 * SETTINGS
	 * ================================================================ */

	public function get_settings(): array {
		$this->settings = $this->get_module_settings( static::get_defaults() );
		return $this->settings;
	}

	public function save_settings( array $settings ): bool {
		$data = [
			'admin_bar' => [
				'hide_wp_logo'          => (bool) ( $settings['admin_bar']['hide_wp_logo']          ?? false ),
				'hide_site_menu'        => (bool) ( $settings['admin_bar']['hide_site_menu']        ?? false ),
				'hide_command_palette'  => (bool) ( $settings['admin_bar']['hide_command_palette']  ?? false ),
				'hide_updates_counter'  => (bool) ( $settings['admin_bar']['hide_updates_counter']  ?? false ),
				'hide_comments_counter' => (bool) ( $settings['admin_bar']['hide_comments_counter'] ?? false ),
				'hide_new_content_menu' => (bool) ( $settings['admin_bar']['hide_new_content_menu'] ?? false ),
				'hide_help_button'      => (bool) ( $settings['admin_bar']['hide_help_button']      ?? false ),
				'hide_screen_options'   => (bool) ( $settings['admin_bar']['hide_screen_options']   ?? false ),
				'remove_howdy'          => (bool) ( $settings['admin_bar']['remove_howdy']          ?? false ),
				'hide_frontend'         => (bool) ( $settings['admin_bar']['hide_frontend']         ?? false ),
			],
			'footer' => [
				'left_text'       => wp_kses_post( $settings['footer']['left_text']   ?? '' ),
				'hide_right_text' => ! empty( $settings['footer']['hide_right_text'] ),
				'right_text'      => wp_kses_post( $settings['footer']['right_text']  ?? '' ),
			],
		];

		return $this->save_module_settings( $data );
	}

	public static function get_defaults(): array {
		return [
			'admin_bar' => [
				'hide_wp_logo'          => true,
				'hide_site_menu'        => false,
				'hide_command_palette'  => true,
				'hide_updates_counter'  => false,
				'hide_comments_counter' => true,
				'hide_new_content_menu' => true,
				'hide_help_button'      => true,
				'hide_screen_options'   => false,
				'remove_howdy'          => true,
				'hide_frontend'         => true,
			],
			'footer' => [
				'left_text'       => '',
				'hide_right_text' => false,
				'right_text'      => '',
			],
		];
	}

	public static function get_uninstall_keys(): array {
		return [
			'options' => [ 'skmt_module_white_label' ],
			'meta'    => [],
		];
	}

	/* ================================================================
	 * ASSETS
	 * ================================================================ */

	public function get_admin_css(): array {
		return [ SKMT_ASSETS_URL . 'admin/css/modules/white-label.css' ];
	}

	public function get_admin_js(): array {
		return [];
	}

	public function get_admin_js_data(): array {
		return [];
	}
}
