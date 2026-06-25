# Prompt 1-09 — Module Médias : Structure + Taxonomie de dossiers

## Contexte projet

Plugin WordPress modulaire **Studio Kyne Mini Tools**. Autoloader PSR-4 : `StudioKyne\MiniTools\` → `includes/`. Pattern module standard : étendre `AbstractModule`, enregistrer dans `Modules::register_default_modules()`, ajouter à `uninstall.php`.

## Concept

Les dossiers médias sont des **dossiers virtuels** implémentés via une taxonomie WordPress (`skmt_media_folder`) attachée au post type `attachment`. Les fichiers ne sont pas déplacés sur le disque — seule l'association taxonomique change. Compatible avec toutes les futures versions WP.

## Ce qu'il faut créer

### Enregistrement du module

Dans `includes/Core/Modules.php`, dans `$defaults` de `register_default_modules()` :
```php
'media' => [
    'name'        => __( 'Médias', 'studio-kyne-mini-tools' ),
    'description' => __( 'Organisez vos médias en dossiers virtuels.', 'studio-kyne-mini-tools' ),
    'menu_label'  => __( 'Médias', 'studio-kyne-mini-tools' ),
    'menu_desc'   => __( 'Organiser les médias', 'studio-kyne-mini-tools' ),
    'class'       => 'StudioKyne\\MiniTools\\Modules\\Media\\Module',
    'icon'        => 'image',
],
```

Dans `uninstall.php` → `$module_classes` :
```php
'media' => \StudioKyne\MiniTools\Modules\Media\Module::class,
```

---

### `includes/Modules/Media/Module.php`

```php
namespace StudioKyne\MiniTools\Modules\Media;
use StudioKyne\MiniTools\Core\AbstractModule;

class Module extends AbstractModule {

    const TAXONOMY = 'skmt_media_folder';

