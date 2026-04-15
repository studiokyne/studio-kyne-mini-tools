<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Plugin {
	protected static $instance = null;
	protected $loader;
	protected $settings;
	protected $logger;
	protected $jobs;
	protected $modules;
	protected $admin;
	protected $updater;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		$this->loader   = new SKMT_Loader();
		$this->settings = new SKMT_Settings();
		$this->logger   = new SKMT_Logger( $this->settings );
		$this->jobs     = new SKMT_Jobs();
		$this->modules  = new SKMT_Module_Manager( $this );
		$this->boot_updater();
		$this->admin    = new SKMT_Admin( $this );
		$this->register_hooks();
		$this->modules->boot_active_modules();
	}

	protected function boot_updater() {
		$repository = apply_filters( 'skmt_updater_repository', defined( 'SKMT_GITHUB_REPO' ) ? SKMT_GITHUB_REPO : '' );
		$repository = is_string( $repository ) ? trim( $repository ) : '';

		if ( '' === $repository ) {
			return;
		}

		$token = apply_filters( 'skmt_updater_token', defined( 'SKMT_GITHUB_TOKEN' ) ? SKMT_GITHUB_TOKEN : '' );
		$token = is_string( $token ) ? trim( $token ) : '';

		$this->updater = new SKMT_Updater( SKMT_PLUGIN_FILE, SKMT_VERSION, $repository, $token );
	}

	protected function register_hooks() {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_textdomain' );
		$this->loader->add_action( 'admin_init', $this, 'register_settings' );
		$this->admin->register();
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'studio-kyne-mini-tools', false, dirname( SKMT_PLUGIN_BASENAME ) . '/languages' );
	}

	public function register_settings() {
		$this->settings->register();
	}

	public static function activate() {
		$settings = new SKMT_Settings();
		$settings->maybe_seed_defaults();
		$plugin = self::instance();
		$plugin->modules()->maybe_seed_defaults();
		$stats_module = $plugin->modules()->get_module( 'image-optimizer' );
		if ( $stats_module ) {
			$stats_module->activate();
		}
	}

	public static function deactivate() {}
	public function settings() { return $this->settings; }
	public function logger() { return $this->logger; }
	public function jobs() { return $this->jobs; }
	public function modules() { return $this->modules; }
}
