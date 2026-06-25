/**
 * Studio Kyne Mini Tools — Module Marque Blanche
 * Liste des profils de menu + shell éditeur (éditeur complet au prompt 1-05).
 */
(function () {
  "use strict";

  if (typeof skmtAdmin === "undefined") return;

  /* ================================================================
   * ÉTAT
   * ================================================================ */

  var wl = {
    profiles:      skmtAdmin.wlProfiles || [],
    currentFilter: "all",
    searchQuery:   "",
  };

  /* ================================================================
   * INITIALISATION
   * ================================================================ */

  document.addEventListener("DOMContentLoaded", function () {
    if (!document.getElementById("skmt-wl-profiles-list")) return;

    renderProfiles();
    initSearch();
    initTabs();
  });

  /* ================================================================
   * RENDU DES CARDS
   * ================================================================ */

  function renderProfiles() {
    var container = document.getElementById("skmt-wl-profiles-list");
    var countEl   = document.getElementById("skmt-wl-count");
    if (!container) return;

    var filtered = wl.profiles.filter(function (p) {
      var matchFilter = wl.currentFilter === "all" || p.status === wl.currentFilter;
      var matchSearch =
        !wl.searchQuery ||
        (p.name || "").toLowerCase().indexOf(wl.searchQuery.toLowerCase()) !== -1;
      return matchFilter && matchSearch;
    });

    if (countEl) countEl.textContent = filtered.length;

    if (!filtered.length) {
      container.innerHTML =
        '<p class="skmt-wl-empty">' +
        escHtml(
          wl.profiles.length === 0
            ? "Aucun menu créé. Cliquez sur « + Nouveau » pour commencer."
            : "Aucun menu ne correspond à votre recherche."
        ) +
        "</p>";
      return;
    }

    container.innerHTML = filtered.map(buildCard).join("");

    container.querySelectorAll(".skmt-wl-profile-card").forEach(function (card) {
      card.addEventListener("click", function () {
        var id = card.dataset.id;
        if (id) window.location.href = (skmtAdmin.wlEditorUrl || "") + "&profile_id=" + encodeURIComponent(id);
      });
      card.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          card.click();
        }
      });
    });
  }

  function buildCard(profile) {
    var i18n        = (skmtAdmin.i18n || {});
    var isActive    = profile.status === "active";
    var statusLabel = isActive ? (i18n.active || "Actif") : (i18n.draft || "Brouillon");
    var badgeMod    = isActive ? "skmt-badge--success" : "skmt-badge--warning";

    var dateStr = "";
    if (profile.updated_at) {
      dateStr = new Date(profile.updated_at * 1000).toLocaleDateString("fr-FR", {
        day: "2-digit", month: "short", year: "numeric",
      });
    }

    var scope = "";
    if (profile.apply_to_all) {
      scope = "Tous les utilisateurs";
    } else if ((profile.include_roles || []).length || (profile.include_users || []).length) {
      var n = (profile.include_roles || []).length + (profile.include_users || []).length;
      scope = n + " attribution" + (n > 1 ? "s" : "");
    }

    return (
      '<div class="skmt-wl-profile-card" data-id="' + escHtml(profile.id) + '"' +
      ' role="button" tabindex="0" aria-label="' + escHtml(profile.name || "Menu sans nom") + '">' +
        '<div class="skmt-wl-profile-card__top">' +
          '<span class="skmt-wl-profile-card__name">' + escHtml(profile.name || "Menu sans nom") + "</span>" +
          '<span class="skmt-badge ' + badgeMod + '">' + escHtml(statusLabel) + "</span>" +
        "</div>" +
        '<div class="skmt-wl-profile-card__meta">' +
          (dateStr ? '<span>' + escHtml(dateStr) + "</span>" : "") +
          (scope ? (dateStr ? ' <span class="skmt-wl-profile-card__sep">·</span> ' : "") + '<span>' + escHtml(scope) + "</span>" : "") +
        "</div>" +
      "</div>"
    );
  }

  /* ================================================================
   * RECHERCHE & TABS
   * ================================================================ */

  function initSearch() {
    var input = document.getElementById("skmt-wl-search");
    if (!input) return;
    input.addEventListener("input", function () {
      wl.searchQuery = input.value;
      renderProfiles();
    });
  }

  function initTabs() {
    document.querySelectorAll(".skmt-wl-tab").forEach(function (tab) {
      tab.addEventListener("click", function () {
        document.querySelectorAll(".skmt-wl-tab").forEach(function (t) {
          t.classList.remove("is-active");
        });
        tab.classList.add("is-active");
        wl.currentFilter = tab.dataset.filter || "all";
        renderProfiles();
      });
    });
  }

  /* ================================================================
   * UTILITAIRES
   * ================================================================ */

  function escHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

})();
