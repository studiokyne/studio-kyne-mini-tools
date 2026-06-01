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
	 * Enregistre les modules par défaut via filter + hook impératif.
	 * Doit être appelé au hook init ou plus tard (JIT i18n WP 6.7+).
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
			'security'        => [
				'name'        => __( 'Sécurité', 'studio-kyne-mini-tools' ),
				'description' => __( 'Authentification, hardening et logging de sécurité', 'studio-kyne-mini-tools' ),
				'menu_label'  => __( 'Sécurité', 'studio-kyne-mini-tools' ),
				'menu_desc'   => __( 'Gérer la sécurité du site', 'studio-kyne-mini-tools' ),
				'class'       => 'StudioKyne\\MiniTools\\Modules\\Security\\Module',
				'icon'        => 'shield',
			],
			'login'           => [
				'name'        => __( 'Connexion', 'studio-kyne-mini-tools' ),
				'description' => __( 'Personnalisez le design et le branding de la page de connexion WordPress.', 'studio-kyne-mini-tools' ),
				'menu_label'  => __( 'Connexion', 'studio-kyne-mini-tools' ),
				'menu_desc'   => __( 'Page de connexion', 'studio-kyne-mini-tools' ),
				'class'       => 'StudioKyne\\MiniTools\\Modules\\Login\\Module',
				'icon'        => 'log-in',
			],
		];

		/**
		 * Permet d'ajouter/surcharger des modules depuis d'autres plugins/thèmes.
		 * Format : [ 'module_id' => [ 'name' => ..., 'class' => ..., ... ] ]
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
	 * Active un module et appelle son hook on_activate().
	 */
	public function activate( string $id ): bool {
		if ( ! isset( $this->registered[ $id ] ) ) {
			return false;
		}

		$result = $this->settings->set( "modules.{$id}", true );

		if ( $result ) {
			$instance = $this->make_instance( $id );
			if ( $instance ) {
				$instance->on_activate();
			}
		}

		return $result;
	}

	/**
	 * Désactive un module et appelle son hook on_deactivate().
	 */
	public function deactivate( string $id ): bool {
		if ( ! isset( $this->registered[ $id ] ) ) {
			return false;
		}

		// Utiliser l'instance active si disponible, sinon en créer une temporaire.
		$instance = $this->active[ $id ] ?? $this->make_instance( $id );

		$result = $this->settings->set( "modules.{$id}", false );

		if ( $result && $instance ) {
			$instance->on_deactivate();
		}

		return $result;
	}

	/**
	 * Instancie et initialise tous les modules actifs.
	 */
	public function init_active_modules(): void {
		foreach ( $this->registered as $id => $module ) {
			if ( ! $this->is_active( $id ) ) {
				continue;
			}

			if ( ! class_exists( $module['class'] ) ) {
				continue;
			}

			$instance = new $module['class']( $id );

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

	/**
	 * Crée une instance du module sans l'initialiser (pour les hooks lifecycle).
	 */
	private function make_instance( string $id ): ?ModuleInterface {
		$class = $this->registered[ $id ]['class'] ?? '';

		if ( empty( $class ) || ! class_exists( $class ) ) {
			return null;
		}

		$instance = new $class( $id );

		return $instance instanceof ModuleInterface ? $instance : null;
	}
}
