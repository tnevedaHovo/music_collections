<?php
use App\Concerts\ConcertRepository;

/** @var array $c */
$t = strtotime($c['date']);
$cancelled = in_array($c['event_status'], ['cancelled', 'postponed'], true);
$place = implode(', ', array_filter([$c['venue'], $c['city'], $c['country']]));
$showTitle = $c['title'] && mb_strtolower($c['title']) !== mb_strtolower($c['artist']);
?>
<li class="gig<?= $cancelled ? ' gig-off' : '' ?><?= $c['my_status'] ? ' gig-mine' : '' ?>">
    <time class="gig-date" datetime="<?= h($c['date']) ?>">
        <span class="gig-wd"><?= h(weekday_pt($c['date'])) ?></span>
        <span class="gig-day"><?= date('j', $t) ?></span>
        <span class="gig-mon"><?= h(month_pt((int) date('n', $t), true)) ?><?= date('Y', $t) !== date('Y') ? ' ' . date('y', $t) : '' ?></span>
    </time>
    <div class="gig-body">
        <div class="gig-artist">
            <?php if ($c['artist_id']): ?>
                <a href="<?= url('collections/artist', ['id' => $c['artist_id']]) ?>"><?= h($c['artist']) ?></a>
                <span class="chip chip-tiny" title="Artista da tua coleção">coleção</span>
            <?php else: ?>
                <?= h($c['artist']) ?>
            <?php endif; ?>
        </div>
        <?php if ($showTitle): ?><div class="gig-title"><?= h($c['title']) ?></div><?php endif; ?>
        <div class="gig-meta">
            <?= $c['time'] ? h($c['time']) . ' · ' : '' ?><?= h($place ?: 'Local por anunciar') ?>
            <?= $c['price'] ? ' · ' . h($c['price']) : '' ?>
            <?php if ($c['genre']): ?><span class="chip chip-muted chip-tiny"><?= h($c['genre']) ?></span><?php endif; ?>
        </div>
        <?php if ($c['notes']): ?><div class="gig-notes"><?= h($c['notes']) ?></div><?php endif; ?>
    </div>
    <div class="gig-side">
        <?php if ($c['event_status'] && $c['event_status'] !== 'onsale'): ?>
            <span class="tag tag-<?= h($c['event_status']) ?>"><?= h(ConcertRepository::EVENT_STATUSES[$c['event_status']] ?? $c['event_status']) ?></span>
        <?php endif; ?>
        <select class="gig-status" data-concert-status="<?= (int) $c['id'] ?>" aria-label="A minha marcação">
            <option value="">—</option>
            <?php foreach (ConcertRepository::MY_STATUSES as $v => $label): ?>
                <option value="<?= $v ?>" <?= $c['my_status'] === $v ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="gig-links">
            <?php if ($c['url']): ?>
                <a href="<?= h($c['url']) ?>" target="_blank" rel="noopener"><?= $c['source'] === 'manual' ? 'Bilhetes' : 'Bilhetes · ' . h(ConcertRepository::SOURCES[$c['source']] ?? $c['source']) ?></a>
            <?php endif; ?>
            <a href="<?= url('concerts/edit', ['id' => $c['id']]) ?>">Editar</a>
        </div>
    </div>
</li>
