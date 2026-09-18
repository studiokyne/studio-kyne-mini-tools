<?php
namespace StudioKyne\MiniTools\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Gère l'activation du plugin.
 *
 * Chaque module déclare ses propres defaults via ::get_defaults().
 */
class Activator {

	/**
	 * Classes des modules intégrés : la SEULE liste, partagée avec
	 * uninstall.php (qui ne boote pas le plugin et ne peut donc pas passer par
	 * Modules::register_default_modules()). À compléter à chaque nouveau module.
	 *
	 * @var array<string, class-string>
	 */
	public const MODULE_CLASSES = [
		'image_optimizer' => \StudioKyne\MiniTools\Modules\ImageOptimizer\Module::class,
		'security'        => \StudioKyne\MiniTools\Modules\Security\Module::class,
		'login'           => \StudioKyne\MiniTools\Modules\Login\Module::class,
		'files'           => \StudioKyne\MiniTools\Modules\Files\Module::class,
		'white_label'     => \StudioKyne\MiniTools\Modules\WhiteLabel\Module::class,
		'menu_creator'    => \StudioKyne\MiniTools\Modules\MenuCreator\Module::class,
		'database'        => \StudioKyne\MiniTools\Modules\Database\Module::class,
		'media'           => \StudioKyne\MiniTools\Modules\Media\Module::class,
	];

	/**
	 * Exécuté à l'activation du plugin.
	 */
	public static function activate(): void {
		// Construire les defaults en incluant l'état initial de chaque module (inactif).
		$modules_defaults = [];
		foreach ( self::MODULE_CLASSES as $id => $class ) {
			$modules_defaults[ $id ] = false;
		}

		$default_settings = [
			'global'  => [
				'update_channel' => 'stable',
			],
			'modules' => $modules_defaults,
		];

		// Créer l'option globale uniquement si elle n'existe pas encore.
		if ( false === get_option( 'skmt_settings' ) ) {
			add_option( 'skmt_settings', $default_settings );
		}

		// Laisser chaque module initialiser ses propres options si nécessaire.
		foreach ( self::MODULE_CLASSES as $id => $class ) {
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
