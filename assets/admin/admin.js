(() => {
  const getToastStack = () => {
    let stack = document.querySelector("[data-skmt-toast-stack]");
    if (stack) return stack;

    stack = document.createElement("div");
    stack.className = "skmt-toast-stack";
    stack.setAttribute("data-skmt-toast-stack", "");
    document.body.appendChild(stack);
    return stack;
  };

  const mountToast = (toast, index = 0) => {
    const stack = toast.closest("[data-skmt-toast-stack]");
    if (!stack) return;

    let isClosing = false;
    const closeToast = () => {
      if (isClosing) return;
      isClosing = true;
      toast.classList.add("is-leaving");
      window.setTimeout(() => {
        toast.remove();
        if (!stack.querySelector("[data-skmt-toast]")) {
          stack.remove();
        }
      }, 220);
    };

    const timeoutId = window.setTimeout(closeToast, 5000 + index * 150);
    const closeButton = toast.querySelector("[data-skmt-toast-close]");
    closeButton?.addEventListener("click", () => {
      window.clearTimeout(timeoutId);
      closeToast();
    });
  };

  const showToast = (message, type = "info") => {
    if (!message) return;
    const stack = getToastStack();
    const toast = document.createElement("div");
    toast.className = `skmt-toast skmt-toast--${type}`;
    toast.setAttribute("data-skmt-toast", "");
    toast.setAttribute("role", type === "error" ? "alert" : "status");
    toast.setAttribute("aria-live", "polite");

    const messageNode = document.createElement("div");
    messageNode.className = "skmt-toast__message";
    messageNode.textContent = message;

    const closeButton = document.createElement("button");
    closeButton.type = "button";
    closeButton.className = "skmt-toast__close";
    closeButton.setAttribute("data-skmt-toast-close", "");
    closeButton.setAttribute(
      "aria-label",
      skmtAdmin?.i18n?.closeNotification || "Fermer la notification",
    );
    closeButton.textContent = "\u00d7";

    toast.appendChild(messageNode);
    toast.appendChild(closeButton);
    stack.appendChild(toast);

    const index = stack.querySelectorAll("[data-skmt-toast]").length - 1;
    mountToast(toast, index);
  };

  const initLucideIcons = () => {
    if (window.lucide && typeof window.lucide.createIcons === "function") {
      window.lucide.createIcons();
    }
  };

  const syncRanges = () => {
    document.querySelectorAll("[data-range-target]").forEach((range) => {
      const target = document.getElementById(
        range.getAttribute("data-range-target"),
      );
      if (!target) return;
      const syncTargetFromRange = () => {
        target.value = range.value;
      };
      const syncRangeFromTarget = () => {
        const next = Number(target.value || range.value);
        if (Number.isNaN(next)) return;
        range.value = String(next);
      };

      syncTargetFromRange();

      range.addEventListener("input", () => {
        syncTargetFromRange();
      });
      target.addEventListener("input", () => {
        syncRangeFromTarget();
      });

      if (range.form) {
        range.form.addEventListener("submit", () => {
          syncRangeFromTarget();
          syncTargetFromRange();
        });
      }
    });
  };
  const initBulkTool = () => {
    const box = document.getElementById("skmt-bulk-box");
    if (!box) return;
    const startButton = document.getElementById("skmt-bulk-start");
    const stopButton = document.getElementById("skmt-bulk-stop");
    const status = document.getElementById("skmt-bulk-status");
    const progressBar = document.getElementById("skmt-bulk-progress-bar");
    const logBox = document.getElementById("skmt-bulk-log");
    const nonce = box.dataset.nonce || "";
    const pollInterval = Number(box.dataset.pollInterval || 4000);
    let pollTimer = null;
    let lastLogMessage = "";
    let previousState = { running: false, completed: false, stopped: false };

    if (!startButton || !stopButton || !status || !progressBar || !logBox) {
      return;
    }

    const writeLog = (message) => {
      if (!message) return;
      const p = document.createElement("p");
      p.textContent = message;
      logBox.prepend(p);
    };

    const stopPolling = () => {
      if (!pollTimer) return;
      window.clearInterval(pollTimer);
      pollTimer = null;
    };

    const startPolling = () => {
      if (pollTimer) return;
      pollTimer = window.setInterval(
        () => {
          fetchStatus(true);
        },
        Math.max(2000, pollInterval),
      );
    };

    const postAction = (action) => {
      const formData = new FormData();
      formData.append("action", action);
      formData.append("_ajax_nonce", nonce);

      return fetch(skmtAdmin.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: formData,
      })
        .then((response) => response.json())
        .then((payload) => {
          if (!payload || !payload.success) {
            throw new Error(
              payload?.data?.message ||
                skmtAdmin?.i18n?.genericError ||
                "Erreur",
            );
          }
          return payload;
        });
    };

    const applyState = (state, opts = {}) => {
      const safeState = state || {};
      const isRunning = Boolean(safeState.running);
      const total = Number(safeState.total || 0);
      const done = Number(
        safeState.done ||
          Number(safeState.processed || 0) +
            Number(safeState.skipped || 0) +
            Number(safeState.failed || 0),
      );

      const computedPercent =
        total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
      const percent = Number.isFinite(Number(safeState.progress_percent))
        ? Number(safeState.progress_percent)
        : computedPercent;

      progressBar.style.width = `${Math.max(0, Math.min(100, percent))}%`;

      status.textContent =
        safeState.last_message ||
        (isRunning
          ? skmtAdmin?.i18n?.bulkRunning || "Conversion en cours…"
          : skmtAdmin?.i18n?.bulkIdle || "En attente.");

      startButton.disabled = isRunning;
      stopButton.disabled = !isRunning;

      if (safeState.last_message && safeState.last_message !== lastLogMessage) {
        writeLog(safeState.last_message);
        lastLogMessage = safeState.last_message;
      }

      if (opts.fromPoll && previousState.running && !isRunning) {
        if (safeState.completed) {
          showToast(
            safeState.last_message ||
              skmtAdmin?.i18n?.bulkCompleted ||
              "Conversion terminée.",
            "success",
          );
        } else if (safeState.stopped) {
          showToast(
            safeState.last_message ||
              skmtAdmin?.i18n?.bulkStopped ||
              "Conversion arrêtée.",
            "warning",
          );
        }
      }

      if (isRunning) {
        startPolling();
      } else {
        stopPolling();
      }

      if (!isRunning && opts.fromPoll && safeState.completed) {
        progressBar.style.width = "100%";
      }

      previousState = {
        running: isRunning,
        completed: Boolean(safeState.completed),
        stopped: Boolean(safeState.stopped),
      };
    };

    const fetchStatus = (fromPoll = false) => {
      return postAction("skmt_image_bulk_status")
        .then((payload) => {
          applyState(payload?.data?.state || {}, { fromPoll });
        })
        .catch((error) => {
          if (fromPoll) {
            stopPolling();
          }
          status.textContent =
            error.message ||
            skmtAdmin?.i18n?.bulkStatusError ||
            skmtAdmin?.i18n?.genericError ||
            "Erreur";
        });
    };

    startButton.addEventListener("click", () => {
      startButton.disabled = true;
      stopButton.disabled = true;
      status.textContent =
        skmtAdmin?.i18n?.bulkStarting || "Démarrage de la conversion…";

      postAction("skmt_image_bulk_start")
        .then((payload) => {
          if (payload?.data?.message) {
            showToast(payload.data.message, "info");
          }
          applyState(payload?.data?.state || {});
        })
        .catch((error) => {
          startButton.disabled = false;
          stopButton.disabled = true;
          status.textContent =
            error.message || skmtAdmin?.i18n?.genericError || "Erreur";
          writeLog(status.textContent);
          showToast(status.textContent, "error");
        });
    });

    stopButton.addEventListener("click", () => {
      stopButton.disabled = true;
      status.textContent =
        skmtAdmin?.i18n?.bulkStopping || "Arrêt de la conversion…";

      postAction("skmt_image_bulk_stop")
        .then((payload) => {
          if (payload?.data?.message) {
            showToast(payload.data.message, "warning");
          }
          applyState(payload?.data?.state || {});
        })
        .catch((error) => {
          status.textContent =
            error.message || skmtAdmin?.i18n?.genericError || "Erreur";
          writeLog(status.textContent);
          showToast(status.textContent, "error");
          fetchStatus(false);
        });
    });

    fetchStatus(false);
  };
  const initConfigImport = () => {
    const trigger = document.getElementById("skmt-import-config-trigger");
    const input = document.getElementById("skmt-config-file");
    const selectedName = document.getElementById("skmt-import-config-name");
    if (!trigger || !input) return;

    trigger.addEventListener("click", () => {
      input.click();
    });
    input.addEventListener("change", () => {
      const file = input.files && input.files[0] ? input.files[0] : null;
      if (!file) return;
      if (selectedName) {
        selectedName.textContent = `${skmtAdmin?.i18n?.selectedFile || "Fichier sélectionné :"} ${file.name}`;
      }
      trigger.disabled = true;
      trigger.textContent =
        skmtAdmin?.i18n?.importingConfig || "Import en cours…";
      input.form?.submit();
    });
  };

  const initToasts = () => {
    const stack = document.querySelector("[data-skmt-toast-stack]");
    if (!stack) return;

    stack.querySelectorAll("[data-skmt-toast]").forEach((toast, index) => {
      mountToast(toast, index);
    });
  };

  document.addEventListener("DOMContentLoaded", () => {
    initLucideIcons();
    syncRanges();
    initBulkTool();
    initConfigImport();
    initToasts();
  });
})();
