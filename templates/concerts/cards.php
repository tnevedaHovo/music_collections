<?php
use App\Concerts\ConcertRepository;

/** @var array $rows */
?>
<div class="gig-cards">
    <?php foreach ($rows as $c):
        $t = strtotime($c['date']);
        $off = in_array($c['event_status'], ['cancelled', 'postponed'], true);
        $showTitle = $c['title'] && mb_strtolower($c['title']) !== mb_strtolower($c['artist']);
        $place = implode(', ', array_filter([$c['venue'], $c['city']])); ?>
        <article class="gig-card<?= $off ? ' gig-off' : '' ?><?= $c['my_status'] ? ' gig-mine' : '' ?>">
            <div class="gig-card-media">
                <?php if ($c['image']): ?>
                    <img src="<?= h($c['image']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                <?php else: ?>
                    <span class="gig-card-initial" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($c['artist'], 0, 1))) ?></span>
                <?php endif; ?>
                <time class="gig-card-date" datetime="<?= h($c['date']) ?>">
                    <span class="gig-day"><?= date('j', $t) ?></span>
                    <span class="gig-mon"><?= h(month_pt((int) date('n', $t), true)) ?><?= date('Y', $t) !== date('Y') ? ' ' . date('y', $t) : '' ?></span>
                </time>
                <?php if ($c['event_status'] && $c['event_status'] !== 'onsale'): ?>
                    <span class="tag tag-<?= h($c['event_status']) ?> gig-card-tag"><?= h(ConcertRepository::EVENT_STATUSES[$c['event_status']] ?? $c['event_status']) ?></span>
                <?php endif; ?>
            </div>
            <div class="gig-card-body">
                <div class="gig-artist">
                    <?php if ($c['artist_id']): ?>
                        <a href="<?= url('collections/artist', ['id' => $c['artist_id']]) ?>"><?= h($c['artist']) ?></a>
                    <?php else: ?>
                        <?= h($c['artist']) ?>
                    <?php endif; ?>
                </div>
                <?php if ($showTitle): ?><div class="gig-title" title="<?= h($c['title']) ?>"><?= h($c['title']) ?></div><?php endif; ?>
                <div class="gig-meta">
                    <?= h(weekday_pt($c['date'])) ?><?= $c['time'] ? ' · ' . h($c['time']) : '' ?> · <?= h($place ?: 'Local por anunciar') ?>
                </div>
                <div class="gig-card-tags">
                    <?php if ($c['artist_id']): ?><span class="chip chip-tiny">coleção</span><?php endif; ?>
                    <?php if ($c['genre']): ?><span class="chip chip-muted chip-tiny"><?= h($c['genre']) ?></span><?php endif; ?>
                    <?php if ($c['price']): ?><span class="gig-price"><?= h($c['price']) ?></span><?php endif; ?>
                </div>
            </div>
            <div class="gig-card-foot">
                <select class="gig-status" data-concert-status="<?= (int) $c['id'] ?>" aria-label="A minha marcação">
                    <option value="">—</option>
                    <?php foreach (ConcertRepository::MY_STATUSES as $v => $label): ?>
                        <option value="<?= $v ?>" <?= $c['my_status'] === $v ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="gig-links">
                    <?php if ($c['url']): ?><a href="<?= h($c['url']) ?>" target="_blank" rel="noopener" title="<?= h(ConcertRepository::SOURCES[$c['source']] ?? '') ?>">Bilhetes</a><?php endif; ?>
                    <a href="<?= url('concerts/edit', ['id' => $c['id']]) ?>">Editar</a>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
