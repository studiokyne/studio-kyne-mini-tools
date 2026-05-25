<?php
namespace StudioKyne\MiniTools\Core;

/**
 * Base commune pour les modules SKMT.
 *
 * Fournit : gestion des options, assets admin vides par défaut,
 * hooks de lifecycle et méthodes statiques pour l'install/uninstall.
 */
abstract class AbstractModule implements ModuleInterface {

	/**
	 * Identifiant unique du module (ex: "image_optimizer").
	 */
	protected string $id;

	/**
	 * Constructeur : reçoit l'ID du module depuis le registre.
	 */
	public function __construct( string $id ) {
		$this->id = $id;
	}

	/* ================================================================
	 * OPTIONS
	 * ================================================================ */

	/**
	 * Clé d'option WordPress pour ce module.
	 */
	protected function get_module_option_key(): string {
		return 'skmt_module_' . $this->id;
	}

	/**
	 * Lit les réglages du module depuis la base, fusionnés avec les defaults.
	 *
	 * @param array $defaults Valeurs par défaut à appliquer.
	 */
	protected function get_module_settings( array $defaults = [] ): array {
		$stored = get_option( $this->get_module_option_key(), [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return empty( $defaults ) ? $stored : wp_parse_args( $stored, $defaults );
	}

	/**
	 * Sauvegarde les réglages du module en base.
	 */
	protected function save_module_settings( array $data ): bool {
		return update_option( $this->get_module_option_key(), $data );
	}

	/* ================================================================
	 * ASSETS (défauts vides)
	 * ================================================================ */

	public function get_admin_css(): array {
		return [];
	}

	public function get_admin_js(): array {
		return [];
	}

	public function get_admin_js_data(): array {
		return [];
	}

	/* ================================================================
	 * LIFECYCLE
	 * ================================================================ */

	/**
	 * Appelé quand le module est activé.
	 * Surcharger pour créer des tables, programmer des crons, etc.
	 */
	public function on_activate(): void {}

	/**
	 * Appelé quand le module est désactivé.
	 * Surcharger pour nettoyer les crons, etc.
	 */
	public function on_deactivate(): void {}

	/* ================================================================
	 * INSTALL / UNINSTALL
	 * ================================================================ */

	/**
	 * Valeurs par défaut des options du module (créées à l'activation du plugin).
	 * Retourner [] si les defaults sont gérés à la volée dans get_settings().
	 */
	public static function get_defaults(): array {
		return [];
	}

	/**
	 * Clés d'options et de post_meta à supprimer lors de la désinstallation.
	 *
	 * @return array{options: string[], meta: string[]}
	 */
	public static function get_uninstall_keys(): array {
		return [
			'options' => [],
			'meta'    => [],
		];
	}
}
