# Prompt 1-06 — Module Base de données : Structure + Liste des tables

## Contexte projet

Plugin WordPress modulaire **Studio Kyne Mini Tools**. Autoloader PSR-4 : `StudioKyne\MiniTools\` → `includes/`. Pattern module : étendre `AbstractModule`, enregistrer dans `Modules::register_default_modules()`, ajouter à `uninstall.php`.

## Ce qu'il faut créer

### Enregistrement du module

Dans `includes/Core/Modules.php`, dans `$defaults` de `register_default_modules()` :
```php
'database' => [
    'name'        => __( 'Base de données', 'studio-kyne-mini-tools' ),
    'description' => __( 'Explorez, éditez et exportez vos tables WordPress.', 'studio-kyne-mini-tools' ),
    'menu_label'  => __( 'Base de données', 'studio-kyne-mini-tools' ),
    'menu_desc'   => __( 'Gérer la base de données', 'studio-kyne-mini-tools' ),
    'class'       => 'StudioKyne\\MiniTools\\Modules\\Database\\Module',
    'icon'        => 'database',
],
```

Dans `uninstall.php` → `$module_classes` :
```php
'database' => \StudioKyne\MiniTools\Modules\Database\Module::class,
```

---

### `includes/Modules/Database/Module.php`

```php
namespace StudioKyne\MiniTools\Modules\Database;
use StudioKyne\MiniTools\Core\AbstractModule;

class Module extends AbstractModule {

    public function init(): void {
        add_action( 'wp_ajax_skmt_db_get_tables',    [ $this, 'ajax_get_tables' ] );
        add_action( 'wp_ajax_skmt_db_get_rows',      [ $this, 'ajax_get_rows' ] );
        add_action( 'wp_ajax_skmt_db_get_structure', [ $this, 'ajax_get_structure' ] );
        add_action( 'wp_ajax_skmt_db_update_row',    [ $this, 'ajax_update_row' ] );
        add_action( 'wp_ajax_skmt_db_delete_row',    [ $this, 'ajax_delete_row' ] );
        add_action( 'wp_ajax_skmt_db_insert_row',    [ $this, 'ajax_insert_row' ] );
        add_action( 'wp_ajax_skmt_db_truncate',      [ $this, 'ajax_truncate_table' ] );
        add_action( 'wp_ajax_skmt_db_export_sql',    [ $this, 'ajax_export_sql' ] );
        add_action( 'wp_ajax_skmt_db_run_query',     [ $this, 'ajax_run_query' ] );
    }

    public function get_settings(): array { return []; }
    public function save_settings( array $s ): bool { return false; }
    public static function get_defaults(): array { return []; }
    public static function get_uninstall_keys(): array { return [ 'options' => [], 'meta' => [] ]; }

    public function get_admin_css(): array {
        return [ SKMT_ASSETS_URL . 'admin/css/modules/database.css' ];
    }

    public function get_admin_js(): array {
        return [ SKMT_ASSETS_URL . 'admin/js/modules/database.js' ];
    }

    public function get_admin_js_data(): array {
        return [
            'i18n' => [
                'confirmDelete'   => __( 'Supprimer cette ligne ?', 'studio-kyne-mini-tools' ),
                'confirmTruncate' => __( 'Vider la table ? Cette action est irréversible.', 'studio-kyne-mini-tools' ),
                'queryWarning'    => __( 'Attention : les requêtes de modification (UPDATE, DELETE, DROP…) s\'exécutent directement sur la base de données. Aucun undo possible.', 'studio-kyne-mini-tools' ),
            ],
        ];
    }

    /* ================================================================
     * AJAX — LISTE DES TABLES
     * ================================================================ */

    public function ajax_get_tables(): void {
        check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        global $wpdb;
        $prefix = $wpdb->prefix;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $tables_raw = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );

        $tables = [];
        foreach ( $tables_raw as $t ) {
            $name      = $t['Name'];
            $is_wp     = str_starts_with( $name, $prefix );
            $core_slug = $is_wp ? substr( $name, strlen( $prefix ) ) : null;

            $tables[] = [
                'name'        => $name,
                'rows'        => (int) $t['Rows'],
                'size'        => ( (int) $t['Data_length'] + (int) $t['Index_length'] ),
                'engine'      => $t['Engine'],
                'is_wp_core'  => $is_wp && in_array( $core_slug, self::WP_CORE_TABLES, true ),
                'is_wp_prefix'=> $is_wp,
                'prefix'      => $is_wp ? $prefix : '',
                'short_name'  => $is_wp ? $core_slug : $name,
            ];
        }

        wp_send_json_success( [ 'tables' => $tables, 'prefix' => $prefix ] );
    }

    const WP_CORE_TABLES = [
        'posts', 'postmeta', 'comments', 'commentmeta',
        'users', 'usermeta', 'terms', 'term_taxonomy',
        'term_relationships', 'options', 'links',
    ];
}
```

Les autres handlers AJAX (`ajax_get_rows`, `ajax_update_row`, etc.) seront implémentés au prompt 1-07 et 1-08. Les déclarer vides ici avec `wp_send_json_error(['message' => 'Not implemented'])`.

---

### `includes/Modules/Database/settings-template.php`

L'interface est entièrement dynamique (JS). Le template PHP ne sert que de conteneur.

```php
<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div
    class="skmt-db"
    id="skmt-db-manager"
    data-nonce="<?php echo esc_attr( wp_create_nonce( 'skmt_admin_nonce' ) ); ?>"
