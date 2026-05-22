<?php
namespace StudioKyne\MiniTools\Core;

/**
 * Gère la désactivation du plugin.
 */
class Deactivator {

	/**
	 * Exécuté à la désactivation du plugin.
	 */
	public static function deactivate(): void {
		// Flush rewrite rules si nécessaire
		flush_rewrite_rules();
	}
}