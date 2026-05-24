/**
 * Studio Kyne Mini Tools - Admin JavaScript
 * Vanilla JS uniquement
 */
(function () {
  "use strict";

  // Attendre que le DOM soit prêt
  document.addEventListener("DOMContentLoaded", function () {
    initToggles();
    initFormValidation();
  });

  /**
   * Initialise les comportements des toggles
   */
  function initToggles() {
    const toggles = document.querySelectorAll(
      '.skmt-toggle input[type="checkbox"]',
    );

    toggles.forEach(function (toggle) {
      toggle.addEventListener("change", function () {
        // Feedback visuel optionnel
        const label = this.closest(".skmt-form__group--toggle");
        if (label) {
          label.classList.toggle("is-active", this.checked);
        }
      });
    });
  }

  /**
   * Validation basique des formulaires
   */
  function initFormValidation() {
    const forms = document.querySelectorAll(".skmt-form");

    forms.forEach(function (form) {
      form.addEventListener("submit", function (e) {
        const requiredInputs = form.querySelectorAll("[required]");
        let isValid = true;

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

  /**
   * Utilitaire pour confirmer les actions destructrices
   */
  window.skmtConfirm = function (message) {
    return confirm(
      message || skmtAdmin.i18n.confirmAction || "Êtes-vous sûr ?",
    );
  };
})();
