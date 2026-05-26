<?php
namespace StudioKyne\MiniTools\Modules\Security;

use StudioKyne\MiniTools\Core\AbstractModule;

/**
 * Module Sécurité.
 *
 * Authentification (force PDP, rate limiting, URL custom, registration).
 * Hardening (XML-RPC, énumération users, version WP).
 * Logging (connexions, actions users, settings).
 */
class Module extends AbstractModule {

	private PasswordValidator $password_validator;
	private RateLimiter $rate_limiter;
	private HardeningService $hardening;
	private LoginUrlHandler $login_handler;
	private SecurityLogger $logger;
	private array $settings = [];

	/**
	 * Initialise le module et enregistre tous les hooks.
	 */
	public function init(): void {
		$this->settings = $this->get_module_settings( self::get_defaults() );

		// Créer les services avec les settings actuels
		$this->password_validator = new PasswordValidator();
		$this->rate_limiter       = new RateLimiter( $this->settings['authentication']['rate_limit_whitelist'] ?? [] );
		$this->hardening          = new HardeningService(
			$this->settings['hardening']['disable_xmlrpc'] ?? false,
			$this->settings['hardening']['prevent_user_enum'] ?? false,
			$this->settings['hardening']['hide_wp_version'] ?? false
		);
		$this->login_handler      = new LoginUrlHandler( $this->settings['authentication']['custom_login_url'] ?? '/connexion' );
		$this->logger             = new SecurityLogger( $this->settings['logging']['log_connections'] ?? false );

		// === AUTHENTICATION HOOKS ===

		// Force du mot de passe à la création d'utilisateur
		if ( $this->settings['authentication']['password_strength'] ?? false ) {
			add_action( 'user_profile_update_errors', [ $this->password_validator, 'validate_user_password' ], 10, 3 );
		}

		// Rate limiting
		if ( $this->settings['authentication']['rate_limiting'] ?? false ) {
			add_action( 'login_form_login', [ $this->rate_limiter, 'check_rate_limit' ] );
			add_action( 'wp_authenticate_user', [ $this, 'handle_authentication_result' ], 10, 2 );
		}

		// URL personnalisée de connexion
		if ( $this->settings['authentication']['enable_custom_login_url'] ?? false ) {
			add_action( 'plugins_loaded', [ $this->login_handler, 'plugins_loaded' ], 9 );
			add_action( 'wp_loaded', [ $this->login_handler, 'wp_loaded' ], 10 );
			add_action( 'template_redirect', [ $this->login_handler, 'block_wp_login' ], 10 );
			add_filter( 'login_url', [ $this->login_handler, 'filter_login_url' ], 10, 3 );
		}

		// Désactiver inscription publique
		if ( $this->settings['authentication']['disable_registration'] ?? false ) {
			add_filter( 'option_users_can_register', '__return_false' );
		}

		// === HARDENING HOOKS ===

		if ( $this->settings['hardening']['disable_xmlrpc'] ?? false ) {
			add_filter( 'xmlrpc_enabled', [ $this->hardening, 'filter_xmlrpc_enabled' ] );
		}

		if ( $this->settings['hardening']['prevent_user_enum'] ?? false ) {
			add_action( 'parse_query', [ $this->hardening, 'prevent_user_enumeration' ] );
			add_filter( 'rest_authentication_errors', [ $this->hardening, 'prevent_rest_user_enumeration' ] );
		}

		if ( $this->settings['hardening']['hide_wp_version'] ?? false ) {
			add_filter( 'wp_headers', [ $this->hardening, 'hide_wp_version_headers' ] );
			add_action( 'wp_head', [ $this->hardening, 'hide_wp_version_meta' ] );
		}

		// === LOGGING HOOKS ===

		if ( $this->settings['logging']['log_connections'] ?? false ) {
			add_action( 'wp_login', [ $this, 'handle_login_success' ], 10, 2 );
			add_action( 'wp_authentication_failed', [ $this, 'handle_login_failed' ] );
		}

		if ( $this->settings['logging']['log_user_actions'] ?? false ) {
			add_action( 'user_register', [ $this->logger, 'log_user_created' ] );
			add_action( 'delete_user', [ $this, 'handle_delete_user' ] );
		}

		// === CRON & CLEANUP ===

		add_action( 'wp_scheduled_delete', [ $this, 'cleanup_logs_and_limits' ] );
	}

	/**
	 * Hook wp_login - log les connexions réussies.
	 *
	 * @param string  $user_login
	 * @param \WP_User $user
	 * @return void
	 */
	public function handle_login_success( string $user_login, \WP_User $user ): void {
		$this->logger->log_login_success( $user );

		// Reset rate limiting après succès
		if ( $this->settings['authentication']['rate_limiting'] ?? false ) {
			$ip = $this->get_client_ip();
			$this->rate_limiter->log_successful_login( $ip );
		}
	}

