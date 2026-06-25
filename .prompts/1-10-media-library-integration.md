# Prompt 1-10 — Module Médias : Intégration médiathèque WP + Sidebar + Drag & Drop + Filtres

## Contexte

Suite du prompt 1-09. La taxonomie `skmt_media_folder` est enregistrée, les AJAX endpoints existent. Cette étape intègre l'interface de gestion des dossiers **directement dans la médiathèque WP native** (`upload.php` en vue grille).

## Approche technique

La médiathèque WP en mode grille utilise **Backbone.js + Underscore** (`wp.media`). L'intégration se fait par :
1. Injection d'une **sidebar HTML** à gauche de la médiathèque via DOM manipulation après le chargement de la page
2. Filtrage de la médiathèque en modifiant le paramètre `posts__in` de la query WP via un filtre Backbone sur la collection `wp.media.query`
3. Drag & drop des items médias vers les dossiers via l'API HTML5 (pas de Backbone)

---

## JS : `assets/admin/js/modules/media.js`

Ce fichier est chargé uniquement sur `upload.php` (enqueue conditionnel dans Module.php → prompt 1-09).

### Structure globale

```javascript
(function ($) {
  "use strict";

  var media = {
    nonce:         skmtMedia.nonce,
    folders:       [],       // array de folder objects
    currentFolder: -1,       // -1 = tous, 0 = non classés, N = folder_id
    draggedItems:  [],       // IDs d'attachments en cours de drag
    i18n:          skmtMedia.i18n,
  };

  document.addEventListener('DOMContentLoaded', function () {
    // Attendre que la médiathèque WP soit initialisée
    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') return;
    initFolderSidebar();
  });

  // ... fonctions détaillées ci-dessous ...

})(jQuery);
```

### `initFolderSidebar()`

Injecter la sidebar dans `.wp-core-ui .media-frame` après son apparition dans le DOM. Utiliser un `MutationObserver` ou un `setInterval` court pour attendre que la médiathèque soit rendue :

```javascript
function initFolderSidebar() {
  var attempts = 0;
  var interval = setInterval(function () {
    var frame = document.querySelector('.media-frame-content');
    if (!frame || ++attempts > 30) { clearInterval(interval); return; }
    clearInterval(interval);
    injectSidebar(frame);
    loadFolders();
  }, 200);
}

function injectSidebar(frame) {
  // Wrapper autour du contenu existant + ajout de la sidebar
  var wrapper = document.createElement('div');
  wrapper.id = 'skmt-media-wrapper';
  wrapper.className = 'skmt-media-wrapper';

  var sidebar = document.createElement('div');
  sidebar.id = 'skmt-media-sidebar';
  sidebar.className = 'skmt-media-sidebar';
  sidebar.innerHTML = buildSidebarHTML();

  frame.parentNode.insertBefore(wrapper, frame);
  wrapper.appendChild(sidebar);
  wrapper.appendChild(frame);

  initSidebarEvents();
}
```

### Structure HTML de la sidebar

```javascript
function buildSidebarHTML() {
  return '' +
    '<div class="skmt-media-sidebar__header">' +
    '<span class="skmt-media-sidebar__title">Dossiers</span>' +
    '<button type="button" id="skmt-media-new-folder" class="skmt-media-sidebar__add-btn" title="' + escHtml(media.i18n.newFolder) + '">+</button>' +
    '</div>' +
    '<div class="skmt-media-sidebar__tree" id="skmt-media-tree">' +
    '<div class="skmt-media-loading">Chargement…</div>' +
    '</div>' +
    '<div id="skmt-media-modal-new-folder" class="skmt-modal-overlay" role="dialog" aria-modal="true">' +
    '<div class="skmt-modal">' +
    '<div class="skmt-modal__header"><h3 class="skmt-modal__title">' + escHtml(media.i18n.newFolder) + '</h3></div>' +
    '<div class="skmt-modal__body"><div class="skmt-form__group">' +
    '<label class="skmt-form__label">' + escHtml(media.i18n.folderName) + '</label>' +
    '<input type="text" class="skmt-input" id="skmt-media-new-folder-name" autocomplete="off">' +
    '</div></div>' +
    '<div class="skmt-modal__footer">' +
    '<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary skmt-modal-close">Annuler</button>' +
    '<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="skmt-media-create-folder-confirm">Créer</button>' +
    '</div></div></div>';
}
```

### `loadFolders()` et `renderTree()`

