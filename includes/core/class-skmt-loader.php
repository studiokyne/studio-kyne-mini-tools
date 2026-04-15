<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Loader {
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		add_action( $hook, array( $component, $callback ), $priority, $accepted_args );
	}

	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		add_filter( $hook, array( $component, $callback ), $priority, $accepted_args );
	}
}
