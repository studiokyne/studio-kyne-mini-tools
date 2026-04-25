<?php

/**
 * Plugin Name: Studio Kyne Mini Tools
 * Plugin URI:  https://studiokyne.com/
 * Description: Suite modulaire d'outils WordPress par Studio Kyne : optimisation d'images et futurs modules activables.
 * Version:     0.1.6
 * Author:      Studio Kyne
 * Author URI:  https://studiokyne.com/
 * Text Domain: studio-kyne-mini-tools
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) {
	exit;
}

define('SKMT_VERSION', '0.1.6');
define('SKMT_PLUGIN_FILE', __FILE__);
define('SKMT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SKMT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SKMT_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('SKMT_GITHUB_REPO')) {
	define('SKMT_GITHUB_REPO', 'studiokyne/studio-kyne-mini-tools');
}
if (!defined('SKMT_GITHUB_TOKEN')) {
	define('SKMT_GITHUB_TOKEN', '');
}

require_once SKMT_PLUGIN_DIR . 'includes/core/interfaces/interface-skmt-module.php';
require_once SKMT_PLUGIN_DIR . 'includes/core/class-skmt-loader.php';
require_once SKMT_PLUGIN_DIR . 'includes/core/class-skmt-capabilities.php';
require_once SKMT_PLUGIN_DIR . 'includes/core/class-skmt-logger.php';
require_once SKMT_PLUGIN_DIR . 'includes/core/class-skmt-notifications.php';
require_once SKMT_PLUGIN_DIR . 'includes/core/class-skmt-settings.php';
require_once SKMT_PLUGIN_DIR . 'includes/core/class-skmt-jobs.php';
require_once SKMT_PLUGIN_DIR . 'includes/core/class-skmt-module-manager.php';
require_once SKMT_PLUGIN_DIR . 'includes/core/class-skmt-updater.php';
require_once SKMT_PLUGIN_DIR . 'includes/core/class-skmt-admin.php';
require_once SKMT_PLUGIN_DIR . 'includes/core/class-skmt-plugin.php';

register_activation_hook(__FILE__, array('SKMT_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('SKMT_Plugin', 'deactivate'));

function skmt()
{
	return SKMT_Plugin::instance();
}

skmt();
