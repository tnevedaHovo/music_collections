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

  // ── Leitor: excertos do Deezer (<audio>) e faixas completas do YouTube ──
  // Cada item da fila: { kind: 'audio'|'yt', src, title, sub, cover, album, index }
  const player = (() => {
    const root = document.querySelector('[data-player]');
    if (!root) return null;
    const $ = (s) => root.querySelector(s);
    const audio = root.querySelector('audio');
    const videoBox = $('.player-video');
    const seek = $('[data-player-seek]');
    let queue = [], pos = -1, yt = null, ytLoading = null, ytTimer = null;

    const clock = (s) => {
      s = Math.max(0, Math.floor(s || 0));
      return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    };
    const current = () => queue[pos];
    const isYt = () => current()?.kind === 'yt';
    const playing = () => (isYt() ? yt?.getPlayerState?.() === 1 : !audio.paused);

    function render(time, duration) {
      $('[data-player-time]').textContent = clock(time);
      $('[data-player-duration]').textContent = clock(duration);
      if (document.activeElement !== seek) seek.value = duration ? Math.round((time / duration) * 1000) : 0;
    }
    function updateToggle() {
      const btn = $('[data-player-toggle]');
      btn.textContent = playing() ? '⏸' : '▶';
      btn.title = playing() ? 'Pausa' : 'Reproduzir';
    }
    // Marca a faixa e o disco que estão a tocar (no alinhamento e nas capas).
    function highlight() {
      document.querySelectorAll('.track.playing, .card.playing').forEach((el) => el.classList.remove('playing'));
      const it = current();
      if (!it) return;
      document.querySelectorAll(`[data-play-album="${it.album}"]`).forEach((b) => b.closest('.card')?.classList.add('playing'));
      const section = document.querySelector(`[data-tracklist="${it.album}"]`);
      if (section && it.index !== null) section.querySelector(`.track[data-index="${it.index}"]`)?.classList.add('playing');
    }

    // A API do YouTube só é carregada na primeira vez que é precisa.
    function youtube() {
      ytLoading ??= new Promise((resolve) => {
        window.onYouTubeIframeAPIReady = () => {
          yt = new YT.Player('yt-player', {
            width: 256, height: 144,
            host: 'https://www.youtube-nocookie.com',
            playerVars: { autoplay: 1, playsinline: 1, rel: 0 },
            events: {
              onReady: () => resolve(yt),
              onStateChange: (e) => { if (e.data === YT.PlayerState.ENDED) next(); updateToggle(); },
              // Vídeo removido ou sem autorização para ser embutido: passa ao seguinte.
              onError: () => { $('.player-sub').textContent = 'Vídeo indisponível no YouTube.'; setTimeout(next, 1500); },
            },
          });
        };
        const s = document.createElement('script');
        s.src = 'https://www.youtube.com/iframe_api';
        document.head.append(s);
      });
      return ytLoading;
    }

    async function playAt(i) {
      if (i < 0 || i >= queue.length) return;
      pos = i;
      const it = current();
      root.hidden = false;
      document.body.classList.add('has-player');
      $('.player-title').textContent = it.title;
      $('.player-sub').textContent = it.sub;
      const cover = $('.player-cover');
      cover.hidden = !it.cover;
      if (it.cover) cover.src = it.cover;
      $('[data-player-source]').textContent = it.kind === 'yt' ? 'YouTube' : 'Excerto 30 s · Deezer';
      render(0, it.kind === 'yt' ? 0 : 30);
      highlight();

      clearInterval(ytTimer);
      if (it.kind === 'yt') {
        audio.pause();
        videoBox.hidden = false;
        root.classList.add('is-video');
        const p = await youtube();
        if (current() !== it) return; // entretanto mudou de faixa
        p.loadVideoById(it.src);
        ytTimer = setInterval(() => { if (yt.getDuration) render(yt.getCurrentTime(), yt.getDuration()); }, 500);
      } else {
        yt?.pauseVideo?.();
        videoBox.hidden = true;
        root.classList.remove('is-video');
        audio.src = it.src;
        audio.play().catch(() => updateToggle());
      }
      updateToggle();
    }
    function next() {
      if (pos < queue.length - 1) playAt(pos + 1);
      else updateToggle();
    }
    function prev() {
      const t = isYt() ? yt?.getCurrentTime?.() ?? 0 : audio.currentTime;
      if (t > 3 || pos === 0) {
        if (isYt()) yt.seekTo(0, true); else audio.currentTime = 0;
      } else {
        playAt(pos - 1);
      }
    }
    function close() {
      audio.pause();
      yt?.stopVideo?.();
      clearInterval(ytTimer);
      queue = [];
      pos = -1;
      root.hidden = true;
      document.body.classList.remove('has-player');
      highlight();
    }

    audio.addEventListener('timeupdate', () => render(audio.currentTime, audio.duration || 30));
    audio.addEventListener('play', updateToggle);
    audio.addEventListener('pause', updateToggle);
    audio.addEventListener('ended', next);
    audio.addEventListener('error', () => { if (audio.src && !isYt()) setTimeout(next, 1000); });
    $('[data-player-next]').addEventListener('click', next);
    $('[data-player-prev]').addEventListener('click', prev);
    $('[data-player-close]').addEventListener('click', close);
    $('[data-player-toggle]').addEventListener('click', () => {
      if (isYt()) playing() ? yt.pauseVideo() : yt.playVideo();
      else audio.paused ? audio.play() : audio.pause();
      setTimeout(updateToggle, 150);
    });
    seek.addEventListener('input', () => {
      const f = seek.value / 1000;
      if (isYt()) yt?.seekTo?.(f * yt.getDuration(), true);
      else if (audio.duration) audio.currentTime = f * audio.duration;
    });

    return { play: (items, start = 0) => { queue = items; playAt(start); }, highlight };
  })();

  // Fila de excertos a partir da resposta de album_audio.
  const deezerQueue = (r, albumId) => r.tracks.map((t) => ({
    kind: 'audio', src: t.preview, title: t.title,
    sub: `${r.album.artist} — ${r.album.title}`, cover: r.album.cover, album: String(albumId), index: t.index,
  }));

  // ▶ nas capas das grelhas: toca os excertos do disco sem sair da página.
  document.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('[data-play-album]');
    if (!btn || !player) return;
    ev.preventDefault();
    btn.classList.add('loading');
    try {
      const r = await api('album_audio', { id: btn.dataset.playAlbum });
      if (!r.found || !r.tracks.length) {
        btn.classList.add('card-play-none');
        btn.title = 'Este disco não foi encontrado no Deezer';
        btn.textContent = '–';
        return;
      }
      player.play(deezerQueue(r, btn.dataset.playAlbum));
    } catch (e) {
      alert(e.message);
    } finally {
      btn.classList.remove('loading');
    }
  });

  // ── Alinhamento do disco: pedido ao Discogs se ainda não estiver em cache ──
  const tracklist = document.querySelector('[data-tracklist]');
  let albumAudio = null;

  async function loadTracklist() {
    const status = tracklist.querySelector('.tracklist-status');
    try {
      const r = await api('tracklist', { id: tracklist.dataset.tracklist });
      tracklist.innerHTML = r.html;
      tracklist.dataset.loaded = '1';
      setupListening();
    } catch (e) {
      if (status) {
        status.textContent = 'Não foi possível obter o alinhamento: ' + e.message;
        status.classList.add('error');
      } else {
        alert(e.message);
      }
    }
  }

  // Procura o disco no Deezer e mostra ▶ nas faixas que têm excerto.
  async function setupListening(retry = false) {
    const status = tracklist.querySelector('.listen-status');
    if (!status) return; // sem alinhamento
    albumAudio = null;
    try {
      const r = await api('album_audio', { id: tracklist.dataset.tracklist, ...(retry ? { retry: 1 } : {}) });
      if (!r.found || !r.tracks.length) {
        status.innerHTML = 'Não encontrado no Deezer. <button type="button" class="link-btn" data-deezer-retry>Procurar de novo</button>';
        return;
      }
      albumAudio = r;
      r.tracks.forEach((t, n) => {
        if (t.index === null) return;
        const row = tracklist.querySelector(`.track[data-index="${t.index}"]`);
        if (!row) return;
        row.dataset.deezer = n;
        row.querySelector('[data-play-track]').hidden = false;
      });
      const matched = r.tracks.filter((t) => t.index !== null).length;
      status.textContent = matched === r.tracks.length ? '' : `${matched} de ${r.tracks.length} faixas do Deezer associadas ao alinhamento.`;
      tracklist.querySelector('[data-play-all="deezer"]').disabled = false;
      const link = tracklist.querySelector('[data-deezer-link]');
      link.href = r.link;
      link.hidden = false;
      player?.highlight();
    } catch (e) {
      status.textContent = 'Deezer indisponível: ' + e.message;
    }
  }

  const ytQueue = () => {
    const sub = `${tracklist.dataset.albumArtist} — ${tracklist.dataset.albumTitle}`;
    return [...tracklist.querySelectorAll('.track[data-yt]')].map((row) => ({
      kind: 'yt', src: row.dataset.yt, title: row.dataset.title, sub,
      cover: tracklist.dataset.cover, album: tracklist.dataset.tracklist, index: Number(row.dataset.index),
    }));
  };

  if (tracklist) {
    if (tracklist.dataset.loaded === '0') loadTracklist();
    else setupListening();

    // Delegação: o conteúdo do alinhamento é substituído quando se atualiza.
    tracklist.addEventListener('click', (ev) => {
      if (!player) return;
      const id = tracklist.dataset.tracklist;
      const row = ev.target.closest('.track');
      if (ev.target.closest('[data-deezer-retry]')) {
        tracklist.querySelector('.listen-status').textContent = 'A procurar no Deezer…';
        setupListening(true);
      } else if (ev.target.closest('[data-play-all="deezer"]') && albumAudio) {
        player.play(deezerQueue(albumAudio, id));
      } else if (ev.target.closest('[data-play-all="yt"]')) {
        const q = ytQueue();
        if (q.length) player.play(q);
        else tracklist.querySelector('[data-video]')?.click();
      } else if (ev.target.closest('[data-play-track]') && row && albumAudio) {
        player.play(deezerQueue(albumAudio, id), Number(row.dataset.deezer));
      } else if (ev.target.closest('[data-play-yt]') && row) {
        const q = ytQueue();
        player.play(q, q.findIndex((it) => it.index === Number(row.dataset.index)));
      } else if (ev.target.closest('[data-video]')) {
        const b = ev.target.closest('[data-video]');
        player.play([{ kind: 'yt', src: b.dataset.video, title: b.dataset.title, sub: tracklist.dataset.albumArtist,
          cover: tracklist.dataset.cover, album: id, index: null }]);
      }
    });
  }
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
      const gig = sel.closest('.gig, .gig-card');
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
  const concertButtons = () => document.querySelectorAll('[data-concerts], [data-area] button, [data-bol]');
  // Ao terminar, cada botão volta ao estado anterior (os da Ticketmaster podem estar desativados por falta de chave).
  const setBusy = (on) => concertButtons().forEach((b) => {
    if (on) {
      b.dataset.wasDisabled = b.disabled ? '1' : '';
      b.disabled = true;
    } else {
      b.disabled = b.dataset.wasDisabled === '1';
    }
  });

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

  document.querySelector('[data-bol]')?.addEventListener('click', async () => {
    const p = panel('sync-bol');
    const only = document.querySelector('[data-bol-only]').checked;
    setBusy(true);
    p.progress(null);
    p.status('A ler a agenda de música da BOL (pode demorar uns segundos)…');
    try {
      const r = await api('concerts_bol', only ? { only: 1 } : {});
      p.status(`Concluído: ${fmt.format(r.total)} eventos na BOL, ${fmt.format(r.collection)} de artistas da tua coleção`
        + (only ? '' : ` · ${fmt.format(r.imported)} importados`) + `. ${fmt.format(r.counts.concerts)} concertos por vir na agenda.`);
      renderConcertCounts(r.counts);
    } catch (e) {
      p.status(e.message, true);
    } finally {
      setBusy(false);
    }
  });

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
