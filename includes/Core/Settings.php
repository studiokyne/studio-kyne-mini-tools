<?php
namespace StudioKyne\MiniTools\Core;

/**
 * Gère les réglages globaux du plugin.
 */
class Settings {

	/**
	 * Clé d'option WordPress.
	 */
	private string $option_key = 'skmt_settings';

	/**
	 * Cache des settings.
	 */
	private ?array $settings = null;

	/**
	 * Récupère tous les settings.
	 */
	public function get_all(): array {
		if ( null === $this->settings ) {
			$this->settings = get_option( $this->option_key, [] );
		}
		return $this->settings;
	}

	/**
	 * Récupère une valeur de setting.
	 *
	 * @param string $key     Clé du setting (peut être imbriquée avec des points).
	 * @param mixed  $default Valeur par défaut.
	 */
	public function get( string $key, $default = null ) {
		$settings = $this->get_all();

		// Support des clés imbriquées (ex: "modules.image_optimizer")
		$keys = explode( '.', $key );
		$value = $settings;

		foreach ( $keys as $k ) {
			if ( ! is_array( $value ) || ! array_key_exists( $k, $value ) ) {
				return $default;
			}
			$value = $value[ $k ];
		}

		return $value;
	}

	/**
	 * Met à jour une valeur de setting.
	 *
	 * @param string $key   Clé du setting.
	 * @param mixed  $value Nouvelle valeur.
	 */
	public function set( string $key, $value ): bool {
		$settings = $this->get_all();

		// Support des clés imbriquées
		$keys   = explode( '.', $key );
		$target = &$settings;

		foreach ( $keys as $k ) {
			if ( ! is_array( $target ) ) {
				$target = [];
			}
			if ( ! array_key_exists( $k, $target ) ) {
				$target[ $k ] = [];
			}
			$target = &$target[ $k ];
		}

		$target = $value;
		$this->settings = $settings;

		return update_option( $this->option_key, $settings );
	}

	/**
	 * Met à jour plusieurs settings en une fois.
	 *
	 * @param array $data Tableau de settings.
	 */
	public function update( array $data ): bool {
		$settings = $this->get_all();
		$settings = array_merge( $settings, $data );
		$this->settings = $settings;

		return update_option( $this->option_key, $settings );
	}
}