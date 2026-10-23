<?php
use App\Core\View;

/** @var array $artist @var array $releases @var array $concerts */
$a = $artist;
$tags = json_list($a['lf_tags']);
$mine = array_sum(array_map(static fn($r) => (int) $r['lf_userplaycount'], $releases));
?>
<a class="back" href="<?= url('collections/artists') ?>">‹ Artistas</a>

<header class="artist-head">
    <p class="eyebrow">Artista · <?= num(count($releases)) ?> <?= count($releases) === 1 ? 'disco' : 'discos' ?> na coleção</p>
    <h1><?= h($a['name']) ?></h1>

    <section class="stats">
        <?php if ($a['lf_status'] === 'ok'): ?>
            <div class="stat"><span class="stat-value"><?= num($a['lf_listeners']) ?></span><span class="stat-label">ouvintes</span></div>
            <div class="stat"><span class="stat-value"><?= num($a['lf_playcount']) ?></span><span class="stat-label">reproduções</span></div>
            <?php if ($a['lf_userplaycount'] !== null): ?>
                <div class="stat stat-mine"><span class="stat-value"><?= num($a['lf_userplaycount']) ?></span><span class="stat-label">as tuas audições</span></div>
            <?php endif; ?>
        <?php elseif ($a['lf_status'] === null): ?>
            <p class="muted">Dados do Last.fm ainda não obtidos.</p>
        <?php else: ?>
            <p class="muted">Sem dados do Last.fm para este artista.</p>
        <?php endif; ?>
    </section>

    <?php if ($tags): ?>
        <div class="chips"><?php foreach ($tags as $t): ?><span class="chip chip-muted"><?= h($t) ?></span><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($a['lf_summary']): ?>
        <p class="summary"><?= nl2br(h($a['lf_summary'])) ?></p>
    <?php endif; ?>

    <div class="actions">
        <a class="btn btn-ghost" href="https://www.discogs.com/artist/<?= (int) $a['id'] ?>" target="_blank" rel="noopener">Ver no Discogs</a>
        <?php if ($a['lf_url']): ?><a class="btn btn-ghost" href="<?= h($a['lf_url']) ?>" target="_blank" rel="noopener">Ver no Last.fm</a><?php endif; ?>
        <button class="btn btn-ghost" data-refresh="lastfm_artist" data-id="<?= (int) $a['id'] ?>">Atualizar Last.fm</button>
    </div>
</header>

<section class="section">
    <div class="section-head">
        <h2>Próximos concertos</h2>
        <a href="<?= h(url('concerts/edit', ['artist' => $a['name']])) ?>">+ Adicionar concerto</a>
    </div>
    <?php if ($concerts): ?>
        <ul class="gigs">
            <?php foreach ($concerts as $c): ?><?= View::partial('concerts/row', ['c' => $c]) ?><?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="muted">
            Nenhum concerto na agenda<?= $a['tm_checked_at'] ? ' (Ticketmaster consultada ' . h(ago($a['tm_checked_at'])) . ')' : '' ?>.
        </p>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-head">
        <h2>Na tua coleção</h2>
        <?php if ($a['lf_userplaycount'] !== null): ?><span class="muted"><?= num($mine) ?> audições destes discos</span><?php endif; ?>
    </div>
    <div class="grid">
        <?php foreach ($releases as $r): ?><?= View::partial('partials/card', ['r' => $r, 'metric' => $r['lf_userplaycount'] !== null ? 'myplays' : 'listeners']) ?><?php endforeach; ?>
    </div>
</section>
