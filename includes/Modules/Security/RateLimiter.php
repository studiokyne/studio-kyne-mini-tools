<?php
namespace StudioKyne\MiniTools\Modules\Security;

/**
 * Gestionnaire de rate limiting par IP.
 * Stocke les tentatives via transients WordPress (TTL auto-expiration).
 */
class RateLimiter {

	private const TRANSIENT_PREFIX = '_skmt_rl_';
	private const DEFAULT_ATTEMPTS = 5;
	private const DEFAULT_WINDOW   = 900;   // 15 min
	private const DEFAULT_LOCKOUT  = 1800;  // 30 min
	private const TRANSIENT_TTL    = 86400; // 24 h max de vie

	private int   $max_attempts;
	private int   $window;
	private int   $lockout;
	private array $whitelist;

	public function __construct( array $whitelist = [], int $max_attempts = self::DEFAULT_ATTEMPTS, int $window = self::DEFAULT_WINDOW, int $lockout = self::DEFAULT_LOCKOUT ) {
		$this->whitelist     = $whitelist;
		$this->max_attempts  = max( 1, $max_attempts );
		$this->window        = max( 60, $window );
		$this->lockout       = max( 60, $lockout );
	}

	private function get_client_ip(): string {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			return trim( end( $ips ) ); // dernière IP = la plus fiable contre le spoofing
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return '0.0.0.0';
	}

	public function is_whitelisted(): bool {
		return in_array( $this->get_client_ip(), $this->whitelist, true );
	}

	private function get_transient_key( string $ip ): string {
		return self::TRANSIENT_PREFIX . md5( $ip );
	}

	private function get_attempt_data( string $ip ): array {
		$data = get_transient( $this->get_transient_key( $ip ) );
		if ( ! is_array( $data ) ) {
			return [ 'count' => 0, 'last_attempt' => 0, 'locked_until' => 0 ];
		}
		return [
			'count'        => $data['count']        ?? 0,
			'last_attempt' => $data['last_attempt']  ?? 0,
			'locked_until' => $data['locked_until']  ?? 0,
		];
	}

	private function record_attempt( string $ip, bool $success = false ): void {
		$key  = $this->get_transient_key( $ip );
		$data = $this->get_attempt_data( $ip );
		$now  = time();

		if ( $success ) {
			delete_transient( $key );
			return;
		}

		// Fenêtre expirée → reset le compteur
		if ( $data['last_attempt'] > 0 && ( $now - $data['last_attempt'] ) > $this->window ) {
			$data = [ 'count' => 0, 'last_attempt' => 0, 'locked_until' => 0 ];
		}

		$data['count']++;
		$data['last_attempt'] = $now;
		$data['locked_until'] = ( $data['count'] >= $this->max_attempts ) ? $now + $this->lockout : 0;

		set_transient( $key, $data, self::TRANSIENT_TTL );
	}

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

	public function log_failed_attempt( string $ip ): void {
		$this->record_attempt( $ip, false );
	}

	public function log_successful_login( string $ip ): void {
		$this->record_attempt( $ip, true );
	}

	public function get_attempt_state( string $ip ): array {
		return $this->get_attempt_data( $ip );
	}

	/**
	 * Les transients expirent automatiquement — méthode conservée pour compatibilité.
	 */
	public function cleanup_expired(): int {
		return 0;
	}
}
