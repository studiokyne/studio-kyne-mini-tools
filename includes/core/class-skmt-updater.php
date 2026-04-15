<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Updater {
	protected $plugin_file;
	protected $plugin_basename;
	protected $plugin_slug;
	protected $current_version;
	protected $repository;
	protected $token;
	protected $cache_key;

	public function __construct( $plugin_file, $current_version, $repository, $token = '' ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->plugin_slug     = dirname( $this->plugin_basename );
		$this->current_version = (string) $current_version;
		$this->repository      = trim( (string) $repository );
		$this->token           = trim( (string) $token );
		$this->cache_key       = 'skmt_github_release_' . md5( $this->repository );

		if ( '' === $this->repository ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'inject_plugin_information' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'normalize_package_source' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'maybe_clear_release_cache' ), 10, 2 );
	}

	public function inject_update( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( empty( $release ) || empty( $release['version'] ) || empty( $release['package'] ) ) {
			return $transient;
		}

		if ( version_compare( $release['version'], $this->current_version, '<=' ) ) {
			if ( isset( $transient->response[ $this->plugin_basename ] ) ) {
				unset( $transient->response[ $this->plugin_basename ] );
			}
			return $transient;
		}

		$transient->response[ $this->plugin_basename ] = (object) array(
			'slug'        => $this->plugin_slug,
			'plugin'      => $this->plugin_basename,
			'new_version' => $release['version'],
			'url'         => $release['details_url'],
			'package'     => $release['package'],
			'tested'      => $release['tested'],
			'requires'    => $release['requires'],
		);

		return $transient;
	}

	public function inject_plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( empty( $release ) || empty( $release['version'] ) ) {
			return $result;
		}

		$description = isset( $release['description'] ) ? (string) $release['description'] : '';
		$changelog   = isset( $release['changelog'] ) ? (string) $release['changelog'] : '';

		return (object) array(
			'name'          => 'Studio Kyne Mini Tools',
			'slug'          => $this->plugin_slug,
			'plugin_name'   => 'Studio Kyne Mini Tools',
			'version'       => $release['version'],
			'author'        => '<a href="https://studiokyne.com/">Studio Kyne</a>',
			'homepage'      => $release['details_url'],
			'requires'      => $release['requires'],
			'tested'        => $release['tested'],
			'last_updated'  => $release['published_at'],
			'sections'      => array(
				'description' => $description,
				'changelog'   => $changelog,
			),
			'download_link' => $release['package'],
		);
	}

	public function normalize_package_source( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $source;
		}

		$normalized_source = untrailingslashit( (string) $source );
		$expected_source   = trailingslashit( (string) $remote_source ) . $this->plugin_slug;
		$expected_source   = untrailingslashit( $expected_source );

		if ( $normalized_source === $expected_source ) {
			return $source;
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( empty( $wp_filesystem ) ) {
			return $source;
		}

		if ( $wp_filesystem->exists( $expected_source ) ) {
			$wp_filesystem->delete( $expected_source, true );
		}

		if ( ! $wp_filesystem->move( $normalized_source, $expected_source, true ) ) {
			return $source;
		}

		return $expected_source;
	}

	public function maybe_clear_release_cache( $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['plugins'] ) || ! is_array( $hook_extra['plugins'] ) ) {
			return;
		}

		if ( in_array( $this->plugin_basename, $hook_extra['plugins'], true ) ) {
			delete_site_transient( $this->cache_key );
		}
	}

	protected function get_latest_release() {
		$cached = get_site_transient( $this->cache_key );
		if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
			return $cached;
		}

		$request_args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			),
		);
		if ( '' !== $this->token ) {
			$request_args['headers']['Authorization'] = 'Bearer ' . $this->token;
		}

		$response = wp_remote_get( 'https://api.github.com/repos/' . $this->repository . '/releases/latest', $request_args );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return false;
		}

		$payload = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $payload ) || empty( $payload['tag_name'] ) || empty( $payload['zipball_url'] ) ) {
			return false;
		}

		$package_url = (string) $payload['zipball_url'];
		if ( ! empty( $payload['assets'] ) && is_array( $payload['assets'] ) ) {
			foreach ( $payload['assets'] as $asset ) {
				if ( empty( $asset['browser_download_url'] ) || empty( $asset['name'] ) ) {
					continue;
				}

				$asset_name = strtolower( (string) $asset['name'] );
				if ( '.zip' !== substr( $asset_name, -4 ) ) {
					continue;
				}

				$package_url = (string) $asset['browser_download_url'];
				if ( false !== strpos( $asset_name, $this->plugin_slug ) ) {
					break;
				}
			}
		}

		$description = '';
		if ( ! empty( $payload['body'] ) ) {
			$description = wp_kses_post( wpautop( (string) $payload['body'] ) );
		}

		$release = array(
			'version'      => ltrim( (string) $payload['tag_name'], "vV\t\n\r\0\x0B" ),
			'package'      => $package_url,
			'details_url'  => ! empty( $payload['html_url'] ) ? (string) $payload['html_url'] : 'https://github.com/' . $this->repository,
			'published_at' => ! empty( $payload['published_at'] ) ? gmdate( 'Y-m-d', strtotime( (string) $payload['published_at'] ) ) : gmdate( 'Y-m-d' ),
			'requires'     => '6.2',
			'tested'       => '6.8',
			'description'  => $description,
			'changelog'    => $description,
		);

		set_site_transient( $this->cache_key, $release, 6 * HOUR_IN_SECONDS );

		return $release;
	}
}