    public function init(): void {
        add_action( 'init',          [ $this, 'register_taxonomy' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_media_library_assets' ] );
        add_action( 'wp_ajax_skmt_media_get_folders',    [ $this, 'ajax_get_folders' ] );
        add_action( 'wp_ajax_skmt_media_create_folder',  [ $this, 'ajax_create_folder' ] );
        add_action( 'wp_ajax_skmt_media_rename_folder',  [ $this, 'ajax_rename_folder' ] );
        add_action( 'wp_ajax_skmt_media_delete_folder',  [ $this, 'ajax_delete_folder' ] );
        add_action( 'wp_ajax_skmt_media_move_items',     [ $this, 'ajax_move_items' ] );
        add_action( 'wp_ajax_skmt_media_move_folder',    [ $this, 'ajax_move_folder' ] );
        add_action( 'wp_ajax_skmt_media_get_folder_items', [ $this, 'ajax_get_folder_items' ] );
    }

    /* ================================================================
     * TAXONOMIE
     * ================================================================ */

    public function register_taxonomy(): void {
        register_taxonomy( self::TAXONOMY, 'attachment', [
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => false,
            'show_admin_column' => false,
            'show_in_nav_menus' => false,
            'show_in_rest'      => false,
            'rewrite'           => false,
            'labels'            => [
                'name'          => __( 'Dossiers médias', 'studio-kyne-mini-tools' ),
                'singular_name' => __( 'Dossier média', 'studio-kyne-mini-tools' ),
            ],
        ] );
    }

    /* ================================================================
     * ASSETS — chargés uniquement sur upload.php et media-new.php
     * ================================================================ */

    public function enqueue_media_library_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'upload.php', 'media-new.php' ], true ) ) {
            return;
        }

        wp_enqueue_style(
            'skmt-media-css',
            SKMT_ASSETS_URL . 'admin/css/modules/media.css',
            [ 'skmt-reset-css' ],
            SKMT_VERSION
        );
        wp_enqueue_script(
            'skmt-media-js',
            SKMT_ASSETS_URL . 'admin/js/modules/media.js',
            [ 'jquery', 'media-views' ], // media-views est requis pour interagir avec la médiathèque WP
            SKMT_VERSION,
            true
        );
        wp_localize_script( 'skmt-media-js', 'skmtMedia', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'skmt_admin_nonce' ),
            'i18n'    => [
                'newFolder'       => __( 'Nouveau dossier', 'studio-kyne-mini-tools' ),
                'folderName'      => __( 'Nom du dossier', 'studio-kyne-mini-tools' ),
                'allMedia'        => __( 'Tous les médias', 'studio-kyne-mini-tools' ),
                'unorganized'     => __( 'Non classés', 'studio-kyne-mini-tools' ),
                'deleteFolder'    => __( 'Supprimer le dossier ?', 'studio-kyne-mini-tools' ),
                'deleteFolderMsg' => __( 'Les médias dans ce dossier seront déplacés à la racine.', 'studio-kyne-mini-tools' ),
                'confirmMove'     => __( 'Déplacer ici ?', 'studio-kyne-mini-tools' ),
            ],
        ] );
    }

    /* ================================================================
     * AJAX
     * ================================================================ */

    public function ajax_get_folders(): void {
        check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error();

        $terms = get_terms( [
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );

        if ( is_wp_error( $terms ) ) wp_send_json_error();

        // Compter les médias par dossier
        $counts = [];
        foreach ( $terms as $term ) {
            $counts[ $term->term_id ] = $term->count;
        }

        // Compter les médias sans dossier
        $total_attachments = (int) wp_count_posts( 'attachment' )->inherit;
        $in_folders        = array_sum( $counts );
        $unorganized       = max( 0, $total_attachments - $in_folders );

        $folders = array_map( function ( $term ) use ( $counts ) {
            return [
                'id'     => $term->term_id,
                'name'   => $term->name,
                'slug'   => $term->slug,
                'parent' => $term->parent,
                'count'  => $counts[ $term->term_id ] ?? 0,
            ];
        }, $terms );

        wp_send_json_success( [
            'folders'      => $folders,
            'unorganized'  => $unorganized,
        ] );
    }

    public function ajax_create_folder(): void {
        check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error();

        $name      = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $parent_id = (int) ( $_POST['parent_id'] ?? 0 );

        if ( ! $name ) wp_send_json_error( [ 'message' => 'Nom requis.' ] );

        $result = wp_insert_term( $name, self::TAXONOMY, [
            'parent' => $parent_id > 0 ? $parent_id : 0,
        ] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $term = get_term( $result['term_id'], self::TAXONOMY );
        wp_send_json_success( [
            'id'     => $term->term_id,
            'name'   => $term->name,
            'slug'   => $term->slug,
            'parent' => $term->parent,
            'count'  => 0,
        ] );
    }

    public function ajax_rename_folder(): void {
        check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error();

        $id   = (int) ( $_POST['id'] ?? 0 );
        $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );

        if ( ! $id || ! $name ) wp_send_json_error();

        $result = wp_update_term( $id, self::TAXONOMY, [ 'name' => $name ] );
        if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        wp_send_json_success( [ 'id' => $id, 'name' => $name ] );
    }

    public function ajax_delete_folder(): void {
        check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error();

        $id = (int) ( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error();

        // Déplacer tous les médias du dossier vers "non classés" (supprimer le terme de leurs attachments)
        $attachments = get_posts( [
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'tax_query'      => [ [
                'taxonomy' => self::TAXONOMY,
                'field'    => 'term_id',
                'terms'    => $id,
            ] ],
            'fields' => 'ids',
        ] );

        foreach ( $attachments as $attachment_id ) {
            wp_remove_object_terms( $attachment_id, $id, self::TAXONOMY );
        }

        // Récupérer les sous-dossiers et les supprimer également (récursif 1 niveau)
        $children = get_terms( [
            'taxonomy'   => self::TAXONOMY,
            'parent'     => $id,
            'hide_empty' => false,
            'fields'     => 'ids',
        ] );
        foreach ( $children as $child_id ) {
            wp_delete_term( $child_id, self::TAXONOMY );
        }

        wp_delete_term( $id, self::TAXONOMY );
        wp_send_json_success();
    }

    public function ajax_move_items(): void {
        check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error();

        $attachment_ids = array_map( 'absint', (array) ( $_POST['ids'] ?? [] ) );
        $folder_id      = (int) ( $_POST['folder_id'] ?? 0 ); // 0 = racine (non classé)

        foreach ( $attachment_ids as $att_id ) {
            if ( $folder_id > 0 ) {
                wp_set_object_terms( $att_id, $folder_id, self::TAXONOMY );
            } else {
                wp_set_object_terms( $att_id, [], self::TAXONOMY );
            }
        }

        wp_send_json_success( [ 'moved' => count( $attachment_ids ) ] );
    }

    public function ajax_move_folder(): void {
        check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error();

        $id        = (int) ( $_POST['id'] ?? 0 );
        $parent_id = (int) ( $_POST['parent_id'] ?? 0 );

        if ( ! $id ) wp_send_json_error();

        $result = wp_update_term( $id, self::TAXONOMY, [ 'parent' => $parent_id ] );
        if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        wp_send_json_success();
    }

    public function ajax_get_folder_items(): void {
        check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error();

        // Retourner les IDs des attachments dans le dossier (pour filtrer la médiathèque WP)
        $folder_id = (int) ( $_POST['folder_id'] ?? -1 ); // -1 = tous, 0 = non classés

        if ( $folder_id === -1 ) {
            wp_send_json_success( [ 'mode' => 'all' ] );
            return;
        }

        $args = [
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        if ( $folder_id === 0 ) {
            $args['tax_query'] = [ [
                'taxonomy' => self::TAXONOMY,
                'operator' => 'NOT EXISTS',
            ] ];
        } else {
            $args['tax_query'] = [ [
                'taxonomy' => self::TAXONOMY,
                'field'    => 'term_id',
                'terms'    => $folder_id,
                'include_children' => true,
            ] ];
        }

        $ids = get_posts( $args );
        wp_send_json_success( [ 'mode' => 'filter', 'ids' => $ids ] );
    }

    /* ================================================================
     * SETTINGS
     * ================================================================ */

    public function get_settings(): array { return []; }
    public function save_settings( array $s ): bool { return false; }
    public static function get_defaults(): array { return []; }

    public static function get_uninstall_keys(): array {
        return [
            'options'   => [],
            'meta'      => [],
            'taxonomy'  => [ self::TAXONOMY ], // suppression de tous les termes
        ];
    }
}
```

**Note pour uninstall.php** : Le tableau `$keys['taxonomy']` n'est pas encore géré dans la boucle d'uninstall. Ajouter :
```php
foreach ( $keys['taxonomy'] ?? [] as $taxonomy ) {
    $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ] );
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term_id ) {
            wp_delete_term( $term_id, $taxonomy );
        }
    }
}
```

---

### `includes/Modules/Media/settings-template.php`

Ce module n'a pas de page de réglages propre — son interface est intégrée directement dans `upload.php` (la médiathèque WP native) via JS. La page de réglages SKMT peut afficher une notice d'information :

```php
<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="skmt-section">
    <div class="skmt-section__header">
        <h2 class="skmt-section__title"><?php esc_html_e( 'Dossiers médias', 'studio-kyne-mini-tools' ); ?></h2>
        <p class="skmt-section__desc">
            <?php esc_html_e( 'Les dossiers virtuels sont accessibles directement depuis la médiathèque WordPress.', 'studio-kyne-mini-tools' ); ?>
        </p>
    </div>
    <div class="skmt-section__body">
        <a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="skmt-btn skmt-btn--primary">
            <?php esc_html_e( 'Ouvrir la médiathèque', 'studio-kyne-mini-tools' ); ?>
        </a>
    </div>
</div>
```

---

## Ce qu'il ne faut PAS faire

- Ne pas afficher la taxonomie dans l'UI native de WP (`show_ui => false`) — on gère l'interface en JS custom.
- Ne pas utiliser `register_taxonomy_for_object_type` séparé — le passer directement dans `register_taxonomy`.
- Ne pas implémenter le JS de la médiathèque dans ce prompt — c'est le prompt 1-10.
- Ne pas oublier que `current_user_can('upload_files')` et non `manage_options` pour les AJAX médias (les éditeurs doivent pouvoir accéder).
