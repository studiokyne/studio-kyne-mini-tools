<?php
namespace StudioKyne\MiniTools\Modules\Security;

use StudioKyne\MiniTools\Core\AbstractModule;

/**
 * Module Sécurité.
 *
 * Authentification (rate limiting, URL custom).
 * Hardening (XML-RPC, énumération users, version WP).
 */
class Module extends AbstractModule {

	private RateLimiter $rate_limiter;
	private HardeningService $hardening;
	private LoginUrlHandler $login_handler;
	private array $settings = [];

	/**
	 * Initialise le module et enregistre tous les hooks.
	 */
	public function init(): void {
		$this->settings = $this->get_module_settings( self::get_defaults() );

		$auth = $this->settings['authentication'];
		$this->rate_limiter  = new RateLimiter(
			$auth['rate_limit_whitelist'] ?? [],
			$auth['rate_limit_attempts']  ?? 5,
			$auth['rate_limit_window']    ?? 900,
			$auth['rate_limit_lockout']   ?? 1800
		);
		$this->hardening     = new HardeningService(
			$this->settings['hardening']['disable_xmlrpc'] ?? false,
			$this->settings['hardening']['prevent_user_enum'] ?? false,
			$this->settings['hardening']['hide_wp_version'] ?? false
		);
		$this->login_handler = new LoginUrlHandler( $this->settings['authentication']['custom_login_url'] ?? '/connexion' );

		// === AUTHENTICATION HOOKS ===

		if ( $this->settings['authentication']['rate_limiting'] ?? false ) {
			add_filter( 'authenticate', [ $this->rate_limiter, 'maybe_block_login' ], 999 );
			add_action( 'wp_login', [ $this, 'handle_login_success' ], 10, 2 );
			add_action( 'wp_login_failed', [ $this, 'handle_login_failed' ] );
		}

		if ( $this->settings['authentication']['enable_custom_login_url'] ?? true ) {
			add_action( 'wp_loaded',          [ $this->login_handler, 'wp_loaded' ], 10 );
			add_filter( 'login_url',          [ $this->login_handler, 'filter_login_url' ], 10, 3 );
			add_filter( 'site_url',           [ $this->login_handler, 'filter_site_url' ], 10 );
			add_filter( 'network_site_url',   [ $this->login_handler, 'filter_site_url' ], 10 );
			add_filter( 'wp_redirect',        [ $this->login_handler, 'filter_site_url' ], 10 );
		}

		// === HARDENING HOOKS ===

		if ( $this->settings['hardening']['disable_xmlrpc'] ?? false ) {
			add_filter( 'xmlrpc_enabled', [ $this->hardening, 'filter_xmlrpc_enabled' ] );
			add_filter( 'wp_xmlrpc_server_class', [ $this->hardening, 'block_xmlrpc_server_class' ] );
		}

		if ( $this->settings['hardening']['prevent_user_enum'] ?? false ) {
			add_action( 'template_redirect', [ $this->hardening, 'prevent_user_enumeration' ] );
			add_filter( 'rest_request_before_callbacks', [ $this->hardening, 'prevent_rest_user_enumeration' ], 10, 3 );
		}

		if ( $this->settings['hardening']['hide_wp_version'] ?? false ) {
			add_filter( 'wp_headers', [ $this->hardening, 'hide_wp_version_headers' ] );
			add_action( 'init', [ $this->hardening, 'remove_wp_version_generators' ] );
			add_filter( 'the_generator', '__return_empty_string', PHP_INT_MAX );
			add_filter( 'script_loader_src', [ $this->hardening, 'obfuscate_version_in_src' ], PHP_INT_MAX );
			add_filter( 'style_loader_src', [ $this->hardening, 'obfuscate_version_in_src' ], PHP_INT_MAX );
		}

		// Les transients du rate limiter expirent automatiquement — pas besoin de cron.
	}

	/**
	 * Hook wp_login — reset le compteur de tentatives après connexion réussie.
	 *
	 * @param string   $user_login
	 * @param \WP_User $user
	 * @return void
	 */
	public function handle_login_success( string $user_login, \WP_User $user ): void {
		$this->rate_limiter->log_successful_login( $this->get_client_ip() );
	}

	/**
	 * Hook wp_login_failed — incrémente le compteur de tentatives échouées.
	 *
	 * @param string $username
	 * @return void
	 */
	public function handle_login_failed( string $username ): void {
		$this->rate_limiter->log_failed_attempt( $this->get_client_ip() );
	}