```javascript
function ajax(action, data, cb) {
  var fd = new FormData();
  fd.append('action', action);
  fd.append('nonce', media.nonce);
  Object.keys(data).forEach(function (k) {
    if (Array.isArray(data[k])) {
      data[k].forEach(function (v) { fd.append(k + '[]', v); });
    } else {
      fd.append(k, data[k]);
    }
  });
  fetch(skmtMedia.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (res.success) cb(res.data);
      else if (typeof window.skmtShowToast === 'function') window.skmtShowToast(res.data && res.data.message || 'Erreur', 'error');
    })
    .catch(function () {
      if (typeof window.skmtShowToast === 'function') window.skmtShowToast('Erreur réseau', 'error');
    });
}

function loadFolders() {
  ajax('skmt_media_get_folders', {}, function (data) {
    media.folders      = data.folders;
    media.unorganized  = data.unorganized;
    renderTree();
  });
}

function renderTree() {
  var tree = document.getElementById('skmt-media-tree');
  if (!tree) return;

  // Item "Tous les médias"
  var html = renderFolderItem({ id: -1, name: media.i18n.allMedia, count: null, parent: null }, true);
  // Item "Non classés"
  html += renderFolderItem({ id: 0, name: media.i18n.unorganized, count: media.unorganized, parent: null }, false);

  // Dossiers root (parent = 0)
  var roots = media.folders.filter(function (f) { return !f.parent; });
  roots.forEach(function (folder) {
    html += renderFolderItem(folder);
    // Sous-dossiers
    var children = media.folders.filter(function (f) { return f.parent === folder.id; });
    if (children.length) {
      html += '<div class="skmt-media-children">';
      children.forEach(function (child) { html += renderFolderItem(child); });
      html += '</div>';
    }
  });

  tree.innerHTML = html;
  bindFolderEvents();
}

function renderFolderItem(folder, isAll) {
  var isActive = folder.id === media.currentFolder;
  var countBadge = folder.count !== null ? '<span class="skmt-media-folder__count">' + folder.count + '</span>' : '';
  var contextMenu = folder.id > 0 ?
    '<button type="button" class="skmt-media-folder__menu-btn" data-folder-id="' + folder.id + '">⋯</button>' : '';

  return '<div class="skmt-media-folder-item' + (isActive ? ' is-active' : '') + '" ' +
         'data-folder-id="' + folder.id + '" ' +
         (folder.id > 0 ? 'data-droppable="true" ' : '') + '>' +
         '<span class="skmt-media-folder__icon">' + folderSvg(folder.id) + '</span>' +
         '<span class="skmt-media-folder__name">' + escHtml(folder.name) + '</span>' +
         countBadge + contextMenu +
         '</div>';
}
```

### Sélection d'un dossier → filtrer la médiathèque

```javascript
function selectFolder(folderId) {
  media.currentFolder = folderId;
  renderTree();

  if (folderId === -1) {
    // Tous les médias : retirer le filtre
    if (wp.media.frame && wp.media.frame.content && wp.media.frame.content.get()) {
      var collection = wp.media.frame.content.get().collection;
      if (collection) {
        collection.props.unset('post__in');
        collection.reset();
        collection.more();
      }
    }
    return;
  }

  // Récupérer les IDs du dossier puis filtrer
  ajax('skmt_media_get_folder_items', { folder_id: folderId }, function (data) {
    var frame = wp.media.frame;
    if (!frame) return;
    var content = frame.content.get();
    if (!content || !content.collection) return;
    var collection = content.collection;

    if (data.mode === 'all') {
      collection.props.unset('post__in');
    } else if (data.ids.length === 0) {
      // Dossier vide : forcer post__in à [0] pour retourner zéro résultat
      collection.props.set('post__in', [0]);
    } else {
      collection.props.set('post__in', data.ids);
    }
    collection.reset();
    collection.more();
  });
}
```

### Drag & Drop des items médias vers les dossiers

La médiathèque WP génère des `<li class="attachment">` dans la grille. Hooker après chargement :

```javascript
function initDragDrop() {
  // Observer les nouvelles attachments ajoutées dans la grille
  var grid = document.querySelector('.attachments-browser .attachments');
  if (!grid) return;

  var observer = new MutationObserver(function () {
    grid.querySelectorAll('li.attachment:not([data-skmt-drag])').forEach(bindDragOnItem);
  });
  observer.observe(grid, { childList: true, subtree: false });
  grid.querySelectorAll('li.attachment').forEach(bindDragOnItem);

  // Dossiers comme drop targets
  document.getElementById('skmt-media-tree').addEventListener('dragover', function (e) {
    var folder = e.target.closest('[data-droppable="true"]');
    if (folder) { e.preventDefault(); folder.classList.add('is-drag-over'); }
  });
  document.getElementById('skmt-media-tree').addEventListener('dragleave', function (e) {
    var folder = e.target.closest('[data-droppable="true"]');
    if (folder) folder.classList.remove('is-drag-over');
  });
  document.getElementById('skmt-media-tree').addEventListener('drop', function (e) {
    e.preventDefault();
    var folder = e.target.closest('[data-droppable="true"]');
    if (!folder) return;
    folder.classList.remove('is-drag-over');
    var folderId = parseInt(folder.dataset.folderId, 10);
    var ids      = media.draggedItems;
    if (!ids.length) return;
    ajax('skmt_media_move_items', { ids: ids, folder_id: folderId }, function () {
      if (typeof window.skmtShowToast === 'function') window.skmtShowToast(ids.length + ' média(s) déplacé(s).', 'success');
      loadFolders(); // rafraîchir les compteurs
    });
  });
}

function bindDragOnItem(el) {
  el.setAttribute('data-skmt-drag', '1');
  el.setAttribute('draggable', 'true');
  el.addEventListener('dragstart', function (e) {
    var id = parseInt(el.dataset.id, 10);
    // Vérifier si l'item est dans une sélection multiple (WP utilise .selected)
    var selected = document.querySelectorAll('li.attachment.selected');
    if (selected.length > 0 && el.classList.contains('selected')) {
      media.draggedItems = Array.from(selected).map(function (s) { return parseInt(s.dataset.id, 10); }).filter(Boolean);
    } else {
      media.draggedItems = id ? [id] : [];
    }
    e.dataTransfer.effectAllowed = 'move';
  });
}
```