	/**
	 * Hook wp_authentication_failed - log les connexions échouées.
	 *
	 * @param \WP_Error|\WP_User $user
	 * @return void
	 */
	public function handle_login_failed( $user ): void {
		$username = '';
		$ip       = $this->get_client_ip();

		if ( is_wp_error( $user ) ) {
			// Récupérer le username depuis $_POST si on peut
			$username = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : 'unknown';
		} elseif ( $user instanceof \WP_User ) {
			$username = $user->user_login;
		}

		$this->logger->log_login_failed( $username, $ip );

		// Incrémenter le rate limit après fail
		if ( $this->settings['authentication']['rate_limiting'] ?? false ) {
			$this->rate_limiter->log_failed_attempt( $ip );
		}
	}

	/**
	 * Hook wp_authenticate_user pour tracker authentification en temps réel.
	 *
	 * @param \WP_User|\WP_Error $user
	 * @param string             $password
	 * @return \WP_User|\WP_Error
	 */
	public function handle_authentication_result( $user, string $password ) {
		// Ne rien faire si c'est une erreur (déjà loggée par wp_authentication_failed)
		return $user;
	}

	/**
	 * Hook delete_user - log les suppressions d'utilisateurs.
	 *
	 * @param int $user_id
	 * @return void
	 */
	public function handle_delete_user( int $user_id ): void {
		$user = get_user_by( 'id', $user_id );
		if ( $user ) {
			$this->logger->log_user_deleted( $user->ID, $user->user_login, $user->user_email );
		}
	}

	/**
	 * Hook wp_scheduled_delete - nettoie les vieux logs et entrées de rate limit.
	 *
	 * @return void
	 */
	public function cleanup_logs_and_limits(): void {
		$this->logger->cleanup_expired_logs();
		$this->rate_limiter->cleanup_expired();
	}

	/**
	 * Récupère l'IP du client.
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
	 * Reconstruit la structure imbriquée à partir des clés plates du formulaire.
	 *
	 * @param array $settings
	 * @return bool
	 */
	public function save_settings( array $settings ): bool {
		$current = $this->get_module_settings( self::get_defaults() );

		// Authentication
		$current['authentication']['password_strength']   = ! empty( $settings['password_strength'] );
		$current['authentication']['rate_limiting']       = ! empty( $settings['rate_limiting'] );
		$current['authentication']['rate_limit_attempts'] = isset( $settings['rate_limit_attempts'] ) ? min( 20, max( 1, absint( $settings['rate_limit_attempts'] ) ) ) : 5;
		$current['authentication']['rate_limit_window']   = isset( $settings['rate_limit_window'] ) ? max( 60, absint( $settings['rate_limit_window'] ) ) : 900;
		$current['authentication']['rate_limit_lockout']     = isset( $settings['rate_limit_lockout'] ) ? max( 60, absint( $settings['rate_limit_lockout'] ) ) : 1800;
		$current['authentication']['disable_registration']  = ! empty( $settings['disable_registration'] );
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

		// Logging
		$current['logging']['log_connections']      = ! empty( $settings['log_connections'] );
		$current['logging']['log_user_actions']     = ! empty( $settings['log_user_actions'] );
		$current['logging']['log_settings_changes'] = ! empty( $settings['log_settings_changes'] );
		$current['logging']['log_retention_days']   = isset( $settings['log_retention_days'] ) ? min( 365, max( 1, absint( $settings['log_retention_days'] ) ) ) : 30;

		return $this->save_module_settings( $current );
	}

	/**
	 * Retourne les CSS du module.
	 *
	 * @return array
	 */
	public function get_admin_css(): array {
		return [
			SKMT_PLUGIN_URL . 'assets/css/modules/security-admin.css',
		];
	}

	/**
	 * Retourne les JS du module.
	 *
	 * @return array
	 */
	public function get_admin_js(): array {
		return [
			SKMT_PLUGIN_URL . 'assets/js/modules/security-admin.js',
		];
	}

	/**
	 * Retourne les données JS du module (i18n, data).
	 *
	 * @return array
	 */
	public function get_admin_js_data(): array {
		return [
			'i18n' => [
				'passwordRequirements' => $this->password_validator->get_requirements_text(),
				'settings'             => __( 'Paramètres de sécurité mis à jour', 'studio-kyne-mini-tools' ),
			],
		];
	}

	/**
	 * Hook d'activation du module.
	 *
	 * @return void
	 */
	public function on_activate(): void {
		// Pas de règles de réécriture nécessaires - tout est géré via hooks PHP
	}

	/**
	 * Hook de désactivation du module.
	 *
	 * @return void
	 */
	public function on_deactivate(): void {
		// Pas de nettoyage nécessaire - les hooks sont automatiquement désactivés
	}

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
				'password_strength'       => true,
				'rate_limiting'           => false,
				'rate_limit_attempts'     => 5,
				'rate_limit_window'       => 900,
				'rate_limit_lockout'      => 1800,
				'rate_limit_whitelist'    => [],
				'disable_registration'    => true,
			],
			'hardening'      => [
				'disable_xmlrpc'       => false,
				'prevent_user_enum'    => true,
				'hide_wp_version'      => true,
			],
			'logging'        => [
				'log_connections'      => false,
				'log_user_actions'     => false,
				'log_settings_changes' => false,
				'log_retention_days'   => 30,
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
