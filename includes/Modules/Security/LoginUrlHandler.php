<?php
namespace StudioKyne\MiniTools\Modules\Security;

/**
 * Gestionnaire de l'URL de connexion.
 *
 * Intercepte les requêtes vers l'URL personnalisée et les traite comme wp-login.php
 * sans dépendre de la configuration du serveur (Nginx, Apache).
 */
class LoginUrlHandler {

	private string $custom_login_url;

	/**
	 * Constructeur.
	 *
	 * @param string $custom_login_url URL de connexion personnalisée (ex: /connexion).
	 */
	public function __construct( string $custom_login_url = '/connexion' ) {
		$this->custom_login_url = ltrim( $custom_login_url, '/' );
	}

	/**
	 * Vérifie si l'URI actuelle correspond à la connexion personnalisée.
	 *
	 * @param string $uri
	 * @return bool
	 */
	private function is_custom_login_uri( string $uri ): bool {
		$pattern = '/^\/?' . preg_quote( $this->custom_login_url, '/' ) . '(\/|\?|$)/';
		return (bool) preg_match( $pattern, $uri );
	}

	/**
	 * Hook wp_loaded : gère à la fois le blocage de wp-login.php et le service de l'URL personnalisée.
	 *
	 * wp_loaded fire dans les deux cas de figure :
	 * - Requête via index.php (URL custom /connexion)
	 * - Requête directe wp-login.php (celui-ci charge wp-load.php qui fire tous les hooks)
	 *
	 * @return void
	 */
	public function wp_loaded(): void {
		if ( defined( 'WP_CLI' ) || wp_doing_cron() || wp_doing_ajax() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request     = wp_parse_url( rawurldecode( $request_uri ) );
		$path        = $request['path'] ?? '';

		// Bloquer l'accès direct à wp-login.php : remplacer l'URI par une URL
		// inexistante et laisser WordPress générer un vrai 404 via son template.
		if ( strpos( rawurldecode( $request_uri ), 'wp-login.php' ) !== false && ! is_admin() ) {
			global $pagenow;
			$pagenow = 'index.php';

			if ( ! defined( 'WP_USE_THEMES' ) ) {
				define( 'WP_USE_THEMES', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
			}

			$_SERVER['REQUEST_URI'] = '/' . str_repeat( '-/', 10 );

			wp();
			require_once ABSPATH . WPINC . '/template-loader.php';
			die;
		}

		// Servir l'URL de connexion personnalisée (/connexion)
		if ( empty( $path ) || ! $this->is_custom_login_uri( $path ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		if ( is_user_logged_in() && 'logout' !== $action ) {
			$user        = wp_get_current_user();
			$redirect_to = apply_filters( 'skmt_custom_login_redirect', admin_url(), $user );
			wp_safe_redirect( $redirect_to );
			die();
		}

		global $error, $user_login;
		$error      = '';
		$user_login = '';

		require_once ABSPATH . 'wp-login.php';
		die;
	}

	/**
	 * Filtre site_url() et network_site_url() pour remplacer wp-login.php
	 * par l'URL personnalisée. Couvre notamment l'action du formulaire de connexion.
	 *
	 * @param string $url
	 * @return string
	 */
	public function filter_site_url( string $url ): string {
		if ( strpos( $url, 'wp-login.php?action=postpass' ) !== false ) {
			return $url;
		}

		if ( strpos( $url, 'wp-login.php' ) !== false ) {
			$parts = explode( '?', $url, 2 );
			$base  = home_url( $this->custom_login_url . '/' );

			if ( isset( $parts[1] ) ) {
				parse_str( $parts[1], $params );
				return add_query_arg( $params, $base );
			}

			return $base;
		}

		return $url;
	}

	/**
	 * Filtre les URLs de connexion pour pointer vers l'URL personnalisée.
	 *
	 * @param string $login_url
	 * @param string $redirect
	 * @param bool   $force_reauth
	 * @return string
	 */
	public function filter_login_url( string $login_url, string $redirect = '', bool $force_reauth = false ): string {
		return $this->filter_site_url( $login_url );
	}
}
