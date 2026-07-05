/**
 * Studio Kyne Mini Tools - Image Optimizer admin JS
 * Script dédié au module Image Optimizer.
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    initBulkOptimization();
    initSingleOptimization();
    initSvgRolesToggle();
  });

  // Grise le sélecteur de rôles quand l'upload SVG est désactivé.
  function initSvgRolesToggle() {
    const master = document.querySelector("[data-svg-master]");
    const roles = document.getElementById("skmt-svg-roles");
    if (!master || !roles) return;

    const sync = function () {
      roles.classList.toggle("is-disabled", !master.checked);
    };
    master.addEventListener("change", sync);
    sync();
  }

  function initBulkOptimization() {
    const startBtn = document.getElementById("skmt-bulk-start");
    if (!startBtn || typeof skmtAdmin === "undefined") return;

    const scanBtn = document.getElementById("skmt-bulk-scan");
    const introEl = document.getElementById("skmt-bulk-scan-intro");
    const resultEl = document.getElementById("skmt-bulk-result");
    const potentialTile = document.getElementById("skmt-bulk-potential-tile");
    const progressEl = document.querySelector(".skmt-bulk-status__progress");
    const messageEl = document.querySelector(".skmt-bulk-status__message");
    const barEl = document.querySelector(".skmt-progress__bar");
    const remainingEl = document.getElementById("skmt-bulk-remaining");
    const potentialEl = document.getElementById("skmt-bulk-potential");

    var POLL_MIN = 2000;
    var POLL_MAX = 5000;
    var pollInterval = POLL_MIN;
    var isRunning = false;

    // Bascule de l'invite « scanner » vers le bloc résultat (stats + bouton).
    function revealResult() {
      if (introEl) introEl.style.display = "none";
      if (resultEl) resultEl.style.display = "flex";
    }

    // Scan à la demande : compte les images et estime les gains.
    if (scanBtn) {
      scanBtn.addEventListener("click", function () {
        scanBtn.disabled = true;
        var originalLabel = scanBtn.textContent;
        scanBtn.textContent = skmtAdmin.i18n.bulkScanning || "Analyse…";

        const formData = new FormData();
        formData.append("action", "skmt_image_optimizer_bulk_scan");
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
            scanBtn.disabled = false;
            scanBtn.textContent = originalLabel;

            if (!data.success) {
              if (typeof window.skmtShowToast === "function") {
                window.skmtShowToast(data.data || "Erreur", "error");
              }
              return;
            }

            const result = data.data || {};
            const remaining = parseInt(result.remaining, 10) || 0;
            const estimated = parseInt(result.estimated_bytes_saved, 10) || 0;

            if (remainingEl) remainingEl.textContent = remaining;

            // On n'affiche l'estimation que si un historique la rend crédible.
            if (potentialTile) {
              if (estimated > 0) {
                if (potentialEl) potentialEl.textContent = formatBytes(estimated);
                potentialTile.style.display = "";
              } else {
                potentialTile.style.display = "none";
              }
            }

            startBtn.disabled = remaining === 0;
            revealResult();
          })
          .catch(function (err) {
            scanBtn.disabled = false;
            scanBtn.textContent = originalLabel;
            if (typeof window.skmtShowToast === "function") {
              window.skmtShowToast(err.message || "Erreur réseau", "error");
            }
          });
      });
    }

    startBtn.addEventListener("click", function () {
      if (isRunning) return;
      isRunning = true;
      pollInterval = POLL_MIN;

      startBtn.disabled = true;
      startBtn.textContent =
        skmtAdmin.i18n.bulkRunning || "Optimisation en cours…";

      if (progressEl) {
        progressEl.style.display = "flex";
      }

      startBulk();
    });

    // Resync : si un bulk tourne déjà côté serveur (lancé avant un
    // rechargement/une fermeture de page), on reprend l'affichage et le
    // polling au lieu de laisser la page paraître inactive.
    var initialState = skmtAdmin.bulkState;
    if (initialState && initialState.running) {
      isRunning = true;
      pollInterval = POLL_MIN;

      revealResult();

      startBtn.disabled = true;
      startBtn.textContent =
        skmtAdmin.i18n.bulkRunning || "Optimisation en cours…";

      if (progressEl) {
        progressEl.style.display = "flex";
      }

      updateStatus({
        processed: initialState.processed,
        remaining: initialState.remaining,
        total: initialState.total,
      });

      pollStatus();
    }

    function startBulk() {
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
          updateStatus(result);

          if (result.done && !result.running) {
            finishBulk();
            return;
          }

          pollStatus();
        })
        .catch(function (err) {
          showError(err.message || "Erreur réseau");
        });
    }

    function pollStatus() {
      const formData = new FormData();
      formData.append("action", "skmt_image_optimizer_bulk_status");
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
          updateStatus(result);

          if (result.done && !result.running) {
            finishBulk();
            return;
          }

          setTimeout(pollStatus, pollInterval);
          pollInterval = Math.min(pollInterval + 500, POLL_MAX);
        })
        .catch(function (err) {
          showError(err.message || "Erreur réseau");
        });
    }

    function updateStatus(result) {
      if (remainingEl) {
        remainingEl.textContent = result.remaining;
      }
      if (potentialEl && typeof result.estimated_bytes_saved === "number") {
        potentialEl.textContent = formatBytes(result.estimated_bytes_saved);
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
        const total = result.total || result.processed + result.remaining || 1;
        const percent = Math.round(((total - result.remaining) / total) * 100);
        barEl.style.width = percent + "%";
      }
    }

    function finishBulk() {
      isRunning = false;
      startBtn.disabled = false;
      startBtn.textContent = skmtAdmin.i18n.bulkDone || "Optimisation terminée";

      var completeMsg =
        skmtAdmin.i18n.bulkComplete || "Toutes les images ont été optimisées.";

      if (messageEl) {
        messageEl.textContent = completeMsg;
      }

      if (barEl) {
        barEl.style.width = "100%";
      }

      if (typeof window.skmtShowToast === "function") {
        window.skmtShowToast(completeMsg, "success");
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

  function initSingleOptimization() {
    document.addEventListener("click", function (event) {
      const button = event.target.closest(".skmt-optimize-single");
      if (!button || typeof skmtAdmin === "undefined") return;

      const wrapper = button.closest(".skmt-media-optimizer");
      const attachmentId = button.getAttribute("data-attachment");
      const messageEl = wrapper
        ? wrapper.querySelector(".skmt-media-optimizer__message")
        : null;

      if (!attachmentId) return;

      button.disabled = true;
      button.textContent = skmtAdmin.i18n.singleRunning || "Optimisation…";

      const formData = new FormData();
      formData.append("action", "skmt_optimize_single");
      formData.append("nonce", skmtAdmin.nonce);
      formData.append("attachment_id", attachmentId);

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
            if (messageEl) {
              messageEl.textContent =
                data.data || skmtAdmin.i18n.singleError || "Erreur";
            }
            button.disabled = false;
            button.textContent = skmtAdmin.i18n.singleError || "Erreur";
            return;
          }

          const result = data.data || {};
          if (wrapper) {
            const savedEl = wrapper.querySelector(".skmt-bytes-saved");
            const finalEl = wrapper.querySelector(".skmt-bytes-final");
            const estimatedEl = wrapper.querySelector(".skmt-bytes-estimated");
            const originalEl = wrapper.querySelector(".skmt-bytes-original");
            const mainSavedEl = wrapper.querySelector(".skmt-main-bytes-saved");
            const mainFinalEl = wrapper.querySelector(".skmt-main-bytes-final");
            const mainOriginalEl = wrapper.querySelector(".skmt-main-bytes-original");
            const potentialBlock = wrapper.querySelector(
              ".skmt-gain-potential",
            );
            const resultBlock = wrapper.querySelector(".skmt-gain-result");

            if (estimatedEl) {
              estimatedEl.textContent = "—";
            }

            if (savedEl) {
              savedEl.textContent =
                typeof result.bytes_saved === "number"
                  ? formatBytes(result.bytes_saved)
                  : savedEl.textContent;
            }

            if (finalEl) {
              finalEl.textContent =
                typeof result.optimized_bytes === "number"
                  ? formatBytes(result.optimized_bytes)
                  : finalEl.textContent;
            }

            if (originalEl) {
              originalEl.textContent =
                typeof result.original_bytes === "number"
                  ? formatBytes(result.original_bytes)
                  : originalEl.textContent;
            }

            if (mainSavedEl) {
              mainSavedEl.textContent =
                typeof result.main_bytes_saved === "number"
                  ? formatBytes(result.main_bytes_saved)
                  : mainSavedEl.textContent;
            }

            if (mainFinalEl) {
              mainFinalEl.textContent =
                typeof result.main_optimized_bytes === "number"
                  ? formatBytes(result.main_optimized_bytes)
                  : mainFinalEl.textContent;
            }

            if (mainOriginalEl) {
              mainOriginalEl.textContent =
                typeof result.main_original_bytes === "number"
                  ? formatBytes(result.main_original_bytes)
                  : mainOriginalEl.textContent;
            }

            if (potentialBlock) {
              potentialBlock.style.display = "none";
            }

            if (resultBlock) {
              resultBlock.style.display = "block";
            }
          }

          if (messageEl) {
            messageEl.textContent = skmtAdmin.i18n.singleDone || "Optimisée";
          }

          button.textContent = skmtAdmin.i18n.singleDone || "Optimisée";
        })
        .catch(function (err) {
          if (messageEl) {
            messageEl.textContent = err.message || "Erreur réseau";
          }
          button.disabled = false;
          button.textContent = skmtAdmin.i18n.singleError || "Erreur";
        });
    });
  }

  function formatBytes(bytes) {
    if (!bytes || bytes <= 0) return "0 B";
    const units = ["B", "KB", "MB", "GB"];
    let index = 0;
    let value = bytes;
    while (value >= 1024 && index < units.length - 1) {
      value /= 1024;
      index++;
    }
    return value.toFixed(2) + " " + units[index];
  }
})();
