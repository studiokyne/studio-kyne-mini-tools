<?php
namespace StudioKyne\MiniTools\Core;

/**
 * Gère l'activation du plugin.
 *
 * La liste des modules est déclarée ici pour bootstrapper les options initiales.
 * Chaque module déclare ses propres defaults via ::get_defaults().
 */
class Activator {

	/**
	 * Classes des modules intégrés.
	 * À mettre à jour lorsqu'un nouveau module est ajouté.
	 *
	 * @var array<string, class-string>
	 */
	private static array $module_classes = [
		'image_optimizer' => \StudioKyne\MiniTools\Modules\ImageOptimizer\Module::class,
		'security'        => \StudioKyne\MiniTools\Modules\Security\Module::class,
	];

	/**
	 * Exécuté à l'activation du plugin.
	 */
	public static function activate(): void {
		// Construire les defaults en incluant l'état initial de chaque module (inactif).
		$modules_defaults = [];
		foreach ( self::$module_classes as $id => $class ) {
			$modules_defaults[ $id ] = false;
		}

		$default_settings = [
			'global'  => [
				'update_channel' => 'stable',
				'auto_updates'   => false,
			],
			'modules' => $modules_defaults,
		];

		// Créer l'option globale uniquement si elle n'existe pas encore.
		if ( false === get_option( 'skmt_settings' ) ) {
			add_option( 'skmt_settings', $default_settings );
		}

		// Laisser chaque module initialiser ses propres options si nécessaire.
		foreach ( self::$module_classes as $id => $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$defaults    = $class::get_defaults();
			$option_key  = 'skmt_module_' . $id;

			if ( ! empty( $defaults ) && false === get_option( $option_key ) ) {
				add_option( $option_key, $defaults );
			}
		}
	}
}
