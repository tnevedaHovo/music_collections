<?php
/** @var string $content @var string $title @var string $nav */
$flash = flash();
$syncIcon = 'M12 4V1L8 5l4 4V6a6 6 0 016 6 6 6 0 01-.7 2.8l1.5 1.5A8 8 0 0012 4zm0 14a6 6 0 01-6-6c0-1 .3-2 .7-2.8L5.2 7.7A8 8 0 0012 20v3l4-4-4-4v3z';
$groups = [
    'Coleções' => [
        'overview' => ['Visão geral', url('collections'), 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z'],
        'albums' => ['Discos', url('collections/albums'), 'M12 2a10 10 0 100 20 10 10 0 000-20zm0 14.5a4.5 4.5 0 110-9 4.5 4.5 0 010 9zm0-5.5a1 1 0 100 2 1 1 0 000-2z'],
        'artists' => ['Artistas', url('collections/artists'), 'M12 12a4 4 0 100-8 4 4 0 000 8zm0 2c-2.7 0-8 1.3-8 4v2h16v-2c0-2.7-5.3-4-8-4z'],
        'sync' => ['Sincronizar', url('collections/sync'), $syncIcon],
    ],
    'Concertos' => [
        'concerts' => ['Agenda', url('concerts'), 'M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2zm0 16H5V9h14v11zM7 11h5v5H7z'],
        'concerts-sync' => ['Procurar', url('concerts/sync'), $syncIcon],
    ],
];
?><!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <title><?= h(($title ? $title . ' · ' : '') . APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css?v=<?= APP_VERSION ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><circle cx='12' cy='12' r='11' fill='%23111'/><circle cx='12' cy='12' r='3' fill='%23e8a33d'/></svg>">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="<?= url('collections') ?>">
            <span class="brand-disc" aria-hidden="true"></span>
            <span><?= h(APP_NAME) ?></span>
        </a>
        <nav class="nav">
            <?php foreach ($groups as $group => $items): ?>
                <div class="nav-group"><?= h($group) ?></div>
                <?php foreach ($items as $key => [$label, $href, $icon]): ?>
                    <a href="<?= h($href) ?>" class="<?= $nav === $key ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $icon ?>"/></svg><?= h($label) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <div class="nav-group">Plataforma</div>
            <a href="<?= url('settings') ?>" class="<?= $nav === 'settings' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.4 13a7.6 7.6 0 000-2l2.1-1.6-2-3.5-2.5 1a7.4 7.4 0 00-1.7-1L15 3h-4l-.4 2.7a7.4 7.4 0 00-1.7 1l-2.5-1-2 3.5L6.6 11a7.6 7.6 0 000 2l-2.1 1.6 2 3.5 2.5-1a7.4 7.4 0 001.7 1L11 21h4l.4-2.7a7.4 7.4 0 001.7-1l2.5 1 2-3.5-2.2-1.8zM13 15.5a3.5 3.5 0 110-7 3.5 3.5 0 010 7z"/></svg>Definições
            </a>
        </nav>
        <div class="sidebar-foot">Dados: Discogs · Last.fm · Ticketmaster · Songkick · Viral Agenda</div>
    </aside>
    <main class="main">
        <?php if ($flash): ?>
            <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </main>
</div>

<div class="player" data-player hidden>
    <div class="player-video" hidden><div id="yt-player"></div></div>
    <img class="player-cover" alt="" referrerpolicy="no-referrer">
    <div class="player-info">
        <div class="player-title"></div>
        <div class="player-sub"></div>
    </div>
    <div class="player-controls">
        <button type="button" data-player-prev title="Anterior" aria-label="Faixa anterior">⏮</button>
        <button type="button" data-player-toggle class="player-main" title="Pausa" aria-label="Reproduzir ou pausar">⏸</button>
        <button type="button" data-player-next title="Seguinte" aria-label="Faixa seguinte">⏭</button>
    </div>
    <div class="player-progress">
        <span data-player-time>0:00</span>
        <input type="range" min="0" max="1000" value="0" data-player-seek aria-label="Posição">
        <span data-player-duration>0:30</span>
    </div>
    <span class="player-source" data-player-source></span>
    <button type="button" class="player-close" data-player-close title="Fechar" aria-label="Fechar leitor">✕</button>
    <audio preload="none"></audio>
</div>
<script src="assets/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