	/**
	 * Hook wp_scheduled_delete — nettoie les entrées de rate limit expirées.
	 *
	 * @return void
	 */
	public function cleanup_rate_limits(): void {
		$this->rate_limiter->cleanup_expired();
	}

	/**
	 * Récupère l'IP du client (supporte proxies et Cloudflare).
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			return trim( $ips[0] );
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return '0.0.0.0';
	}

	/**
	 * Retourne les settings du module.
	 *
	 * @return array
	 */
	public function get_settings(): array {
		return $this->settings;
	}

	/**
	 * Sauvegarde les settings du module.
	 *
	 * @param array $settings
	 * @return bool
	 */
	public function save_settings( array $settings ): bool {
		$current = $this->get_module_settings( self::get_defaults() );

		// Authentication
		$current['authentication']['rate_limiting']           = ! empty( $settings['rate_limiting'] );
		$current['authentication']['rate_limit_attempts']     = isset( $settings['rate_limit_attempts'] ) ? min( 20, max( 1, absint( $settings['rate_limit_attempts'] ) ) ) : 5;
		$current['authentication']['rate_limit_window']       = isset( $settings['rate_limit_window'] ) ? max( 60, absint( $settings['rate_limit_window'] ) ) : 900;
		$current['authentication']['rate_limit_lockout']      = isset( $settings['rate_limit_lockout'] ) ? max( 60, absint( $settings['rate_limit_lockout'] ) ) : 1800;
		$current['authentication']['enable_custom_login_url'] = ! empty( $settings['enable_custom_login_url'] );

		if ( isset( $settings['custom_login_url'] ) ) {
			$url = sanitize_text_field( wp_unslash( $settings['custom_login_url'] ) );
			if ( ! empty( $url ) ) {
				if ( $url[0] !== '/' ) {
					$url = '/' . $url;
				}
				$current['authentication']['custom_login_url'] = $url;
			}
		}

		if ( isset( $settings['rate_limit_whitelist'] ) ) {
			$ips = array_filter( array_map( 'trim', explode( "\n", $settings['rate_limit_whitelist'] ) ) );
			$current['authentication']['rate_limit_whitelist'] = array_values( $ips );
		}

		// Hardening
		$current['hardening']['disable_xmlrpc']    = ! empty( $settings['disable_xmlrpc'] );
		$current['hardening']['prevent_user_enum'] = ! empty( $settings['prevent_user_enum'] );
		$current['hardening']['hide_wp_version']   = ! empty( $settings['hide_wp_version'] );

		return $this->save_module_settings( $current );
	}

	/**
	 * Retourne les CSS du module.
	 *
	 * @return array
	 */
	public function get_admin_css(): array {
		return [
			SKMT_ASSETS_URL . 'admin/css/modules/security-admin.css',
		];
	}

	/**
	 * Retourne les JS du module.
	 *
	 * @return array
	 */
	public function get_admin_js(): array {
		return [
			SKMT_ASSETS_URL . 'admin/js/modules/security-admin.js',
		];
	}

	/**
	 * Retourne les données JS du module.
	 *
	 * @return array
	 */
	public function get_admin_js_data(): array {
		return [
			'i18n' => [
				'settings' => __( 'Paramètres de sécurité mis à jour', 'studio-kyne-mini-tools' ),
			],
		];
	}

	/**
	 * Hook d'activation du module.
	 *
	 * @return void
	 */
	public function on_activate(): void {}

	/**
	 * Hook de désactivation du module.
	 *
	 * @return void
	 */
	public function on_deactivate(): void {}

	/**
	 * Retourne les defaults du module.
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return [
			'authentication' => [
				'enable_custom_login_url' => true,
				'custom_login_url'        => '/connexion',
				'rate_limiting'           => false,
				'rate_limit_attempts'     => 5,
				'rate_limit_window'       => 900,
				'rate_limit_lockout'      => 1800,
				'rate_limit_whitelist'    => [],
			],
			'hardening'      => [
				'disable_xmlrpc'    => false,
				'prevent_user_enum' => true,
				'hide_wp_version'   => true,
			],
		];
	}

	/**
	 * Retourne les clés à supprimer à la désinstallation.
	 *
	 * @return array
	 */
	public static function get_uninstall_keys(): array {
		return [
			'options' => [
				'skmt_module_security',
			],
		];
	}
}
