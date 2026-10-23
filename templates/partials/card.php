<?php /** @var array $r @var string $metric ('myplays'|'listeners'|'') */ $metric ??= ''; ?>
<div class="card">
    <a class="card-link" href="<?= url('collections/album', ['id' => $r['instance_id']]) ?>">
        <div class="cover">
            <?php if (!empty($r['cover']) || !empty($r['thumb'])): ?>
                <img src="<?= h($r['cover'] ?: $r['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
            <?php endif; ?>
            <?php if ($metric === 'myplays' && $r['lf_userplaycount'] !== null): ?>
                <span class="badge" title="As tuas audições"><?= num($r['lf_userplaycount']) ?> ▶</span>
            <?php elseif ($metric === 'listeners' && $r['lf_listeners'] !== null): ?>
                <span class="badge" title="Ouvintes no Last.fm"><?= short_num($r['lf_listeners']) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-title" title="<?= h($r['title']) ?>"><?= h($r['title']) ?></div>
        <div class="card-sub" title="<?= h($r['artist']) ?>"><?= h($r['artist']) ?></div>
        <div class="card-meta"><?= $r['year'] ? h($r['year']) . ' · ' : '' ?><?= h($r['format_name'] ?? '') ?></div>
    </a>
    <?php if ((string) ($r['deezer_id'] ?? '') !== '0'): // 0 = já se sabe que não está no Deezer ?>
        <button class="card-play" type="button" data-play-album="<?= (int) $r['instance_id'] ?>" title="Ouvir excertos (Deezer)" aria-label="Ouvir excertos de <?= h($r['title']) ?>">▶</button>
    <?php endif; ?>
</div>
