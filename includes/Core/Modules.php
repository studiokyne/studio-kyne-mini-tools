<?php
namespace StudioKyne\MiniTools\Core;

/**
 * Gère l'enregistrement et le chargement des modules.
 */
class Modules {

	/**
	 * Liste des modules enregistrés.
	 *
	 * @var array<string, array>
	 */
	private array $registered = [];

	/**
	 * Instances des modules actifs.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private array $active = [];

	/**
	 * Settings manager.
	 */
	private Settings $settings;

	/**
	 * Constructeur.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Enregistre les modules par défaut.
	 * Doit être appelé au hook init ou plus tard pour éviter
	 * le chargement trop tôt des traductions (WP 6.7+ JIT).
	 */
	public function register_default_modules( bool $only_active = false ): void {
		$defaults = [
			'image_optimizer' => [
				'name'        => __( 'Image Optimizer', 'studio-kyne-mini-tools' ),
				'description' => __( 'Optimisation des images', 'studio-kyne-mini-tools' ),
				'menu_label'  => __( 'Image Optimizer', 'studio-kyne-mini-tools' ),
				'menu_desc'   => __( 'Optimiser les images', 'studio-kyne-mini-tools' ),
				'class'       => 'StudioKyne\\MiniTools\\Modules\\ImageOptimizer\\Module',
				'icon'        => 'image',
			],
		];

		/**
		 * Permet d'ajouter/surcharger des modules depuis d'autres plugins/themes.
		 *
		 * Format attendu:
		 * [ 'module_id' => [ 'name' => ..., 'class' => ..., ... ] ]
		 */
		$definitions = apply_filters( 'skmt_module_definitions', $defaults );

		if ( ! is_array( $definitions ) ) {
			$definitions = $defaults;
		}

		foreach ( $definitions as $id => $args ) {
			$id = sanitize_key( (string) $id );
			if ( '' === $id || ! is_array( $args ) ) {
				continue;
			}

			$args = $this->normalize_definition( $args );
			if ( empty( $args['class'] ) ) {
				continue;
			}

			if ( $only_active && ! $this->is_active( $id ) ) {
				continue;
			}

			$this->register( $id, $args );
		}

		/**
		 * Hook impératif pour enregistrer des modules via $modules->register(...).
		 */
		do_action( 'skmt_register_modules', $this, $only_active );
	}

	/**
	 * Normalise une définition de module.
	 *
	 * @param array $args Definition brute.
	 */
	private function normalize_definition( array $args ): array {
		$normalized = wp_parse_args( $args, [
			'name'        => '',
			'description' => '',
			'menu_label'  => '',
			'menu_desc'   => '',
			'class'       => '',
			'icon'        => 'package',
		] );

		$normalized['name']        = is_string( $normalized['name'] ) ? $normalized['name'] : '';
		$normalized['description'] = is_string( $normalized['description'] ) ? $normalized['description'] : '';
		$normalized['menu_label']  = is_string( $normalized['menu_label'] ) ? $normalized['menu_label'] : '';
		$normalized['menu_desc']   = is_string( $normalized['menu_desc'] ) ? $normalized['menu_desc'] : '';
		$normalized['class']       = is_string( $normalized['class'] ) ? ltrim( $normalized['class'], '\\' ) : '';
		$normalized['icon']        = is_string( $normalized['icon'] ) ? sanitize_key( $normalized['icon'] ) : 'package';

		return $normalized;
	}

	/**
	 * Enregistre un module.
	 *
	 * @param string $id   Identifiant unique du module.
	 * @param array  $args Arguments du module (name, description, class, icon).
	 */
	public function register( string $id, array $args ): void {
		$this->registered[ $id ] = wp_parse_args( $args, [
			'name'        => '',
			'description' => '',
			'menu_label'  => '',
			'menu_desc'   => '',
			'class'       => '',
			'icon'        => 'package',
		] );
	}

	/**
	 * Retourne tous les modules enregistrés.
	 */
	public function get_all(): array {
		return $this->registered;
	}

	/**
	 * Retourne un module spécifique.
	 */
	public function get( string $id ): ?array {
		return $this->registered[ $id ] ?? null;
	}

	/**
	 * Vérifie si un module est actif.
	 */
	public function is_active( string $id ): bool {
		return (bool) $this->settings->get( "modules.{$id}", false );
	}

	/**
	 * Active un module.
	 */
	public function activate( string $id ): bool {
		if ( ! isset( $this->registered[ $id ] ) ) {
			return false;
		}
		return $this->settings->set( "modules.{$id}", true );
	}

	/**
	 * Désactive un module.
	 */
	public function deactivate( string $id ): bool {
		if ( ! isset( $this->registered[ $id ] ) ) {
			return false;
		}
		return $this->settings->set( "modules.{$id}", false );
	}

	/**
	 * Initialise les modules actifs.
	 */
	public function init_active_modules(): void {
		foreach ( $this->registered as $id => $module ) {
			if ( ! $this->is_active( $id ) ) {
				continue;
			}

			if ( ! class_exists( $module['class'] ) ) {
				continue;
			}

			$instance = new $module['class']();

			if ( $instance instanceof ModuleInterface ) {
				$instance->init();
				$this->active[ $id ] = $instance;
			}
		}
	}

	/**
	 * Retourne les instances des modules actifs.
	 */
	public function get_active_instances(): array {
		return $this->active;
	}
}
