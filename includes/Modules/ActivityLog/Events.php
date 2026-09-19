<?php
namespace StudioKyne\MiniTools\Modules\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Catalogue des événements journalisés : familles et libellés.
 *
 * Un événement appartient à une seule famille ; c'est la famille qui sert
 * d'unité aux exclusions et au filtre de la liste. La colonne `event` de la
 * table ne stocke que la clé : le libellé est traduit à l'affichage, si bien
 * qu'un journal écrit en français se relit en anglais après changement de
 * langue.
 */
class Events {

	/**
	 * Familles d'événements.
	 *
	 * @return array<string, string> Clé => libellé.
	 */
	public static function groups(): array {
		return [
			'auth'     => __( 'Connexions', 'studio-kyne-mini-tools' ),
			'content'  => __( 'Contenus', 'studio-kyne-mini-tools' ),
			'media'    => __( 'Médias', 'studio-kyne-mini-tools' ),
			'users'    => __( 'Utilisateurs', 'studio-kyne-mini-tools' ),
			'plugins'  => __( 'Extensions', 'studio-kyne-mini-tools' ),
			'themes'   => __( 'Thèmes', 'studio-kyne-mini-tools' ),
			'options'  => __( 'Réglages WordPress', 'studio-kyne-mini-tools' ),
			'settings' => __( 'Réglages Mini Tools', 'studio-kyne-mini-tools' ),
		];
	}

	/**
	 * Événements connus.
	 *
	 * @return array<string, array{group: string, label: string}>
	 */
	public static function all(): array {
		return [
			'login'            => [
				'group' => 'auth',
				'label' => __( 'Connexion', 'studio-kyne-mini-tools' ),
			],
			'login_failed'     => [
				'group' => 'auth',
				'label' => __( 'Échec de connexion', 'studio-kyne-mini-tools' ),
			],
			'logout'           => [
				'group' => 'auth',
				'label' => __( 'Déconnexion', 'studio-kyne-mini-tools' ),
			],
			'post_created'     => [
				'group' => 'content',
				'label' => __( 'Contenu créé', 'studio-kyne-mini-tools' ),
			],
			'post_updated'     => [
				'group' => 'content',
				'label' => __( 'Contenu modifié', 'studio-kyne-mini-tools' ),
			],
			'post_trashed'     => [
				'group' => 'content',
				'label' => __( 'Contenu mis à la corbeille', 'studio-kyne-mini-tools' ),
			],
			'post_restored'    => [
				'group' => 'content',
				'label' => __( 'Contenu restauré', 'studio-kyne-mini-tools' ),
			],
			'post_deleted'     => [
				'group' => 'content',
				'label' => __( 'Contenu supprimé', 'studio-kyne-mini-tools' ),
			],
			'media_added'      => [
				'group' => 'media',
				'label' => __( 'Média ajouté', 'studio-kyne-mini-tools' ),
			],
			'media_updated'    => [
				'group' => 'media',
				'label' => __( 'Média modifié', 'studio-kyne-mini-tools' ),
			],
			'media_deleted'    => [
				'group' => 'media',
				'label' => __( 'Média supprimé', 'studio-kyne-mini-tools' ),
			],
			'user_created'     => [
				'group' => 'users',
				'label' => __( 'Utilisateur créé', 'studio-kyne-mini-tools' ),
			],
			'user_updated'     => [
				'group' => 'users',
				'label' => __( 'Profil modifié', 'studio-kyne-mini-tools' ),
			],
			'user_role'        => [
				'group' => 'users',
				'label' => __( 'Rôle modifié', 'studio-kyne-mini-tools' ),
			],
			'user_deleted'     => [
				'group' => 'users',
				'label' => __( 'Utilisateur supprimé', 'studio-kyne-mini-tools' ),
			],
			'password_reset'   => [
				'group' => 'users',
				'label' => __( 'Mot de passe réinitialisé', 'studio-kyne-mini-tools' ),
			],
			'plugin_activated' => [
				'group' => 'plugins',
				'label' => __( 'Extension activée', 'studio-kyne-mini-tools' ),
			],
			'plugin_disabled'  => [
				'group' => 'plugins',
				'label' => __( 'Extension désactivée', 'studio-kyne-mini-tools' ),
			],
			'plugin_installed' => [
				'group' => 'plugins',
				'label' => __( 'Extension installée', 'studio-kyne-mini-tools' ),
			],
			'plugin_updated'   => [
				'group' => 'plugins',
				'label' => __( 'Extension mise à jour', 'studio-kyne-mini-tools' ),
			],
			'plugin_deleted'   => [
				'group' => 'plugins',
				'label' => __( 'Extension supprimée', 'studio-kyne-mini-tools' ),
			],
			'theme_switched'   => [
				'group' => 'themes',
				'label' => __( 'Thème activé', 'studio-kyne-mini-tools' ),
			],
			'theme_installed'  => [
				'group' => 'themes',
				'label' => __( 'Thème installé', 'studio-kyne-mini-tools' ),
			],
			'theme_updated'    => [
				'group' => 'themes',
				'label' => __( 'Thème mis à jour', 'studio-kyne-mini-tools' ),
			],
			'theme_deleted'    => [
				'group' => 'themes',
				'label' => __( 'Thème supprimé', 'studio-kyne-mini-tools' ),
			],
			'option_updated'   => [
				'group' => 'options',
				'label' => __( 'Réglage modifié', 'studio-kyne-mini-tools' ),
			],
			'skmt_settings'    => [
				'group' => 'settings',
				'label' => __( 'Réglages modifiés', 'studio-kyne-mini-tools' ),
			],
		];
	}

	/**
	 * Famille d'un événement, '' s'il est inconnu.
	 */
	public static function group_of( string $event ): string {
		return self::all()[ $event ]['group'] ?? '';
	}

	/**
	 * Libellé d'un événement ; la clé brute s'il est inconnu (ligne écrite par
	 * une version ultérieure, ou par un tiers via le filtre).
	 */
	public static function label( string $event ): string {
		return self::all()[ $event ]['label'] ?? $event;
	}

	/**
	 * Clés des événements d'une famille.
	 *
	 * @return string[]
	 */
	public static function in_group( string $group ): array {
		return array_keys(
			array_filter(
				self::all(),
				static function ( array $def ) use ( $group ): bool {
					return $def['group'] === $group;
				}
			)
		);
	}
}
