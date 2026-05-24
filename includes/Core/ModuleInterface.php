<?php
namespace StudioKyne\MiniTools\Core;

/**
 * Interface que tous les modules doivent implémenter.
 */
interface ModuleInterface {

	/**
	 * Initialise le module.
	 */
	public function init(): void;

	/**
	 * Retourne les réglages du module.
	 *
	 * @return array Tableau de réglages.
	 */
	public function get_settings(): array;

	/**
	 * Enregistre les réglages du module.
	 *
	 * @param array $settings Nouveaux réglages.
	 * @return bool Succès ou échec.
	 */
	public function save_settings( array $settings ): bool;

	/**
	 * Retourne les styles admin du module.
	 *
	 * @return string[] Liste d'URLs CSS.
	 */
	public function get_admin_css(): array;

	/**
	 * Retourne les scripts admin du module.
	 *
	 * @return string[] Liste d'URLs JS.
	 */
	public function get_admin_js(): array;
}