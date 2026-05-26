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
		$this->disable_xmlrpc      = $disable_xmlrpc;
		$this->prevent_user_enum   = $prevent_user_enum;
		$this->hide_wp_version     = $hide_wp_version;
	}

	/**
	 * Hook xmlrpc_enabled pour désactiver XML-RPC.
	 *
	 * @return bool
	 */
	public function filter_xmlrpc_enabled(): bool {
		if ( ! $this->disable_xmlrpc ) {
			return apply_filters( 'xmlrpc_enabled', true );
		}
		return false;
	}

	/**
	 * Hook parse_query pour bloquer énumération users via ?author=.
	 *
	 * @param \WP_Query $query
	 * @return void
	 */
	public function prevent_user_enumeration( \WP_Query $query ): void {
		if ( ! $this->prevent_user_enum || is_admin() ) {
			return;
		}

		if ( isset( $query->query_vars['author'] ) && ! empty( $query->query_vars['author'] ) ) {
			$query->set( 'author', -1 );
			$query->set( 's', '0' );
		}
	}

	/**
	 * Hook rest_authentication_errors pour bloquer énumération users via REST.
	 *
	 * @param mixed $result
	 * @return mixed
	 */
	public function prevent_rest_user_enumeration( $result ) {
		if ( ! $this->prevent_user_enum ) {
			return $result;
		}

		if ( ! is_user_logged_in() && strpos( $_SERVER['REQUEST_URI'] ?? '', '/wp-json/wp/v2/users' ) !== false ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'La requête n\'est pas autorisée.', 'studio-kyne-mini-tools' ),
				[ 'status' => 403 ]
			);
		}

		return $result;
	}

	/**
	 * Hook wp_headers pour retirer headers de version.
	 *
	 * @param array $headers
	 * @return array
	 */
	public function hide_wp_version_headers( array $headers ): array {
		if ( ! $this->hide_wp_version ) {
			return $headers;
		}

		unset( $headers['X-Powered-By'] );
		return $headers;
	}

	/**
	 * Hook wp_footer pour retirer le meta generator.
	 * Appelé via wp_head également (via remove_action).
	 *
	 * @return void
	 */
	public function hide_wp_version_meta(): void {
		if ( ! $this->hide_wp_version ) {
			return;
		}
		remove_action( 'wp_head', 'wp_generator' );
	}
}
