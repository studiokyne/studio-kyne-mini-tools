<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SKMT_Module_Interface {
	public function get_id();
	public function get_name();
	public function get_description();
	public function get_icon();
	public function is_default_active();
	public function is_configurable();
	public function register();
	public function register_admin_pages( $parent_slug );
	public function activate();
	public function deactivate();
}
