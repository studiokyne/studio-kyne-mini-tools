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

		// Base des includes. On privilégie la constante définie par le bootstrap,
		// mais on retombe sur un chemin calculé depuis ce fichier : lors de la
		// désinstallation, WordPress ne charge que uninstall.php (pas le bootstrap),
		// donc SKMT_INCLUDES_DIR n'est pas définie et l'autoload doit rester fonctionnel.
		$base = defined( 'SKMT_INCLUDES_DIR' ) ? SKMT_INCLUDES_DIR : dirname( __DIR__ ) . '/';

		// Convertir en chemin de fichier
		$file = $base . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}