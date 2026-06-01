/**
 * Studio Kyne Mini Tools - Notifications
 * Toast SKMT + Centre de notifications WP
 * Chargé sur tout l'admin WordPress.
 */
(function () {
  "use strict";

  var TOAST_DURATION = 4000;

  var ICONS = {
    success:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
    error:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
    warning:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
  };

  var TITLES = {
    success: "Succès",
    error: "Erreur",
    warning: "Avertissement",
    info: "Information",
  };

  var CLOSE_SVG =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

  var BELL_EMPTY_SVG =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>';

  /* ================================================================
   * TOAST
   * ================================================================ */

  function initToasts() {
    var data = window.skmtToastData;
    if (!data || !data.message) return;
    showToast(data.message, data.type || "success");
  }

  function showToast(message, type) {
    var container = document.getElementById("skmt-toast-container");
    if (!container) return;

    type = type || "success";
    var title = TITLES[type] || TITLES.success;

    var toast = document.createElement("div");
    toast.className = "skmt-toast skmt-toast--" + type;
    toast.setAttribute("role", "alert");
    toast.setAttribute("aria-live", "assertive");

    toast.innerHTML =
      '<div class="skmt-toast__header">' +
      '<span class="skmt-toast__icon">' +
      (ICONS[type] || ICONS.info) +
      "</span>" +
      '<span class="skmt-toast__title">' +
      escapeHtml(title) +
      "</span>" +
      '<button class="skmt-toast__close" type="button" aria-label="Fermer">' +
      CLOSE_SVG +
      "</button>" +
      "</div>" +
      '<div class="skmt-toast__body">' +
      '<p class="skmt-toast__message">' +
      escapeHtml(message) +
      "</p>" +
      "</div>" +
      '<div class="skmt-toast__footer">' +
      '<span class="skmt-toast__countdown">Ce message se ferme dans <strong class="skmt-toast__seconds">' +
      Math.ceil(TOAST_DURATION / 1000) +
      "</strong> s. <a href=\"#\" class=\"skmt-toast__pause-link\">Arrêter</a>.</span>" +
      "</div>" +
      '<div class="skmt-toast__progress-bar"><span class="skmt-toast__progress-fill"></span></div>';

    container.appendChild(toast);

    var closeBtn = toast.querySelector(".skmt-toast__close");
    var fill = toast.querySelector(".skmt-toast__progress-fill");
    var secondsEl = toast.querySelector(".skmt-toast__seconds");
    var pauseLink = toast.querySelector(".skmt-toast__pause-link");

    var dismissed = false;
    var paused = false;
    var elapsed = 0;
    var lastTick = Date.now();

    // Entrée animée
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        toast.classList.add("is-visible");
      });
    });

    function updateProgress() {
      var pct = Math.max(0, 1 - elapsed / TOAST_DURATION);
      fill.style.transform = "scaleX(" + pct + ")";
      var secsLeft = Math.ceil((TOAST_DURATION - elapsed) / 1000);
      if (secondsEl) secondsEl.textContent = secsLeft;
    }

    var raf;
    function tick() {
      if (dismissed || paused) return;
      var now = Date.now();
      elapsed += now - lastTick;
      lastTick = now;
      updateProgress();
      if (elapsed >= TOAST_DURATION) {
        dismiss();
        return;
      }
      raf = requestAnimationFrame(tick);
    }

    // Démarre le ticker après l'animation d'entrée
    setTimeout(function () {
      lastTick = Date.now();
      raf = requestAnimationFrame(tick);
    }, 50);

    function dismiss() {
      if (dismissed) return;
      dismissed = true;
      cancelAnimationFrame(raf);
      toast.classList.remove("is-visible");
      toast.classList.add("is-hiding");
      toast.addEventListener(
        "transitionend",
        function () {
          if (toast.parentNode) toast.parentNode.removeChild(toast);
        },
        { once: true },
      );
    }

    closeBtn.addEventListener("click", dismiss);

    if (pauseLink) {
      pauseLink.addEventListener("click", function (e) {
        e.preventDefault();
        paused = true;
        cancelAnimationFrame(raf);
        fill.style.transform = "scaleX(" + (1 - elapsed / TOAST_DURATION) + ")";
        var footer = toast.querySelector(".skmt-toast__footer");
        if (footer) footer.style.display = "none";
      });
    }

    // Pause au survol
    toast.addEventListener("mouseenter", function () {
      if (!paused) {
        cancelAnimationFrame(raf);
      }
    });
    toast.addEventListener("mouseleave", function () {
      if (!paused && !dismissed) {
        lastTick = Date.now();
        raf = requestAnimationFrame(tick);
      }
    });
  }

  /* ================================================================
   * CENTRE DE NOTIFICATIONS WP
   * ================================================================ */

  function initNotificationCenter() {
    var drawer = document.getElementById("skmt-notif-drawer");
    var overlay = document.getElementById("skmt-notif-overlay");
    var trigger = document.querySelector(
      "#wp-admin-bar-skmt-notif-center > a",
    );
    var closeBtn = document.getElementById("skmt-notif-close");
    var badge = document.getElementById("skmt-notif-badge");
    var body = document.getElementById("skmt-notif-body");

    if (!drawer) return;

    var notices = parseWpNotices(window.skmtWpNoticesHtml || "");

    populateDrawer(body, notices);

    if (notices.length > 0 && badge) {
      badge.setAttribute("data-count", notices.length);
      badge.style.display = "";
    }

    function openDrawer() {
      drawer.classList.add("is-open");
      if (overlay) overlay.classList.add("is-open");
      drawer.setAttribute("aria-hidden", "false");
      if (closeBtn) closeBtn.focus();
    }

    function closeDrawer() {
      drawer.classList.remove("is-open");
      if (overlay) overlay.classList.remove("is-open");
      drawer.setAttribute("aria-hidden", "true");
      if (trigger) trigger.focus();
    }

    if (trigger) {
      trigger.addEventListener("click", function (e) {
        e.preventDefault();
        drawer.classList.contains("is-open") ? closeDrawer() : openDrawer();
      });
    }

    if (closeBtn) closeBtn.addEventListener("click", closeDrawer);
    if (overlay) overlay.addEventListener("click", closeDrawer);

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && drawer.classList.contains("is-open")) {
        closeDrawer();
      }
    });
  }

  function parseWpNotices(html) {
    if (!html || !html.trim()) return [];
    var tmp = document.createElement("div");
    tmp.innerHTML = html;
    var nodes = tmp.querySelectorAll(".notice, .updated, .error");
    var notices = [];
    nodes.forEach(function (el) {
      var btn = el.querySelector(".notice-dismiss");
      if (btn) btn.parentNode.removeChild(btn);
      notices.push({ type: detectNoticeType(el), html: el.innerHTML.trim() });
    });
    return notices;
  }

  function detectNoticeType(el) {
    if (el.classList.contains("notice-success") || el.classList.contains("updated")) return "success";
    if (el.classList.contains("notice-error")   || el.classList.contains("error"))   return "error";
    if (el.classList.contains("notice-warning"))  return "warning";
    return "info";
  }

  function populateDrawer(body, notices) {
    if (!body) return;
    if (notices.length === 0) {
      body.innerHTML =
        '<div class="skmt-notif-drawer__empty">' +
        BELL_EMPTY_SVG +
        "<span>Aucune notification</span>" +
        "</div>";
      return;
    }
    var html = "";
    notices.forEach(function (n) {
      html +=
        '<div class="skmt-notif-item skmt-notif-item--' +
        sanitizeClass(n.type) +
        '">' +
        '<span class="skmt-notif-item__icon">' +
        (ICONS[n.type] || ICONS.info) +
        "</span>" +
        '<div class="skmt-notif-item__content">' +
        n.html +
        "</div>" +
        "</div>";
    });
    body.innerHTML = html;
  }

  /* ================================================================
   * UTILITAIRES
   * ================================================================ */

  function escapeHtml(str) {
    var d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  }

  function sanitizeClass(str) {
    return String(str).replace(/[^a-z0-9-]/gi, "");
  }

  /* ================================================================
   * INIT
   * ================================================================ */

  document.addEventListener("DOMContentLoaded", function () {
    initToasts();
    initNotificationCenter();
  });

  // API publique pour les modules SKMT
  window.skmtShowToast = showToast;
})();
