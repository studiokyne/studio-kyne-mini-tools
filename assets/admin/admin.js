(() => {
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
    let running = false;
    let cursor = 0;
    let processed = 0;
    let total = 0;
    const writeLog = (message) => {
      const p = document.createElement("p");
      p.textContent = message;
      logBox.prepend(p);
    };
    const updateProgress = () => {
      const percent =
        total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
      progressBar.style.width = `${percent}%`;
    };
    const processNextBatch = () => {
      if (!running) return;
      status.textContent =
        skmtAdmin?.i18n?.bulkRunning || "Conversion en cours…";
      const formData = new FormData();
      formData.append("action", "skmt_image_bulk_process");
      formData.append("_ajax_nonce", box.dataset.nonce || "");
      formData.append("cursor", String(cursor));
      formData.append("batch_size", String(box.dataset.batchSize || 10));
      fetch(skmtAdmin.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: formData,
      })
        .then((response) => response.json())
        .then((payload) => {
          if (!payload || !payload.success)
            throw new Error(
              payload?.data?.message ||
                skmtAdmin?.i18n?.bulkError ||
                skmtAdmin?.i18n?.genericError ||
                "Erreur",
            );
          const data = payload.data || {};
          cursor = Number(data.cursor || cursor);
          processed +=
            Number(data.batch_processed || 0) +
            Number(data.batch_skipped || 0) +
            Number(data.batch_failed || 0);
          total = Number(data.total || total);
          updateProgress();
          const batchLogTemplate =
            skmtAdmin?.i18n?.bulkBatchLog ||
            "Lot: %1$d optimisées, %2$d ignorées, %3$d erreurs.";
          writeLog(
            batchLogTemplate
              .replace("%1$d", String(data.batch_processed || 0))
              .replace("%2$d", String(data.batch_skipped || 0))
              .replace("%3$d", String(data.batch_failed || 0)),
          );
          if (data.completed) {
            running = false;
            startButton.disabled = false;
            stopButton.disabled = true;
            status.textContent =
              skmtAdmin?.i18n?.bulkCompleted || "Conversion terminée.";
            progressBar.style.width = "100%";
            return;
          }
          window.setTimeout(processNextBatch, 150);
        })
        .catch((error) => {
          running = false;
          startButton.disabled = false;
          stopButton.disabled = true;
          status.textContent =
            error.message ||
            skmtAdmin?.i18n?.bulkError ||
            skmtAdmin?.i18n?.genericError ||
            "Erreur";
          writeLog(status.textContent);
        });
    };
    startButton?.addEventListener("click", () => {
      running = true;
      cursor = 0;
      processed = 0;
      total = 0;
      startButton.disabled = true;
      stopButton.disabled = false;
      progressBar.style.width = "0%";
      logBox.innerHTML = "";
      writeLog(
        skmtAdmin?.i18n?.bulkStart || "Démarrage de la conversion de masse…",
      );
      processNextBatch();
    });
    stopButton?.addEventListener("click", () => {
      running = false;
      startButton.disabled = false;
      stopButton.disabled = true;
      status.textContent =
        skmtAdmin?.i18n?.bulkPaused || "Conversion mise en pause.";
      writeLog(status.textContent);
    });
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

  document.addEventListener("DOMContentLoaded", () => {
    syncRanges();
    initBulkTool();
    initConfigImport();
  });
})();