### Menu contextuel sur les dossiers (⋯)

Click sur `⋯` → mini-dropdown avec :
- **Renommer** → modal `skmt-media-modal-rename` (à ajouter au HTML sidebar)
- **Nouveau sous-dossier** → modal new folder avec `parent_id` pré-rempli
- **Supprimer** → `window.skmtModal.open({ danger: true, title: i18n.deleteFolder, message: i18n.deleteFolderMsg, ... })`

Le dropdown apparaît à côté du bouton ⋯, se ferme au click elsewhere.

---

## CSS : `assets/admin/css/modules/media.css`

```css
/* Layout : sidebar + médiathèque */
.skmt-media-wrapper {
  display: flex;
  height: 100%;
  position: relative;
}

/* La médiathèque WP prend tout l'espace restant */
.skmt-media-wrapper .media-frame-content {
  flex: 1;
  min-width: 0;
}

/* Sidebar */
.skmt-media-sidebar {
  width: 220px;
  flex-shrink: 0;
  border-right: 1px solid #ddd;
  background: #fff;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  z-index: 10;
}

.skmt-media-sidebar__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  border-bottom: 1px solid #ddd;
}
.skmt-media-sidebar__title { font-weight: 600; font-size: 13px; }
.skmt-media-sidebar__add-btn {
  width: 24px; height: 24px;
  border: none; background: none; cursor: pointer;
  font-size: 18px; line-height: 1; color: #615FFF;
  border-radius: 4px;
}
.skmt-media-sidebar__add-btn:hover { background: #f0f0f0; }

.skmt-media-sidebar__tree { flex: 1; overflow-y: auto; padding: 6px 0; }

/* Items de dossier */
.skmt-media-folder-item {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 12px; cursor: pointer; border-radius: 4px; margin: 0 4px;
  font-size: 13px; position: relative;
}
.skmt-media-folder-item:hover { background: #f5f5f5; }
.skmt-media-folder-item.is-active { background: #615FFF; color: #fff; }
.skmt-media-folder-item.is-active .skmt-media-folder__count { color: rgba(255,255,255,.7); }
.skmt-media-folder-item.is-drag-over { background: #e8e7ff; border: 1px dashed #615FFF; }

.skmt-media-folder__icon { flex-shrink: 0; width: 16px; }
.skmt-media-folder__name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.skmt-media-folder__count { font-size: 11px; color: #888; flex-shrink: 0; }
.skmt-media-folder__menu-btn {
  display: none; position: absolute; right: 6px;
  background: none; border: none; cursor: pointer; padding: 2px 4px; font-size: 14px;
}
.skmt-media-folder-item:hover .skmt-media-folder__menu-btn { display: block; }

.skmt-media-children { padding-left: 16px; }
```

**Important** : les variables CSS `var(--skmt-*)` ne sont disponibles que sur les pages SKMT. Sur `upload.php`, utiliser des valeurs CSS directes (couleurs hexadécimales, etc.) ou charger `reset.css` en dépendance dans `enqueue_media_library_assets()`.

## Ce qu'il ne faut PAS faire

- Ne pas utiliser `wp.media.frame.on('open', ...)` pour initialiser — la frame est déjà ouverte sur `upload.php`.
- Ne pas modifier les fichiers WP core ou les templates Underscore de la médiathèque.
- Ne pas faire de rechargement de page pour filtrer — tout se fait via `collection.props.set('post__in', ids)`.
- Ne pas utiliser les variables CSS `var(--skmt-*)` dans `media.css` — cette feuille est chargée sur `upload.php` où `reset.css` SKMT n'est pas forcément chargé (à moins de l'ajouter en dépendance).
- Ne pas oublier que `window.skmtModal` (le modal programmatique) n'est disponible que sur les pages SKMT — pour la médiathèque WP, utiliser les modals nommées du design system (HTML inline dans la sidebar) ou un simple `confirm()` natif en fallback.
