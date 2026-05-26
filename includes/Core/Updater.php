<?php
namespace StudioKyne\MiniTools\Core;

/**
 * Updater GitHub pour le plugin.
 * Vérifie les mises à jour depuis un dépôt GitHub.
 */
class Updater {

	/**
	 * Utilisateur GitHub.
	 */
	private string $github_user = 'studiokyne';

	/**
	 * Nom du dépôt GitHub.
	 */
	private string $github_repo = 'studio-kyne-mini-tools';

	/**
	 * Transient pour le cache des mises à jour.
	 */
	private string $transient_key = 'skmt_github_update';

	/**
	 * Canal de mise a jour.
	 */
	private string $channel = 'stable';

	/**
	 * Durée du cache (12 heures).
	 */
	private int $cache_duration = 43200;

	/**
	 * Initialise l'updater.
	 */
	public function init(): void {
		$settings = get_option( 'skmt_settings', [] );

		if ( ! empty( $settings['global']['update_channel'] ) ) {
			$this->channel = sanitize_key( $settings['global']['update_channel'] );
		}

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
	}

	/**
	 * Vérifie les mises à jour disponibles.
	 *
	 * @param object $transient Données du transient.
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = $this->get_remote_version();

		if ( false === $remote ) {
			return $transient;
		}

		$plugin_file = plugin_basename( SKMT_PLUGIN_FILE );

		// Comparer les versions
		$has_update = $this->compare_versions( SKMT_VERSION, $remote['version'] );

		if ( $has_update ) {
			$transient->response[ $plugin_file ] = (object) [
				'slug'        => dirname( $plugin_file ),
				'plugin'      => $plugin_file,
				'new_version' => $remote['version'],
				'url'         => $remote['url'],
				'package'     => $remote['download_url'],
			];
		}

		return $transient;
	}

	/**
	 * Compare deux versions en tenant compte des suffixes (dev, beta, etc).
	 *
	 * @param string $installed_version Version installée.
	 * @param string $remote_version    Version distante.
	 * @return bool True si mise à jour disponible.
	 */
	private function compare_versions( string $installed_version, string $remote_version ): bool {
		// Séparer version base et suffixe
		preg_match( '/^(\d+\.\d+\.\d+)(?:-(.+))?$/', $remote_version, $remote_match );
		preg_match( '/^(\d+\.\d+\.\d+)(?:-(.+))?$/', $installed_version, $installed_match );

		$remote_base    = $remote_match[1] ?? $remote_version;
		$remote_suffix  = $remote_match[2] ?? '';
		$installed_base = $installed_match[1] ?? $installed_version;
		$installed_suffix = $installed_match[2] ?? '';

		// Comparer les versions de base
		$base_cmp = version_compare( $installed_base, $remote_base, '=' );

		if ( 'dev' !== $this->channel ) {
			// Canal stable : mettre à jour seulement si version base supérieure
			return version_compare( $installed_base, $remote_base, '<' );
		}

		// Canal dev
		if ( ! $base_cmp ) {
			// Versions de base différentes
			return version_compare( $installed_base, $remote_base, '<' );
		}

		// Même version de base
		if ( empty( $remote_suffix ) && ! empty( $installed_suffix ) ) {
			// Remote est stable, installed est dev → pas de downgrade
			return false;
		}

		if ( ! empty( $remote_suffix ) && empty( $installed_suffix ) ) {
			// Remote est dev, installed est stable → mettre à jour
			return true;
		}

		// Comparer les suffixes dev
		if ( ! empty( $remote_suffix ) && ! empty( $installed_suffix ) ) {
			return strcmp( $remote_suffix, $installed_suffix ) > 0;
		}

		return false;
	}

	/**
	 * Fournit les informations du plugin pour l'écran de détails.
	 *
	 * @param false|object|array $result Valeur par défaut.
	 * @param string             $action Action demandée.
	 * @param object             $args   Arguments.
	 */
	public function plugin_info( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$plugin_file = plugin_basename( SKMT_PLUGIN_FILE );

		if ( dirname( $plugin_file ) !== $args->slug ) {
			return $result;
		}

		$remote = $this->get_remote_version();

		if ( false === $remote ) {
			return $result;
		}

		return (object) [
			'name'          => 'Studio Kyne Mini Tools',
			'slug'          => dirname( $plugin_file ),
			'author'        => '<a href="https://studiokyne.com">Studio Kyne</a>',
			'author_profile'=> 'https://studiokyne.com',
			'homepage'      => $remote['url'],
			'download_link' => $remote['download_url'],
			'version'       => $remote['version'],
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'last_updated'  => $remote['published_at'],
			'sections'      => [
				'description' => __( 'Suite d\'outils modulaires pour optimiser et améliorer votre site WordPress.', 'studio-kyne-mini-tools' ),
			],
		];
	}

	/**
	 * Récupère la dernière version depuis GitHub.
	 *
	 * @return array|false Données de la release ou false en cas d'erreur.
	 */
	private function get_remote_version() {
		$cache_key = $this->transient_key . '_' . $this->channel;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$api_url = $this->get_api_url();

		$response = wp_remote_get( $api_url, [
			'timeout' => 10,
			'headers' => [
				'Accept' => 'application/vnd.github.v3+json',
				'User-Agent' => 'StudioKyneMiniTools',
			],
		] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = $this->normalize_release_data( $body );

		if ( false === $data ) {
			return false;
		}

		set_transient( $cache_key, $data, $this->cache_duration );

		return $data;
	}

	/**
	 * Retourne l'URL de l'API selon le canal.
	 */
	private function get_api_url(): string {
		if ( 'dev' === $this->channel ) {
			return "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases";
		}

		return "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
	}

	/**
	 * Normalise les donnees de release selon le canal.
	 */
	private function normalize_release_data( $body ) {
		if ( 'dev' === $this->channel ) {
			if ( ! is_array( $body ) ) {
				return false;
			}

			foreach ( $body as $release ) {
				if ( empty( $release['prerelease'] ) ) {
					continue;
				}

				return $this->format_release_payload( $release );
			}

			return false;
		}

		if ( empty( $body['tag_name'] ) ) {
			return false;
		}

		return $this->format_release_payload( $body );
	}

	/**
	 * Convertit une release GitHub en payload updater.
	 */
	private function format_release_payload( array $release ): array {
		$download_url = $this->find_asset_download_url( $release );

		return [
			'version'      => ltrim( $release['tag_name'] ?? '', 'v' ),
			'url'          => $release['html_url'] ?? '',
			'download_url' => $download_url ?: ( $release['zipball_url'] ?? '' ),
			'published_at' => $release['published_at'] ?? '',
		];
	}

	/**
	 * Recupere l'asset zip si disponible.
	 */
	private function find_asset_download_url( array $release ): string {
		if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
			return '';
		}

		foreach ( $release['assets'] as $asset ) {
			if ( empty( $asset['name'] ) ) {
				continue;
			}

			// Chercher un fichier zip nommé studio-kyne-mini-tools-*.zip
			if ( preg_match( '/^studio-kyne-mini-tools-.*\.zip$/', $asset['name'] ) ) {
				return $asset['browser_download_url'] ?? '';
			}
		}

		return '';
	}
}