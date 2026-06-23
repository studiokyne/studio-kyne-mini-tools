/**
 * Studio Kyne Mini Tools - Admin JavaScript
 * Vanilla JS uniquement. Chargé uniquement sur les pages SKMT.
 * La logique toast/notifications est dans notifications.js (chargé global).
 */
(function () {
  "use strict";

  var isDirty = false;

  document.addEventListener("DOMContentLoaded", function () {
    initToggles();
    initFormValidation();
    initModal();
    initModalTriggers();
    initUnsavedWarning();
    initModuleAjaxToggles();
  });

  /* ================================================================
   * TOGGLES
   * ================================================================ */

  function initToggles() {
    var toggles = document.querySelectorAll(
      '.skmt-toggle input[type="checkbox"]',
    );

    toggles.forEach(function (toggle) {
      toggle.addEventListener("change", function () {
        var label = this.closest(".skmt-form__group--toggle");
        if (label) {
          label.classList.toggle("is-active", this.checked);
        }
      });
    });
  }

  /* ================================================================
   * VALIDATION FORMULAIRES
   * ================================================================ */

  function initFormValidation() {
    var forms = document.querySelectorAll(".skmt-form");

    forms.forEach(function (form) {
      form.addEventListener("submit", function (e) {
        var requiredInputs = form.querySelectorAll("[required]");
        var isValid = true;

        requiredInputs.forEach(function (input) {
          if (!input.value.trim()) {
            isValid = false;
            input.classList.add("is-invalid");
          } else {
            input.classList.remove("is-invalid");
          }
        });

        if (!isValid) {
          e.preventDefault();
        }
      });
    });
  }

  /* ================================================================
   * MODAL RÉUTILISABLE
   * Usage : window.skmtModal.open({ title, message, confirmLabel,
   *         cancelLabel, onConfirm, danger })
   * ================================================================ */

  var modalOverlay, modalEl, modalTitle, modalMessage, modalConfirmBtn, modalCancelBtn;

  function initModal() {
    modalOverlay = document.getElementById("skmt-modal-overlay");
    if (!modalOverlay) return;

    modalEl         = modalOverlay.querySelector(".skmt-modal");
    modalTitle      = modalOverlay.querySelector(".skmt-modal__title");
    modalMessage    = modalOverlay.querySelector(".skmt-modal__message");
    modalConfirmBtn = modalOverlay.querySelector(".skmt-modal__confirm");
    modalCancelBtn  = modalOverlay.querySelector(".skmt-modal__cancel");

    modalCancelBtn.addEventListener("click", closeModal);
    modalOverlay.addEventListener("click", function (e) {
      if (e.target === modalOverlay) closeModal();
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modalOverlay.classList.contains("is-open")) {
        closeModal();
      }
    });
  }

  function openModal(options) {
    if (!modalOverlay) return;

    options = options || {};
    modalTitle.textContent      = options.title   || "";
    modalMessage.textContent    = options.message || "";
    modalConfirmBtn.textContent = options.confirmLabel || "Confirmer";
    modalCancelBtn.textContent  = options.cancelLabel  || "Annuler";

    modalConfirmBtn.className = "skmt-btn skmt-btn--sm " +
      (options.danger ? "skmt-btn--danger" : "skmt-btn--primary");

    var handler = options.onConfirm || function () {};
    var newBtn  = modalConfirmBtn.cloneNode(true);
    newBtn.textContent = modalConfirmBtn.textContent;
    newBtn.className   = modalConfirmBtn.className;
    newBtn.addEventListener("click", function () {
      closeModal();
      handler();
    });
    modalConfirmBtn.parentNode.replaceChild(newBtn, modalConfirmBtn);
    modalConfirmBtn = newBtn;

    modalOverlay.classList.add("is-open");
    modalOverlay.setAttribute("aria-hidden", "false");
    modalConfirmBtn.focus();
  }

  function closeModal() {
    if (!modalOverlay) return;
    modalOverlay.classList.remove("is-open");
    modalOverlay.setAttribute("aria-hidden", "true");
  }

  window.skmtModal = { open: openModal, close: closeModal };

  /* ================================================================
   * DÉCLENCHEURS [data-modal-confirm]
   * Boutons qui déclenchent la modal avant de soumettre un form.
   * ================================================================ */

  function initModalTriggers() {
    document.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-modal-confirm]");
      if (!btn) return;
      e.preventDefault();

      var formId = btn.getAttribute("data-modal-form");
      var form   = formId ? document.getElementById(formId) : null;

      openModal({
        title:        btn.getAttribute("data-modal-title")   || "Confirmer",
        message:      btn.getAttribute("data-modal-message") || "",
        confirmLabel: btn.getAttribute("data-modal-confirm-label") || "Confirmer",
        danger:       btn.hasAttribute("data-modal-danger") || btn.classList.contains("skmt-btn--danger"),
        onConfirm: function () {
          if (form) form.submit();
        },
      });
    });
  }

  /* ================================================================
   * AVERTISSEMENT MODIFICATIONS NON SAUVEGARDÉES
   * ================================================================ */

  function initUnsavedWarning() {
    var forms   = document.querySelectorAll(
      ".skmt-form, #skmt-save-settings-form, #skmt-module-form",
    );

    forms.forEach(function (form) {
      form.addEventListener(
        "input",
        function () { isDirty = true; },
        { passive: true },
      );
      form.addEventListener(
        "change",
        function () { isDirty = true; },
        { passive: true },
      );
      // Soumettre le form reset l'état dirty
      form.addEventListener("submit", function () { isDirty = false; });
    });

    // Intercepte les liens de navigation interne (sidebar, menus WP, etc.)
    document.addEventListener("click", function (e) {
      if (!isDirty) return;
      var link = e.target.closest("a[href]");
      if (!link) return;

      var href = link.getAttribute("href");
      if (!href || href.startsWith("#")) return;

      // Ignorer les déconnexions
      if (href.indexOf("action=logout") !== -1) return;

      // Résoudre l'URL absolue pour comparer l'origine
      var resolved;
      try {
        resolved = new URL(href, window.location.href);
      } catch (_) {
        return;
      }
      // Ignorer les liens vers un autre domaine
      if (resolved.origin !== window.location.origin) return;

      e.preventDefault();
      var fullHref = resolved.href;
      openModal({
        title:        "Modifications non sauvegardées",
        message:      "Vous avez des modifications non enregistrées. Quitter sans sauvegarder ?",
        confirmLabel: "Quitter sans sauvegarder",
        cancelLabel:  "Rester sur la page",
        danger:       true,
        onConfirm: function () {
          isDirty = false;
          window.location.href = fullHref;
        },
      });
    });

    window.addEventListener("beforeunload", function (e) {
      if (isDirty) {
        e.preventDefault();
        e.returnValue = "";
      }
    });
  }

  /* ================================================================
   * AJAX TOGGLE MODULES
   * ================================================================ */

  function initModuleAjaxToggles() {
    var moduleGrid = document.querySelector(".skmt-module-grid");
    if (!moduleGrid || typeof skmtAdmin === "undefined") return;

    moduleGrid.addEventListener("change", function (e) {
      var checkbox = e.target.closest(
        '.skmt-module-card .skmt-toggle input[type="checkbox"]',
      );
      if (!checkbox) return;

      var card     = checkbox.closest(".skmt-module-card");
      var moduleId = checkbox.getAttribute("data-module-id");
      if (!card || !moduleId) return;

      var action  = checkbox.checked ? "activate" : "deactivate";
      var formData = new FormData();
      formData.append("action",       "skmt_ajax_toggle_module");
      formData.append("nonce",        skmtAdmin.nonce);
      formData.append("module",       moduleId);
      formData.append("skmt_action",  action);

      // Feedback visuel immédiat
      checkbox.disabled = true;

      fetch(skmtAdmin.ajaxUrl, {
        method:      "POST",
        credentials: "same-origin",
        body:        formData,
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          checkbox.disabled = false;
          if (!data.success) {
            // Revenir à l'état précédent
            checkbox.checked = !checkbox.checked;
            if (typeof window.skmtShowToast === "function") {
              window.skmtShowToast(
                (data.data && data.data.message) || "Erreur",
                "error",
              );
            }
            return;
          }

          var isActive = data.data.active;
          card.classList.toggle("skmt-module-card--active", isActive);

          // Mettre à jour / créer le bouton "Configurer"
          var actions = card.querySelector(".skmt-module-card__actions");
          if (actions) {
            var existingLink = actions.querySelector(".skmt-btn");
            if (isActive) {
              if (!existingLink) {
                var a    = document.createElement("a");
                a.href   = data.data.configure_url;
                a.className = "skmt-btn skmt-btn--sm skmt-btn--secondary";
                a.textContent = "Configurer";
                actions.appendChild(a);
              }
            } else {
              if (existingLink) existingLink.remove();
            }
          }

          if (typeof window.skmtShowToast === "function") {
            window.skmtShowToast(data.data.notice, "success");
          }

          // Recharger la page pour mettre à jour la navigation latérale
          isDirty = false;
          setTimeout(function () {
            window.location.reload();
          }, 1200);
        })
        .catch(function () {
          checkbox.disabled = false;
          checkbox.checked  = !checkbox.checked;
        });
    });
  }

  /* ================================================================
   * UTILITAIRES
   * ================================================================ */

  window.skmtConfirm = function (message) {
    return confirm(
      message ||
        (window.skmtAdmin && skmtAdmin.i18n.confirmAction) ||
        "Êtes-vous sûr ?",
    );
  };

  /* ================================================================
   * MODALS NOMMÉES — skmtModalOpen / skmtModalClose
   * Pour les modals avec HTML persistant (form, etc.).
   * Complément à skmtModal.open() qui est programmatique.
   * Usage : skmtModalOpen('mon-modal-id')
   * ================================================================ */

  window.skmtModalOpen = function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.add("is-open");
    // Focaliser le premier champ texte si présent
    var input = el.querySelector("input[type='text'], input[type='number'], textarea");
    if (input) {
      setTimeout(function () {
        input.select();
        input.focus();
      }, 60);
    }
  };

  window.skmtModalClose = function (id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove("is-open");
  };

  // Délégation globale : click hors du .skmt-modal ou sur .skmt-modal-close
  document.addEventListener("click", function (e) {
    // Clic sur l'overlay lui-même (hors de la boîte)
    if (
      e.target.classList.contains("skmt-modal-overlay") &&
      e.target.id !== "skmt-modal-overlay" // géré par initModal()
    ) {
      e.target.classList.remove("is-open");
      return;
    }
    // Bouton de fermeture explicite
    var closeBtn = e.target.closest && e.target.closest(".skmt-modal-close");
    if (closeBtn) {
      var overlay = closeBtn.closest(".skmt-modal-overlay");
      if (overlay && overlay.id !== "skmt-modal-overlay") {
        overlay.classList.remove("is-open");
      }
    }
  });

  // Échap ferme toutes les modals nommées ouvertes (sauf la principale)
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    var open = document.querySelectorAll(
      ".skmt-modal-overlay.is-open:not(#skmt-modal-overlay)",
    );
    open.forEach(function (el) {
      el.classList.remove("is-open");
    });
  });
})();
