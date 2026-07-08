<?php
/**
 * Plugin Name: Studio Kyne Mini Tools
 * Plugin URI:  https://github.com/studiokyne/studio-kyne-mini-tools
 * Update URI:  https://github.com/studiokyne/studio-kyne-mini-tools
 * Description: Suite d'outils modulaires pour optimiser et améliorer votre site WordPress.
 * Version:     1.0.9
 * Author:      Studio Kyne
 * Author URI:  https://studiokyne.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: studio-kyne-mini-tools
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Sécurité : empêcher l'accès direct
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constantes de base
define( 'SKMT_VERSION', '1.0.9-dev.7' );
define( 'SKMT_PLUGIN_FILE', __FILE__ );
define( 'SKMT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SKMT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SKMT_INCLUDES_DIR', SKMT_PLUGIN_DIR . 'includes/' );
define( 'SKMT_TEMPLATES_DIR', SKMT_PLUGIN_DIR . 'templates/' );
define( 'SKMT_ASSETS_URL', SKMT_PLUGIN_URL . 'assets/' );

// Autoloader
require_once SKMT_INCLUDES_DIR . 'Core/Autoloader.php';
StudioKyne\MiniTools\Core\Autoloader::register();

// Bootstrap
add_action( 'plugins_loaded', [ 'StudioKyne\MiniTools\Core\Plugin', 'instance' ], 10 );

// Activation hook
register_activation_hook( __FILE__, function () {
	require_once SKMT_INCLUDES_DIR . 'Core/Activator.php';
	StudioKyne\MiniTools\Core\Activator::activate();
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function () {
	require_once SKMT_INCLUDES_DIR . 'Core/Deactivator.php';
	StudioKyne\MiniTools\Core\Deactivator::deactivate();
} );