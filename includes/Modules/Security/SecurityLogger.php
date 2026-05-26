<?php
namespace StudioKyne\MiniTools\Modules\Security;

/**
 * Gestionnaire des logs de sécurité.
 *
 * Logs: connexions, créations/suppressions d'utilisateurs, changements de config.
 * Storage: post meta avec rétention automatique.
 */
class SecurityLogger {

	private const LOG_POST_TYPE = 'skmt_security_log';
	private const LOG_RETENTION_DAYS = 30;
	private bool $enabled = false;

	public function __construct( bool $enabled = false ) {
		$this->enabled = $enabled;
	}

	/**
	 * Crée un log entry.
	 *
	 * @param string $action    Type d'action (login_success, login_fail, user_created, etc.)
	 * @param array  $data      Données additionnelles (user_id, ip, etc.)
	 * @return int|false Post ID ou false si erreur
	 */
	public function log( string $action, array $data = [] ): int|false {
		if ( ! $this->enabled ) {
			return false;
		}

		$log_entry = [
			'timestamp' => current_time( 'mysql' ),
			'action'    => $action,
			'user_id'   => get_current_user_id(),
			'ip'        => $this->get_client_ip(),
			'data'      => $data,
		];

		// Utiliser post type custom pour les logs
		$post_id = wp_insert_post( [
			'post_type'   => self::LOG_POST_TYPE,
			'post_status' => 'private',
			'post_title'  => $action . ' - ' . current_time( 'Y-m-d H:i:s' ),
			'post_author' => get_current_user_id(),
		], true );

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		// Stocker le contenu en JSON dans le post_content
		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => wp_json_encode( $log_entry ),
		] );

		return $post_id;
	}

	/**
	 * Log une tentative de connexion réussie.
	 *
	 * @param \WP_User $user
	 * @return int|false
	 */
	public function log_login_success( \WP_User $user ): int|false {
		return $this->log( 'login_success', [
			'user_id'  => $user->ID,
			'username' => $user->user_login,
			'email'    => $user->user_email,
		] );
	}

	/**
	 * Log une tentative de connexion échouée.
	 *
	 * @param string $username
	 * @param string $ip
	 * @return int|false
	 */
	public function log_login_failed( string $username, string $ip = '' ): int|false {
		return $this->log( 'login_failed', [
			'username' => $username,
			'ip'       => $ip ?: $this->get_client_ip(),
		] );
	}

	/**
	 * Log une création d'utilisateur.
	 *
	 * @param int $user_id
	 * @return int|false
	 */
	public function log_user_created( int $user_id ): int|false {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		return $this->log( 'user_created', [
			'user_id'  => $user->ID,
			'username' => $user->user_login,
			'email'    => $user->user_email,
			'role'     => isset( $user->roles ) ? implode( ', ', $user->roles ) : '',
			'creator'  => get_current_user_id(),
		] );
	}

	/**
	 * Log une suppression d'utilisateur.
	 *
	 * @param int    $user_id
	 * @param string $username
	 * @param string $email
	 * @return int|false
	 */
	public function log_user_deleted( int $user_id, string $username, string $email ): int|false {
		return $this->log( 'user_deleted', [
			'user_id'  => $user_id,
			'username' => $username,
			'email'    => $email,
			'deleted_by' => get_current_user_id(),
		] );
	}

	/**
	 * Log un changement de setting du module.
	 *
	 * @param string $setting Nom du setting
	 * @param mixed  $old_value
	 * @param mixed  $new_value
	 * @return int|false
	 */
	public function log_setting_changed( string $setting, $old_value, $new_value ): int|false {
		return $this->log( 'setting_changed', [
			'setting'   => $setting,
			'old_value' => maybe_serialize( $old_value ),
			'new_value' => maybe_serialize( $new_value ),
			'changed_by' => get_current_user_id(),
		] );
	}

	/**
	 * Log une IP bloquée par rate limiting.
	 *
	 * @param string $ip
	 * @param int    $attempts
	 * @param int    $lockout_duration (en secondes)
	 * @return int|false
	 */
	public function log_ip_blocked( string $ip, int $attempts, int $lockout_duration ): int|false {
		return $this->log( 'ip_blocked', [
			'ip'               => $ip,
			'attempts'         => $attempts,
			'lockout_minutes'  => (int) floor( $lockout_duration / 60 ),
		] );
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
	 * Nettoie les logs expirés (> 30 jours).
	 * À appeler via cron.
	 *
	 * @return int Nombre de logs supprimés
	 */
	public function cleanup_expired_logs(): int {
		$threshold = time() - ( self::LOG_RETENTION_DAYS * DAY_IN_SECONDS );
		$threshold_date = gmdate( 'Y-m-d H:i:s', $threshold );

		$posts = get_posts( [
			'post_type'      => self::LOG_POST_TYPE,
			'posts_per_page' => -1,
			'date_query'     => [
				[
					'before' => $threshold_date,
					'inclusive' => true,
				],
			],
		] );

		$deleted = 0;
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
			$deleted++;
		}

		return $deleted;
	}

	/**
	 * Récupère les logs récents.
	 *
	 * @param int    $limit
	 * @param string $action (optionnel, filtre par action)
	 * @return array
	 */
	public function get_recent_logs( int $limit = 50, string $action = '' ): array {
		$args = [
			'post_type'      => self::LOG_POST_TYPE,
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( ! empty( $action ) ) {
			$args['s'] = $action;
		}

		$posts = get_posts( $args );
		$logs  = [];

		foreach ( $posts as $post ) {
			$data = json_decode( $post->post_content, true );
			if ( is_array( $data ) ) {
				$logs[] = $data;
			}
		}

		return $logs;
	}
}
