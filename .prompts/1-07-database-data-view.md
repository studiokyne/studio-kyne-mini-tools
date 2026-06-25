# Prompt 1-07 — Module Base de données : Vue Données (CRUD + pagination + recherche + structure)

## Contexte

Suite du prompt 1-06. Le module `database` existe, la sidebar des tables et la navigation par tabs sont en place. `window.skmtDb` est exposé avec `currentTable`, `nonce`, etc. Les handlers AJAX existent mais sont vides (`ajax_get_rows`, `ajax_get_structure`, `ajax_update_row`, `ajax_delete_row`, `ajax_insert_row`, `ajax_truncate_table`).

Cette étape implémente les onglets **Données** et **Structure**.

## Onglet Données

### AJAX `skmt_db_get_rows`

Paramètres POST : `table`, `page` (int, 1-based), `per_page` (int, défaut 50), `search` (string), `order_col` (string), `order_dir` (`ASC`|`DESC`).

```php
public function ajax_get_rows(): void {
    check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

    global $wpdb;
    $table    = isset( $_POST['table'] ) ? sanitize_key( $_POST['table'] ) : '';
    $page     = max( 1, (int) ( $_POST['page'] ?? 1 ) );
    $per_page = min( 200, max( 10, (int) ( $_POST['per_page'] ?? 50 ) ) );
    $search   = sanitize_text_field( $_POST['search'] ?? '' );
    $order_col = sanitize_key( $_POST['order_col'] ?? '' );
    $order_dir = strtoupper( sanitize_key( $_POST['order_dir'] ?? 'ASC' ) ) === 'DESC' ? 'DESC' : 'ASC';

    // Valider que la table existe
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
    $exists = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s', $table ) );
    if ( ! $exists ) wp_send_json_error( [ 'message' => 'Table introuvable.' ] );

    // Colonnes
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
    $columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM `' . $table . '`' ), ARRAY_A );
    // Note : les backticks entourant $table sont sûrs car la table a été validée via information_schema ci-dessus.
    $col_names = array_column( $columns, 'Field' );
    $primary   = '';
    foreach ( $columns as $col ) {
        if ( $col['Key'] === 'PRI' ) { $primary = $col['Field']; break; }
    }

    // Recherche : WHERE sur toutes les colonnes de type texte (LIKE)
    $where = '';
    if ( $search !== '' ) {
        $text_cols = array_filter( $columns, fn($c) => str_contains( strtolower( $c['Type'] ), 'char' ) || str_contains( strtolower( $c['Type'] ), 'text' ) );
        $clauses = array_map( fn($c) => '`' . $c['Field'] . '` LIKE ' . $wpdb->prepare( '%s', '%' . $wpdb->esc_like( $search ) . '%' ), $text_cols );
        if ( $clauses ) $where = ' WHERE ' . implode( ' OR ', $clauses );
    }

    // ORDER BY
    $order = '';
    if ( $order_col && in_array( $order_col, $col_names, true ) ) {
        $order = ' ORDER BY `' . $order_col . '` ' . $order_dir;
    }

    // COUNT
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
    $total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $table . '`' . $where );

    // ROWS
    $offset = ( $page - 1 ) * $per_page;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
    $rows = $wpdb->get_results( 'SELECT * FROM `' . $table . '`' . $where . $order . ' LIMIT ' . $per_page . ' OFFSET ' . $offset, ARRAY_A );

    wp_send_json_success( [
        'columns'    => $col_names,
        'primary'    => $primary,
        'rows'       => $rows,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $per_page,
        'pages'      => (int) ceil( $total / $per_page ),
    ] );
}
```

### AJAX `skmt_db_update_row`

Paramètres POST : `table`, `primary_col`, `primary_val`, `col`, `value`.

```php
public function ajax_update_row(): void {
    check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

    global $wpdb;
    $table       = sanitize_key( $_POST['table'] ?? '' );
    $primary_col = sanitize_key( $_POST['primary_col'] ?? '' );
    $primary_val = $_POST['primary_val'] ?? '';
    $col         = sanitize_key( $_POST['col'] ?? '' );
    $value       = wp_unslash( $_POST['value'] ?? '' );

    if ( ! $table || ! $primary_col || ! $col ) wp_send_json_error();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $result = $wpdb->update( $table, [ $col => $value ], [ $primary_col => $primary_val ] );
    if ( $result === false ) wp_send_json_error( [ 'message' => $wpdb->last_error ] );
    wp_send_json_success();
}
```

### AJAX `skmt_db_delete_row`

Paramètres POST : `table`, `primary_col`, `primary_val`.

```php
public function ajax_delete_row(): void {
    check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

    global $wpdb;
    $table       = sanitize_key( $_POST['table'] ?? '' );
    $primary_col = sanitize_key( $_POST['primary_col'] ?? '' );
    $primary_val = $_POST['primary_val'] ?? '';

    if ( ! $table || ! $primary_col ) wp_send_json_error();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $result = $wpdb->delete( $table, [ $primary_col => $primary_val ] );
    if ( $result === false ) wp_send_json_error( [ 'message' => $wpdb->last_error ] );
    wp_send_json_success();
}
```

### AJAX `skmt_db_truncate_table`

```php
public function ajax_truncate_table(): void {
    check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

    global $wpdb;
    $table = sanitize_key( $_POST['table'] ?? '' );
    if ( ! $table ) wp_send_json_error();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
    $wpdb->query( 'TRUNCATE TABLE `' . $table . '`' );
    wp_send_json_success();
}
```

---

## JS : Onglet Données (`database.js`)

Ajouter dans `switchTab()` le chargement des données quand l'onglet 'data' est actif :

```javascript
function switchTab(tab) {
  // ... (code existant) ...
  if (tab === 'data')      loadData();
  if (tab === 'structure') loadStructure();
  // 'query' sera dans le prompt 1-08
}
```

### `loadData(page)`

```javascript
var dataState = {
  page: 1, perPage: 50, search: '', orderCol: '', orderDir: 'ASC', columns: [], primary: ''
};

