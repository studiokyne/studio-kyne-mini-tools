<?php
/**
 * Nettoyage des données lors de la désinstallation.
 *
 * Chaque module déclare les clés à supprimer via ::get_uninstall_keys().
 * Pour ajouter un module : déclarer sa classe dans $module_classes ci-dessous.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Chargement de l'autoloader pour accéder aux classes des modules.
require_once plugin_dir_path( __FILE__ ) . 'includes/Core/Autoloader.php';
\StudioKyne\MiniTools\Core\Autoloader::register();

/**
 * Classes des modules intégrés.
 * À mettre à jour lorsqu'un nouveau module est ajouté.
 *
 * @var array<string, class-string>
 */
$module_classes = [
	'image_optimizer' => \StudioKyne\MiniTools\Modules\ImageOptimizer\Module::class,
	'security'        => \StudioKyne\MiniTools\Modules\Security\Module::class,
	'login'           => \StudioKyne\MiniTools\Modules\Login\Module::class,
	'files'           => \StudioKyne\MiniTools\Modules\Files\Module::class,
	'white_label'     => \StudioKyne\MiniTools\Modules\WhiteLabel\Module::class,
	'menu_creator'    => \StudioKyne\MiniTools\Modules\MenuCreator\Module::class,
	'database'        => \StudioKyne\MiniTools\Modules\Database\Module::class,
	'media'           => \StudioKyne\MiniTools\Modules\Media\Module::class,
];

// Suppression de l'option globale.
delete_option( 'skmt_settings' );
delete_site_option( 'skmt_settings' );

// Métadonnées écrites par le cœur du plugin (centre de notifications).
// Aucun module ne les déclare : elles ne sont rattachées à aucun d'entre eux.
delete_metadata( 'user', 0, 'skmt_notices', '', true );

// Suppression des options et meta propres à chaque module.
foreach ( $module_classes as $id => $class ) {
	if ( ! class_exists( $class ) ) {
		continue;
	}

	$keys = $class::get_uninstall_keys();

	foreach ( $keys['options'] ?? [] as $option_key ) {
		delete_option( $option_key );
		delete_site_option( $option_key );
	}

	foreach ( $keys['meta'] ?? [] as $meta_key ) {
		delete_post_meta_by_key( $meta_key );
	}

	// Métadonnées d'utilisateur : delete_post_meta_by_key() ne les touche pas,
	// elles vivent dans une autre table.
	foreach ( $keys['user_meta'] ?? [] as $meta_key ) {
		delete_metadata( 'user', 0, $meta_key, '', true );
	}

	// Suppression des post types custom
	foreach ( $keys['post_type'] ?? [] as $post_type ) {
		// Récupérer tous les posts du type custom
		$posts = get_posts( [
			'post_type'      => $post_type,
			'numberposts'    => -1,
			'posts_per_page' => -1,
		] );

		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true ); // true = hard delete
		}
	}

	// Suppression des taxonomies custom (tous les termes).
	// Le plugin n'étant pas booté ici, la taxonomie n'est pas enregistrée : on
	// l'enregistre à la volée pour que get_terms()/wp_delete_term() fonctionnent.
	foreach ( $keys['taxonomy'] ?? [] as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, 'attachment', [ 'public' => false ] );
		}

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		] );

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				wp_delete_term( $term_id, $taxonomy );
			}
		}
	}
}
