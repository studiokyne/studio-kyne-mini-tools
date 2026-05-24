<?php
namespace StudioKyne\MiniTools\Core;

/**
 * Gère l'activation du plugin.
 */
class Activator {

	/**
	 * Exécuté à l'activation du plugin.
	 */
	public static function activate(): void {
		// Options par défaut
		$default_settings = [
			'global'   => [
				'update_channel'=> 'stable',
				'auto_updates'  => false,
			],
			'modules'  => [
				'image_optimizer' => false,
			],
		];

		if ( false === get_option( 'skmt_settings' ) ) {
			add_option( 'skmt_settings', $default_settings );
		}

	}
}
