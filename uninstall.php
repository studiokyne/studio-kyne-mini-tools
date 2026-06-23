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
];

// Suppression de l'option globale.
delete_option( 'skmt_settings' );
delete_site_option( 'skmt_settings' );

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
}
