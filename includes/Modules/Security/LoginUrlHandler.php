<?php
namespace StudioKyne\MiniTools\Modules\Security;

/**
 * Gestionnaire de l'URL de connexion.
 *
 * Redirige /wp-login.php vers une URL personnalisée avec tous les paramètres.
 */
class LoginUrlHandler {

	private string $custom_login_url;

	/**
	 * Constructeur.
	 *
	 * @param string $custom_login_url URL de connexion personnalisée (ex: /connexion).
	 */
	public function __construct( string $custom_login_url = '/connexion' ) {
		$this->custom_login_url = $custom_login_url;
	}

	/**
	 * Hook template_redirect pour rediriger /wp-login.php vers l'URL personnalisée.
	 *
	 * @return void
	 */
	public function block_wp_login(): void {
		// Ne pas bloquer dans WP CLI, cron, ou ajax
		if ( defined( 'WP_CLI' ) || wp_doing_cron() || wp_doing_ajax() ) {
			return;
		}

		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		// Vérifier si on accède à /wp-login.php
		if ( strpos( $request_uri, '/wp-login.php' ) === false ) {
			return;
		}

		// Construire l'URL de redirection personnalisée
		$redirect_url = home_url( ltrim( $this->custom_login_url, '/' ) );

		// Préserver tous les paramètres GET (?action=, ?redirect_to=, etc.)
		if ( ! empty( $_GET ) ) {
			$redirect_url = add_query_arg( array_map( 'sanitize_text_field', wp_unslash( $_GET ) ), $redirect_url );
		}

		// Rediriger avec un code 302 (temporaire)
		wp_safe_redirect( $redirect_url, 302 );
		exit;
	}

	/**
	 * Hook login_url pour rediriger les références à wp-login.php vers 404.
	 * Utile pour les plugins/thèmes qui renvoient wp-login.php en lien.
	 *
	 * @param string $login_url
	 * @param string $redirect
	 * @param bool   $force_reauth
	 * @return string
	 */
	public function filter_login_url( string $login_url, string $redirect = '', bool $force_reauth = false ): string {
		// Remplacer /wp-login.php par la page de connexion du builder
		// Ici on laisse la page de connexion custom être gérée par le builder
		// On peut customiser si besoin
		return $login_url;
	}
}