>

    <!-- LAYOUT DEUX COLONNES : sidebar tables + zone principale -->
    <div class="skmt-db__layout">

        <!-- SIDEBAR TABLES -->
        <div class="skmt-db__sidebar">
            <div class="skmt-db__sidebar-header">
                <input type="search" class="skmt-input skmt-input--sm" id="skmt-db-search-table"
                    placeholder="<?php esc_attr_e( 'Rechercher une table…', 'studio-kyne-mini-tools' ); ?>">
            </div>
            <div class="skmt-db__table-list" id="skmt-db-table-list">
                <div class="skmt-db__loading"><?php esc_html_e( 'Chargement…', 'studio-kyne-mini-tools' ); ?></div>
            </div>
        </div>

        <!-- ZONE PRINCIPALE -->
        <div class="skmt-db__main" id="skmt-db-main">

            <!-- État vide (aucune table sélectionnée) -->
            <div class="skmt-db__empty" id="skmt-db-empty">
                <p><?php esc_html_e( 'Sélectionnez une table dans la barre latérale.', 'studio-kyne-mini-tools' ); ?></p>
            </div>

            <!-- Vue table (masquée jusqu'à sélection) -->
            <div id="skmt-db-table-view" style="display:none">

                <!-- Header de la table -->
                <div class="skmt-db__table-header">
                    <div class="skmt-db__table-title">
                        <h2 class="skmt-db__table-name" id="skmt-db-table-name"></h2>
                        <span class="skmt-db__table-meta" id="skmt-db-table-meta"></span>
                    </div>
                    <div class="skmt-db__table-actions">
                        <button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-db-export-btn">
                            <?php esc_html_e( 'Exporter SQL', 'studio-kyne-mini-tools' ); ?>
                        </button>
                        <button type="button" class="skmt-btn skmt-btn--sm skmt-btn--danger" id="skmt-db-truncate-btn">
                            <?php esc_html_e( 'Vider la table', 'studio-kyne-mini-tools' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Tabs Données / Structure / Requête SQL -->
                <div class="skmt-db__tabs">
                    <button type="button" class="skmt-db__tab is-active" data-tab="data">
                        <?php esc_html_e( 'Données', 'studio-kyne-mini-tools' ); ?>
                    </button>
                    <button type="button" class="skmt-db__tab" data-tab="structure">
                        <?php esc_html_e( 'Structure', 'studio-kyne-mini-tools' ); ?>
                    </button>
                    <button type="button" class="skmt-db__tab" data-tab="query">
                        <?php esc_html_e( 'Requête SQL', 'studio-kyne-mini-tools' ); ?>
                    </button>
                </div>

                <!-- Contenu des tabs (généré en JS) -->
                <div id="skmt-db-tab-data" class="skmt-db__tab-content"></div>
                <div id="skmt-db-tab-structure" class="skmt-db__tab-content" style="display:none"></div>
                <div id="skmt-db-tab-query" class="skmt-db__tab-content" style="display:none"></div>

            </div><!-- #skmt-db-table-view -->
        </div><!-- .skmt-db__main -->
    </div><!-- .skmt-db__layout -->
</div>
```

---

### `assets/admin/js/modules/database.js`

Implémenter uniquement **la liste des tables** dans ce prompt. Les autres fonctions (données, structure, SQL) sont dans les prompts suivants.

```javascript
(function () {
  "use strict";

  var db = {
    nonce:         '',
    currentTable:  null,
    tables:        [],
  };

  document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.getElementById('skmt-db-manager');
    if (!wrap) return;
    db.nonce = wrap.dataset.nonce;
    loadTables();
    initSearch();
  });

  function ajax(action, data, cb) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', db.nonce);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    fetch(skmtAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (res) { if (res.success) cb(res.data); else showToast(res.data && res.data.message || 'Erreur', 'error'); })
      .catch(function () { showToast('Erreur réseau', 'error'); });
  }

  function showToast(msg, type) {
    if (typeof window.skmtShowToast === 'function') window.skmtShowToast(msg, type);
  }

  function loadTables() {
    ajax('skmt_db_get_tables', {}, function (data) {
      db.tables = data.tables;
      db.prefix = data.prefix;
      renderTableList(db.tables);
    });
  }

  function renderTableList(tables) {
    var list = document.getElementById('skmt-db-table-list');
    if (!list) return;
    if (!tables.length) {
      list.innerHTML = '<p class="skmt-db__no-tables">Aucune table trouvée.</p>';
      return;
    }

    // Grouper : tables WP préfixées d'abord, puis les autres
    var wpTables    = tables.filter(function (t) { return t.is_wp_prefix; });
    var otherTables = tables.filter(function (t) { return !t.is_wp_prefix; });

    var html = '';
    if (wpTables.length) {
      html += '<div class="skmt-db__table-group-label">WordPress (' + db.prefix + ')</div>';
      wpTables.forEach(function (t) { html += renderTableItem(t); });
    }
    if (otherTables.length) {
      html += '<div class="skmt-db__table-group-label">Autres tables</div>';
      otherTables.forEach(function (t) { html += renderTableItem(t); });
    }
    list.innerHTML = html;

    list.querySelectorAll('.skmt-db__table-item').forEach(function (el) {
      el.addEventListener('click', function () {
        var name = el.dataset.table;
        selectTable(name);
      });
    });
  }

  function renderTableItem(t) {
    var label = t.is_wp_prefix ? t.short_name : t.name;
    var badge = t.is_wp_core ? '<span class="skmt-badge skmt-badge--info">WP</span>' : '';
    var rows  = t.rows.toLocaleString();
    return '<div class="skmt-db__table-item" data-table="' + escHtml(t.name) + '">' +
           '<span class="skmt-db__table-item-name">' + escHtml(label) + badge + '</span>' +
           '<span class="skmt-db__table-item-rows">' + rows + '</span>' +
           '</div>';
  }

  function selectTable(tableName) {
    db.currentTable = tableName;
    // Mettre à jour la sélection visuelle dans la sidebar
    document.querySelectorAll('.skmt-db__table-item').forEach(function (el) {
      el.classList.toggle('is-active', el.dataset.table === tableName);
    });
    // Afficher la vue table, masquer l'état vide
    document.getElementById('skmt-db-empty').style.display = 'none';
    document.getElementById('skmt-db-table-view').style.display = '';
    // Mettre à jour le nom/meta dans le header
    var t = db.tables.find(function (t) { return t.name === tableName; });
    if (t) {
      document.getElementById('skmt-db-table-name').textContent = t.name;
      document.getElementById('skmt-db-table-meta').textContent =
        t.rows.toLocaleString() + ' lignes · ' + formatSize(t.size);
    }
    // Activer l'onglet Données par défaut (implémenté au prompt 1-07)
    switchTab('data');
  }

  function switchTab(tab) {
    document.querySelectorAll('.skmt-db__tab').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.dataset.tab === tab);
    });
    document.querySelectorAll('.skmt-db__tab-content').forEach(function (el) {
      el.style.display = el.id === 'skmt-db-tab-' + tab ? '' : 'none';
    });
    // Chargement du contenu (sera implémenté aux prompts suivants)
  }

  function initSearch() {
    var input = document.getElementById('skmt-db-search-table');
    if (!input) return;
    input.addEventListener('input', function () {
      var q = input.value.toLowerCase();
      var filtered = db.tables.filter(function (t) {
        return t.name.toLowerCase().includes(q);
      });
      renderTableList(filtered);
    });
  }

  function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' o';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko';
    return (bytes / 1024 / 1024).toFixed(2) + ' Mo';
  }

  function escHtml(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  window.skmtDb = db; // exposer pour les modules suivants

})();
```

---

### `assets/admin/css/modules/database.css`

```css
/* Layout */
.skmt-db__layout {
  display: flex;
  gap: 0;
  height: calc(100vh - 120px); /* pleine hauteur sous la topbar WP */
  border: 1px solid var(--skmt-border);
  border-radius: var(--skmt-radius);
  overflow: hidden;
  background: var(--skmt-surface);
}

