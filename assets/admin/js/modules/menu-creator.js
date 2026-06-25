/**
 * Studio Kyne Mini Tools — Module Créateur de menu
 * Éditeur intégré 3 colonnes.
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

  var expandedUids = new Set();

  // Dropdown flottant pour le picker d'icône (singleton, body)
  var iconPickerEl   = null;
  var iconPickerItem = null;

  var ms = { include: null, exclude: null };
  var sidebarState = { filter: "all", search: "" };

  /* ================================================================
   * INIT
   * ================================================================ */

  document.addEventListener("DOMContentLoaded", function () {
    if (!document.getElementById("skmt-mc-editor")) return;

    // Masquer le bouton Enregistrer du header module (remplacé par le footer du panel)
    var headerSaveBtn = document.getElementById("skmt-module-save-btn");
    if (headerSaveBtn) {
      headerSaveBtn.style.display = "none";
    }

    // Nouveau menu
    var newBtn = document.getElementById("skmt-mc-new-btn");
    if (newBtn) {
      newBtn.addEventListener("click", function () { confirmDirty(startNewProfile); });
    }

    // Bouton retour dans le panel droit
    var backBtn = document.getElementById("skmt-mc-back-btn");
    if (backBtn) {
      backBtn.addEventListener("click", function () { showProfilePanel(); });
    }

    // Boutons du pied de page
    bindPanelFooter();

    // Boutons +séparateur / +lien
    bindTreeActions();

    // Dropdown flottant icon picker (singleton, appendé au body)
    createFloatingIconPicker();

    // Fermer le dropdown si clic en dehors — capture phase pour résister aux WP stopPropagation
    document.addEventListener("mousedown", function (e) {
      if (iconPickerEl &&
          !iconPickerEl.classList.contains("is-hidden") &&
          !iconPickerEl.contains(e.target) &&
          !e.target.closest(".skmt-wl-icon-btn")) {
        iconPickerEl.classList.add("is-hidden");
      }
    }, true);

    renderProfilesSidebar();
    bindProfilesSidebar();
    showPlaceholder();

    window.addEventListener("beforeunload", function (e) {
      if (ed.dirty) { e.preventDefault(); e.returnValue = ""; }
    });
  });

  /* ================================================================
   * DIRTY STATE
   * ================================================================ */

  function setDirty(val) {
    ed.dirty = val;
    var btn = document.getElementById("skmt-mc-save-panel-btn");
    if (btn) btn.disabled = !val;
  }

  /* ================================================================
   * CONFIRMATION UNSAVED CHANGES
   * ================================================================ */

  function confirmDirty(callback) {
    if (!ed.dirty) { callback(); return; }
    window.skmtModal.open({
      title:        "Modifications non sauvegardées",
      message:      "Vos modifications seront perdues. Continuer ?",
      confirmLabel: "Continuer sans enregistrer",
      cancelLabel:  "Annuler",
      danger:       true,
      onConfirm:    function () { setDirty(false); callback(); },
    });
  }

  /* ================================================================
   * PROFIL VIDE
   * ================================================================ */

  function blankProfile() {
    var profiles = skmtAdmin.mcProfiles || [];
    var names    = profiles.map(function (p) { return p.name || ""; });
    var n = 1;
    while (names.indexOf("Menu " + n) !== -1) { n++; }
    return {
      id: "__new__", name: "Menu " + n, status: "draft", apply_to_all: false,
      include_roles: [], include_users: [], exclude_roles: [], exclude_users: [],
      items: [], updated_at: 0,
    };
  }

  /* ================================================================
   * CHARGEMENT D'UN PROFIL
   * ================================================================ */

  function loadProfile(profile) {
    ed.profile     = deepCopy(profile);
    ed.selectedUid = null;
    expandedUids.clear();
    setDirty(false);

    ensureUids(ed.profile.items);
    mergeWpMenu();
    hidePlaceholder();
    renderEditor();
    renderProfilesSidebar();
  }

  function startNewProfile() {
    ed.profile     = blankProfile();
    ed.selectedUid = null;
    expandedUids.clear();
    setDirty(false);

    ensureUids(ed.profile.items);
    mergeWpMenu();
    hidePlaceholder();
    renderEditor();
    renderProfilesSidebar();
  }

  /* ================================================================
   * PLACEHOLDER
   * ================================================================ */

  function showPlaceholder() {
    setDisplay("skmt-mc-placeholder",  "");
    setDisplay("skmt-mc-tree-actions", "none");
    setDisplay("skmt-wl-tree",         "none");
    setDisplay("skmt-wl-settings-col", "none");
    setDirty(false);
  }

  function hidePlaceholder() {
    setDisplay("skmt-mc-placeholder",  "none");
    setDisplay("skmt-mc-tree-actions", "");
    setDisplay("skmt-wl-tree",         "");
    setDisplay("skmt-wl-settings-col", "");
  }

  /* ================================================================
   * SIDEBAR — liste des profils
   * ================================================================ */

  function renderProfilesSidebar() {
    var container = document.getElementById("skmt-wl-ep-profiles-list");
    if (!container) return;

    var profiles  = skmtAdmin.mcProfiles || [];
    var currentId = ed.profile ? ed.profile.id : null;

    var filtered = profiles.filter(function (p) {
      var matchF = sidebarState.filter === "all" || p.status === sidebarState.filter;
      var matchS = !sidebarState.search ||
        (p.name || "").toLowerCase().indexOf(sidebarState.search.toLowerCase()) !== -1;
      return matchF && matchS;
    });

    if (!filtered.length) {
      container.innerHTML = '<p class="skmt-wl-ep-profiles-empty">' +
        esc(profiles.length === 0
          ? "Aucun menu. Cliquez sur « + » pour créer."
          : "Aucun résultat.") + "</p>";
      return;
    }

    container.innerHTML = filtered.map(function (p) {
      var isActive  = p.status === "active";
      var dotClass  = isActive ? "skmt-mc-dot--active" : "skmt-mc-dot--draft";
      var isCurrent = p.id === currentId;
      return (
        '<div class="skmt-wl-ep-profile-item' + (isCurrent ? " is-active" : "") +
            '" data-id="' + esc(p.id) + '">' +
          '<span class="skmt-mc-dot ' + dotClass + '" title="' +
            (isActive ? "Actif" : "Brouillon") + '"></span>' +
          '<span class="skmt-wl-ep-profile-item__name">' +
            esc(p.name || "Menu sans nom") + "</span>" +
          '<span class="skmt-mc-item-actions">' +
            '<button type="button" class="skmt-mc-item-action" data-action="duplicate" ' +
              'data-id="' + esc(p.id) + '" title="Dupliquer">' +
              (L.copy || "") + "</button>" +
            '<button type="button" class="skmt-mc-item-action skmt-mc-item-action--danger" ' +
              'data-action="delete" data-id="' + esc(p.id) + '" title="Supprimer">' +
              (L.trash || "×") + "</button>" +
          "</span>" +
        "</div>"
      );
    }).join("");

    container.querySelectorAll(".skmt-wl-ep-profile-item").forEach(function (item) {
      item.addEventListener("click", function (e) {
        if (e.target.closest(".skmt-mc-item-action")) return;
        var profile = findProfileById(item.dataset.id);
        if (!profile) return;
        confirmDirty(function () { loadProfile(profile); });
      });
    });
    container.querySelectorAll("[data-action='duplicate']").forEach(function (btn) {
      btn.addEventListener("click", function (e) { e.stopPropagation(); onSidebarDuplicate(btn.dataset.id); });
    });
    container.querySelectorAll("[data-action='delete']").forEach(function (btn) {
      btn.addEventListener("click", function (e) { e.stopPropagation(); onSidebarDelete(btn.dataset.id); });
    });
  }

  function bindProfilesSidebar() {
    var searchEl = document.getElementById("skmt-wl-ep-search");
    if (searchEl) {
      searchEl.addEventListener("input", function () {
        sidebarState.search = searchEl.value;
        renderProfilesSidebar();
      });
    }
    document.querySelectorAll(".skmt-wl-ep__profiles-tab").forEach(function (tab) {
      tab.addEventListener("click", function () {
        document.querySelectorAll(".skmt-wl-ep__profiles-tab").forEach(function (t) {
          t.classList.remove("is-active");
        });
        tab.classList.add("is-active");
        sidebarState.filter = tab.dataset.filter || "all";
        renderProfilesSidebar();
      });
    });
  }

  /* ================================================================
   * ACTIONS SIDEBAR
   * ================================================================ */

  function onSidebarDelete(profileId) {
    var p    = findProfileById(profileId);
    var name = p ? (p.name || "ce menu") : "ce menu";
    window.skmtModal.open({
      title: "Supprimer le menu",
      message: 'Supprimer « ' + name + ' » ? Cette action est irréversible.',
      confirmLabel: "Supprimer", cancelLabel: "Annuler", danger: true,
      onConfirm: function () {
        ajaxPost("skmt_wl_delete_profile", { profile_id: profileId }, function (data) {
          if (data && data.success) {
            skmtAdmin.mcProfiles = (skmtAdmin.mcProfiles || []).filter(function (x) { return x.id !== profileId; });
            if (ed.profile && ed.profile.id === profileId) { ed.profile = null; showPlaceholder(); }
            renderProfilesSidebar();
            toast("Menu supprimé.", "success");
          } else { toast("Erreur lors de la suppression.", "error"); }
        });
      },
    });
  }

  function onSidebarDuplicate(profileId) {
    ajaxPost("skmt_wl_duplicate_profile", { profile_id: profileId }, function (data) {
      if (data && data.success) {
        skmtAdmin.mcProfiles = skmtAdmin.mcProfiles || [];
        skmtAdmin.mcProfiles.push(data.data.profile);
        renderProfilesSidebar();
        toast("Menu dupliqué.", "success");
      } else { toast("Erreur lors de la duplication.", "error"); }
    });
  }

  /* ================================================================
   * PIED DE PAGE — Enregistrer / Réinitialiser
   * ================================================================ */

  function bindPanelFooter() {
    var saveBtn  = document.getElementById("skmt-mc-save-panel-btn");
    var resetBtn = document.getElementById("skmt-mc-reset-menu-btn");

    if (saveBtn) {
      saveBtn.addEventListener("click", function () {
        if (ed.profile && ed.dirty) onSave();
      });
    }

    if (resetBtn) {
      resetBtn.addEventListener("click", function () {
        if (!ed.profile) return;
        window.skmtModal.open({
          title:        "Réinitialiser le menu",
          message:      "Toutes les modifications non sauvegardées seront perdues et le menu sera rechargé depuis la dernière sauvegarde.",
          confirmLabel: "Réinitialiser",
          cancelLabel:  "Annuler",
          danger:       true,
          onConfirm: function () {
            if (ed.profile.id === "__new__") {
              startNewProfile();
            } else {
              var saved = findProfileById(ed.profile.id);
              if (saved) { loadProfile(saved); }
              else        { startNewProfile(); }
            }
          },
        });
      });
    }
  }

  /* ================================================================
   * FUSION AVEC LE MENU WP
   * ================================================================ */

  function mergeWpMenu() {
    var wpMenu = skmtAdmin.wpMenu || [];
    if (!ed.profile.items.length && wpMenu.length) {
      ed.profile.items = wpMenu.map(function (m) { return wpItemToEditorItem(m); }).filter(Boolean);
    } else {
      var seen = {};
      ed.profile.items.forEach(function (item) {
        if (!item._uid) item._uid = genUid();
        if (item.type === "separator") { seen[item.slug] = true; return; }
        var wp = findWpItem(item.slug);
        item._wpLabel = wp ? stripTags(wp.label) : (item._wpLabel || item.slug);
        item._wpIcon  = wp ? (wp.icon || "") : (item._wpIcon || "");
        seen[item.slug] = true;
        (item.children || []).forEach(function (c) {
          if (!c._uid) c._uid = genUid();
          var sub = findWpSub(item.slug, c.slug);
          c._wpLabel = sub ? stripTags(sub.label) : (c._wpLabel || c.slug);
          c._wpIcon  = "";
        });
      });
      wpMenu.forEach(function (m) {
        if (m.slug && !seen[m.slug]) {
          var item = wpItemToEditorItem(m);
          if (item) ed.profile.items.push(item);
        }
      });
    }
  }

  function wpItemToEditorItem(m) {
    if (!m.slug) return null;
    // Séparateurs WP : slug commençant par "separator" (label toujours vide)
    if (/^separator/.test(m.slug)) {
      return {
        type: "separator", slug: m.slug, _uid: genUid(),
        visible: true, label: null, icon: null,
        url: "", target_blank: false, children: [],
      };
    }
    return {
      type: "wp_item", slug: m.slug, label: null, icon: null,
      visible: true, target_blank: false, url: "",
      children: buildWpChildren(m.slug),
      _uid: genUid(),
      _wpLabel: stripTags(m.label || m.slug),
      _wpIcon:  m.icon || "",
    };
  }

  function buildWpChildren(parentSlug) {
    return ((skmtAdmin.wpSubmenu || {})[parentSlug] || []).map(function (s) {
      if (!s.slug) return null;
      return {
        type: "wp_item", slug: s.slug, label: null, icon: null,
        visible: true, target_blank: false, url: "", children: [],
        _uid: genUid(),
        _wpLabel: stripTags(s.label || s.slug),
        _wpIcon: "",
      };
    }).filter(Boolean);
  }

  /* ================================================================
   * RENDU GLOBAL
   * ================================================================ */

  function renderEditor() {
    populateProfilePanel();
    showProfilePanel();
    renderTree();
    ms.include = createMultiSelect("skmt-wl-include-select", buildInitialChips(
      ed.profile.include_roles || [], ed.profile.include_users || []
    ));
    ms.exclude = createMultiSelect("skmt-wl-exclude-select", buildInitialChips(
      ed.profile.exclude_roles || [], ed.profile.exclude_users || []
    ));
    syncApplyAll();
    updateStatusBadge();
  }

  /* ================================================================
   * NAVIGATION DU PANEL DROIT (sans tabs)
   * ================================================================ */

  function showProfilePanel() {
    ed.selectedUid = null;
    setDisplay("skmt-mc-back-btn", "none");
    var titleEl = document.getElementById("skmt-mc-panel-title");
    if (titleEl) titleEl.textContent = "Paramètres du menu";
    show("skmt-wl-profile-settings");
    hide("skmt-wl-item-settings");
    renderTree();
  }

  function showItemPanel(item) {
    ed.selectedUid = item._uid;
    setDisplay("skmt-mc-back-btn", "");
    var titleEl = document.getElementById("skmt-mc-panel-title");
    if (titleEl) {
      titleEl.textContent = item.label || item._wpLabel || item.slug || "Élément";
    }
    hide("skmt-wl-profile-settings");
    show("skmt-wl-item-settings");
    var fields = document.getElementById("skmt-wl-item-fields");
    if (fields) { fields.innerHTML = buildItemFields(item); bindItemFields(item); }
    renderTree();
  }

  /* ================================================================
   * ARBRE
   * ================================================================ */

  function renderTree() {
    var tree = document.getElementById("skmt-wl-tree");
    if (!tree) return;
    var items = ed.profile.items;
    tree.innerHTML = items.map(function (item, i) {
      return buildItemHtml(item, i, items.length, "");
    }).join("");
    bindTree(tree);
  }

  function buildItemHtml(item, idx, total, parentUid) {
    var uid = item._uid;

    /* --- SÉPARATEUR : juste une ligne, aucun texte --- */
    if (item.type === "separator") {
      return (
        '<div class="skmt-wl-tree-item skmt-wl-tree-item--sep" data-uid="' + esc(uid) + '">' +
          '<div class="skmt-wl-tree-item__row">' +
            '<span class="skmt-wl-tree-item__handle">' + (L.grip || "") + "</span>" +
            '<span class="skmt-wl-tree-item__toggle-ph"></span>' +
            '<span class="skmt-wl-tree-sep-line"></span>' +
            '<div class="skmt-wl-tree-item__btns">' +
              mvBtn(uid, parentUid, idx, total) +
              '<button type="button" class="skmt-wl-tree-item__del" ' +
                'data-uid="' + esc(uid) + '" data-parent="' + esc(parentUid) + '">' +
                (L.trash || "×") + "</button>" +
            "</div>" +
          "</div>" +
        "</div>"
      );
    }

    /* --- ITEM NORMAL --- */
    var label    = item.label || item._wpLabel || item.slug;
    var hidden   = item.visible === false;
    var selected = ed.selectedUid === uid;
    var hasChild = item.children && item.children.length > 0;
    var expanded = expandedUids.has(uid);

    var toggleHtml = hasChild
      ? '<button type="button" class="skmt-wl-tree-item__toggle" data-uid="' + esc(uid) + '">' +
          (expanded ? (L.chevronD || "▾") : (L.chevronR || "▸")) + "</button>"
      : '<span class="skmt-wl-tree-item__toggle-ph"></span>';

    var childrenHtml = "";
    if (hasChild) {
      var childList = item.children;
      childrenHtml = '<div class="skmt-wl-tree-item__children"' +
        (expanded ? "" : ' style="display:none"') + ">" +
        childList.map(function (c, ci) {
          return buildChildHtml(c, ci, childList.length, uid);
        }).join("") + "</div>";
    }

    return (
      '<div class="skmt-wl-tree-item' + (selected ? " is-selected" : "") +
          '" data-uid="' + esc(uid) + '">' +
        '<div class="skmt-wl-tree-item__row">' +
          '<span class="skmt-wl-tree-item__handle">' + (L.grip || "") + "</span>" +
          toggleHtml +
          buildIconEl(item) +
          '<span class="skmt-wl-tree-item__label' + (hidden ? " is-hidden" : "") + '">' +
            esc(label) + "</span>" +
          '<div class="skmt-wl-tree-item__btns">' +
            '<button type="button" class="skmt-wl-tree-item__vis" data-uid="' + esc(uid) + '" ' +
              'title="' + (hidden ? "Afficher" : "Masquer") + '">' +
              (hidden ? (L.eyeOff || "") : (L.eye || "")) + "</button>" +
            mvBtn(uid, parentUid, idx, total) +
            (item.type === "custom_link"
              ? '<button type="button" class="skmt-wl-tree-item__del" ' +
                  'data-uid="' + esc(uid) + '" data-parent="' + esc(parentUid) + '">' +
                  (L.trash || "×") + "</button>"
              : "") +
          "</div>" +
        "</div>" +
        childrenHtml +
      "</div>"
    );
  }

  function buildChildHtml(c, ci, total, parentUid) {
    var uid    = c._uid;
    var label  = c.label || c._wpLabel || c.slug;
    var hidden = c.visible === false;
    var selected = ed.selectedUid === uid;
    return (
      '<div class="skmt-wl-tree-item skmt-wl-tree-item--child' +
          (selected ? " is-selected" : "") + '" data-uid="' + esc(uid) + '">' +
        '<div class="skmt-wl-tree-item__row">' +
          '<span class="skmt-wl-tree-item__handle">' + (L.grip || "") + "</span>" +
          '<span class="skmt-wl-tree-item__toggle-ph"></span>' +
          '<span class="skmt-wl-tree-item__icon-ph"></span>' +
          '<span class="skmt-wl-tree-item__label' + (hidden ? " is-hidden" : "") + '">' +
            esc(label) + "</span>" +
          '<div class="skmt-wl-tree-item__btns">' +
            '<button type="button" class="skmt-wl-tree-item__vis" data-uid="' + esc(uid) + '" ' +
              'title="' + (hidden ? "Afficher" : "Masquer") + '">' +
              (hidden ? (L.eyeOff || "") : (L.eye || "")) + "</button>" +
            mvBtn(uid, parentUid, ci, total) +
          "</div>" +
        "</div>" +
      "</div>"
    );
  }

  function mvBtn(uid, parentUid, idx, total) {
    var up = idx === 0 ? ' style="opacity:.3;pointer-events:none"' : "";
    var dn = idx === total - 1 ? ' style="opacity:.3;pointer-events:none"' : "";
    return (
      '<button type="button" class="skmt-wl-tree-item__mv" data-mv="up" ' +
        'data-uid="' + esc(uid) + '" data-parent="' + esc(parentUid) + '" title="Monter"' + up + '>' +
        (L.chevronU || "↑") + "</button>" +
      '<button type="button" class="skmt-wl-tree-item__mv" data-mv="down" ' +
        'data-uid="' + esc(uid) + '" data-parent="' + esc(parentUid) + '" title="Descendre"' + dn + '>' +
        (L.chevronD || "↓") + "</button>"
    );
  }

  function buildIconEl(item) {
    var icon = item.icon || item._wpIcon || "";
    if (!icon) return '<span class="skmt-wl-tree-item__icon-ph"></span>';
    if (icon.indexOf("dashicons-") === 0) {
      return '<span class="skmt-wl-tree-item__icon dashicons ' + esc(icon) + '" aria-hidden="true"></span>';
    }
    var src = icon.indexOf("svg:") === 0 ? "data:image/svg+xml;base64," + icon.slice(4) : icon;
    return '<img class="skmt-wl-tree-item__icon" src="' + esc(src) + '" aria-hidden="true" alt="">';
  }

  /* ================================================================
   * BIND ARBRE
   * ================================================================ */

  function bindTree(tree) {
    /* --- DRAG & DROP : drag uniquement depuis le handle --- */
    tree.querySelectorAll(
      ".skmt-wl-tree-item:not(.skmt-wl-tree-item--child)"
    ).forEach(function (el) {
      // Activer draggable SEULEMENT si mousedown sur le handle
      el.addEventListener("mousedown", function (e) {
        var onHandle = !!e.target.closest(".skmt-wl-tree-item__handle");
        el.draggable = onHandle;
      });
      el.addEventListener("mouseup", function () {
        el.draggable = false;
      });
      el.addEventListener("dragstart", function (e) {
        if (!el.draggable) { e.preventDefault(); return; }
        ed.dragSrcUid = el.dataset.uid;
        el.classList.add("is-dragging");
        e.dataTransfer.effectAllowed = "move";
        e.dataTransfer.setData("text/plain", el.dataset.uid);
      });
      el.addEventListener("dragend", function () {
        el.draggable = false;
        el.classList.remove("is-dragging");
        tree.querySelectorAll(".is-drag-over").forEach(function (t) { t.classList.remove("is-drag-over"); });
        ed.dragSrcUid = null;
      });
      el.addEventListener("dragover", function (e) {
        e.preventDefault();
        if (!ed.dragSrcUid || el.dataset.uid === ed.dragSrcUid) return;
        // N'appliquer que sur la row directe (pas les enfants)
        var childrenEl = el.querySelector(".skmt-wl-tree-item__children");
        if (childrenEl && childrenEl.contains(e.target)) return;
        tree.querySelectorAll(".is-drag-over").forEach(function (t) { t.classList.remove("is-drag-over"); });
        el.classList.add("is-drag-over");
      });
      el.addEventListener("dragleave", function (e) {
        if (!el.contains(e.relatedTarget)) el.classList.remove("is-drag-over");
      });
      el.addEventListener("drop", function (e) {
        e.preventDefault();
        e.stopPropagation();
        el.classList.remove("is-drag-over");
        var srcUid = ed.dragSrcUid, tgtUid = el.dataset.uid;
        if (srcUid && tgtUid && srcUid !== tgtUid) moveItem(srcUid, tgtUid);
        ed.dragSrcUid = null;
        el.draggable  = false;
      });
    });

    /* --- CLIC SUR ROW → sélection --- */
    tree.querySelectorAll(
      ".skmt-wl-tree-item:not(.skmt-wl-tree-item--sep) .skmt-wl-tree-item__row"
    ).forEach(function (row) {
      row.addEventListener("click", function (e) {
        if (e.target.closest(".skmt-wl-tree-item__btns") ||
            e.target.closest(".skmt-wl-tree-item__toggle") ||
            e.target.closest(".skmt-wl-tree-item__handle")) return;
        var uid = row.parentElement.dataset.uid;
        if (!uid) return;
        var item = findByUid(uid);
        if (!item || item.type === "separator") return;
        showItemPanel(item);
      });
    });

    /* --- TOGGLE COLLAPSE --- */
    tree.querySelectorAll(".skmt-wl-tree-item__toggle").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.stopPropagation();
        var uid = btn.dataset.uid;
        if (expandedUids.has(uid)) expandedUids.delete(uid);
        else expandedUids.add(uid);
        renderTree();
      });
    });

    /* --- VISIBILITÉ --- */
    tree.querySelectorAll(".skmt-wl-tree-item__vis").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.stopPropagation();
        var item = findByUid(btn.dataset.uid);
        if (!item) return;
        item.visible = item.visible === false;
        setDirty(true);
        renderTree();
      });
    });

    /* --- MONTER / DESCENDRE --- */
    tree.querySelectorAll(".skmt-wl-tree-item__mv").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.stopPropagation();
        var uid       = btn.dataset.uid;
        var parentUid = btn.dataset.parent || "";
        var dir       = btn.dataset.mv;
        var list      = parentUid ? ((findByUid(parentUid) || {}).children || null) : ed.profile.items;
        if (!list) return;
        var idx    = list.findIndex(function (i) { return i._uid === uid; });
        var newIdx = dir === "up" ? idx - 1 : idx + 1;
        if (idx === -1 || newIdx < 0 || newIdx >= list.length) return;
        var tmp       = list[idx];
        list[idx]     = list[newIdx];
        list[newIdx]  = tmp;
        setDirty(true);
        renderTree();
      });
    });

    /* --- SUPPRIMER (séparateurs et liens custom) --- */
    tree.querySelectorAll(".skmt-wl-tree-item__del").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.stopPropagation();
        var uid       = btn.dataset.uid;
        var parentUid = btn.dataset.parent || "";
        var list      = parentUid ? ((findByUid(parentUid) || {}).children || null) : ed.profile.items;
        if (!list) return;
        var idx = list.findIndex(function (i) { return i._uid === uid; });
        if (idx === -1) return;
        list.splice(idx, 1);
        setDirty(true);
        if (ed.selectedUid === uid) showProfilePanel();
        renderTree();
      });
    });
  }

  /* ================================================================
   * DÉPLACEMENT D&D — index correct dans toutes les directions
   * ================================================================ */

  function moveItem(srcUid, tgtUid) {
    var items  = ed.profile.items;
    var srcIdx = items.findIndex(function (i) { return i._uid === srcUid; });
    var tgtIdx = items.findIndex(function (i) { return i._uid === tgtUid; });
    if (srcIdx === -1 || tgtIdx === -1 || srcIdx === tgtIdx) return;
    var srcItem = items[srcIdx];
    var without = items.filter(function (_, i) { return i !== srcIdx; });
    var newTgt  = without.findIndex(function (i) { return i._uid === tgtUid; });
    without.splice(newTgt, 0, srcItem);
    ed.profile.items = without;
    setDirty(true);
    renderTree();
  }

  /* ================================================================
   * ACTIONS ARBRE (+séparateur / +lien) — ajout EN HAUT
   * ================================================================ */

  function bindTreeActions() {
    var sepBtn  = document.getElementById("skmt-wl-add-sep");
    var linkBtn = document.getElementById("skmt-wl-add-link");
    if (sepBtn) {
      sepBtn.addEventListener("click", function () {
        if (!ed.profile) return;
        ed.profile.items.unshift({
          type: "separator", slug: "sep-" + genUid(), _uid: genUid(),
          visible: true, label: null, icon: null, url: "", target_blank: false, children: [],
        });
        setDirty(true);
        renderTree();
      });
    }
    if (linkBtn) {
      linkBtn.addEventListener("click", function () {
        if (!ed.profile) return;
        ed.profile.items.unshift({
          type: "custom_link", slug: "lien-" + genUid(), label: "Nouveau lien",
          _uid: genUid(), _wpLabel: "Nouveau lien", _wpIcon: "",
          visible: true, target_blank: false, url: "", icon: null, children: [],
        });
        setDirty(true);
        renderTree();
      });
    }
  }

  /* ================================================================
   * PANEL PROFIL
   * ================================================================ */

  function populateProfilePanel() {
    var nameEl = document.getElementById("skmt-wl-profile-name");
    if (nameEl) {
      nameEl.value = ed.profile.name || "";
      nameEl.oninput = function () { ed.profile.name = nameEl.value; setDirty(true); };
    }

    var applyAll = document.getElementById("skmt-wl-apply-all");
    if (applyAll) {
      applyAll.checked = !!ed.profile.apply_to_all;
      applyAll.onchange = function () {
        ed.profile.apply_to_all = applyAll.checked;
        setDirty(true);
        syncApplyAll();
      };
    }

    setSegment("skmt-wl-status-", ed.profile.status || "draft");

    ["skmt-wl-status-draft", "skmt-wl-status-active"].forEach(function (id) {
      var btn = document.getElementById(id);
      if (!btn) return;
      var fresh = btn.cloneNode(true);
      btn.parentNode.replaceChild(fresh, btn);
      fresh.addEventListener("click", function () {
        setSegment("skmt-wl-status-", fresh.dataset.value);
        setDirty(true);
        updateStatusBadge();
      });
    });
  }

  function syncApplyAll() {
    var applyAll = document.getElementById("skmt-wl-apply-all");
    var checked  = !!(applyAll && applyAll.checked);
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

  function updateStatusBadge() {
    var badge = document.getElementById("skmt-mc-status-badge");
    if (!badge) return;
    var activeBtn = document.getElementById("skmt-wl-status-active");
    var isActive  = activeBtn && activeBtn.classList.contains("is-active");
    badge.className  = "skmt-mc-status-badge " + (isActive ? "is-active" : "is-draft");
    badge.textContent = isActive ? "Actif" : "Brouillon";
  }

  /* ================================================================
   * CHAMPS D'ITEM
   * ================================================================ */

  function buildItemFields(item) {
    if (item.type === "separator") {
      return '<p class="skmt-wl-note" style="padding:16px">Séparateur — aucun paramètre.</p>';
    }
    var iconBtnHtml = buildIconBtnHtml(item);
    var html = "";
    if (item.type === "custom_link") {
      html += settingsRow("URL",
        '<input type="url" class="skmt-input" id="skmt-wl-item-url" value="' + esc(item.url || "") + '">', "");
    }
    html += settingsRow("Label",
      '<input type="text" class="skmt-input" id="skmt-wl-item-label" value="' + esc(item.label || "") + '" ' +
        'placeholder="' + esc(item._wpLabel || item.slug) + '">',
      "Laisser vide pour conserver le label d'origine.");
    html += (
      '<div class="skmt-wl-settings-row skmt-wl-settings-row--inline">' +
        '<div class="skmt-wl-settings-row__label"><span>Icône</span></div>' +
        iconBtnHtml +
      "</div>"
    );
    html += settingsRow("Visible",
      '<label class="skmt-toggle">' +
        '<input type="checkbox" id="skmt-wl-item-visible"' + (item.visible !== false ? " checked" : "") + '>' +
        '<span class="skmt-toggle__slider"></span></label>', "", true);
    html += settingsRow("Ouvrir dans un nouvel onglet",
      '<label class="skmt-toggle">' +
        '<input type="checkbox" id="skmt-wl-item-target"' + (item.target_blank ? " checked" : "") + '>' +
        '<span class="skmt-toggle__slider"></span></label>', "", true);
    if (item.type === "wp_item") {
      html += (
        '<div class="skmt-wl-settings-row skmt-wl-item-reset-row">' +
          '<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--danger" id="skmt-wl-item-reset">' +
            'Réinitialiser l\'élément</button>' +
        "</div>"
      );
    }
    return html;
  }

  function settingsRow(label, control, help, inline) {
    var cls = "skmt-wl-settings-row" + (inline ? " skmt-wl-settings-row--inline" : "");
    return (
      '<div class="' + cls + '">' +
        '<div class="skmt-wl-settings-row__label">' +
          '<span>' + esc(label) + "</span>" +
          (help ? '<p class="skmt-form__help">' + esc(help) + "</p>" : "") +
        "</div>" + control +
      "</div>"
    );
  }

  function buildIconBtnHtml(item) {
    var icon  = item.icon;
    var thumb = buildIconThumbInner(icon);
    return (
      '<button type="button" class="skmt-wl-icon-btn" id="skmt-wl-icon-open">' +
        '<span class="skmt-wl-icon-btn-thumb">' + thumb + "</span>" +
        '<span>' + (icon ? "Modifier" : "Choisir une icône") + "</span>" +
      "</button>"
    );
  }

  function buildIconThumbInner(icon) {
    if (!icon) return "";
    if (icon.indexOf("dashicons-") === 0) return '<span class="dashicons ' + esc(icon) + '"></span>';
    var src = icon.indexOf("svg:") === 0 ? "data:image/svg+xml;base64," + icon.slice(4) : icon;
    return '<img src="' + esc(src) + '" alt="">';
  }

  function bindItemFields(item) {
    var urlEl = document.getElementById("skmt-wl-item-url");
    if (urlEl) urlEl.addEventListener("input", function () { item.url = urlEl.value; setDirty(true); });

    var lblEl = document.getElementById("skmt-wl-item-label");
    if (lblEl) lblEl.addEventListener("input", function () {
      item.label = lblEl.value || null;
      setDirty(true);
      var titleEl = document.getElementById("skmt-mc-panel-title");
      if (titleEl) titleEl.textContent = item.label || item._wpLabel || item.slug || "Élément";
      renderTree();
    });

    var visEl = document.getElementById("skmt-wl-item-visible");
    if (visEl) visEl.addEventListener("change", function () { item.visible = visEl.checked; setDirty(true); renderTree(); });

    var tgtEl = document.getElementById("skmt-wl-item-target");
    if (tgtEl) tgtEl.addEventListener("change", function () { item.target_blank = tgtEl.checked; setDirty(true); });

    var rstBtn = document.getElementById("skmt-wl-item-reset");
    if (rstBtn) rstBtn.addEventListener("click", function () {
      item.label = null; item.icon = null; item.visible = true; item.target_blank = false;
      setDirty(true);
      showItemPanel(item);
      renderTree();
    });

    var iconBtn = document.getElementById("skmt-wl-icon-open");
    if (iconBtn) iconBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      openIconPicker(iconBtn, item);
    });
  }

  /* ================================================================
   * FLOATING ICON PICKER
   * ================================================================ */

  function createFloatingIconPicker() {
    iconPickerEl = document.createElement("div");
    iconPickerEl.className = "skmt-wl-icon-float is-hidden";
    document.body.appendChild(iconPickerEl);
  }

  function openIconPicker(triggerBtn, item) {
    iconPickerItem = item;
    iconPickerEl.innerHTML = buildIconDropdownHtml(item);
    iconPickerEl.classList.remove("is-hidden");

    var rect = triggerBtn.getBoundingClientRect();
    var w = 272, maxH = 300;
    var top  = rect.bottom + 4;
    var left = rect.left;
    if (left + w > window.innerWidth - 8)  left = window.innerWidth - w - 8;
    if (top + maxH > window.innerHeight - 8) top = rect.top - maxH - 4;
    iconPickerEl.style.top  = top + "px";
    iconPickerEl.style.left = left + "px";

    bindIconDropdown(iconPickerEl, item, triggerBtn);
  }

  function buildIconDropdownHtml(item) {
    var icon   = item.icon;
    var imgSrc = "";
    if (icon && icon.indexOf("dashicons-") !== 0) {
      imgSrc = icon.indexOf("svg:") === 0 ? "data:image/svg+xml;base64," + icon.slice(4) : icon;
    }
    var lib  = (skmtAdmin && skmtAdmin.iconLibrary) || window.skmtWlIconLibrary || {};
    var keys = Object.keys(lib);
    var gridHtml = keys.length
      ? '<div class="skmt-wl-icon-grid">' +
          keys.map(function (k) {
            var b64 = btoa(unescape(encodeURIComponent(lib[k])));
            return (
              '<button type="button" class="skmt-wl-icon-grid-item" ' +
                'data-icon-val="svg:' + esc(k) + '" title="' + esc(k) + '">' +
                '<img src="data:image/svg+xml;base64,' + b64 + '" alt="' + esc(k) + '">' +
              "</button>"
            );
          }).join("") + "</div>"
      : '<p class="skmt-wl-icon-lib-empty">Bibliothèque vide.</p>';

    return (
      '<div class="skmt-wl-icon-picker-tabs">' +
        '<button type="button" class="skmt-wl-icon-tab is-active" data-tab="library">Bibliothèque</button>' +
        '<button type="button" class="skmt-wl-icon-tab" data-tab="media">Médiathèque</button>' +
      "</div>" +
      '<div class="skmt-wl-icon-pane" data-pane="library">' + gridHtml + "</div>" +
      '<div class="skmt-wl-icon-pane" data-pane="media" style="display:none"><div style="padding:10px">' +
        '<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-ip-media-btn" ' +
          'style="margin-bottom:8px">Ouvrir la médiathèque</button>' +
        '<img class="skmt-wl-icon-media-preview" id="skmt-ip-media-prev" src="' +
          esc(imgSrc) + '" alt=""' + (imgSrc ? "" : ' style="display:none"') + '>' +
      "</div></div>" +
      '<div class="skmt-wl-icon-picker-footer">' +
        '<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-ip-default-btn">' +
          'Icône par défaut</button>' +
      "</div>"
    );
  }

  function bindIconDropdown(el, item, triggerBtn) {
    el.querySelectorAll(".skmt-wl-icon-tab").forEach(function (tab) {
      tab.addEventListener("click", function () {
        el.querySelectorAll(".skmt-wl-icon-tab").forEach(function (t) { t.classList.remove("is-active"); });
        tab.classList.add("is-active");
        el.querySelectorAll(".skmt-wl-icon-pane").forEach(function (p) { p.style.display = "none"; });
        var pane = el.querySelector('[data-pane="' + tab.dataset.tab + '"]');
        if (pane) pane.style.display = "";
      });
    });
    el.querySelectorAll(".skmt-wl-icon-grid-item").forEach(function (btn) {
      btn.addEventListener("click", function () {
        item.icon = btn.dataset.iconVal;
        setDirty(true);
        refreshIconBtn(triggerBtn, item);
        renderTree();
        iconPickerEl.classList.add("is-hidden");
      });
    });
    var defBtn = el.querySelector("#skmt-ip-default-btn");
    if (defBtn) defBtn.addEventListener("click", function () {
      item.icon = null;
      setDirty(true);
      refreshIconBtn(triggerBtn, item);
      renderTree();
      iconPickerEl.classList.add("is-hidden");
    });
    var mediaBtn  = el.querySelector("#skmt-ip-media-btn");
    var mediaPrev = el.querySelector("#skmt-ip-media-prev");
    if (mediaBtn && typeof wp !== "undefined" && wp.media) {
      mediaBtn.addEventListener("click", function () {
        var frame = wp.media({ title: "Choisir une icône", multiple: false });
        frame.on("select", function () {
          var att = frame.state().get("selection").first().toJSON();
          item.icon = att.url;
          setDirty(true);
          if (mediaPrev) { mediaPrev.src = att.url; mediaPrev.style.display = ""; }
          refreshIconBtn(triggerBtn, item);
          renderTree();
          iconPickerEl.classList.add("is-hidden");
        });
        frame.open();
      });
    }
  }

  function refreshIconBtn(btn, item) {
    if (!btn) return;
    var thumb = btn.querySelector(".skmt-wl-icon-btn-thumb");
    var label = btn.querySelector("span:last-child");
    if (thumb) thumb.innerHTML = buildIconThumbInner(item.icon);
    if (label) label.textContent = item.icon ? "Modifier" : "Choisir une icône";
  }

  /* ================================================================
   * SAUVEGARDE
   * ================================================================ */

  function onSave() {
    if (!ed.profile) return;
    var saveBtn = document.getElementById("skmt-mc-save-panel-btn");
    if (saveBtn) saveBtn.disabled = true;

    ajaxPost("skmt_wl_save_profile", { profile: JSON.stringify(collectProfile()) }, function (data) {
      if (data && data.success) {
        var saved  = data.data.profile;
        var wasNew = ed.profile.id === "__new__";
        setDirty(false);

        ensureUids(saved.items || []);
        restoreRuntimeProps(saved.items || [], ed.profile.items || []);
        ed.profile = saved;

        if (wasNew) {
          skmtAdmin.mcProfiles = skmtAdmin.mcProfiles || [];
          skmtAdmin.mcProfiles.push(saved);
        } else {
          skmtAdmin.mcProfiles = (skmtAdmin.mcProfiles || []).map(function (p) {
            return p.id === saved.id ? saved : p;
          });
        }
        renderProfilesSidebar();
        toast("Menu enregistré.", "success");
      } else {
        if (saveBtn) saveBtn.disabled = false;
        toast("Erreur lors de la sauvegarde.", "error");
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
      name:          nameEl ? nameEl.value.trim() : (ed.profile.name || ""),
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
      type:         item.type,
      slug:         item.slug,
      label:        item.label || null,
      icon:         item.icon  || null,
      visible:      item.visible !== false,
      target_blank: !!item.target_blank,
      url:          item.url   || "",
      children: (item.children || []).map(function (c) {
        return {
          type: c.type, slug: c.slug, label: c.label || null, icon: c.icon || null,
          visible: c.visible !== false, target_blank: !!c.target_blank, url: "", children: [],
        };
      }),
    };
  }

  /* ================================================================
   * MULTI-SELECT
   * ================================================================ */

  function buildInitialChips(roles, users) {
    var wpRoles = skmtAdmin.wpRoles     || {};
    var recent  = skmtAdmin.wpRecentUsers || [];
    var sel = [];
    roles.forEach(function (k) { sel.push({ id: "role:" + k, rawId: k, label: wpRoles[k] || k, type: "role" }); });
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
          '<button type="button" class="skmt-wl-chip__remove" data-id="' + esc(s.id) + '">' +
            (L.x || "×") + "</button></span>";
      }).join("");
      container.innerHTML = (
        '<div class="skmt-wl-ms-tags">' + chips +
          '<input type="text" class="skmt-wl-ms-input" placeholder="Rechercher…">' +
        "</div>" +
        '<div class="skmt-wl-ms-dropdown" style="display:' + (widget.open ? "" : "none") + '">' +
          msDropdownHtml(widget) + "</div>"
      );
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
          setDirty(true);
          widget.render();
        });
      });
      container.querySelectorAll(".skmt-wl-ms-option").forEach(function (opt) {
        opt.addEventListener("mousedown", function (e) {
          e.preventDefault();
          if (!widget.selected.find(function (s) { return s.id === opt.dataset.id; })) {
            var rawId = opt.dataset.type === "user" ? parseInt(opt.dataset.raw, 10) : opt.dataset.raw;
            widget.selected.push({ id: opt.dataset.id, rawId: rawId, label: opt.dataset.label, type: opt.dataset.type });
            setDirty(true);
          }
          widget.open = false;
          widget.render();
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
      widget.results = res;
      widget.render();
      if (q.length >= 2) {
        var xhr = new XMLHttpRequest();
        xhr.open("GET", skmtAdmin.ajaxUrl + "?action=skmt_wl_search_users&nonce=" +
          encodeURIComponent(skmtAdmin.nonce) + "&q=" + encodeURIComponent(q));
        xhr.onload = function () {
          try {
            var d = JSON.parse(xhr.responseText);
            if (d.success && d.data) {
              d.data.forEach(function (u) {
                if (!res.find(function (r) { return r.id === "user:" + u.id; })) {
                  res.push({ id: "user:" + u.id, rawId: u.id, label: u.label, type: "user" });
                }
              });
              widget.results = res;
              widget.render();
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
      return '<div class="skmt-wl-ms-option" data-id="' + esc(r.id) + '" data-label="' + esc(r.label) +
        '" data-type="' + esc(r.type) + '" data-raw="' + esc(String(r.rawId)) + '">' +
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
    xhr.onload  = function () { try { cb(JSON.parse(xhr.responseText)); } catch (e) { cb(null); } };
    xhr.onerror = function () { cb(null); };
    xhr.send(body);
  }

  function toast(msg, type) {
    if (typeof window.skmtShowToast === "function") window.skmtShowToast(msg, type || "success");
  }

  function findProfileById(id) {
    return (skmtAdmin.mcProfiles || []).find(function (p) { return p.id === id; }) || null;
  }

  function findByUid(uid) {
    if (!ed.profile) return null;
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
    return ((skmtAdmin.wpSubmenu || {})[parentSlug] || [])
      .find(function (s) { return s.slug === slug; }) || null;
  }

  function ensureUids(items) {
    (items || []).forEach(function (i) { if (!i._uid) i._uid = genUid(); ensureUids(i.children || []); });
  }

  function restoreRuntimeProps(newItems, prevItems) {
    newItems.forEach(function (item, i) {
      var prev = prevItems[i] || {};
      item._uid     = item._uid     || prev._uid     || genUid();
      item._wpLabel = item._wpLabel || prev._wpLabel || "";
      item._wpIcon  = item._wpIcon  || prev._wpIcon  || "";
      (item.children || []).forEach(function (c, j) {
        var pc = (prev.children || [])[j] || {};
        c._uid     = c._uid     || pc._uid     || genUid();
        c._wpLabel = c._wpLabel || pc._wpLabel || "";
      });
    });
  }

  function setDisplay(id, v) { var el = document.getElementById(id); if (el) el.style.display = v; }
  function show(id) { var el = document.getElementById(id); if (el) el.style.display = ""; }
  function hide(id) { var el = document.getElementById(id); if (el) el.style.display = "none"; }

  var _uid = 0;
  function genUid() { return "u" + (++_uid); }
  function deepCopy(o) { return JSON.parse(JSON.stringify(o)); }
  function stripTags(s) { return String(s || "").replace(/<[^>]*>/g, "").trim(); }
  function esc(s) {
    return String(s || "")
      .replace(/&/g, "&amp;").replace(/</g, "&lt;")
      .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }
})();
