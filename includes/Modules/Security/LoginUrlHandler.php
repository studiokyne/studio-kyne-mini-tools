<?php
namespace StudioKyne\MiniTools\Modules\Security;

/**
 * Gestionnaire de l'URL de connexion.
 *
 * Bloque accès à /wp-login.php en renvoyant 404.
 */
class LoginUrlHandler {

	private string $custom_login_url;

	/**
	 * Constructeur.
	 *
	 * @param string $custom_login_url URL de connexion personnalisée.
	 */
	public function __construct( string $custom_login_url = '/connexion' ) {
		$this->custom_login_url = $custom_login_url;
	}

	/**
	 * Hook template_redirect pour bloquer /wp-login.php.
	 *
	 * @return void
	 */
	public function block_wp_login(): void {
		// Ne pas bloquer dans WP CLI, cron, ou ajax
		if ( defined( 'WP_CLI' ) || wp_doing_cron() || wp_doing_ajax() ) {
			return;
		}

		// Checker si on accède à /wp-login.php
		if ( strpos( $_SERVER['REQUEST_URI'] ?? '', '/wp-login.php' ) !== false ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			get_template_part( 404 );
			exit;
		}
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