/* Sidebar */
.skmt-db__sidebar {
  width: 240px;
  flex-shrink: 0;
  border-right: 1px solid var(--skmt-border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.skmt-db__sidebar-header { padding: 12px; border-bottom: 1px solid var(--skmt-border); }
.skmt-db__table-list { flex: 1; overflow-y: auto; padding: 8px 0; }
.skmt-db__table-group-label { padding: 6px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--skmt-text-secondary); }
.skmt-db__table-item { display: flex; align-items: center; justify-content: space-between; padding: 6px 12px; cursor: pointer; border-radius: 4px; margin: 0 4px; gap: 8px; }
.skmt-db__table-item:hover { background: var(--skmt-n100); }
.skmt-db__table-item.is-active { background: var(--skmt-accent); color: white; }
.skmt-db__table-item.is-active .skmt-badge { background: rgba(255,255,255,.2); color: white; }
.skmt-db__table-item-name { flex: 1; font-size: 13px; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; align-items: center; gap: 6px; }
.skmt-db__table-item-rows { font-size: 11px; color: var(--skmt-text-secondary); flex-shrink: 0; }

/* Zone principale */
.skmt-db__main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.skmt-db__empty { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--skmt-text-secondary); }
.skmt-db__table-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--skmt-border); gap: 12px; }
.skmt-db__table-name { font-size: 16px; font-weight: 600; margin: 0; }
.skmt-db__table-meta { font-size: 12px; color: var(--skmt-text-secondary); }
.skmt-db__table-actions { display: flex; gap: 8px; }

/* Tabs */
.skmt-db__tabs { display: flex; border-bottom: 1px solid var(--skmt-border); padding: 0 16px; gap: 0; }
.skmt-db__tab { padding: 10px 16px; background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; font-size: 13px; color: var(--skmt-text-secondary); margin-bottom: -1px; }
.skmt-db__tab:hover { color: var(--skmt-text); }
.skmt-db__tab.is-active { color: var(--skmt-accent); border-bottom-color: var(--skmt-accent); font-weight: 500; }

.skmt-db__tab-content { flex: 1; overflow: auto; }
```

## Icône `database` dans Admin.php

L'icône `database` n'existe peut-être pas encore dans `Admin::get_icon_paths()`. Ajouter une icône SVG Lucide database :
```php
'database' => '<path d="M12 2C6.48 2 2 4.24 2 7s4.48 5 10 5 10-2.24 10-5-4.48-5-10-5z"/><path d="M2 7v5c0 2.76 4.48 5 10 5s10-2.24 10-5V7"/><path d="M2 12v5c0 2.76 4.48 5 10 5s10-2.24 10-5v-5"/>',
```

## Ce qu'il ne faut PAS faire

- Ne pas implémenter les onglets Données, Structure, SQL dans ce prompt — juste la structure HTML + la liste des tables + la sélection.
- Ne pas exécuter de requêtes SQL au-delà de `SHOW TABLE STATUS` dans ce prompt.
- Ne pas oublier que `$wpdb->get_results()` sans `prepare()` est autorisé pour `SHOW TABLE STATUS` (pas de paramètre externe).
