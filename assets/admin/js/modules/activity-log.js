(function () {
  "use strict";

  var state = {
    nonce: '',
    page: 1,
    pages: 1,
    rows: [],
    timer: null,
    request: 0,
  };

  var el = {};

  document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.getElementById('skmt-al');
    if (!wrap) return;

    state.nonce = wrap.dataset.nonce;

    ['search', 'user', 'type', 'from', 'to', 'reset', 'export', 'rows', 'total', 'page', 'prev', 'next'].forEach(function (id) {
      el[id] = document.getElementById('skmt-al-' + id);
    });

    // Entrée dans la recherche soumettrait le formulaire de réglages qui
    // englobe la liste.
    el.search.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); reload(); }
    });
    el.search.addEventListener('input', function () {
      clearTimeout(state.timer);
      state.timer = setTimeout(reload, 300);
    });
    [el.user, el.type, el.from, el.to].forEach(function (input) {
      input.addEventListener('change', reload);
    });

    el.reset.addEventListener('click', function () {
      el.search.value = '';
      el.user.value = '';
      el.type.value = '';
      el.from.value = '';
      el.to.value = '';
      reload();
    });

    el.prev.addEventListener('click', function () { if (state.page > 1) load(state.page - 1); });
    el.next.addEventListener('click', function () { if (state.page < state.pages) load(state.page + 1); });
    el.export.addEventListener('click', exportCsv);

    el.rows.addEventListener('click', function (e) {
      var tr = e.target.closest('tr[data-index]');
      if (tr) openDetail(state.rows[+tr.dataset.index]);
    });
    el.rows.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      var tr = e.target.closest('tr[data-index]');
      if (tr) { e.preventDefault(); openDetail(state.rows[+tr.dataset.index]); }
    });

    load(1);
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

  /** Filtres courants, sous la forme attendue par le serveur. */
  function filters() {
    var type = el.type.value;
    return {
      search: el.search.value.trim(),
      user_id: el.user.value,
      group: type.indexOf('group:') === 0 ? type.slice(6) : '',
      event: type.indexOf('event:') === 0 ? type.slice(6) : '',
      from: el.from.value,
      to: el.to.value,
    };
  }

  function reload() { load(1); }

  function load(page) {
    var data = filters();
    var fd = new FormData();
    var request = ++state.request;

    fd.append('action', 'skmt_activity_log_list');
    fd.append('nonce', state.nonce);
    fd.append('page', page);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });

    setState(t('alLoading', 'Chargement…'));

    fetch(skmtAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        // Une frappe rapide dans la recherche lance plusieurs requêtes : seule
        // la dernière doit s'afficher, même si une plus ancienne répond après.
        if (request !== state.request) return;
        if (!res.success) throw new Error((res.data && res.data.message) || '');
        render(res.data);
      })
      .catch(function (err) {
        if (request !== state.request) return;
        setState(t('alError', 'Impossible de charger le journal.'));
        if (typeof window.skmtShowToast === 'function') {
          window.skmtShowToast((err && err.message) || t('alError', 'Impossible de charger le journal.'), 'error');
        }
      });
  }

  function setState(message) {
    el.rows.innerHTML = '';
    var tr = document.createElement('tr');
    var td = document.createElement('td');
    td.colSpan = 5;
    td.className = 'skmt-al__state';
    td.textContent = message;
    tr.appendChild(td);
    el.rows.appendChild(tr);
  }

  function render(data) {
    state.rows = data.rows;
    state.page = data.page;
    state.pages = data.pages;

    el.total.textContent = format(t('alTotal', '%s événement(s)'), data.total.toLocaleString());
    el.page.textContent = format(t('alPage', 'Page %1$s sur %2$s'), data.page, data.pages);
    el.prev.disabled = data.page <= 1;
    el.next.disabled = data.page >= data.pages;

    if (!data.rows.length) {
      setState(t('alEmpty', 'Aucun événement pour ces critères.'));
      return;
    }

    el.rows.innerHTML = '';
    data.rows.forEach(function (row, index) {
      var tr = document.createElement('tr');
      tr.className = 'skmt-al__row';
      tr.dataset.index = index;
      tr.tabIndex = 0;

      tr.appendChild(cell(row.date, 'skmt-al__date'));

      var user = cell(row.user, 'skmt-al__user');
      if (row.role) {
        var role = document.createElement('span');
        role.className = 'skmt-al__muted';
        role.textContent = row.role;
        user.appendChild(role);
      }
      tr.appendChild(user);

      var event = cell('', 'skmt-al__event');
      var badge = document.createElement('span');
      badge.className = 'skmt-badge skmt-al__badge ' + badgeClass(row);
      badge.textContent = row.group;
      event.appendChild(badge);
      event.appendChild(document.createTextNode(row.event));
      tr.appendChild(event);

      tr.appendChild(cell(row.object, 'skmt-al__object'));
      tr.appendChild(cell(row.ip, 'skmt-al__ip'));

      el.rows.appendChild(tr);
    });
  }

  /** Variante de badge du design system selon la famille ; rouge pour ce qui détruit ou échoue. */
  function badgeClass(row) {
    if (row.event_key === 'login_failed' || /_deleted$/.test(row.event_key)) return 'skmt-badge--danger';
    return {
      auth: 'skmt-badge--info',
      content: 'skmt-badge--success',
      users: 'skmt-badge--warning',
      options: 'skmt-badge--warning',
      settings: 'skmt-badge--info',
    }[row.group_key] || 'skmt-badge--neutral';
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

  function openDetail(row) {
    if (!row) return;

    document.getElementById('skmt-al-detail-title').textContent = row.event + (row.object ? ' — ' + row.object : '');

    var body = document.getElementById('skmt-al-detail-body');
    body.innerHTML = '';

    var lines = [
      [t('alDate', 'Date'), row.date],
      [t('alUser', 'Utilisateur'), row.user],
      [t('alRole', 'Rôle'), row.role],
      [t('alIp', 'Adresse IP'), row.ip],
      [t('alEvent', 'Événement'), row.group + ' — ' + row.event],
      [t('alObject', 'Objet'), row.object],
    ].concat(row.details);

    lines.forEach(function (pair) {
      if (!pair[1]) return;
      var dt = document.createElement('dt');
      var dd = document.createElement('dd');
      dt.textContent = pair[0];
      dd.textContent = pair[1];
      body.appendChild(dt);
      body.appendChild(dd);
    });

    var link = document.getElementById('skmt-al-detail-link');
    if (row.link) {
      link.href = row.link;
      link.hidden = false;
    } else {
      link.removeAttribute('href');
      link.hidden = true;
    }

    window.skmtModalOpen('skmt-al-detail-modal');
  }

  /**
   * Téléchargement par un formulaire POST éphémère : le navigateur gère le
   * fichier renvoyé, ce que fetch() ne sait pas faire sans passer par un Blob
   * gardé entier en mémoire.
   */
  function exportCsv() {
    var form = document.createElement('form');
    var data = filters();

    form.method = 'post';
    form.action = skmtAdmin.alExportUrl;
    form.hidden = true;

    data.action = 'skmt_activity_log_export';
    data.skmt_nonce = skmtAdmin.alExportNonce;

    Object.keys(data).forEach(function (k) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = k;
      input.value = data[k];
      form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    form.remove();
  }
})();
