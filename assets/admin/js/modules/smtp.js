(function () {
  "use strict";

  var state = {
    nonce: '',
    page: 1,
    pages: 1,
    rows: [],
    timer: null,
    request: 0,
    current: null,
    loaded: false,
  };

  var el = {};

  var DEFAULT_PORTS = { tls: 587, ssl: 465, none: 25 };

  document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.getElementById('skmt-sm');
    if (!wrap) return;

    state.nonce = wrap.dataset.nonce;

    ['search', 'status', 'from', 'to', 'reset', 'clear', 'rows', 'total', 'page', 'prev', 'next'].forEach(function (id) {
      el[id] = document.getElementById('skmt-sm-' + id);
    });

    initSettings();
    initTest();

    // Entrée dans un filtre soumettrait le formulaire de réglages qui englobe
    // la liste (même piège que le journal d'activité).
    wrap.querySelector('.skmt-sm__filters').addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); reload(); }
    });
    el.search.addEventListener('input', function () {
      clearTimeout(state.timer);
      state.timer = setTimeout(reload, 300);
    });
    [el.status, el.from, el.to].forEach(function (input) {
      input.addEventListener('change', reload);
    });

    el.reset.addEventListener('click', function () {
      el.search.value = '';
      el.status.value = '';
      el.from.value = '';
      el.to.value = '';
      reload();
    });
    el.clear.addEventListener('click', confirmClear);

    el.prev.addEventListener('click', function () { if (state.page > 1) load(state.page - 1); });
    el.next.addEventListener('click', function () { if (state.page < state.pages) load(state.page + 1); });

    el.rows.addEventListener('click', function (e) {
      var tr = e.target.closest('tr[data-index]');
      if (tr) openDetail(state.rows[+tr.dataset.index]);
    });
    el.rows.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      var tr = e.target.closest('tr[data-index]');
      if (tr) { e.preventDefault(); openDetail(state.rows[+tr.dataset.index]); }
    });

    document.getElementById('skmt-sm-detail-resend').addEventListener('click', resend);

    // Le journal ne se charge qu'à l'ouverture de son onglet. admin.js a
    // déjà restauré l'onglet mémorisé : on regarde s'il est déjà ouvert.
    if (!wrap.closest('[data-skmt-tab-panel]').hidden) load(1);
    document.addEventListener('skmt:tab', function (e) {
      if (e.detail.group === 'smtp' && e.detail.name === 'log' && !state.loaded) load(1);
    });
  });

  function t(key, fallback) {
    return (window.skmtAdmin && skmtAdmin.i18n && skmtAdmin.i18n[key]) || fallback;
  }

  function format(str) {
    var args = Array.prototype.slice.call(arguments, 1);
    var i = 0;
    return str.replace(/%(\d+\$)?s/g, function (m, pos) {
      return pos ? args[parseInt(pos, 10) - 1] : args[i++];
    });
  }

  function toast(message, type) {
    if (typeof window.skmtShowToast === 'function') window.skmtShowToast(message, type);
  }

  function post(action, data) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', state.nonce);
    Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });

    return fetch(skmtAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function (r) { return r.json(); });
  }

  /* ================================================================
   * RÉGLAGES
   * ================================================================ */

  function initSettings() {
    var encryption = document.getElementById('skmt_sm_encryption');
    var port = document.getElementById('skmt_sm_port');
    var autoTls = document.getElementById('skmt-sm-autotls-row');
    var auth = document.getElementById('skmt_sm_auth');
    var credentials = document.getElementById('skmt-sm-credentials');
    var provider = document.getElementById('skmt_sm_provider');
    var hint = document.getElementById('skmt-sm-provider-hint');
    var host = document.getElementById('skmt_sm_host');
    var username = document.getElementById('skmt_sm_username');
    var presets = skmtAdmin.smProviders || {};
    var transport = document.getElementById('skmt_sm_transport');
    var smtpFields = document.getElementById('skmt-sm-smtp-fields');
    var apiFields = document.getElementById('skmt-sm-api-fields');

    // Les champs de l'autre transport sont masqués, pas vidés : ils partent
    // quand même à l'enregistrement, et revenir en arrière ne perd rien.
    transport.addEventListener('change', function () {
      smtpFields.hidden = transport.value !== 'smtp';
      apiFields.hidden = transport.value === 'smtp';
    });

    // Un préréglage remplit hôte, port, chiffrement et, s'il en impose un,
    // l'identifiant (SendGrid : « apikey »). Tout reste modifiable ensuite ;
    // repasser sur « personnalisé » ne vide rien.
    provider.addEventListener('change', function () {
      var preset = presets[provider.value];
      if (!preset) {
        hint.textContent = t('smProviderHint', '');
        return;
      }
      host.value = preset.host;
      encryption.value = preset.encryption;
      port.value = preset.port;
      autoTls.hidden = preset.encryption !== 'none';
      auth.checked = true;
      credentials.hidden = false;
      if (preset.username && !username.disabled) username.value = preset.username;
      hint.textContent = preset.hint;
    });

    // Changer de chiffrement change le port attendu ; un port personnalisé
    // (ni 25, ni 465, ni 587) est laissé tel quel.
    encryption.addEventListener('change', function () {
      var known = Object.keys(DEFAULT_PORTS).some(function (k) { return +port.value === DEFAULT_PORTS[k]; });
      if (known || !port.value) port.value = DEFAULT_PORTS[encryption.value];
      autoTls.hidden = encryption.value !== 'none';
    });

    auth.addEventListener('change', function () {
      credentials.hidden = !auth.checked;
    });
  }

  /* ================================================================
   * MAIL DE TEST
   * ================================================================ */

  function initTest() {
    var to = document.getElementById('skmt-sm-test-to');
    var button = document.getElementById('skmt-sm-test-send');

    to.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); button.click(); }
    });

    button.addEventListener('click', function () {
      var label = button.textContent;
      button.disabled = true;
      button.textContent = t('smTesting', 'Envoi en cours…');

      post('skmt_smtp_test', { to: to.value.trim() })
        .then(function (res) {
          showTestResult(!!res.success, (res.data && res.data.message) || '', (res.data && res.data.transcript) || []);
          if (state.loaded) load(1);
        })
        .catch(function () {
          showTestResult(false, t('smError', 'Erreur'), []);
        })
        .then(function () {
          button.disabled = false;
          button.textContent = label;
        });
    });
  }

  function showTestResult(success, message, transcript) {
    var box = document.getElementById('skmt-sm-test-result');
    var msg = document.getElementById('skmt-sm-test-message');
    var pre = document.getElementById('skmt-sm-test-transcript');

    msg.className = 'skmt-notice ' + (success ? 'skmt-notice--success' : 'skmt-notice--error');
    msg.textContent = message;
    pre.textContent = transcript.join('\n');
    pre.hidden = !transcript.length;
    box.hidden = false;
  }

  /* ================================================================
   * JOURNAL
   * ================================================================ */

  function filters() {
    return {
      search: el.search.value.trim(),
      status: el.status.value,
      from: el.from.value,
      to: el.to.value,
    };
  }

  function reload() { load(1); }

  function load(page) {
    var request = ++state.request;
    state.loaded = true;
    var data = filters();
    data.page = page;

    setState(t('smLoading', 'Chargement…'));

    post('skmt_smtp_log_list', data)
      .then(function (res) {
        // Seule la dernière requête s'affiche : une frappe rapide en lance
        // plusieurs, qui peuvent répondre dans le désordre.
        if (request !== state.request) return;
        if (!res.success) throw new Error((res.data && res.data.message) || '');
        render(res.data);
      })
      .catch(function (err) {
        if (request !== state.request) return;
        setState(t('smError', 'Impossible de charger le journal.'));
        toast((err && err.message) || t('smError', 'Impossible de charger le journal.'), 'error');
      });
  }

  function setState(message) {
    el.rows.innerHTML = '';
    var tr = document.createElement('tr');
    var td = document.createElement('td');
    td.colSpan = 4;
    td.className = 'skmt-sm__state';
    td.textContent = message;
    tr.appendChild(td);
    el.rows.appendChild(tr);
  }

  function render(data) {
    state.rows = data.rows;
    state.page = data.page;
    state.pages = data.pages;

    el.total.textContent = format(t('smTotal', '%s mail(s)'), data.total.toLocaleString());
    el.page.textContent = format(t('smPage', 'Page %1$s sur %2$s'), data.page, data.pages);
    el.prev.disabled = data.page <= 1;
    el.next.disabled = data.page >= data.pages;

    if (!data.rows.length) {
      setState(t('smEmpty', 'Aucun mail pour ces critères.'));
      return;
    }

    el.rows.innerHTML = '';
    data.rows.forEach(function (row, index) {
      var tr = document.createElement('tr');
      tr.className = 'skmt-sm__row';
      tr.dataset.index = index;
      tr.tabIndex = 0;

      tr.appendChild(cell(row.date, 'skmt-sm__date'));

      var status = cell('', 'skmt-sm__status');
      status.appendChild(badge(row));
      tr.appendChild(status);

      tr.appendChild(cell(row.to, 'skmt-sm__to'));

      var subject = cell(row.subject || t('smNoSubject', '(sans objet)'), 'skmt-sm__subject');
      if (row.status === 'failed' && row.error) {
        var error = document.createElement('span');
        error.className = 'skmt-sm__error';
        error.textContent = row.error;
        subject.appendChild(error);
      }
      tr.appendChild(subject);

      el.rows.appendChild(tr);
    });
  }

  function badge(row) {
    var span = document.createElement('span');
    var sent = row.status === 'sent';
    span.className = 'skmt-badge skmt-sm__badge ' + (sent ? 'skmt-badge--success' : 'skmt-badge--danger');
    span.textContent = sent ? t('smSent', 'Envoyé') : t('smFailed', 'Échec');
    return span;
  }

  function cell(text, className) {
    var td = document.createElement('td');
    td.className = className;
    if (text) {
      var span = document.createElement('span');
      span.textContent = text;
      td.appendChild(span);
    }
    return td;
  }

  function confirmClear() {
    window.skmtModal.open({
      title: t('smClearTitle', 'Vider le journal des mails ?'),
      message: t('smClearMessage', 'Tous les mails journalisés seront supprimés définitivement.'),
      confirmLabel: t('smClearConfirm', 'Vider le journal'),
      cancelLabel: t('smCancel', 'Annuler'),
      danger: true,
      onConfirm: function () {
        post('skmt_smtp_log_clear')
          .then(function (res) {
            toast((res.data && res.data.message) || '', res.success ? 'success' : 'error');
            reload();
          });
      },
    });
  }

  /* ================================================================
   * DÉTAIL
   * ================================================================ */

  function openDetail(row) {
    if (!row) return;

    post('skmt_smtp_log_detail', { id: row.id })
      .then(function (res) {
        if (!res.success) throw new Error((res.data && res.data.message) || '');
        fillDetail(res.data);
        window.skmtModalOpen('skmt-sm-detail-modal');
      })
      .catch(function (err) {
        toast((err && err.message) || t('smError', 'Erreur'), 'error');
      });
  }

  function fillDetail(mail) {
    state.current = mail;

    document.getElementById('skmt-sm-detail-title').textContent = mail.subject || t('smNoSubject', '(sans objet)');

    var meta = document.getElementById('skmt-sm-detail-meta');
    meta.innerHTML = '';

    var attachments = mail.attachments.map(function (a) {
      return a.name + (a.exists ? '' : ' (' + t('smMissing', 'introuvable') + ')');
    }).join(', ');

    [
      [t('smDate', 'Date'), mail.date],
      [t('smStatus', 'Statut'), mail.status === 'sent' ? t('smSent', 'Envoyé') : t('smFailed', 'Échec')],
      [t('smFrom', 'Expéditeur'), mail.from],
      [t('smTo', 'Destinataire'), mail.to],
      [t('smTransport', 'Transport'), mail.transport],
      [t('smAttachments', 'Pièces jointes'), attachments],
      [t('smResentOf', 'Renvoi de'), mail.resent_of ? '#' + mail.resent_of : ''],
      [t('smTruncatedLabel', 'Tronqué'), mail.truncated ? t('smTruncated', 'Message trop long, tronqué à l\'enregistrement.') : ''],
    ].forEach(function (pair) {
      if (!pair[1]) return;
      var dt = document.createElement('dt');
      var dd = document.createElement('dd');
      dt.textContent = pair[0];
      dd.textContent = pair[1];
      meta.appendChild(dt);
      meta.appendChild(dd);
    });

    var error = document.getElementById('skmt-sm-detail-error');
    error.textContent = mail.error;
    error.hidden = !mail.error;

    var frame = document.getElementById('skmt-sm-detail-html');
    var text = document.getElementById('skmt-sm-detail-text');
    if (mail.is_html) {
      // srcdoc dans un iframe sandbox="" : le HTML du mail est rendu sans
      // script ni accès à la page d'administration.
      frame.srcdoc = mail.message;
      frame.hidden = false;
      text.hidden = true;
    } else {
      frame.removeAttribute('srcdoc');
      frame.hidden = true;
      text.textContent = mail.message;
      text.hidden = false;
    }

    var headersWrap = document.getElementById('skmt-sm-detail-headers-wrap');
    document.getElementById('skmt-sm-detail-headers').textContent = mail.headers.join('\n');
    headersWrap.hidden = !mail.headers.length;
    headersWrap.open = false;

    document.getElementById('skmt-sm-detail-resend').hidden = !mail.can_resend;
  }

  function resend() {
    var mail = state.current;
    var button = document.getElementById('skmt-sm-detail-resend');
    if (!mail) return;

    var label = button.textContent;
    button.disabled = true;
    button.textContent = t('smResending', 'Renvoi en cours…');

    post('skmt_smtp_log_resend', { id: mail.id })
      .then(function (res) {
        toast((res.data && res.data.message) || '', res.success ? 'success' : 'error');
        if (res.success) window.skmtModalClose('skmt-sm-detail-modal');
        reload();
      })
      .catch(function () {
        toast(t('smError', 'Erreur'), 'error');
      })
      .then(function () {
        button.disabled = false;
        button.textContent = label;
      });
  }
})();