function loadData(page) {
  page = page || 1;
  dataState.page = page;
  var content = document.getElementById('skmt-db-tab-data');
  content.innerHTML = '<div class="skmt-db__loading">Chargement…</div>';

  ajax('skmt_db_get_rows', {
    table:     db.currentTable,
    page:      page,
    per_page:  dataState.perPage,
    search:    dataState.search,
    order_col: dataState.orderCol,
    order_dir: dataState.orderDir,
  }, function (data) {
    dataState.columns = data.columns;
    dataState.primary = data.primary;
    renderDataTable(content, data);
  });
}
```

### `renderDataTable(container, data)`

Rendre un tableau HTML avec :
- **Barre de recherche + pagination** en haut
- **Thead** : une `<th>` par colonne cliquable pour trier (flèche ↑↓ active)
- **Tbody** : une `<tr>` par ligne, chaque cellule est un `<td>` cliquable pour édition inline
- **Colonne actions** à droite : bouton Supprimer

**Édition inline** : click sur une cellule → la cellule devient `<input>` ou `<textarea>` (si le contenu est long > 100 chars). Blur ou Enter → AJAX `skmt_db_update_row`. Escape → annuler.

**Pagination** : liens Précédent / [1] [2] [3] / Suivant. Maximum 5 pages visibles autour de la page courante.

**Bouton Supprimer** : `window.skmtModal.open({ danger: true, title: 'Supprimer la ligne ?', ... })` → AJAX `skmt_db_delete_row` → retirer la `<tr>` du DOM + mettre à jour le compteur.

**Bouton "Vider la table"** (dans le header) : `window.skmtModal.open({ danger: true, message: skmtAdmin.i18n.confirmTruncate, ... })` → AJAX `skmt_db_truncate_table` → rechargement des données.

---

## Onglet Structure

### AJAX `skmt_db_get_structure`

```php
public function ajax_get_structure(): void {
    check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

    global $wpdb;
    $table = sanitize_key( $_POST['table'] ?? '' );
    if ( ! $table ) wp_send_json_error();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
    $columns = $wpdb->get_results( 'SHOW FULL COLUMNS FROM `' . $table . '`', ARRAY_A );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
    $indexes = $wpdb->get_results( 'SHOW INDEX FROM `' . $table . '`', ARRAY_A );

    wp_send_json_success( [ 'columns' => $columns, 'indexes' => $indexes ] );
}
```

### JS : Onglet Structure

`loadStructure()` → AJAX `skmt_db_get_structure` → rendre deux tableaux :

**Tableau Colonnes** (colonnes : Nom, Type, Null, Défaut, Clé, Extra) :
```
| Nom         | Type         | Null | Défaut | Clé  | Extra          |
| ID          | bigint(20)   | NO   | —      | PRI  | auto_increment |
| post_author | bigint(20)   | NO   | 0      |      |                |
```

**Tableau Index/Clés** (colonnes : Nom, Type, Colonne, Unique) :
```
| Nom       | Type   | Colonne     | Unique |
| PRIMARY   | BTREE  | ID          | Oui    |
| post_name | BTREE  | post_name   | Non    |
```

---

## CSS supplémentaire (`database.css`)

```css
/* Tableau données */
.skmt-db__data-table-wrap { overflow-x: auto; }
.skmt-db__data-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.skmt-db__data-table th { padding: 8px 10px; text-align: left; background: var(--skmt-n50); border-bottom: 1px solid var(--skmt-border); white-space: nowrap; cursor: pointer; user-select: none; }
.skmt-db__data-table th:hover { background: var(--skmt-n100); }
.skmt-db__data-table th.is-sorted-asc::after { content: ' ↑'; }
.skmt-db__data-table th.is-sorted-desc::after { content: ' ↓'; }
.skmt-db__data-table td { padding: 6px 10px; border-bottom: 1px solid var(--skmt-border); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
.skmt-db__data-table td.is-editing { padding: 0; }
.skmt-db__data-table td.is-editing input,
.skmt-db__data-table td.is-editing textarea { width: 100%; padding: 6px 10px; border: 2px solid var(--skmt-accent); outline: none; font-size: 12px; }
.skmt-db__data-table tr:hover td { background: var(--skmt-n50); }
.skmt-db__data-table td.skmt-db__td-null { color: var(--skmt-text-secondary); font-style: italic; }

/* Barre recherche + pagination */
.skmt-db__data-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; border-bottom: 1px solid var(--skmt-border); gap: 12px; }
.skmt-db__pagination { display: flex; align-items: center; gap: 4px; }
.skmt-db__page-btn { min-width: 30px; height: 30px; ... }
.skmt-db__page-btn.is-active { background: var(--skmt-accent); color: white; }

/* Onglet Structure */
.skmt-db__structure-section-title { padding: 12px 16px; font-weight: 600; border-bottom: 1px solid var(--skmt-border); }
```

## Ce qu'il ne faut PAS faire

- Ne pas permettre la modification du nom de colonne ou du type (DDL) — uniquement les données (DML).
- Ne pas afficher les valeurs binaires/BLOB en clair — les tronquer avec `[BINARY DATA]`.
- Ne pas oublier d'échapper le HTML lors du rendu des cellules — utiliser `escHtml()` déjà défini en JS.
- L'onglet SQL (éditeur) est dans le prompt 1-08 — `loadQuery()` peut rester vide pour l'instant.
