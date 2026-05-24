<?php
namespace StudioKyne\MiniTools\Core;

/**
 * Base commune pour les modules SKMT.
 *
 * Elle fournit des helpers standards (options + assets admin) afin
 * d'éviter de réécrire le même socle dans chaque module.
 */
abstract class AbstractModule implements ModuleInterface {

	/**
	 * Retourne les styles admin du module (vide par défaut).
	 *
	 * @return string[]
	 */
	public function get_admin_css(): array {
		return [];
	}

	/**
	 * Retourne les scripts admin du module (vide par défaut).
	 *
	 * @return string[]
	 */
	public function get_admin_js(): array {
		return [];
	}

	/**
	 * Lecture option avec fallback.
	 *
	 * @param string $option_key Clé d'option.
	 * @param mixed  $default    Valeur par défaut.
	 * @return mixed
	 */
	protected function get_option_value( string $option_key, $default = [] ) {
		return get_option( $option_key, $default );
	}

	/**
	 * Ecriture option.
	 *
	 * @param string $option_key Clé d'option.
	 * @param mixed  $value      Valeur à enregistrer.
	 */
	protected function update_option_value( string $option_key, $value ): bool {
		return update_option( $option_key, $value );
	}
}

