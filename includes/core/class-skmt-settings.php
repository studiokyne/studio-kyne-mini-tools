<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Settings {
	protected $option_name = 'skmt_settings';
	protected $defaults = array(
		'cleanup_on_uninstall' => 1,
		'logging_enabled'      => 0,
		'log_retention_days'   => 0,
	);

	public function register() {
		register_setting(
			'skmt_settings_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->defaults,
			)
		);
	}

	public function get_all() {
		$stored = get_option( $this->option_name, array() );
		$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), $this->defaults );

		// Global logs UI is removed in favor of per-image history.
		$settings['logging_enabled']    = 0;
		$settings['log_retention_days'] = 0;

		return $settings;
	}

	public function get( $key ) {
		$settings = $this->get_all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
	}

	public function sanitize( $input ) {
		$current = $this->get_all();
		$input   = is_array( $input ) ? $input : array();

		$current['cleanup_on_uninstall'] = empty( $input['cleanup_on_uninstall'] ) ? 0 : 1;
		$current['logging_enabled']      = 0;
		$current['log_retention_days']   = 0;

		return $current;
	}

	public function maybe_seed_defaults() {
		if ( false === get_option( $this->option_name, false ) ) {
			add_option( $this->option_name, $this->defaults );
		}
	}
}
