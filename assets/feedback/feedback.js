(() => {
  const config = window.skmtFeedback;
  if (!config || !config.enabled) {
    return;
  }

  const categories = config.categories || {};

  const state = {
    authenticated: Boolean(config.hasSession),
    tokenFromUrl: String(config.tokenFromUrl || ""),
    requiresPassword: Boolean(config.requiresPassword),
    pickMode: false,
    selectedTarget: null,
    items: [],
    selectedItemId: null,
    deviceMode: config.deviceMode === "mobile" ? "mobile" : "desktop",
  };

  const selectors = {
    root: null,
    panel: null,
    pinLayer: null,
    info: null,
    pickButton: null,
    sendButton: null,
    category: null,
    comment: null,
    desktopButton: null,
    mobileButton: null,
    structureList: null,
    structureDetails: null,
  };

  let highlightBox = null;

  const t = (key, fallback) => config?.strings?.[key] || fallback;

  const createElem = (tag, className, textContent) => {
    const node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (typeof textContent === "string") {
      node.textContent = textContent;
    }
    return node;
  };

  const toast = (message, type = "info") => {
    if (!message) return;

    const stack =
      document.querySelector("[data-skmt-feedback-toast-stack]") ||
      (() => {
        const next = createElem("div", "skmt-feedback-toast-stack");
        next.setAttribute("data-skmt-feedback-toast-stack", "");
        document.body.appendChild(next);
        return next;
      })();

    const toastItem = createElem(
      "div",
      `skmt-feedback-toast skmt-feedback-toast--${type}`,
      message,
    );
    stack.appendChild(toastItem);

    window.setTimeout(() => {
      toastItem.classList.add("is-leaving");
      window.setTimeout(() => {
        toastItem.remove();
        if (!stack.querySelector(".skmt-feedback-toast")) {
          stack.remove();
        }
      }, 200);
    }, 4600);
  };

  const post = (action, payload = {}) => {
    const formData = new FormData();
    formData.append("action", action);

    Object.entries(payload).forEach(([key, value]) => {
      if (value === undefined || value === null) return;
      formData.append(key, String(value));
    });

    return fetch(config.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      body: formData,
    })
      .then((response) => response.json())
      .then((json) => {
        if (!json || !json.success) {
          throw new Error(json?.data?.message || t("loadError", "Erreur"));
        }
        return json.data || {};
      });
  };

  const getDocSize = () => {
    const body = document.body;
    const doc = document.documentElement;

    return {
      width: Math.max(
        body.scrollWidth,
        body.offsetWidth,
        doc.clientWidth,
        doc.scrollWidth,
        doc.offsetWidth,
      ),
      height: Math.max(
        body.scrollHeight,
        body.offsetHeight,
        doc.clientHeight,
        doc.scrollHeight,
        doc.offsetHeight,
      ),
    };
  };

  const formatCategory = (value) => {
    return categories?.[value] || categories?.[""] || "Sans categorie";
  };

  const getElementSelector = (element) => {
    if (!element || !element.tagName) return "";
    if (element.id) {
      return `#${CSS.escape(element.id)}`;
    }

    const parts = [];
    let current = element;

    while (
      current &&
      current.nodeType === Node.ELEMENT_NODE &&
      parts.length < 5
    ) {
      let selector = current.tagName.toLowerCase();

      if (current.classList && current.classList.length) {
        selector += `.${Array.from(current.classList)
          .slice(0, 2)
          .map((c) => CSS.escape(c))
          .join(".")}`;
      }

      const parent = current.parentElement;
      if (parent) {
        const siblings = Array.from(parent.children).filter(
          (node) => node.tagName === current.tagName,
        );
        if (siblings.length > 1) {
          selector += `:nth-of-type(${siblings.indexOf(current) + 1})`;
        }
      }

      parts.unshift(selector);
      current = parent;
    }

    return parts.join(" > ");
  };

  const setDeviceButtonState = () => {
    selectors.desktopButton?.classList.toggle(
      "is-active",
      state.deviceMode === "desktop",
    );
    selectors.mobileButton?.classList.toggle(
      "is-active",
      state.deviceMode === "mobile",
    );
  };

  const switchDeviceMode = (mode) => {
    const nextMode = mode === "mobile" ? "mobile" : "desktop";
    if (nextMode === state.deviceMode) {
      return;
    }

    const url = new URL(window.location.href);
    const queryArg = String(config.deviceQueryArg || "skmt_feedback_device");
    url.searchParams.set(queryArg, nextMode);

    window.location.href = url.toString();
  };

  const getPointCoordinates = (item) => {
    const doc = getDocSize();
    const x = (Number(item.x_percent || 0) / 100) * doc.width;
    const y = (Number(item.y_percent || 0) / 100) * doc.height;

    return {
      x,
      y,
    };
  };

  const goToItem = (itemId) => {
    const selected = state.items.find((item) => item.id === itemId);
    if (!selected) return;

    const { y } = getPointCoordinates(selected);
    const offset = Math.max(0, y - 180);

    window.scrollTo({
      top: offset,
      left: 0,
      behavior: "smooth",
    });

    const pin = selectors.pinLayer?.querySelector(
      `[data-item-id="${CSS.escape(selected.id)}"]`,
    );
    if (pin) {
      pin.classList.add("is-focus");
      window.setTimeout(() => {
        pin.classList.remove("is-focus");
      }, 900);
    }
  };

  const renderStructure = () => {
    if (!selectors.structureList || !selectors.structureDetails) {
      return;
    }

    selectors.structureList.innerHTML = "";

    if (!state.items.length) {
      const empty = createElem(
        "p",
        "skmt-feedback-structure-empty",
        t("emptyStructure", "Aucun point sur cette page."),
      );
      selectors.structureList.appendChild(empty);
      selectors.structureDetails.innerHTML = "";
      return;
    }

    state.items.forEach((item, index) => {
      const row = createElem("button", "skmt-feedback-structure-item");
      row.type = "button";
      row.setAttribute("data-category", String(item.category || "") || "none");
      row.classList.toggle("is-selected", item.id === state.selectedItemId);

      const statusLabel = item.status === "resolved" ? "RESOLU" : "OUVERT";
      const label = `${index + 1}. ${formatCategory(item.category || "")}`;
      const preview = String(item.comment || "").slice(0, 68);

      row.innerHTML =
        `<span class="skmt-feedback-structure-item__title">${label}</span>` +
        `<span class="skmt-feedback-structure-item__meta">${statusLabel}</span>` +
        `<span class="skmt-feedback-structure-item__preview">${preview}</span>`;

      row.addEventListener("click", () => {
        state.selectedItemId = item.id;
        renderStructure();
      });

      selectors.structureList.appendChild(row);
    });

    const selected =
      state.items.find((item) => item.id === state.selectedItemId) ||
      state.items[0];

    if (!selected) {
      selectors.structureDetails.innerHTML = "";
      return;
    }

    state.selectedItemId = selected.id;

    const details = createElem("div", "skmt-feedback-structure-details");
    const title = createElem(
      "h4",
      "",
      t("selectedPointTitle", "Point selectionne"),
    );
    const info = createElem(
      "p",
      "",
      `${formatCategory(selected.category || "")} • ${selected.status === "resolved" ? "RESOLU" : "OUVERT"}`,
    );
    const message = createElem("p", "", String(selected.comment || ""));
    const goto = createElem(
      "button",
      "skmt-feedback-button skmt-feedback-button--primary",
      t("goToPoint", "Aller au point"),
    );

    goto.type = "button";
    goto.addEventListener("click", () => {
      goToItem(selected.id);
    });

    details.appendChild(title);
    details.appendChild(info);
    details.appendChild(message);
    details.appendChild(goto);

    selectors.structureDetails.innerHTML = "";
    selectors.structureDetails.appendChild(details);
  };

  const renderPins = () => {
    if (!selectors.pinLayer) {
      return;
    }

    selectors.pinLayer.innerHTML = "";

    state.items.forEach((item, index) => {
      const pin = createElem("button", "skmt-feedback-pin");
      pin.type = "button";
      pin.textContent = String(index + 1);
      pin.setAttribute("data-item-id", String(item.id || ""));
      pin.setAttribute("data-category", String(item.category || "") || "none");

      const coords = getPointCoordinates(item);
      pin.style.left = `${coords.x}px`;
      pin.style.top = `${coords.y}px`;

      pin.addEventListener("click", (event) => {
        event.preventDefault();
        state.selectedItemId = item.id;
        renderStructure();
        toast(
          `[${formatCategory(item.category || "")}] ${String(item.comment || "")}`,
          item.status === "resolved" ? "success" : "info",
        );
      });

      selectors.pinLayer.appendChild(pin);
    });
  };

  const loadItems = () => {
    return post("skmt_feedback_list", {
      page_url: window.location.href,
    })
      .then((data) => {
        state.items = Array.isArray(data.items) ? data.items : [];
        if (!state.selectedItemId && state.items.length) {
          state.selectedItemId = state.items[0].id;
        }
        renderPins();
        renderStructure();
      })
      .catch((error) => {
        toast(
          error.message ||
            t("loadError", "Impossible de charger les feedbacks."),
          "error",
        );
      });
  };

  const stopPickMode = () => {
    state.pickMode = false;
    document.documentElement.classList.remove("skmt-feedback-pick-mode");
    if (selectors.pickButton) {
      selectors.pickButton.classList.remove("is-active");
      selectors.pickButton.textContent = t(
        "pickElement",
        "Selectionner un element",
      );
    }
    if (highlightBox) {
      highlightBox.style.display = "none";
    }
  };

  const startPickMode = () => {
    state.pickMode = true;
    document.documentElement.classList.add("skmt-feedback-pick-mode");
    selectors.pickButton?.classList.add("is-active");
    selectors.pickButton &&
      (selectors.pickButton.textContent = t(
        "pickHint",
        "Cliquez sur un element",
      ));
  };

  const collectPayload = () => {
    if (!state.selectedTarget) {
      throw new Error(
        t("targetRequired", "Selectionnez un element avant d'envoyer."),
      );
    }

    const comment = selectors.comment?.value
      ? selectors.comment.value.trim()
      : "";

    if (!comment) {
      throw new Error(t("commentRequired", "Merci de saisir un commentaire."));
    }

    return {
      page_url: window.location.href,
      selector: state.selectedTarget.selector,
      x_percent: state.selectedTarget.xPercent,
      y_percent: state.selectedTarget.yPercent,
      comment,
      category: selectors.category?.value || "",
      device_mode: state.deviceMode,
      mobile_width: state.deviceMode === "mobile" ? 390 : 0,
      viewport_width: window.innerWidth,
      viewport_height: window.innerHeight,
    };
  };

  const submitFeedback = () => {
    let payload;

    try {
      payload = collectPayload();
    } catch (error) {
      toast(error.message, "warning");
      return;
    }

    selectors.sendButton && (selectors.sendButton.disabled = true);

    post("skmt_feedback_add", payload)
      .then((data) => {
        if (data.item) {
          state.items.unshift(data.item);
          state.selectedItemId = data.item.id;
        }

        if (selectors.comment) {
          selectors.comment.value = "";
        }

        state.selectedTarget = null;
        selectors.info &&
          (selectors.info.textContent = t("sendSuccess", "Feedback envoye."));

        stopPickMode();
        renderPins();
        renderStructure();
        toast(t("sendSuccess", "Feedback envoye."), "success");
      })
      .catch((error) => {
        toast(error.message || t("loadError", "Erreur"), "error");
      })
      .finally(() => {
        selectors.sendButton && (selectors.sendButton.disabled = false);
      });
  };

  const initPickingEvents = () => {
    if (highlightBox) {
      return;
    }

    highlightBox = createElem("div", "skmt-feedback-highlight");
    document.body.appendChild(highlightBox);

    document.addEventListener(
      "mousemove",
      (event) => {
        if (!state.pickMode) return;
        if (selectors.panel?.contains(event.target)) return;

        const target = event.target;
        if (!(target instanceof HTMLElement)) return;

        const rect = target.getBoundingClientRect();
        highlightBox.style.display = "block";
        highlightBox.style.left = `${rect.left + window.scrollX}px`;
        highlightBox.style.top = `${rect.top + window.scrollY}px`;
        highlightBox.style.width = `${rect.width}px`;
        highlightBox.style.height = `${rect.height}px`;
      },
      true,
    );

    document.addEventListener(
      "click",
      (event) => {
        if (!state.pickMode) return;
        if (selectors.panel?.contains(event.target)) return;

        const target = event.target;
        if (!(target instanceof HTMLElement)) return;

        event.preventDefault();
        event.stopPropagation();

        const doc = getDocSize();
        const xPercent = (event.pageX / doc.width) * 100;
        const yPercent = (event.pageY / doc.height) * 100;

        state.selectedTarget = {
          selector: getElementSelector(target),
          xPercent: Math.min(100, Math.max(0, xPercent)).toFixed(4),
          yPercent: Math.min(100, Math.max(0, yPercent)).toFixed(4),
        };

        selectors.info &&
          (selectors.info.textContent = `${t("selectedElement", "Element selectionne.")} ${state.selectedTarget.selector}`);

        stopPickMode();
      },
      true,
    );
  };

  const renderComposer = () => {
    const section = createElem("section", "skmt-feedback-section");
    const title = createElem("h3", "skmt-feedback-section__title", "Capture");

    selectors.info = createElem(
      "p",
      "skmt-feedback-help",
      t(
        "pickHint",
        "Cliquez sur un element de la page, puis redigez votre remarque.",
      ),
    );

    selectors.pickButton = createElem(
      "button",
      "skmt-feedback-button",
      t("pickElement", "Selectionner un element"),
    );
    selectors.pickButton.type = "button";
    selectors.pickButton.addEventListener("click", () => {
      if (state.pickMode) {
        stopPickMode();
      } else {
        startPickMode();
      }
    });

    const categoryLabel = createElem(
      "label",
      "skmt-feedback-label",
      t("categoryLabel", "Categorie"),
    );

    selectors.category = createElem("select", "skmt-feedback-select");
    Object.entries(categories).forEach(([value, label]) => {
      const option = createElem("option", "", label);
      option.value = value;
      selectors.category.appendChild(option);
    });

    const commentLabel = createElem("label", "skmt-feedback-label", "Comment");
    selectors.comment = createElem("textarea", "skmt-feedback-textarea");
    selectors.comment.placeholder = t(
      "feedbackPlaceholder",
      "Decrivez votre retour (texte, bug responsive, interaction, etc.)",
    );

    selectors.sendButton = createElem(
      "button",
      "skmt-feedback-button skmt-feedback-button--primary",
      t("sendFeedback", "Envoyer la remarque"),
    );
    selectors.sendButton.type = "button";
    selectors.sendButton.addEventListener("click", submitFeedback);

    section.appendChild(title);
    section.appendChild(selectors.info);
    section.appendChild(selectors.pickButton);
    section.appendChild(categoryLabel);
    section.appendChild(selectors.category);
    section.appendChild(commentLabel);
    section.appendChild(selectors.comment);
    section.appendChild(selectors.sendButton);

    return section;
  };

  const renderStructurePanel = () => {
    const section = createElem("section", "skmt-feedback-section");
    const title = createElem(
      "h3",
      "skmt-feedback-section__title",
      t("structureTitle", "Structure"),
    );

    selectors.structureList = createElem("div", "skmt-feedback-structure-list");
    selectors.structureDetails = createElem(
      "div",
      "skmt-feedback-structure-details-wrap",
    );

    section.appendChild(title);
    section.appendChild(selectors.structureList);
    section.appendChild(selectors.structureDetails);

    return section;
  };

  const renderUnlockedPanel = () => {
    const body = createElem("div", "skmt-feedback-panel__body");
    body.appendChild(renderComposer());
    body.appendChild(renderStructurePanel());
    return body;
  };

  const renderLockedPanel = () => {
    const body = createElem("div", "skmt-feedback-panel__body");
    const title = createElem(
      "p",
      "skmt-feedback-help",
      t("lockedTitle", "Lien protege"),
    );

    const form = createElem("form", "skmt-feedback-auth-form");
    const password = createElem("input", "skmt-feedback-input");
    password.type = "password";
    password.placeholder = t("passwordPlaceholder", "Mot de passe");

    const submit = createElem(
      "button",
      "skmt-feedback-button skmt-feedback-button--primary",
      t("unlock", "Deverrouiller"),
    );
    submit.type = "submit";

    form.appendChild(password);
    form.appendChild(submit);

    form.addEventListener("submit", (event) => {
      event.preventDefault();

      const value = password.value || "";
      if (!state.tokenFromUrl || !value) {
        toast(
          t("unlockError", "Mot de passe invalide ou lien expire."),
          "error",
        );
        return;
      }

      submit.disabled = true;

      post("skmt_feedback_auth", {
        token: state.tokenFromUrl,
        password: value,
      })
        .then(() => {
          state.authenticated = true;
          selectors.panel.innerHTML = "";
          mountPanelContents();
          loadItems();
          toast(t("unlockSuccess", "Acces autorise."), "success");
        })
        .catch((error) => {
          toast(
            error.message ||
              t("unlockError", "Mot de passe invalide ou lien expire."),
            "error",
          );
        })
        .finally(() => {
          submit.disabled = false;
        });
    });

    body.appendChild(title);
    body.appendChild(form);

    return body;
  };

  const renderHeader = () => {
    const header = createElem("div", "skmt-feedback-panel__header");
    const title = createElem(
      "span",
      "skmt-feedback-panel__title",
      t("hudTitle", "Feedback"),
    );
    const modes = createElem("div", "skmt-feedback-device-switch");

    selectors.desktopButton = createElem(
      "button",
      "skmt-feedback-device-button",
      "🖥",
    );
    selectors.desktopButton.type = "button";
    selectors.desktopButton.title = t("desktopMode", "Desktop");
    selectors.desktopButton.addEventListener("click", () => {
      switchDeviceMode("desktop");
    });

    selectors.mobileButton = createElem(
      "button",
      "skmt-feedback-device-button",
      "📱",
    );
    selectors.mobileButton.type = "button";
    selectors.mobileButton.title = t("mobileMode", "Mobile");
    selectors.mobileButton.addEventListener("click", () => {
      switchDeviceMode("mobile");
    });

    modes.appendChild(selectors.desktopButton);
    if (config.allowMobileMode) {
      modes.appendChild(selectors.mobileButton);
    }

    header.appendChild(title);
    header.appendChild(modes);

    return header;
  };

  const mountPanelContents = () => {
    selectors.panel.appendChild(renderHeader());
    setDeviceButtonState();

    if (state.requiresPassword && !state.authenticated) {
      selectors.panel.appendChild(renderLockedPanel());
      return;
    }

    selectors.panel.appendChild(renderUnlockedPanel());
  };

  const buildHud = () => {
    selectors.root = createElem("div", "skmt-feedback-root");
    selectors.panel = createElem("div", "skmt-feedback-panel");
    selectors.pinLayer = createElem("div", "skmt-feedback-pin-layer");

    selectors.root.appendChild(selectors.panel);
    document.body.appendChild(selectors.root);
    document.body.appendChild(selectors.pinLayer);

    mountPanelContents();
    initPickingEvents();

    if (!state.requiresPassword || state.authenticated) {
      loadItems();
    }

    window.addEventListener("resize", renderPins);
    window.addEventListener("scroll", renderPins, { passive: true });
  };

  document.addEventListener("DOMContentLoaded", buildHud);
})();
