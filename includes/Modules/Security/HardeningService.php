<?php
namespace StudioKyne\MiniTools\Modules\Security;

/**
 * Service de hardening WordPress.
 *
 * Désactiver XML-RPC, empêcher énumération utilisateurs, masquer version WP.
 */
class HardeningService {

	private bool $disable_xmlrpc = false;
	private bool $prevent_user_enum = false;
	private bool $hide_wp_version = false;

	public function __construct(
		bool $disable_xmlrpc = false,
		bool $prevent_user_enum = false,
		bool $hide_wp_version = false
	) {
		$this->disable_xmlrpc    = $disable_xmlrpc;
		$this->prevent_user_enum = $prevent_user_enum;
		$this->hide_wp_version   = $hide_wp_version;
	}

	// === XML-RPC ===

	/**
	 * Désactive XML-RPC via filtre xmlrpc_enabled.
	 */
	public function filter_xmlrpc_enabled(): bool {
		return false;
	}

	/**
	 * Bloque l'accès au serveur XML-RPC avec un 403.
	 */
	public function block_xmlrpc_server_class( string $class ): string {
		http_response_code( 403 );
		exit;
	}

	// === USER ENUMERATION ===

	/**
	 * Hook template_redirect : retourne 403 sur les pages auteur.
	 */
	public function prevent_user_enumeration(): void {
		if ( is_admin() ) {
			return;
		}
		if ( is_author() ) {
			wp_die(
				esc_html__( 'Accès interdit.', 'studio-kyne-mini-tools' ),
				esc_html__( 'Interdit', 'studio-kyne-mini-tools' ),
				[ 'response' => 403 ]
			);
		}
	}

	/**
	 * Hook rest_request_before_callbacks : bloque /wp/v2/users pour les non-admins.
	 *
	 * @param mixed            $response
	 * @param mixed            $handler
	 * @param \WP_REST_Request $request
	 * @return mixed
	 */
	public function prevent_rest_user_enumeration( $response, $handler, \WP_REST_Request $request ) {
		if ( ! current_user_can( 'list_users' ) && strpos( $request->get_route(), '/wp/v2/users' ) !== false ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Accès interdit.', 'studio-kyne-mini-tools' ),
				[ 'status' => 403 ]
			);
		}
		return $response;
	}

	// === HIDE WP VERSION ===

	/**
	 * Hook wp_headers : retire les headers exposant la version.
	 */
	public function hide_wp_version_headers( array $headers ): array {
		unset( $headers['X-Powered-By'] );
		return $headers;
	}

	/**
	 * Hook init : supprime le generator WP de toutes les sorties (head, feeds).
	 */
	public function remove_wp_version_generators(): void {
		$actions = [ 'wp_head', 'rss2_head', 'commentsrss2_head', 'rss_head', 'rdf_header', 'atom_head', 'comments_atom_head', 'opml_head', 'app_head' ];
		foreach ( $actions as $action ) {
			remove_action( $action, 'the_generator' );
			remove_action( $action, 'wp_generator' );
		}
	}

	/**
	 * Filtre script_loader_src / style_loader_src : remplace la version WP par un hash.
	 */
	public function obfuscate_version_in_src( string $src ): string {
		if ( is_admin() ) {
			return $src;
		}
		$version = get_bloginfo( 'version' );
		if ( empty( $version ) ) {
			return $src;
		}
		$hash = substr( md5( $version ), 0, 8 );
		return str_replace( 'ver=' . $version, 'ver=' . $hash, $src );
	}
}
