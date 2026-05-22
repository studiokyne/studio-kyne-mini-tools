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
    initLucideIcons();
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
   * Initialise Lucide Icons
   */
  function initLucideIcons() {
    if (typeof lucide !== "undefined") {
      lucide.createIcons();
      document.body.classList.add("skmt-icons-ready");
      return;
    }

    // Fallback: avoid hiding icons forever if lucide fails to load
    setTimeout(function () {
      document.body.classList.add("skmt-icons-ready");
    }, 1000);
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

  /* ================================================================
   * BULK OPTIMIZATION (Image Optimizer)
   * ================================================================ */
  initBulkOptimization();

  function initBulkOptimization() {
    const startBtn = document.getElementById("skmt-bulk-start");
    if (!startBtn) return;

    const progressEl = document.querySelector(".skmt-bulk-status__progress");
    const messageEl = document.querySelector(".skmt-bulk-status__message");
    const barEl = document.querySelector(".skmt-progress__bar");
    const remainingEl = document.getElementById("skmt-bulk-remaining");

    let isRunning = false;

    startBtn.addEventListener("click", function () {
      if (isRunning) return;
      isRunning = true;

      startBtn.disabled = true;
      startBtn.textContent =
        skmtAdmin.i18n.bulkRunning || "Optimisation en cours…";

      if (progressEl) {
        progressEl.style.display = "flex";
      }

      runBatch();
    });

    function runBatch() {
      const formData = new FormData();
      formData.append("action", "skmt_image_optimizer_bulk");
      formData.append("nonce", skmtAdmin.nonce);

      fetch(skmtAdmin.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: formData,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (!data.success) {
            showError(data.data || "Erreur");
            return;
          }

          const result = data.data;

          if (remainingEl) {
            remainingEl.textContent = result.remaining;
          }

          if (messageEl) {
            messageEl.textContent =
              (skmtAdmin.i18n.bulkProcessed || "Traité : ") +
              result.processed +
              " — " +
              (skmtAdmin.i18n.bulkRemaining || "Restant : ") +
              result.remaining;
          }

          if (barEl) {
            const total = result.processed + result.remaining || 1;
            const percent = Math.round(
              ((total - result.remaining) / total) * 100,
            );
            barEl.style.width = percent + "%";
          }

          if (result.done) {
            finishBulk();
          } else {
            // Continuer avec le lot suivant
            setTimeout(runBatch, 500);
          }
        })
        .catch(function (err) {
          showError(err.message || "Erreur réseau");
        });
    }

    function finishBulk() {
      isRunning = false;
      startBtn.disabled = false;
      startBtn.textContent = skmtAdmin.i18n.bulkDone || "Optimisation terminée";

      if (messageEl) {
        messageEl.textContent =
          skmtAdmin.i18n.bulkComplete ||
          "Toutes les images ont été optimisées.";
      }

      if (barEl) {
        barEl.style.width = "100%";
      }
    }

    function showError(msg) {
      isRunning = false;
      startBtn.disabled = false;
      startBtn.textContent = skmtAdmin.i18n.bulkRetry || "Réessayer";

      if (messageEl) {
        messageEl.textContent = msg;
        messageEl.style.color = "var(--skmt-danger)";
      }
    }
  }
})();
