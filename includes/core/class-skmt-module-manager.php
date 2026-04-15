<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Module_Manager {
	protected $option_name = 'skmt_active_modules';
	protected $active_modules = array();
	protected $modules = array();
	protected $plugin;

	public function __construct( $plugin ) {
		$this->plugin         = $plugin;
		$this->active_modules = $this->get_stored_active_modules();
		$this->discover_modules();
		$this->normalize_active_modules();
	}

	protected function discover_modules() {
		$module_files = glob( SKMT_PLUGIN_DIR . 'includes/modules/*/module.php' );
		if ( empty( $module_files ) ) {
			return;
		}
		foreach ( $module_files as $module_file ) {
			$class_name = require $module_file;
			if ( ! is_string( $class_name ) || ! class_exists( $class_name ) ) {
				continue;
			}
			$module = new $class_name( $this->plugin );
			if ( ! $module instanceof SKMT_Module_Interface ) {
				continue;
			}
			$this->modules[ $module->get_id() ] = $module;
		}
		ksort( $this->modules );
	}

	public function boot_active_modules() {
		foreach ( $this->get_modules() as $module ) {
			if ( $this->is_module_active( $module->get_id() ) ) {
				$module->register();
			}
		}
	}

	public function maybe_seed_defaults() {
		if ( false !== get_option( $this->option_name, false ) ) {
			return;
		}
		add_option( $this->option_name, array() );
		$this->active_modules = array();
	}

	public function get_modules() {
		return $this->modules;
	}

	public function get_module( $module_id ) {
		$module_id = sanitize_key( $module_id );
		return $this->modules[ $module_id ] ?? null;
	}

	public function get_active_module_ids() {
		return array_values( array_unique( $this->active_modules ) );
	}

	public function is_module_active( $module_id ) {
		return in_array( sanitize_key( $module_id ), $this->get_active_module_ids(), true );
	}

	public function activate_module( $module_id ) {
		$module = $this->get_module( $module_id );
		if ( ! $module ) {
			return false;
		}
		if ( ! $this->is_module_active( $module_id ) ) {
			$this->active_modules[] = $module->get_id();
			$this->persist();
		}
		$module->activate();
		return true;
	}

	public function deactivate_module( $module_id ) {
		$module = $this->get_module( $module_id );
		if ( ! $module ) {
			return false;
		}
		$this->active_modules = array_values(
			array_filter(
				$this->get_active_module_ids(),
				static function ( $id ) use ( $module_id ) {
					return $id !== sanitize_key( $module_id );
				}
			)
		);
		$this->persist();
		$module->deactivate();
		return true;
	}

	protected function persist() {
		update_option( $this->option_name, array_values( array_unique( $this->active_modules ) ) );
	}

	protected function get_stored_active_modules() {
		$stored = get_option( $this->option_name, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array_values( array_filter( array_map( 'sanitize_key', $stored ) ) );
	}

	protected function normalize_active_modules() {
		$available_ids = array_keys( $this->modules );
		$normalized    = array_values(
			array_filter(
				$this->active_modules,
				static function ( $module_id ) use ( $available_ids ) {
					return in_array( $module_id, $available_ids, true );
				}
			)
		);

		if ( $normalized !== $this->active_modules ) {
			$this->active_modules = $normalized;
			$this->persist();
		}
	}
}
