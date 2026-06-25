/**
 * Studio Kyne Mini Tools — Éditeur de menus (page dédiée)
 * Initialisé via skmtAdmin.wlCurrentProfile et skmtAdmin.wpMenu.
 */
(function () {
  "use strict";

  if (typeof skmtAdmin === "undefined") return;

  var L = window.skmtLucide || {};

  /* ================================================================
   * ÉTAT
   * ================================================================ */

  var ed = {
    profile:     null,
    dirty:       false,
    selectedUid: null,
    dragSrcUid:  null,
  };

  var ms = { include: null, exclude: null };

  /* ================================================================
   * INIT
   * ================================================================ */

  document.addEventListener("DOMContentLoaded", function () {
    if (!document.getElementById("skmt-wl-tree")) return;

    ed.profile = skmtAdmin.wlCurrentProfile
      ? deepCopy(skmtAdmin.wlCurrentProfile)
      : blankProfile();

    ensureUids(ed.profile.items);
    mergeWpMenu();
    renderEditor();
    renderProfilesSidebar();
    bindTopbar();
    bindTreeActions();
    bindProfilesSidebar();

    var itemBack = document.getElementById("skmt-wl-item-back");
    if (itemBack) itemBack.onclick = function () { showProfilePanel(); renderTree(); };

    window.addEventListener("beforeunload", function (e) {
      if (ed.dirty) { e.preventDefault(); e.returnValue = ""; }
    });
  });

  /* ================================================================
   * PROFIL VIDE
   * ================================================================ */

  function blankProfile() {
    var profiles = skmtAdmin.wlProfiles || [];
    var names = profiles.map(function (p) { return p.name || ""; });
    var n = 1;
    while (names.indexOf("Menu " + n) !== -1) { n++; }
    return {
      id: "__new__", name: "Menu " + n, status: "draft", apply_to_all: false,
      include_roles: [], include_users: [], exclude_roles: [], exclude_users: [],
      items: [], updated_at: 0,
    };
  }

  /* ================================================================
   * FUSION AVEC LE MENU WP
   * ================================================================ */

  function mergeWpMenu() {
    var wpMenu = skmtAdmin.wpMenu || [];
    if (!ed.profile.items.length && wpMenu.length) {
      ed.profile.items = wpMenu
        .filter(function (m) { return m.slug !== ""; })
        .map(function (m) { return mkItem("wp_item", m.slug, buildWpChildren(m.slug), m); });
    } else {
      var seen = {};
      ed.profile.items.forEach(function (item) {
        if (!item._uid) item._uid = genUid();
        var wp = findWpItem(item.slug);
        item._wpLabel = wp ? stripTags(wp.label) : item.slug;
        item._wpIcon  = wp ? (wp.icon || "") : "";
        seen[item.slug] = true;
        (item.children || []).forEach(function (c) {
          if (!c._uid) c._uid = genUid();
          var sub = findWpSub(item.slug, c.slug);
          c._wpLabel = sub ? stripTags(sub.label) : c.slug;
          c._wpIcon  = "";
        });
      });
      wpMenu.forEach(function (m) {
        if (m.slug && !seen[m.slug]) {
          ed.profile.items.push(mkItem("wp_item", m.slug, buildWpChildren(m.slug), m));
        }
      });
    }
  }

  function mkItem(type, slug, children, wp) {
    return {
      type: type, slug: slug, label: null, icon: null,
      visible: true, target_blank: false, url: "",
      children: children || [],
      _uid: genUid(),
      _wpLabel: wp ? stripTags(wp.label || slug) : slug,
      _wpIcon:  wp ? (wp.icon || "") : "",
    };
  }

  function buildWpChildren(parentSlug) {
    return ((skmtAdmin.wpSubmenu || {})[parentSlug] || []).map(function (s) {
      return mkItem("wp_item", s.slug, [], s);
    });
  }

  /* ================================================================
   * RENDU GLOBAL
   * ================================================================ */

  function renderEditor() {
    populateProfilePanel();
    renderTree();
    showProfilePanel();
    ms.include = createMultiSelect("skmt-wl-include-select", buildInitialChips(
      ed.profile.include_roles || [], ed.profile.include_users || []
    ));
    ms.exclude = createMultiSelect("skmt-wl-exclude-select", buildInitialChips(
      ed.profile.exclude_roles || [], ed.profile.exclude_users || []
    ));
    syncApplyAll();
  }

  /* ================================================================
   * ARBRE
   * ================================================================ */

  function renderTree() {
    var tree = document.getElementById("skmt-wl-tree");
    if (!tree) return;
    tree.innerHTML = ed.profile.items.map(buildItemHtml).join("");
    bindTree(tree);
  }

  function buildItemHtml(item) {
    if (item.type === "separator") {
      return (
        '<div class="skmt-wl-tree-item skmt-wl-tree-item--sep" data-uid="' + esc(item._uid) + '" draggable="true">' +
          '<div class="skmt-wl-tree-item__row">' +
            '<span class="skmt-wl-tree-item__handle">' + (L.gripVertical || "") + "</span>" +
            '<span class="skmt-wl-tree-sep-line"></span>' +
            '<button type="button" class="skmt-wl-tree-item__del" aria-label="Supprimer">' + (L.trash || "×") + "</button>" +
          "</div>" +
        "</div>"
      );
    }

    var label    = item.label || item._wpLabel || item.slug;
    var hidden   = item.visible === false;
    var selected = ed.selectedUid === item._uid;

    var childrenHtml = "";
    if (item.children && item.children.length) {
      childrenHtml = '<div class="skmt-wl-tree-item__children">' +
        item.children.map(function (c) {
          var cl = c.label || c._wpLabel || c.slug;
          var ch = c.visible === false;
          var cs = ed.selectedUid === c._uid;
          return (
            '<div class="skmt-wl-tree-item skmt-wl-tree-item--child' + (cs ? " is-selected" : "") + '" data-uid="' + esc(c._uid) + '">' +
              '<div class="skmt-wl-tree-item__row">' +
                '<span class="skmt-wl-tree-item__handle">' + (L.gripVertical || "") + "</span>" +
                '<span class="skmt-wl-tree-item__label' + (ch ? " is-hidden" : "") + '">' + esc(cl) + "</span>" +
                '<button type="button" class="skmt-wl-tree-item__vis" data-uid="' + esc(c._uid) + '" title="' + (ch ? "Afficher" : "Masquer") + '">' +
                  (ch ? (L.eyeOff || "") : (L.eye || "")) +
                "</button>" +
              "</div>" +
            "</div>"
          );
        }).join("") +
      "</div>";
    }

    return (
      '<div class="skmt-wl-tree-item' + (selected ? " is-selected" : "") + '" data-uid="' + esc(item._uid) + '" draggable="true">' +
        '<div class="skmt-wl-tree-item__row">' +
          '<span class="skmt-wl-tree-item__handle">' + (L.gripVertical || "") + "</span>" +
          buildIconEl(item) +
          '<span class="skmt-wl-tree-item__label' + (hidden ? " is-hidden" : "") + '">' + esc(label) + "</span>" +
          '<button type="button" class="skmt-wl-tree-item__vis" data-uid="' + esc(item._uid) + '" title="' + (hidden ? "Afficher" : "Masquer") + '">' +
            (hidden ? (L.eyeOff || "") : (L.eye || "")) +
          "</button>" +
          (item.type === "custom_link"
            ? '<button type="button" class="skmt-wl-tree-item__del" aria-label="Supprimer">' + (L.trash || "×") + "</button>"
            : "") +
        "</div>" +
        childrenHtml +
      "</div>"
    );
  }

  function buildIconEl(item) {
    var icon = item.icon || item._wpIcon || "";
    if (!icon) {
      return '<span class="skmt-wl-tree-item__icon-ph"></span>';
    }
    if (icon.indexOf("dashicons-") === 0) {
      return '<span class="skmt-wl-tree-item__icon dashicons ' + esc(icon) + '" aria-hidden="true"></span>';
    }
    var src = icon.indexOf("svg:") === 0
      ? "data:image/svg+xml;base64," + icon.slice(4)
      : icon;
    return '<img class="skmt-wl-tree-item__icon" src="' + esc(src) + '" aria-hidden="true" alt="">';
  }

  function bindTree(tree) {
    tree.querySelectorAll(".skmt-wl-tree-item:not(.skmt-wl-tree-item--child)").forEach(function (el) {
      el.addEventListener("dragstart", function (e) {
        ed.dragSrcUid = el.dataset.uid;
        el.classList.add("is-dragging");
        e.dataTransfer.effectAllowed = "move";
        e.dataTransfer.setData("text/plain", el.dataset.uid);
      });
      el.addEventListener("dragend", function () {
        el.classList.remove("is-dragging", "is-drag-over");
        tree.querySelectorAll(".is-drag-over").forEach(function (t) { t.classList.remove("is-drag-over"); });
        ed.dragSrcUid = null;
      });
      el.addEventListener("dragover", function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = "move";
        if (el.dataset.uid !== ed.dragSrcUid) {
          tree.querySelectorAll(".is-drag-over").forEach(function (t) { t.classList.remove("is-drag-over"); });
          el.classList.add("is-drag-over");
        }
      });
      el.addEventListener("dragleave", function (e) {
        if (!el.contains(e.relatedTarget)) el.classList.remove("is-drag-over");
      });
      el.addEventListener("drop", function (e) {
        e.preventDefault();
        el.classList.remove("is-drag-over");
        var src = ed.dragSrcUid, tgt = el.dataset.uid;
        if (src && tgt && src !== tgt) moveItem(src, tgt);
        ed.dragSrcUid = null;
      });
    });

    tree.querySelectorAll(".skmt-wl-tree-item:not(.skmt-wl-tree-item--sep)").forEach(function (el) {
      el.querySelector(".skmt-wl-tree-item__row").addEventListener("click", function (e) {
        if (e.target.closest(".skmt-wl-tree-item__vis") || e.target.closest(".skmt-wl-tree-item__del")) return;
        selectItem(el.dataset.uid);
      });
    });

    tree.querySelectorAll(".skmt-wl-tree-item__vis").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var item = findByUid(btn.dataset.uid);
        if (!item) return;
        item.visible = item.visible === false;
        ed.dirty = true; renderTree();
      });
    });

    tree.querySelectorAll(".skmt-wl-tree-item__del").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var uid = btn.closest("[data-uid]").dataset.uid;
        var idx = ed.profile.items.findIndex(function (i) { return i._uid === uid; });
        if (idx !== -1) {
          ed.profile.items.splice(idx, 1);
          ed.dirty = true;
          if (ed.selectedUid === uid) { ed.selectedUid = null; showProfilePanel(); }
          renderTree();
        }
      });
    });
  }

  /* ================================================================
   * DRAG & DROP REORDER
   * ================================================================ */

  function moveItem(srcUid, tgtUid) {
    var items  = ed.profile.items;
    var srcIdx = items.findIndex(function (i) { return i._uid === srcUid; });
    var tgtIdx = items.findIndex(function (i) { return i._uid === tgtUid; });
    if (srcIdx === -1 || tgtIdx === -1) return;
    var item = items.splice(srcIdx, 1)[0];
    items.splice(srcIdx < tgtIdx ? tgtIdx : tgtIdx, 0, item);
    ed.dirty = true; renderTree();
  }

  /* ================================================================
   * SÉLECTION
   * ================================================================ */

  function selectItem(uid) {
    ed.selectedUid = uid;
    var item = findByUid(uid);
    if (item) showItemPanel(item);
    renderTree();
  }

  function showProfilePanel() {
    ed.selectedUid = null;
    show("skmt-wl-profile-settings");
    hide("skmt-wl-item-settings");
  }

  function showItemPanel(item) {
    hide("skmt-wl-profile-settings");
    show("skmt-wl-item-settings");
    var t = document.getElementById("skmt-wl-item-settings-title");
    if (t) t.textContent = item.label || item._wpLabel || item.slug || "Item";
    var fields = document.getElementById("skmt-wl-item-fields");
    if (fields) { fields.innerHTML = buildItemFields(item); bindItemFields(item); }
  }

  /* ================================================================
   * CHAMPS D'ITEM
   * ================================================================ */

  function buildItemFields(item) {
    if (item.type === "separator") return '<p class="skmt-wl-note">Séparateur — aucun paramètre.</p>';

    var iconType = "default", iconVal = "";
    if (item.icon) {
      if (item.icon.indexOf("dashicons-") === 0) { iconType = "dashicons"; iconVal = item.icon; }
      else { iconType = "image"; iconVal = item.icon; }
    }
    var imgSrc = (iconType === "image" && iconVal)
      ? (iconVal.indexOf("svg:") === 0 ? "data:image/svg+xml;base64," + iconVal.slice(4) : iconVal)
      : "";

    var html = "";
    if (item.type === "custom_link") {
      html += row("URL", '<input type="url" class="skmt-input" id="skmt-wl-item-url" value="' + esc(item.url || "") + '">', "");
    }
    html += row("Label",
      '<input type="text" class="skmt-input" id="skmt-wl-item-label" value="' + esc(item.label || "") + '" placeholder="' + esc(item._wpLabel || item.slug) + '">',
      "Laisser vide pour conserver le label d'origine."
    );
    html += '<div class="skmt-wl-settings-row skmt-wl-settings-row--col">' +
      '<div class="skmt-wl-settings-row__label"><span>Icône</span></div>' +
      buildIconPicker(iconType, iconVal, imgSrc) +
    "</div>";
    html += row("Visible",
      '<label class="skmt-toggle"><input type="checkbox" id="skmt-wl-item-visible"' + (item.visible !== false ? " checked" : "") + '><span class="skmt-toggle__slider"></span></label>',
      "", true
    );
    html += row("Ouvrir dans un nouvel onglet",
      '<label class="skmt-toggle"><input type="checkbox" id="skmt-wl-item-target"' + (item.target_blank ? " checked" : "") + '><span class="skmt-toggle__slider"></span></label>',
      "", true
    );
    if (item.type === "wp_item") {
      html += '<div class="skmt-wl-item-reset-row"><button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-wl-item-reset">Réinitialiser l\'élément</button></div>';
    }
    return html;
  }

  function row(label, control, help, inline) {
    var cls = inline ? "skmt-wl-settings-row skmt-wl-settings-row--inline" : "skmt-wl-settings-row";
    return (
      '<div class="' + cls + '">' +
        '<div class="skmt-wl-settings-row__label"><span>' + esc(label) + "</span>" +
          (help ? '<p class="skmt-form__help">' + esc(help) + "</p>" : "") +
        "</div>" + control +
      "</div>"
    );
  }

  function buildIconPicker(iconType, iconVal, imgSrc) {
    return (
      '<div class="skmt-wl-icon-picker">' +
        '<div class="skmt-wl-icon-preview-row">' +
          '<div class="skmt-wl-icon-thumb" id="skmt-wl-icon-thumb">' + buildIconThumb(iconType, iconVal, imgSrc) + "</div>" +
          '<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-wl-icon-open">Modifier l\'icône</button>' +
        "</div>" +
        '<div class="skmt-wl-icon-dropdown" id="skmt-wl-icon-dropdown" style="display:none">' +
          '<div class="skmt-wl-icon-picker-tabs">' +
            '<button type="button" class="skmt-wl-icon-tab is-active" data-tab="library">Bibliothèque</button>' +
            '<button type="button" class="skmt-wl-icon-tab" data-tab="media">Médiathèque WP</button>' +
          "</div>" +
          '<div class="skmt-wl-icon-pane" data-pane="library">' + buildIconLibrary() + "</div>" +
          '<div class="skmt-wl-icon-pane" data-pane="media" style="display:none">' +
            '<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-wl-media-btn">Ouvrir la médiathèque</button>' +
            (imgSrc
              ? '<img class="skmt-wl-icon-media-preview" id="skmt-wl-media-preview" src="' + esc(imgSrc) + '" alt="">'
              : '<img class="skmt-wl-icon-media-preview" id="skmt-wl-media-preview" src="" alt="" style="display:none">') +
          "</div>" +
          '<div class="skmt-wl-icon-picker-footer">' +
            '<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-wl-icon-default-btn">Icône par défaut</button>' +
          "</div>" +
        "</div>" +
      "</div>"
    );
  }

  function buildIconThumb(iconType, iconVal, imgSrc) {
    if (iconType === "default" || !iconVal) return '<span class="skmt-wl-icon-thumb__ph">Par défaut</span>';
    if (iconType === "dashicons") return '<span class="dashicons ' + esc(iconVal) + '"></span>';
    return '<img src="' + esc(imgSrc || iconVal) + '" alt="">';
  }

  function buildIconLibrary() {
    var lib  = window.skmtWlIconLibrary || {};
    var keys = Object.keys(lib);
    if (!keys.length) {
      return '<p class="skmt-wl-icon-lib-empty">Bibliothèque vide — les icônes seront configurées prochainement.</p>';
    }
    return '<div class="skmt-wl-icon-grid">' +
      keys.map(function (k) {
        var b64 = btoa(unescape(encodeURIComponent(lib[k])));
        return '<button type="button" class="skmt-wl-icon-grid-item" data-value="svg:' + esc(k) + '" title="' + esc(k) + '">' +
          '<img src="data:image/svg+xml;base64,' + b64 + '" alt="' + esc(k) + '">' +
        "</button>";
      }).join("") +
    "</div>";
  }

  function bindItemFields(item) {
    var urlEl = document.getElementById("skmt-wl-item-url");
    if (urlEl) urlEl.addEventListener("input", function () { item.url = urlEl.value; ed.dirty = true; });

    var lblEl = document.getElementById("skmt-wl-item-label");
    if (lblEl) lblEl.addEventListener("input", function () {
      item.label = lblEl.value || null; ed.dirty = true; renderTree();
    });

    var visEl = document.getElementById("skmt-wl-item-visible");
    if (visEl) visEl.addEventListener("change", function () { item.visible = visEl.checked; ed.dirty = true; renderTree(); });

    var tgtEl = document.getElementById("skmt-wl-item-target");
    if (tgtEl) tgtEl.addEventListener("change", function () { item.target_blank = tgtEl.checked; ed.dirty = true; });

    var rstBtn = document.getElementById("skmt-wl-item-reset");
    if (rstBtn) rstBtn.addEventListener("click", function () {
      item.label = null; item.icon = null; item.visible = true; item.target_blank = false;
      ed.dirty = true; showItemPanel(item); renderTree();
    });

    bindIconPicker(item);
  }

  function bindIconPicker(item) {
    var openBtn   = document.getElementById("skmt-wl-icon-open");
    var dropdown  = document.getElementById("skmt-wl-icon-dropdown");
    var thumb     = document.getElementById("skmt-wl-icon-thumb");
    var defBtn    = document.getElementById("skmt-wl-icon-default-btn");
    var mediaBtn  = document.getElementById("skmt-wl-media-btn");
    var mediaPrev = document.getElementById("skmt-wl-media-preview");

    if (openBtn && dropdown) openBtn.addEventListener("click", function () {
      dropdown.style.display = dropdown.style.display === "none" ? "" : "none";
    });

    document.querySelectorAll(".skmt-wl-icon-tab").forEach(function (tab) {
      tab.addEventListener("click", function () {
        document.querySelectorAll(".skmt-wl-icon-tab").forEach(function (t) { t.classList.remove("is-active"); });
        tab.classList.add("is-active");
        document.querySelectorAll(".skmt-wl-icon-pane").forEach(function (p) { p.style.display = "none"; });
        var pane = document.querySelector('.skmt-wl-icon-pane[data-pane="' + tab.dataset.tab + '"]');
        if (pane) pane.style.display = "";
      });
    });

    document.querySelectorAll(".skmt-wl-icon-grid-item").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var val = btn.dataset.value;
        var b64 = val.slice(4);
        item.icon = "svg:" + b64;
        ed.dirty = true; renderTree();
        if (thumb) thumb.innerHTML = '<img src="data:image/svg+xml;base64,' + esc(b64) + '" alt="">';
        if (dropdown) dropdown.style.display = "none";
      });
    });

    if (defBtn) defBtn.addEventListener("click", function () {
      item.icon = null; ed.dirty = true; renderTree();
      if (thumb) thumb.innerHTML = '<span class="skmt-wl-icon-thumb__ph">Par défaut</span>';
      if (dropdown) dropdown.style.display = "none";
    });

    if (mediaBtn && typeof wp !== "undefined" && wp.media) {
      mediaBtn.addEventListener("click", function () {
        var frame = wp.media({ title: "Choisir une icône", multiple: false });
        frame.on("select", function () {
          var att = frame.state().get("selection").first().toJSON();
          item.icon = att.url; ed.dirty = true; renderTree();
          if (mediaPrev) { mediaPrev.src = att.url; mediaPrev.style.display = ""; }
          if (thumb) thumb.innerHTML = '<img src="' + esc(att.url) + '" alt="">';
          if (dropdown) dropdown.style.display = "none";
        });
        frame.open();
      });
    }
  }

  /* ================================================================
   * ACTIONS ARBRE
   * ================================================================ */

  function bindTreeActions() {
    var sepBtn  = document.getElementById("skmt-wl-add-sep");
    var linkBtn = document.getElementById("skmt-wl-add-link");
    if (sepBtn)  sepBtn.addEventListener("click",  function () { ed.profile.items.push(mkItem("separator", "", [])); ed.dirty = true; renderTree(); });
    if (linkBtn) linkBtn.addEventListener("click", function () {
      var item = mkItem("custom_link", "custom-" + genUid(), []);
      item.label = "Nouveau lien"; item._wpLabel = "Nouveau lien";
      ed.profile.items.push(item); ed.dirty = true; renderTree();
    });
  }

  /* ================================================================
   * SIDEBAR LISTE DES PROFILS
   * ================================================================ */

  var sidebarState = { filter: "all", search: "" };

  function renderProfilesSidebar() {
    var container = document.getElementById("skmt-wl-ep-profiles-list");
    if (!container) return;

    var profiles = skmtAdmin.wlProfiles || [];
    var currentId = window.skmtWlCurrentProfileId || ed.profile.id || "__new__";

    var filtered = profiles.filter(function (p) {
      var matchFilter = sidebarState.filter === "all" || p.status === sidebarState.filter;
      var matchSearch = !sidebarState.search ||
        (p.name || "").toLowerCase().indexOf(sidebarState.search.toLowerCase()) !== -1;
      return matchFilter && matchSearch;
    });

    if (!filtered.length) {
      container.innerHTML = '<p class="skmt-wl-ep-profiles-empty">' +
        (profiles.length === 0
          ? "Aucun menu. Cliquez sur « + » pour créer."
          : "Aucun résultat.") +
        "</p>";
      return;
    }

    var i18n = skmtAdmin.i18n || {};
    container.innerHTML = filtered.map(function (p) {
      var isActive = p.status === "active";
      var badgeMod = isActive ? "skmt-badge--success" : "skmt-badge--warning";
      var statusLabel = isActive ? (i18n.active || "Actif") : (i18n.draft || "Brouillon");
      var isCurrent = p.id === currentId;
      return (
        '<button type="button" class="skmt-wl-ep-profile-item' + (isCurrent ? " is-active" : "") + '" data-id="' + esc(p.id) + '">' +
          '<span class="skmt-wl-ep-profile-item__name">' + esc(p.name || "Menu sans nom") + "</span>" +
          '<span class="skmt-badge ' + badgeMod + '">' + esc(statusLabel) + "</span>" +
        "</button>"
      );
    }).join("");
  }

  function bindProfilesSidebar() {
    var container = document.getElementById("skmt-wl-ep-profiles-list");
    var searchEl = document.getElementById("skmt-wl-ep-search");

    if (container) {
      container.addEventListener("click", function (e) {
        var btn = e.target.closest(".skmt-wl-ep-profile-item");
        if (!btn) return;
        var id = btn.dataset.id;
        if (!id) return;

        function navigate() {
          window.location.href = (skmtAdmin.wlEditorUrl || "") + "&profile_id=" + encodeURIComponent(id);
        }

        if (ed.dirty) {
          window.skmtModal.open({
            title:        (skmtAdmin.i18n || {}).unsavedChanges || "Modifications non sauvegardées",
            message:      (skmtAdmin.i18n || {}).leaveConfirm   || "Vos modifications seront perdues. Continuer ?",
            confirmLabel: "Quitter sans enregistrer",
            cancelLabel:  "Annuler",
            danger:       true,
            onConfirm:    navigate,
          });
        } else {
          navigate();
        }
      });
    }

    if (searchEl) {
      searchEl.addEventListener("input", function () {
        sidebarState.search = searchEl.value;
        renderProfilesSidebar();
      });
    }

    document.querySelectorAll(".skmt-wl-ep__profiles-tab").forEach(function (tab) {
      tab.addEventListener("click", function () {
        document.querySelectorAll(".skmt-wl-ep__profiles-tab").forEach(function (t) { t.classList.remove("is-active"); });
        tab.classList.add("is-active");
        sidebarState.filter = tab.dataset.filter || "all";
        renderProfilesSidebar();
      });
    });
  }

  /* ================================================================
   * PANEL PROFIL
   * ================================================================ */

  function populateProfilePanel() {
    var nameEl = document.getElementById("skmt-wl-profile-name");
    if (nameEl) {
      nameEl.value = ed.profile.name || "";
      updatePageTitle();
      nameEl.addEventListener("input", function () { ed.profile.name = nameEl.value; ed.dirty = true; updatePageTitle(); });
    }

    var applyAll = document.getElementById("skmt-wl-apply-all");
    if (applyAll) {
      applyAll.checked = !!ed.profile.apply_to_all;
      applyAll.addEventListener("change", function () { ed.profile.apply_to_all = applyAll.checked; ed.dirty = true; syncApplyAll(); });
    }

    setSegment("skmt-wl-status-", ed.profile.status || "draft");
    ["skmt-wl-status-draft", "skmt-wl-status-active"].forEach(function (id) {
      var btn = document.getElementById(id);
      if (btn) btn.addEventListener("click", function () { setSegment("skmt-wl-status-", btn.dataset.value); ed.dirty = true; });
    });
  }

  function syncApplyAll() {
    var checked = !!(document.getElementById("skmt-wl-apply-all") || {}).checked;
    ["skmt-wl-targeting-rows", "skmt-wl-targeting-rows-ex"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.style.display = checked ? "none" : "";
    });
  }

  function setSegment(prefix, value) {
    document.querySelectorAll('[id^="' + prefix + '"]').forEach(function (b) {
      b.classList.toggle("is-active", b.dataset.value === value);
    });
  }

  function updatePageTitle() {
    var t = document.getElementById("skmt-wl-ep-title");
    if (t && ed.profile.name) t.textContent = ed.profile.name;
  }

  /* ================================================================
   * TOPBAR
   * ================================================================ */

  function bindTopbar() {
    var backBtn = document.getElementById("skmt-wl-back-btn");
    var saveBtn = document.getElementById("skmt-wl-save-btn");
    var delBtn  = document.getElementById("skmt-wl-delete-btn");
    var dupBtn  = document.getElementById("skmt-wl-duplicate-btn");

    if (backBtn) backBtn.addEventListener("click", function (e) {
      if (!ed.dirty) return;
      e.preventDefault();
      window.skmtModal.open({
        title:        (skmtAdmin.i18n || {}).unsavedChanges || "Modifications non sauvegardées",
        message:      (skmtAdmin.i18n || {}).leaveConfirm   || "Vos modifications seront perdues. Continuer ?",
        confirmLabel: "Quitter sans enregistrer",
        cancelLabel:  "Annuler",
        danger:       true,
        onConfirm:    function () { window.location.href = skmtAdmin.wlListUrl || "#"; },
      });
    });

    if (saveBtn) saveBtn.addEventListener("click", onSave);
    if (delBtn)  delBtn.addEventListener("click",  onDelete);
    if (dupBtn)  dupBtn.addEventListener("click",  onDuplicate);
  }

  function onSave() {
    var saveBtn = document.getElementById("skmt-wl-save-btn");
    if (saveBtn) saveBtn.disabled = true;

    ajaxPost("skmt_wl_save_profile", { profile: JSON.stringify(collectProfile()) }, function (data) {
      if (saveBtn) saveBtn.disabled = false;
      if (data && data.success) {
        var saved = data.data.profile;
        var wasNew = ed.profile.id === "__new__";
        ed.dirty = false;

        ensureUids(saved.items || []);
        var prevItems = ed.profile.items;
        saved.items = (saved.items || []).map(function (item, i) {
          var prev = prevItems[i] || {};
          item._uid     = prev._uid     || genUid();
          item._wpLabel = item._wpLabel || prev._wpLabel || "";
          item._wpIcon  = item._wpIcon  || prev._wpIcon  || "";
          (item.children || []).forEach(function (c, j) {
            var pc = (prev.children || [])[j] || {};
            c._uid     = pc._uid     || genUid();
            c._wpLabel = c._wpLabel  || pc._wpLabel || "";
          });
          return item;
        });
        ed.profile = saved;

        if (wasNew && saved.id) {
          window.location.href = (skmtAdmin.wlEditorUrl || "") + "&profile_id=" + encodeURIComponent(saved.id);
          return;
        }
        toast("Menu enregistré.", "success");
        // Rechargement pour vider le cache et rafraîchir la liste
        setTimeout(function () { window.location.reload(); }, 900);
      } else {
        toast("Erreur lors de la sauvegarde.", "error");
      }
    });
  }

  function onDelete() {
    window.skmtModal.open({
      title:        "Supprimer le menu",
      message:      'Supprimer « ' + esc(ed.profile.name || "ce menu") + ' » ? Cette action est irréversible.',
      confirmLabel: "Supprimer",
      cancelLabel:  "Annuler",
      danger:       true,
      onConfirm:    function () {
        ajaxPost("skmt_wl_delete_profile", { profile_id: ed.profile.id }, function (data) {
          if (data && data.success) { ed.dirty = false; window.location.href = skmtAdmin.wlListUrl || "#"; }
          else toast("Erreur lors de la suppression.", "error");
        });
      },
    });
  }

  function onDuplicate() {
    ajaxPost("skmt_wl_duplicate_profile", { profile_id: ed.profile.id }, function (data) {
      if (data && data.success) {
        toast("Menu dupliqué.", "success");
        window.location.href = (skmtAdmin.wlEditorUrl || "") + "&profile_id=" + encodeURIComponent(data.data.profile.id);
      } else {
        toast("Erreur lors de la duplication.", "error");
      }
    });
  }

  /* ================================================================
   * COLLECTE
   * ================================================================ */

  function collectProfile() {
    var nameEl    = document.getElementById("skmt-wl-profile-name");
    var statusAct = document.getElementById("skmt-wl-status-active");
    var applyAll  = document.getElementById("skmt-wl-apply-all");
    var incVals   = ms.include ? ms.include.getValue() : { roles: [], users: [] };
    var excVals   = ms.exclude ? ms.exclude.getValue() : { roles: [], users: [] };
    return {
      id:            ed.profile.id === "__new__" ? "" : (ed.profile.id || ""),
      name:          nameEl ? nameEl.value.trim() : ed.profile.name,
      status:        statusAct && statusAct.classList.contains("is-active") ? "active" : "draft",
      apply_to_all:  !!(applyAll && applyAll.checked),
      include_roles: incVals.roles,
      include_users: incVals.users,
      exclude_roles: excVals.roles,
      exclude_users: excVals.users,
      items:         ed.profile.items.map(serializeItem),
      updated_at:    0,
    };
  }

  function serializeItem(item) {
    return {
      type: item.type, slug: item.slug, label: item.label || null, icon: item.icon || null,
      visible: item.visible !== false, target_blank: !!item.target_blank, url: item.url || "",
      children: (item.children || []).map(function (c) {
        return { type: c.type, slug: c.slug, label: c.label || null, icon: c.icon || null,
                 visible: c.visible !== false, target_blank: !!c.target_blank, url: "", children: [] };
      }),
    };
  }

  /* ================================================================
   * MULTI-SELECT
   * ================================================================ */

  function buildInitialChips(roles, users) {
    var wpRoles = skmtAdmin.wpRoles || {};
    var recent  = skmtAdmin.wpRecentUsers || [];
    var sel = [];
    roles.forEach(function (k) {
      sel.push({ id: "role:" + k, rawId: k, label: wpRoles[k] || k, type: "role" });
    });
    users.forEach(function (uid) {
      var u = recent.find(function (r) { return r.id === uid; });
      sel.push({ id: "user:" + uid, rawId: uid, label: u ? u.label : "#" + uid, type: "user" });
    });
    return sel;
  }

  function createMultiSelect(containerId, initialSelected) {
    var container = document.getElementById(containerId);
    if (!container) return null;
    var widget = { selected: initialSelected || [], results: [], open: false, timer: null };

    widget.getValue = function () {
      return {
        roles: widget.selected.filter(function (s) { return s.type === "role"; }).map(function (s) { return s.rawId; }),
        users: widget.selected.filter(function (s) { return s.type === "user"; }).map(function (s) { return s.rawId; }),
      };
    };

    widget.render = function () {
      var chips = widget.selected.map(function (s) {
        return '<span class="skmt-wl-chip">' + esc(s.label) +
          '<button type="button" class="skmt-wl-chip__remove" data-id="' + esc(s.id) + '" aria-label="Retirer">' + (L.x || "×") + "</button></span>";
      }).join("");
      container.innerHTML =
        '<div class="skmt-wl-ms-tags">' + chips +
          '<input type="text" class="skmt-wl-ms-input" placeholder="Rechercher…">' +
        "</div>" +
        '<div class="skmt-wl-ms-dropdown" style="display:' + (widget.open ? "" : "none") + '">' +
          msDropdownHtml(widget) +
        "</div>";

      var input = container.querySelector(".skmt-wl-ms-input");
      if (input) {
        input.addEventListener("focus",  function () { widget.open = true;  widget.search(""); });
        input.addEventListener("blur",   function () { setTimeout(function () { widget.open = false; widget.render(); }, 200); });
        input.addEventListener("input",  function () {
          clearTimeout(widget.timer);
          widget.timer = setTimeout(function () { widget.search(input.value); }, 300);
        });
      }
      container.querySelectorAll(".skmt-wl-chip__remove").forEach(function (btn) {
        btn.addEventListener("mousedown", function (e) {
          e.preventDefault();
          widget.selected = widget.selected.filter(function (s) { return s.id !== btn.dataset.id; });
          ed.dirty = true; widget.render();
        });
      });
      container.querySelectorAll(".skmt-wl-ms-option").forEach(function (opt) {
        opt.addEventListener("mousedown", function (e) {
          e.preventDefault();
          if (!widget.selected.find(function (s) { return s.id === opt.dataset.id; })) {
            var rawId = opt.dataset.type === "user" ? parseInt(opt.dataset.raw, 10) : opt.dataset.raw;
            widget.selected.push({ id: opt.dataset.id, rawId: rawId, label: opt.dataset.label, type: opt.dataset.type });
            ed.dirty = true;
          }
          widget.open = false; widget.render();
        });
      });
    };

    widget.search = function (q) {
      var wpRoles = skmtAdmin.wpRoles || {};
      var res = [];
      for (var k in wpRoles) {
        if (!Object.prototype.hasOwnProperty.call(wpRoles, k)) continue;
        if (!q || wpRoles[k].toLowerCase().indexOf(q.toLowerCase()) !== -1) {
          res.push({ id: "role:" + k, rawId: k, label: wpRoles[k], type: "role" });
        }
      }
      (skmtAdmin.wpRecentUsers || []).filter(function (u) {
        return !q || u.label.toLowerCase().indexOf(q.toLowerCase()) !== -1;
      }).forEach(function (u) {
        res.push({ id: "user:" + u.id, rawId: u.id, label: u.label, type: "user" });
      });
      widget.results = res; widget.render();

      if (q.length >= 2) {
        var xhr = new XMLHttpRequest();
        xhr.open("GET", skmtAdmin.ajaxUrl + "?action=skmt_wl_search_users&nonce=" + encodeURIComponent(skmtAdmin.nonce) + "&q=" + encodeURIComponent(q));
        xhr.onload = function () {
          try {
            var d = JSON.parse(xhr.responseText);
            if (d.success && d.data) {
              d.data.forEach(function (u) {
                if (!res.find(function (r) { return r.id === "user:" + u.id; })) {
                  res.push({ id: "user:" + u.id, rawId: u.id, label: u.label, type: "user" });
                }
              });
              widget.results = res; widget.render();
            }
          } catch (e) { /* ignore */ }
        };
        xhr.send();
      }
    };

    widget.render();
    return widget;
  }

  function msDropdownHtml(widget) {
    var selIds  = widget.selected.map(function (s) { return s.id; });
    var options = widget.results.filter(function (r) { return selIds.indexOf(r.id) === -1; });
    if (!options.length) return '<div class="skmt-wl-ms-empty">Aucun résultat.</div>';
    return options.map(function (r) {
      var badge = r.type === "role"
        ? '<span class="skmt-badge skmt-badge--info">Rôle</span>'
        : '<span class="skmt-badge skmt-badge--inactive">Utilisateur</span>';
      return '<div class="skmt-wl-ms-option" data-id="' + esc(r.id) + '" data-label="' + esc(r.label) + '" data-type="' + esc(r.type) + '" data-raw="' + esc(String(r.rawId)) + '">' +
        esc(r.label) + " " + badge + "</div>";
    }).join("");
  }

  /* ================================================================
   * UTILITAIRES
   * ================================================================ */

  function ajaxPost(action, data, cb) {
    var body = "action=" + encodeURIComponent(action) + "&nonce=" + encodeURIComponent(skmtAdmin.nonce);
    for (var k in data) {
      if (Object.prototype.hasOwnProperty.call(data, k)) {
        body += "&" + encodeURIComponent(k) + "=" + encodeURIComponent(data[k]);
      }
    }
    var xhr = new XMLHttpRequest();
    xhr.open("POST", skmtAdmin.ajaxUrl);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function () { try { cb(JSON.parse(xhr.responseText)); } catch (e) { cb(null); } };
    xhr.onerror = function () { cb(null); };
    xhr.send(body);
  }

  function toast(msg, type) {
    if (typeof window.skmtShowToast === "function") window.skmtShowToast(msg, type || "success");
  }

  function findByUid(uid) {
    for (var i = 0; i < ed.profile.items.length; i++) {
      if (ed.profile.items[i]._uid === uid) return ed.profile.items[i];
      var ch = ed.profile.items[i].children || [];
      for (var j = 0; j < ch.length; j++) {
        if (ch[j]._uid === uid) return ch[j];
      }
    }
    return null;
  }

  function findWpItem(slug) {
    return (skmtAdmin.wpMenu || []).find(function (m) { return m.slug === slug; }) || null;
  }

  function findWpSub(parentSlug, slug) {
    return ((skmtAdmin.wpSubmenu || {})[parentSlug] || []).find(function (s) { return s.slug === slug; }) || null;
  }

  function ensureUids(items) {
    (items || []).forEach(function (i) {
      if (!i._uid) i._uid = genUid();
      ensureUids(i.children || []);
    });
  }

  var _uid = 0;
  function genUid() { return "u" + (++_uid); }

  function deepCopy(o) { return JSON.parse(JSON.stringify(o)); }

  function stripTags(s) { return String(s || "").replace(/<[^>]*>/g, "").trim(); }

  function esc(s) {
    return String(s || "")
      .replace(/&/g, "&amp;").replace(/</g, "&lt;")
      .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  function show(id) { var el = document.getElementById(id); if (el) el.style.display = ""; }
  function hide(id) { var el = document.getElementById(id); if (el) el.style.display = "none"; }
})();
