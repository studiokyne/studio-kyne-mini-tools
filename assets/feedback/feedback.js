(() => {
  const config = window.skmtFeedback;
  if (!config || !config.enabled) {
    return;
  }

  const categories = config.categories || {};

  const state = {
    authenticated: Boolean(config.hasSession),
    tokenFromUrl: String(config.tokenFromUrl || ""),
    mobileMode: false,
    mobileWidth: 390,
    pickMode: false,
    selectedTarget: null,
    items: [],
  };

  const selectors = {
    root: null,
    panel: null,
    info: null,
    authForm: null,
    password: null,
    pickButton: null,
    sendButton: null,
    comment: null,
    category: null,
    mobileToggle: null,
    mobileWidth: null,
    mobileFrame: null,
    pinLayer: null,
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
        const s = createElem("div", "skmt-feedback-toast-stack");
        s.setAttribute("data-skmt-feedback-toast-stack", "");
        document.body.appendChild(s);
        return s;
      })();

    const item = createElem(
      "div",
      `skmt-feedback-toast skmt-feedback-toast--${type}`,
    );
    item.textContent = message;
    stack.appendChild(item);

    window.setTimeout(() => {
      item.classList.add("is-leaving");
      window.setTimeout(() => {
        item.remove();
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
    return categories?.[value] || categories?.[""] || "No category";
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
          const index = siblings.indexOf(current) + 1;
          selector += `:nth-of-type(${index})`;
        }
      }

      parts.unshift(selector);
      current = parent;
    }

    return parts.join(" > ");
  };

  const renderPins = () => {
    if (!selectors.pinLayer) return;
    selectors.pinLayer.innerHTML = "";

    const docSize = getDocSize();

    state.items.forEach((item, index) => {
      const x = (Number(item.x_percent || 0) / 100) * docSize.width;
      const y = (Number(item.y_percent || 0) / 100) * docSize.height;
      const pin = createElem("button", "skmt-feedback-pin");
      pin.type = "button";
      pin.style.left = `${x}px`;
      pin.style.top = `${y}px`;
      pin.textContent = String(index + 1);

      const category = String(item.category || "");
      pin.setAttribute("data-category", category || "none");
      pin.setAttribute(
        "title",
        `[${formatCategory(category)}] ${item.comment || ""}`,
      );

      pin.addEventListener("click", (event) => {
        event.preventDefault();
        const status = item.status === "resolved" ? "Resolved" : "Open";
        toast(
          `${formatCategory(category)} • ${status}\n${item.comment || ""}`,
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
        renderPins();
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

  const updateMobileFrame = () => {
    if (!selectors.mobileFrame) return;
    if (!state.mobileMode) {
      selectors.mobileFrame.style.display = "none";
      return;
    }

    selectors.mobileFrame.style.display = "block";
    selectors.mobileFrame.style.width = `${state.mobileWidth}px`;
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
      device_mode: state.mobileMode ? "mobile" : "desktop",
      mobile_width: state.mobileMode ? state.mobileWidth : 0,
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
          renderPins();
        }

        if (selectors.comment) {
          selectors.comment.value = "";
        }
        state.selectedTarget = null;
        selectors.info &&
          (selectors.info.textContent = t("sendSuccess", "Feedback envoye."));
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
    if (highlightBox) return;

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

  const renderAuthenticatedPanel = () => {
    const body = createElem("div", "skmt-feedback-panel__body");

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

    body.appendChild(selectors.info);
    body.appendChild(selectors.pickButton);
    body.appendChild(categoryLabel);
    body.appendChild(selectors.category);
    body.appendChild(commentLabel);
    body.appendChild(selectors.comment);

    if (config.allowMobileMode) {
      const mobileRow = createElem("div", "skmt-feedback-mobile-row");
      selectors.mobileToggle = createElem(
        "button",
        "skmt-feedback-button",
        t("mobileMode", "Mode mobile"),
      );
      selectors.mobileToggle.type = "button";
      selectors.mobileToggle.addEventListener("click", () => {
        state.mobileMode = !state.mobileMode;
        selectors.mobileToggle.classList.toggle("is-active", state.mobileMode);
        selectors.mobileToggle.textContent = state.mobileMode
          ? `${t("mobileMode", "Mode mobile")}: ON`
          : `${t("mobileMode", "Mode mobile")}: OFF`;
        updateMobileFrame();
      });

      selectors.mobileWidth = createElem("select", "skmt-feedback-select");
      [360, 375, 390, 414, 428].forEach((width) => {
        const option = createElem("option", "", `${width}px`);
        option.value = String(width);
        if (width === state.mobileWidth) {
          option.selected = true;
        }
        selectors.mobileWidth.appendChild(option);
      });
      selectors.mobileWidth.addEventListener("change", () => {
        state.mobileWidth = Number(selectors.mobileWidth.value || 390);
        updateMobileFrame();
      });

      mobileRow.appendChild(selectors.mobileToggle);
      mobileRow.appendChild(selectors.mobileWidth);
      body.appendChild(mobileRow);
    }

    body.appendChild(selectors.sendButton);

    return body;
  };

  const renderLockedPanel = () => {
    const body = createElem("div", "skmt-feedback-panel__body");
    const title = createElem(
      "p",
      "skmt-feedback-help",
      t("lockedTitle", "Lien protege"),
    );

    selectors.authForm = createElem("form", "skmt-feedback-auth-form");
    selectors.password = createElem("input", "skmt-feedback-input");
    selectors.password.type = "password";
    selectors.password.placeholder = t("passwordPlaceholder", "Mot de passe");

    const submit = createElem(
      "button",
      "skmt-feedback-button skmt-feedback-button--primary",
      t("unlock", "Deverrouiller"),
    );
    submit.type = "submit";

    selectors.authForm.appendChild(selectors.password);
    selectors.authForm.appendChild(submit);

    selectors.authForm.addEventListener("submit", (event) => {
      event.preventDefault();

      const password = selectors.password?.value || "";
      if (!password || !state.tokenFromUrl) {
        toast(
          t("unlockError", "Mot de passe invalide ou lien expire."),
          "error",
        );
        return;
      }

      submit.disabled = true;

      post("skmt_feedback_auth", {
        token: state.tokenFromUrl,
        password,
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
    body.appendChild(selectors.authForm);

    return body;
  };

  const mountPanelContents = () => {
    const header = createElem("div", "skmt-feedback-panel__header");
    header.textContent = t("hudTitle", "Feedback");

    selectors.panel.appendChild(header);
    selectors.panel.appendChild(
      state.authenticated ? renderAuthenticatedPanel() : renderLockedPanel(),
    );
  };

  const buildHud = () => {
    selectors.root = createElem("div", "skmt-feedback-root");
    selectors.panel = createElem("div", "skmt-feedback-panel");
    selectors.pinLayer = createElem("div", "skmt-feedback-pin-layer");
    selectors.mobileFrame = createElem("div", "skmt-feedback-mobile-frame");

    selectors.root.appendChild(selectors.panel);
    document.body.appendChild(selectors.root);
    document.body.appendChild(selectors.pinLayer);
    document.body.appendChild(selectors.mobileFrame);

    mountPanelContents();
    initPickingEvents();
    updateMobileFrame();

    if (state.authenticated) {
      loadItems();
    }

    window.addEventListener("resize", renderPins);
    window.addEventListener("scroll", renderPins, { passive: true });
  };

  document.addEventListener("DOMContentLoaded", buildHud);
})();
