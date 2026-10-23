(() => {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const fmt = new Intl.NumberFormat('pt-PT');

  async function api(action, params = {}) {
    const qs = new URLSearchParams({ action, ...params });
    const res = await fetch('api.php?' + qs, { method: 'POST', headers: { 'X-CSRF-Token': csrf } });
    let data;
    try { data = await res.json(); } catch { data = { error: 'Resposta inválida do servidor (HTTP ' + res.status + ').' }; }
    if (!res.ok || data.error) throw new Error(data.error || 'Erro HTTP ' + res.status);
    return data;
  }

  // Filtros: as listas aplicam-se logo ao mudar.
  document.querySelectorAll('[data-autosubmit]').forEach((el) =>
    el.addEventListener('change', () => el.form.requestSubmit()));

  // Botões "Atualizar Last.fm" nas páginas de disco e artista.
  document.querySelectorAll('[data-refresh]').forEach((btn) =>
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      btn.textContent = 'A atualizar…';
      try {
        await api(btn.dataset.refresh, { id: btn.dataset.id });
        location.reload();
      } catch (e) {
        alert(e.message);
        btn.disabled = false;
        btn.textContent = 'Atualizar Last.fm';
      }
    }));

  // ── Alinhamento do disco: pedido ao Discogs se ainda não estiver em cache ──
  const tracklist = document.querySelector('[data-tracklist]');
  async function loadTracklist() {
    const status = tracklist.querySelector('.tracklist-status');
    try {
      const r = await api('tracklist', { id: tracklist.dataset.tracklist });
      tracklist.innerHTML = r.html;
      tracklist.dataset.loaded = '1';
    } catch (e) {
      if (status) {
        status.textContent = 'Não foi possível obter o alinhamento: ' + e.message;
        status.classList.add('error');
      } else {
        alert(e.message);
      }
    }
  }
  if (tracklist && tracklist.dataset.loaded === '0') loadTracklist();
  document.querySelector('[data-tracklist-refresh]')?.addEventListener('click', async (ev) => {
    const btn = ev.currentTarget;
    btn.disabled = true;
    btn.textContent = 'A atualizar…';
    await loadTracklist();
    btn.disabled = false;
    btn.textContent = 'Atualizar alinhamento';
  });

  // ── Concertos ───────────────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach((el) =>
    el.addEventListener('click', (ev) => { if (!confirm(el.dataset.confirm)) ev.preventDefault(); }));

  // Novo concerto: preencher o formulário a partir de um link do Songkick / Viral Agenda.
  const importBox = document.querySelector('[data-import-link]');
  if (importBox) {
    const input = importBox.querySelector('input');
    const btn = importBox.querySelector('button');
    const status = importBox.querySelector('.import-status');
    const form = document.querySelector('.concert-form');
    const say = (html, isError = false) => {
      status.innerHTML = html;
      status.className = 'import-status small ' + (isError ? 'error' : 'muted');
    };
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    async function readLink() {
      const url = input.value.trim();
      if (!url) return input.focus();
      btn.disabled = true;
      say('A ler o evento…');
      try {
        const r = await api('concert_from_url', { url });
        for (const [name, value] of Object.entries(r.fields)) {
          const el = form.elements[name];
          if (!el) continue;
          el.value = value ?? '';
          el.classList.toggle('filled', value !== null && value !== '');
        }
        const missing = ['date', 'time', 'venue'].filter((k) => !r.fields[k]);
        say(`✓ Dados lidos do ${esc(r.source)}. Confirma o artista e completa o que faltar`
          + (missing.length ? ` (sem ${missing.map((k) => ({ date: 'data', time: 'hora', venue: 'sala' })[k]).join(', ')})` : '')
          + '.' + (r.duplicate ? ` <strong>Atenção:</strong> este evento já está na agenda — <a href="${esc(r.duplicate)}">abrir</a>.` : ''));
        form.elements.artist.focus();
      } catch (e) {
        say(esc(e.message), true);
      } finally {
        btn.disabled = false;
      }
    }
    btn.addEventListener('click', readLink);
    input.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); readLink(); } });
    // Colar um link lê logo o evento.
    input.addEventListener('paste', () => setTimeout(readLink, 0));
  }

  // Marcação rápida (interessa-me / vou / bilhete) diretamente na agenda.
  document.querySelectorAll('[data-concert-status]').forEach((sel) =>
    sel.addEventListener('change', async () => {
      const gig = sel.closest('.gig');
      try {
        await api('concert_status', { id: sel.dataset.concertStatus, status: sel.value });
        gig?.classList.toggle('gig-mine', sel.value !== '');
      } catch (e) {
        alert(e.message);
      }
    }));

  function renderConcertCounts(counts) {
    document.querySelectorAll('[data-concert-counts] [data-k]').forEach((td) =>
      (td.textContent = fmt.format(counts[td.dataset.k] ?? 0)));
  }
  const concertButtons = () => document.querySelectorAll('[data-concerts], [data-area] button');
  const setBusy = (on) => concertButtons().forEach((b) => (b.disabled = on));

  async function syncConcerts() {
    const p = panel('sync-concerts');
    setBusy(true);
    try {
      let counts = await api('concerts_counts');
      const total = counts.total || 1;
      let found = 0, r;
      do {
        p.progress((total - counts.pending) / total);
        p.status(`A procurar artistas: faltam ${fmt.format(counts.pending)}… (${fmt.format(found)} datas encontradas)`);
        r = await api('concerts_step');
        found += r.found;
        counts = r.counts;
        renderConcertCounts(counts);
      } while (r.processed > 0 && counts.pending > 0);
      p.progress(1);
      p.status(`Concluído: ${fmt.format(found)} datas encontradas · ${fmt.format(counts.concerts)} concertos por vir na agenda.`);
    } catch (e) {
      p.status(e.message, true);
    } finally {
      setBusy(false);
    }
  }
  document.querySelectorAll('[data-concerts]').forEach((btn) =>
    btn.addEventListener('click', async () => {
      const scope = btn.dataset.concerts;
      if (scope === 'all' && !confirm('Voltar a procurar concertos de todos os artistas da coleção?')) return;
      if (scope !== 'step') renderConcertCounts(await api('concerts_reset', { scope }));
      await syncConcerts();
    }));

  document.querySelector('[data-area]')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const form = ev.currentTarget;
    const p = panel('sync-area');
    const country = form.country.value.trim().toUpperCase();
    const city = form.city.value.trim();
    setBusy(true);
    p.progress(0);
    try {
      let page = 0, r;
      do {
        p.status(`A importar página ${page + 1}${r ? ' de ' + r.pages : ''}…`);
        r = await api('concerts_area', { country, city, page });
        p.progress((r.page + 1) / r.pages);
        page++;
      } while (!r.done);
      p.status(`Concluído: ${fmt.format(r.total)} eventos em ${city || country}${r.total > 1000 ? ' (importados os primeiros 1000)' : ''}.`);
      renderConcertCounts(r.counts);
    } catch (e) {
      p.status(e.message, true);
    } finally {
      setBusy(false);
    }
  });

  // ── Página de sincronização ─────────────────────────────────
  const panel = (id) => {
    const root = document.getElementById(id);
    if (!root) return null;
    const progress = root.querySelector('.progress');
    return {
      root,
      status: (msg, isError = false) => {
        const el = root.querySelector('.sync-status');
        el.textContent = msg;
        el.classList.toggle('error', isError);
      },
      progress: (ratio) => {
        progress.hidden = ratio === null;
        if (ratio !== null) progress.firstElementChild.style.width = Math.max(2, ratio * 100) + '%';
      },
      busy: (on) => document.querySelectorAll('[data-sync], [data-reset]').forEach((b) => (b.disabled = on)),
    };
  };

  function renderCounts(counts) {
    for (const kind of ['albums', 'artists']) {
      const row = document.querySelector(`[data-counts="${kind}"]`);
      if (!row || !counts[kind]) continue;
      row.querySelectorAll('[data-k]').forEach((td) => (td.textContent = fmt.format(counts[kind][td.dataset.k] ?? 0)));
    }
  }

  async function syncDiscogs() {
    const p = panel('sync-discogs');
    p.busy(true);
    p.progress(0);
    try {
      let page = 1, r;
      do {
        p.status(`A ler página ${page}${r ? ' de ' + r.pages : ''}…`);
        r = await api('discogs_page', { page });
        p.progress(r.page / r.pages);
        page++;
      } while (!r.done);
      p.status(`Concluído: ${fmt.format(r.items)} discos importados${r.removed ? `, ${r.removed} removidos` : ''}.`);
      return true;
    } catch (e) {
      p.status(e.message, true);
      return false;
    } finally {
      p.busy(false);
    }
  }

  async function syncLastfm() {
    const p = panel('sync-lastfm');
    p.busy(true);
    try {
      p.status('A ler o perfil Last.fm…');
      await api('lastfm_profile');
      let counts = await api('lastfm_counts');
      for (const [kind, label] of [['albums', 'discos'], ['artists', 'artistas']]) {
        const total = counts[kind].total || 1;
        let r;
        do {
          p.progress((total - counts[kind].pending) / total);
          p.status(`A obter ${label}: faltam ${fmt.format(counts[kind].pending)}…`);
          r = await api('lastfm_step', { kind });
          counts = r.counts;
          renderCounts(counts);
        } while (r.processed > 0 && counts[kind].pending > 0);
      }
      p.progress(1);
      p.status('Concluído.');
    } catch (e) {
      p.status(e.message, true);
    } finally {
      p.busy(false);
    }
  }

  document.querySelector('[data-sync="discogs"]')?.addEventListener('click', async () => {
    // Depois de importar, segue logo para o Last.fm se estiver configurado.
    const ok = await syncDiscogs();
    const lf = document.querySelector('[data-sync="lastfm"]');
    if (ok && lf && !lf.hasAttribute('disabled')) {
      renderCounts(await api('lastfm_counts'));
      await syncLastfm();
    }
  });
  document.querySelector('[data-sync="lastfm"]')?.addEventListener('click', syncLastfm);
  document.querySelectorAll('[data-reset]').forEach((btn) =>
    btn.addEventListener('click', async () => {
      if (btn.dataset.reset === 'all' && !confirm('Voltar a obter os dados Last.fm de todos os discos e artistas?')) return;
      renderCounts(await api('lastfm_reset', { scope: btn.dataset.reset }));
      await syncLastfm();
    }));

  // ── Definições: guardar e testar ────────────────────────────
  document.querySelector('[data-test]')?.addEventListener('click', async (ev) => {
    const btn = ev.currentTarget;
    const form = btn.form;
    const list = form.querySelector('.test-results');
    btn.disabled = true;
    list.innerHTML = '<li class="muted">A guardar e a testar…</li>';
    try {
      await fetch(form.action, { method: 'POST', body: new FormData(form) });
      const r = await api('test');
      list.innerHTML = '';
      for (const [name, label] of [['discogs', 'Discogs'], ['lastfm', 'Last.fm'], ['ticketmaster', 'Ticketmaster']]) {
        if (!r[name]) continue;
        const li = document.createElement('li');
        li.className = r[name].ok ? 'ok' : 'error';
        li.textContent = `${r[name].ok ? '✓' : '✕'} ${label}: ${r[name].msg}`;
        list.append(li);
      }
    } catch (e) {
      list.innerHTML = '';
      const li = document.createElement('li');
      li.className = 'error';
      li.textContent = e.message;
      list.append(li);
    } finally {
      btn.disabled = false;
    }
  });
})();
