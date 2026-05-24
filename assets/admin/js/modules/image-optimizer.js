/**
 * Studio Kyne Mini Tools - Image Optimizer admin JS
 * Script dédié au module Image Optimizer.
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    initBulkOptimization();
    initSingleOptimization();
  });

  function initBulkOptimization() {
    const startBtn = document.getElementById("skmt-bulk-start");
    if (!startBtn || typeof skmtAdmin === "undefined") return;

    const progressEl = document.querySelector(".skmt-bulk-status__progress");
    const messageEl = document.querySelector(".skmt-bulk-status__message");
    const barEl = document.querySelector(".skmt-progress__bar");
    const remainingEl = document.getElementById("skmt-bulk-remaining");
    const potentialEl = document.getElementById("skmt-bulk-potential");

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

      startBulk();
    });

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

          setTimeout(pollStatus, 1000);
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
