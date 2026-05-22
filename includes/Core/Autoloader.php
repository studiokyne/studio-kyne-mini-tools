<?php
namespace StudioKyne\MiniTools\Core;

/**
 * Autoloader simple PSR-4 pour le plugin.
 */
class Autoloader {

	/**
	 * Enregistre l'autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * Charge une classe.
	 *
	 * @param string $class Nom complet de la classe.
	 */
	public static function autoload( string $class ): void {
		$prefix = 'StudioKyne\\MiniTools\\';

		// Vérifier que la classe appartient au namespace du plugin
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}

		// Retirer le prefix
		$relative_class = substr( $class, strlen( $prefix ) );

		// Convertir en chemin de fichier
		$file = SKMT_INCLUDES_DIR . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}