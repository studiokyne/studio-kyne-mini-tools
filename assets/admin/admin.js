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

  const serializeForm = (form) => {
    const params = new URLSearchParams();
    const data = new FormData(form);
    data.forEach((value, key) => {
      if (value instanceof File) return;
      params.append(key, String(value));
    });
    return params.toString();
  };

  const initAutoSaveForms = () => {
    const forms = Array.from(
      document.querySelectorAll('.skmt-shell form[action*="options.php"]'),
    ).filter((form) => form.dataset.skmtAutosave !== "0");

    forms.forEach((form) => {
      let inFlight = false;
      let queued = false;
      let timer = null;
      let lastSerialized = serializeForm(form);

      const runSave = () => {
        timer = null;
        const nextSerialized = serializeForm(form);
        if (nextSerialized === lastSerialized) {
          return;
        }

        if (inFlight) {
          queued = true;
          return;
        }

        inFlight = true;
        const body = new FormData(form);

        fetch(form.action, {
          method: (form.method || "POST").toUpperCase(),
          credentials: "same-origin",
          body,
        })
          .then((response) => {
            if (!response.ok) {
              throw new Error(
                skmtAdmin?.i18n?.autoSaveError ||
                  "Impossible d’enregistrer automatiquement les réglages.",
              );
            }
            lastSerialized = nextSerialized;
            showToast(
              skmtAdmin?.i18n?.autoSaved ||
                "Réglages enregistrés automatiquement.",
              "success",
            );
          })
          .catch((error) => {
            showToast(
              error?.message ||
                skmtAdmin?.i18n?.autoSaveError ||
                "Impossible d’enregistrer automatiquement les réglages.",
              "error",
            );
          })
          .finally(() => {
            inFlight = false;
            if (queued) {
              queued = false;
              runSave();
            }
          });
      };

      const scheduleSave = () => {
        if (timer) {
          window.clearTimeout(timer);
        }
        timer = window.setTimeout(runSave, 480);
      };

      form.addEventListener("change", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (target.matches('[type="file"], [type="submit"], [type="button"]')) {
          return;
        }
        scheduleSave();
      });

      form.addEventListener("input", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (
          !target.matches('input[type="text"], input[type="number"], textarea')
        ) {
          return;
        }
        scheduleSave();
      });
    });
  };

  const escapeHtml = (value) =>
    String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");

  const highlightCode = (code, lang) => {
    let html = escapeHtml(code);
    const keywordMap = {
      php: "function|class|public|protected|private|if|else|elseif|return|foreach|while|new|array|static",
      javascript:
        "function|const|let|var|if|else|return|class|new|import|export|async|await|try|catch",
      css: "@media|@keyframes|display|position|color|background|border|grid|flex",
      json: "true|false|null",
      html: "div|span|script|style|a|img|form|input|button|section|header|footer",
      xml: "xml|version|encoding",
      yaml: "true|false|null",
    };

    html = html.replace(
      /(\/\*[\s\S]*?\*\/|\/\/[^\n]*|#.*$)/gm,
      '<span class="skmt-code-comment">$1</span>',
    );
    html = html.replace(
      /("(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*')/g,
      '<span class="skmt-code-string">$1</span>',
    );
    html = html.replace(
      /\b(\d+(?:\.\d+)?)\b/g,
      '<span class="skmt-code-number">$1</span>',
    );

    const keywordList = keywordMap[lang] || keywordMap.text;
    if (keywordList) {
      const keywordRegex = new RegExp(`\\b(${keywordList})\\b`, "g");
      html = html.replace(
        keywordRegex,
        '<span class="skmt-code-keyword">$1</span>',
      );
    }

    return html;
  };

  const initFilesEditorModal = () => {
    const modal = document.querySelector("[data-skmt-files-modal]");
    if (!modal) return;

    const closeTriggers = Array.from(
      modal.querySelectorAll("[data-skmt-files-modal-close]"),
    );
    const form = modal.querySelector("[data-skmt-files-editor-form]");
    const textarea = modal.querySelector("[data-skmt-files-editor]");
    const highlight = modal.querySelector(".skmt-files-editor-highlight");
    const wrap = modal.querySelector(".skmt-files-editor-wrap");
    if (!(textarea instanceof HTMLTextAreaElement) || !form || !highlight) {
      return;
    }

    const lang = wrap?.getAttribute("data-lang") || "text";
    let hasUnsavedChanges = false;

    const getExitUrl = () => {
      const url = new URL(window.location.href);
      url.searchParams.delete("edit");
      return url.toString();
    };

    const confirmClose = () => {
      if (!hasUnsavedChanges) return true;
      return window.confirm(
        skmtAdmin?.i18n?.unsavedEditor ||
          "Vous avez des modifications non enregistrées. Quitter quand même ?",
      );
    };

    const updateHighlight = () => {
      highlight.innerHTML = `${highlightCode(textarea.value, lang)}\n`;
      highlight.scrollTop = textarea.scrollTop;
      highlight.scrollLeft = textarea.scrollLeft;
    };

    const requestClose = () => {
      if (!confirmClose()) return;
      hasUnsavedChanges = false;
      window.removeEventListener("beforeunload", handleBeforeUnload);
      window.location.href = getExitUrl();
    };

    const handleBeforeUnload = (event) => {
      if (!hasUnsavedChanges) return;
      event.preventDefault();
      event.returnValue = "";
    };

    textarea.addEventListener("input", () => {
      hasUnsavedChanges = true;
      modal.setAttribute("data-unsaved", "1");
      updateHighlight();
    });

    textarea.addEventListener("scroll", () => {
      highlight.scrollTop = textarea.scrollTop;
      highlight.scrollLeft = textarea.scrollLeft;
    });

    form.addEventListener("submit", () => {
      hasUnsavedChanges = false;
      modal.setAttribute("data-unsaved", "0");
      window.removeEventListener("beforeunload", handleBeforeUnload);
    });

    closeTriggers.forEach((trigger) => {
      trigger.addEventListener("click", requestClose);
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        requestClose();
      }
    });

    window.addEventListener("beforeunload", handleBeforeUnload);
    updateHighlight();
    textarea.focus();
  };

  document.addEventListener("DOMContentLoaded", () => {
    initLucideIcons();
    syncRanges();
    initBulkTool();
    initConfigImport();
    initToasts();
    initAutoSaveForms();
    initFilesEditorModal();
  });
})();
