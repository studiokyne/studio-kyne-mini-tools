<?php
namespace StudioKyne\MiniTools\Core;

/**
 * Interface que tous les modules doivent implémenter.
 */
interface ModuleInterface {

	/**
	 * Initialise le module (enregistrement des hooks).
	 */
	public function init(): void;

	/**
	 * Retourne les réglages du module.
	 */
	public function get_settings(): array;

	/**
	 * Enregistre les réglages du module.
	 */
	public function save_settings( array $settings ): bool;

	/**
	 * Retourne les URLs CSS admin du module.
	 *
	 * @return string[]
	 */
	public function get_admin_css(): array;

	/**
	 * Retourne les URLs JS admin du module.
	 *
	 * @return string[]
	 */
	public function get_admin_js(): array;

	/**
	 * Retourne les données JS à injecter dans skmtAdmin pour ce module.
	 * Typiquement : ['i18n' => ['key' => 'translated string', ...]]
	 */
	public function get_admin_js_data(): array;
}
