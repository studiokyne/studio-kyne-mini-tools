<?php
namespace StudioKyne\MiniTools\Modules\Security;

/**
 * Gestionnaire de rate limiting par IP.
 *
 * Limite les tentatives de connexion: 5 tentatives / 15 min → blocage 30 min.
 * Stockage en post meta. Whitelist IP supportée.
 */
class RateLimiter {

	private const RATE_LIMIT_ATTEMPTS = 5;
	private const RATE_LIMIT_WINDOW = 900;        // 15 min en secondes
	private const RATE_LIMIT_LOCKOUT = 1800;      // 30 min en secondes
	private const META_PREFIX = '_skmt_security_login_attempt_';

	private array $whitelist = [];

	public function __construct( array $whitelist = [] ) {
		$this->whitelist = $whitelist;
	}

	/**
	 * Récupère l'IP du client (supporte proxies).
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
	 * Checker si l'IP est whitelistée.
	 *
	 * @return bool
	 */
	public function is_whitelisted(): bool {
		return in_array( $this->get_client_ip(), $this->whitelist, true );
	}

	/**
	 * Récupère la clé meta pour une IP.
	 *
	 * @param string $ip
	 * @return string
	 */
	private function get_meta_key( string $ip ): string {
		return self::META_PREFIX . md5( $ip );
	}

	/**
	 * Récupère les données de tentative pour une IP.
	 * Format: ['count' => int, 'last_attempt' => int (timestamp), 'locked_until' => int (timestamp)]
	 *
	 * @param string $ip
	 * @return array
	 */
	private function get_attempt_data( string $ip ): array {
		$meta_key = $this->get_meta_key( $ip );
		$data     = (array) get_option( $meta_key, [] );

		return [
			'count'        => $data['count'] ?? 0,
			'last_attempt' => $data['last_attempt'] ?? 0,
			'locked_until' => $data['locked_until'] ?? 0,
		];
	}

	/**
	 * Enregistre une tentative de connexion.
	 *
	 * @param string $ip
	 * @param bool   $success
	 * @return void
	 */
	private function record_attempt( string $ip, bool $success = false ): void {
		$meta_key = $this->get_meta_key( $ip );
		$data     = $this->get_attempt_data( $ip );
		$now      = time();

		// Si succès ou réinitialisation de la fenêtre : reset
		if ( $success || ( $data['last_attempt'] > 0 && ( $now - $data['last_attempt'] ) > self::RATE_LIMIT_WINDOW ) ) {
			update_option( $meta_key, [ 'count' => 0, 'last_attempt' => 0, 'locked_until' => 0 ], false );
			return;
		}

		// Incrémenter le compteur
		$data['count']         = (int) $data['count'] + 1;
		$data['last_attempt']  = $now;
		$data['locked_until']  = ( $data['count'] >= self::RATE_LIMIT_ATTEMPTS ) ? $now + self::RATE_LIMIT_LOCKOUT : 0;

		update_option( $meta_key, $data, false );
	}

	/**
	 * Filtre authenticate : bloque la connexion si l'IP est en lockout.
	 * Retourne un WP_Error pour bloquer, ou $user inchangé pour laisser passer.
	 *
	 * @param \WP_User|\WP_Error|null $user
	 * @return \WP_User|\WP_Error|null
	 */
	public function maybe_block_login( $user ) {
		if ( $this->is_whitelisted() ) {
			return $user;
		}

		$ip   = $this->get_client_ip();
		$data = $this->get_attempt_data( $ip );
		$now  = time();

		if ( $data['locked_until'] > 0 && $now < $data['locked_until'] ) {
			$remaining_minutes = (int) ceil( ( $data['locked_until'] - $now ) / 60 );
			return new \WP_Error(
				'too_many_attempts',
				sprintf(
					__( '<b>Accès bloqué :</b> Trop de tentatives de connexion. Réessayez dans %d minute(s).', 'studio-kyne-mini-tools' ),
					$remaining_minutes
				)
			);
		}

		return $user;
	}

	/**
	 * Enregistrer une tentative échouée.
	 *
	 * @param string $ip
	 * @return void
	 */
	public function log_failed_attempt( string $ip ): void {
		$this->record_attempt( $ip, false );
	}

	/**
	 * Enregistrer une connexion réussie (reset compteur).
	 *
	 * @param string $ip
	 * @return void
	 */
	public function log_successful_login( string $ip ): void {
		$this->record_attempt( $ip, true );
	}

	/**
	 * Récupère l'état actuel du rate limit pour une IP.
	 * Utile pour les logs/monitoring.
	 *
	 * @param string $ip
	 * @return array
	 */
	public function get_attempt_state( string $ip ): array {
		return $this->get_attempt_data( $ip );
	}

	/**
	 * Nettoie les entrées de rate limit expirées.
	 * À appeler périodiquement (ex: via cron).
	 *
	 * @return int Nombre d'entrées supprimées
	 */
	public function cleanup_expired(): int {
		global $wpdb;

		$prefix = self::META_PREFIX;
		$now    = time();

		// Requête pour récupérer toutes les clés de rate limit
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$prefix . '%'
			)
		);

		$deleted = 0;
		foreach ( $results as $row ) {
			$data = maybe_unserialize( $row->option_value );
			// Supprimer si l'entrée est expirée (dernière tentative > 24h)
			if ( isset( $data['last_attempt'] ) && ( $now - $data['last_attempt'] ) > 86400 ) {
				delete_option( $wpdb->options_table . $row->option_id );
				$deleted++;
			}
		}

		return $deleted;
	}
}
