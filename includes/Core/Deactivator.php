<?php
namespace StudioKyne\MiniTools\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Gère la désactivation du plugin.
 */
class Deactivator {

	/**
	 * Exécuté à la désactivation du plugin.
	 */
	public static function deactivate(): void {
		// Intentionnellement vide: aucune rewrite rule n'est enregistrée par le plugin.
	}
}
