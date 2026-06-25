# Prompt 1-08 — Module Base de données : Éditeur SQL + Export

## Contexte

Suite du prompt 1-07. Les onglets Données et Structure fonctionnent. Il reste à implémenter l'onglet **Requête SQL** (éditeur libre) et le bouton **Exporter SQL**. Le handler AJAX `ajax_run_query` et `ajax_export_sql` existent mais sont vides.

## Onglet Requête SQL

### Comportement général

- Textarea pleine largeur pour saisir du SQL libre
- Bouton "Exécuter" (ou Ctrl+Enter)
- **Warning visible et permanent** en haut du panel : "Les requêtes de modification s'exécutent directement sur la base de données. Aucun undo possible."
- Historique des N dernières requêtes (stocké en `localStorage`, clé `skmt_db_query_history`)
- Zone de résultats sous l'éditeur :
  - Si SELECT → tableau HTML (identique au tableau de l'onglet Données)
  - Si autre type → message de confirmation avec nombre de lignes affectées
  - Si erreur SQL → message d'erreur en rouge

### Contenu HTML de `#skmt-db-tab-query` (généré en JS à `switchTab('query')`)

```javascript
function initQueryTab() {
  var content = document.getElementById('skmt-db-tab-query');
  if (content.dataset.initialized) return;
  content.dataset.initialized = '1';

  content.innerHTML =
    '<div class="skmt-db__query-warning">' +
    '<svg ...><!-- warning icon --></svg>' +
    escHtml(skmtAdmin.i18n.queryWarning) +
    '</div>' +
    '<div class="skmt-db__query-editor-wrap">' +
    '<textarea id="skmt-db-query-input" class="skmt-db__query-input" ' +
    'placeholder="SELECT * FROM ' + escHtml(db.currentTable || 'ma_table') + ' LIMIT 100;"></textarea>' +
    '<div class="skmt-db__query-toolbar">' +
    '<div class="skmt-db__query-history-wrap">' +
    '<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-db-history-btn">Historique</button>' +
    '<div class="skmt-db__history-dropdown" id="skmt-db-history-list" style="display:none"></div>' +
    '</div>' +
    '<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="skmt-db-run-query">Exécuter</button>' +
    '</div>' +
    '</div>' +
    '<div id="skmt-db-query-result" class="skmt-db__query-result"></div>';

  var runBtn    = content.querySelector('#skmt-db-run-query');
  var queryInput = content.querySelector('#skmt-db-query-input');
  var historyBtn = content.querySelector('#skmt-db-history-btn');

  runBtn.addEventListener('click', runQuery);
  queryInput.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); runQuery(); }
  });
  historyBtn.addEventListener('click', toggleHistory);
  renderHistory();
}
```

### `runQuery()`

```javascript
function runQuery() {
  var input  = document.getElementById('skmt-db-query-input');
  var result = document.getElementById('skmt-db-query-result');
  if (!input || !result) return;
  var sql = input.value.trim();
  if (!sql) return;

  result.innerHTML = '<div class="skmt-db__loading">Exécution…</div>';

  ajax('skmt_db_run_query', { sql: sql }, function (data) {
    saveToHistory(sql);
    if (data.type === 'select') {
      // Réutiliser renderDataTable
      renderQueryResult(result, data);
    } else {
      result.innerHTML =
        '<div class="skmt-db__query-success">' +
        '<strong>' + data.affected + '</strong> ligne(s) affectée(s). ' +
        (data.insert_id ? 'Dernier ID inséré : <strong>' + escHtml(String(data.insert_id)) + '</strong>.' : '') +
        '</div>';
    }
  });
}
```

En cas d'erreur retournée par le serveur, `ajax()` affiche déjà un toast d'erreur. Ajouter aussi l'affichage dans `result` :
```javascript
// Dans la fonction ajax(), en cas d'erreur :
result.innerHTML = '<div class="skmt-db__query-error">' + escHtml(errorMessage) + '</div>';
```

### Historique localStorage

```javascript
var HISTORY_KEY = 'skmt_db_query_history';
var MAX_HISTORY = 20;

function saveToHistory(sql) {
  var h = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
  h = h.filter(function (q) { return q !== sql; }); // dédupliquer
  h.unshift(sql);
  if (h.length > MAX_HISTORY) h = h.slice(0, MAX_HISTORY);
  localStorage.setItem(HISTORY_KEY, JSON.stringify(h));
  renderHistory();
}

function renderHistory() {
  var list = document.getElementById('skmt-db-history-list');
  if (!list) return;
  var h = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
  if (!h.length) { list.innerHTML = '<p class="skmt-db__history-empty">Aucun historique.</p>'; return; }
  list.innerHTML = h.map(function (q, i) {
    return '<div class="skmt-db__history-item" data-index="' + i + '">' + escHtml(q.substring(0, 80)) + (q.length > 80 ? '…' : '') + '</div>';
  }).join('');
  list.querySelectorAll('.skmt-db__history-item').forEach(function (el) {
    el.addEventListener('click', function () {
      var idx = parseInt(el.dataset.index, 10);
      var h2  = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
      var input = document.getElementById('skmt-db-query-input');
      if (input) input.value = h2[idx] || '';
      toggleHistory();
    });
  });
}

function toggleHistory() {
  var list = document.getElementById('skmt-db-history-list');
  if (list) list.style.display = list.style.display === 'none' ? '' : 'none';
}
```

---

### AJAX `skmt_db_run_query`

```php
public function ajax_run_query(): void {
    check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

    global $wpdb;
    $sql = trim( wp_unslash( $_POST['sql'] ?? '' ) );
    if ( ! $sql ) wp_send_json_error( [ 'message' => 'Requête vide.' ] );

    // Détecter si c'est un SELECT
    $is_select = preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql );

    // Désactiver temporairement le cache de requêtes (évite les faux résultats en dev)
    $wpdb->flush();

    if ( $is_select ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
        $results = $wpdb->get_results( $sql, ARRAY_A );
        if ( $wpdb->last_error ) {
            wp_send_json_error( [ 'message' => $wpdb->last_error ] );
        }
        wp_send_json_success( [
            'type'    => 'select',
            'columns' => ! empty( $results ) ? array_keys( $results[0] ) : [],
            'rows'    => $results,
            'total'   => count( $results ),
        ] );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
        $result = $wpdb->query( $sql );
        if ( $result === false ) {
            wp_send_json_error( [ 'message' => $wpdb->last_error ] );
        }
        wp_send_json_success( [
            'type'      => 'write',
            'affected'  => $result,
            'insert_id' => $wpdb->insert_id,
        ] );
    }
}
```

---

## Export SQL

### Bouton "Exporter SQL" (dans le header de la table)

Click → `window.skmtModal.open({ title: 'Exporter SQL', ... })` non nécessaire ici — l'export se déclenche directement (pas destructif) via une requête POST qui retourne un fichier à télécharger.

Le téléchargement s'effectue via une soumission de formulaire POST caché (pour bypasser les limites du fetch sur les fichiers) :

```javascript
document.getElementById('skmt-db-export-btn').addEventListener('click', function () {
  var form = document.createElement('form');
  form.method = 'POST';
  form.action = skmtAdmin.ajaxUrl;
  var fields = {
    action: 'skmt_db_export_sql',
    nonce:  db.nonce,
    table:  db.currentTable,
  };
  Object.keys(fields).forEach(function (k) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = k;
    input.value = fields[k];
    form.appendChild(input);
  });
  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
});
```

### AJAX `skmt_db_export_sql`

Ce handler envoie directement un fichier SQL en réponse (pas de `wp_send_json_*`) :

```php
public function ajax_export_sql(): void {
    check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Permissions insuffisantes.' );

    global $wpdb;
    $table = sanitize_key( $_POST['table'] ?? '' );
    if ( ! $table ) wp_die( 'Table invalide.' );

    $filename = $table . '_' . gmdate( 'Y-m-d_His' ) . '.sql';

    header( 'Content-Type: application/octet-stream' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    // Structure de la table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
    $create = $wpdb->get_row( 'SHOW CREATE TABLE `' . $table . '`', ARRAY_N );
    echo "-- Studio Kyne Mini Tools - Export SQL\n";
    echo '-- Table: ' . $table . "\n";
    echo '-- Date: ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n\n";
    echo "DROP TABLE IF EXISTS `" . $table . "`;\n";
    echo $create[1] . ";\n\n";

    // Données par lots de 500
    $offset = 0;
    $batch  = 500;
    do {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
        $rows = $wpdb->get_results( 'SELECT * FROM `' . $table . '` LIMIT ' . $batch . ' OFFSET ' . $offset, ARRAY_A );
        if ( ! $rows ) break;
        foreach ( $rows as $row ) {
            $values = array_map( function ( $v ) use ( $wpdb ) {
                return $v === null ? 'NULL' : "'" . esc_sql( $v ) . "'";
            }, $row );
            echo 'INSERT INTO `' . $table . '` VALUES (' . implode( ', ', $values ) . ");\n";
        }
        $offset += $batch;
    } while ( count( $rows ) === $batch );

    exit;
}
```

---

## CSS supplémentaire (`database.css`)

```css
/* Éditeur SQL */
.skmt-db__query-warning {
  display: flex; align-items: flex-start; gap: 8px;
  padding: 10px 16px; background: oklch(from var(--skmt-warning) l c h / 0.1);
  border-bottom: 1px solid oklch(from var(--skmt-warning) l c h / 0.3);
  font-size: 12px; color: var(--skmt-text);
}
.skmt-db__query-editor-wrap { padding: 12px 16px; border-bottom: 1px solid var(--skmt-border); }
.skmt-db__query-input {
  width: 100%; min-height: 120px; font-family: "SFMono-Regular", Consolas, Menlo, monospace;
  font-size: 13px; resize: vertical; padding: 10px; border: 1px solid var(--skmt-border);
  border-radius: var(--skmt-radius-sm); background: var(--skmt-n50); line-height: 1.6;
}
.skmt-db__query-input:focus { border-color: var(--skmt-accent); outline: none; }
.skmt-db__query-toolbar { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; }
.skmt-db__query-result { padding: 16px; overflow: auto; }
.skmt-db__query-success { padding: 12px; background: oklch(from var(--skmt-success) l c h / 0.1); border-radius: var(--skmt-radius-sm); border: 1px solid oklch(from var(--skmt-success) l c h / 0.3); }
.skmt-db__query-error   { padding: 12px; background: oklch(from var(--skmt-danger)  l c h / 0.1); border-radius: var(--skmt-radius-sm); border: 1px solid oklch(from var(--skmt-danger)  l c h / 0.3); font-family: monospace; font-size: 12px; }

/* Historique */
.skmt-db__query-history-wrap { position: relative; }
.skmt-db__history-dropdown {
  position: absolute; bottom: calc(100% + 4px); left: 0;
  min-width: 400px; max-height: 250px; overflow-y: auto;
  background: var(--skmt-surface); border: 1px solid var(--skmt-border);
  border-radius: var(--skmt-radius); box-shadow: var(--skmt-shadow-md); z-index: 100;
}
.skmt-db__history-item { padding: 8px 12px; font-size: 12px; font-family: monospace; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.skmt-db__history-item:hover { background: var(--skmt-n100); }
.skmt-db__history-empty { padding: 12px; font-size: 12px; color: var(--skmt-text-secondary); }
```

## Ce qu'il ne faut PAS faire

- Ne pas limiter l'éditeur SQL aux SELECT — la restriction est un warning UX, pas une restriction technique (l'utilisateur `manage_options` est responsable).
- Ne pas utiliser `wp_json_encode` pour retourner le fichier SQL — envoyer directement avec `echo` + `exit`.
- Ne pas stocker l'historique en DB — localStorage uniquement.
- Ne pas implementer `ajax_insert_row` dans ce prompt (ajout d'une nouvelle ligne) — c'est un bonus facultatif.
