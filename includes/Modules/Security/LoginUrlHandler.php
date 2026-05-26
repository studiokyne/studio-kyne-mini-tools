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
	 * Hook wp_loaded pour intercepter et servir l'URL de connexion personnalisée.
	 *
	 * Enregistré sur 'wp_loaded' (qui fire APRÈS 'init') : seul point d'entrée
	 * possible puisque Module::init() lui-même est appelé depuis le hook 'init'.
	 *
	 * @return void
	 */
	public function wp_loaded(): void {
		if ( defined( 'WP_CLI' ) || wp_doing_cron() || wp_doing_ajax() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request     = wp_parse_url( rawurldecode( $request_uri ) );

		if ( empty( $request['path'] ) || ! $this->is_custom_login_uri( $request['path'] ) ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$user        = wp_get_current_user();
			$redirect_to = apply_filters( 'skmt_custom_login_redirect', admin_url(), $user );
			wp_safe_redirect( $redirect_to );
			die();
		}

		require_once ABSPATH . 'wp-login.php';
		die;
	}

	/**
	 * Hook template_redirect pour bloquer l'accès direct à /wp-login.php.
	 *
	 * @return void
	 */
	public function block_wp_login(): void {
		if ( defined( 'WP_CLI' ) || wp_doing_cron() || wp_doing_ajax() ) {
			return;
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

		// Vérifier si on accède directement à /wp-login.php
		if ( strpos( $request_uri, '/wp-login.php' ) === false ) {
			return;
		}

		// Rediriger vers l'URL personnalisée avec les paramètres GET
		$redirect_url = home_url( $this->custom_login_url . '/' );

		if ( ! empty( $_GET ) ) {
			$redirect_url = add_query_arg( array_map( 'sanitize_text_field', wp_unslash( $_GET ) ), $redirect_url );
		}

		wp_safe_redirect( $redirect_url, 302 );
		exit;
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
		// Remplacer wp-login.php par la page de connexion personnalisée
		if ( strpos( $login_url, 'wp-login.php' ) !== false ) {
			$args = explode( '?', $login_url );

			if ( isset( $args[1] ) ) {
				parse_str( $args[1], $params );
				$login_url = add_query_arg( $params, home_url( $this->custom_login_url . '/' ) );
			} else {
				$login_url = home_url( $this->custom_login_url . '/' );
			}
		}

		return $login_url;
	}
}
