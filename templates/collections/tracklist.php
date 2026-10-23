<?php
use App\Collections\Tracklist;

/** @var array $tracks @var string|null $fetchedAt */
$count = count(array_filter($tracks, static fn($t) => $t['kind'] === 'track'));
$total = Tracklist::totalSeconds($tracks);
?>
<div class="section-head">
    <h2>Alinhamento</h2>
    <span class="muted small">
        <?= num($count) ?> <?= $count === 1 ? 'faixa' : 'faixas' ?><?= $total ? ' · ' . Tracklist::formatSeconds($total) : '' ?>
    </span>
</div>
<?php if (!$tracks): ?>
    <p class="muted">O Discogs não tem alinhamento para esta edição.</p>
<?php else: ?>
    <ol class="tracklist">
        <?php foreach ($tracks as $t): ?>
            <?php if ($t['kind'] === 'heading'): ?>
                <li class="track-heading"><?= h($t['title']) ?></li>
            <?php else: ?>
                <li class="track<?= $t['kind'] === 'sub' ? ' track-sub' : '' ?>">
                    <span class="track-pos"><?= h($t['position']) ?></span>
                    <span class="track-title">
                        <?= h($t['title']) ?>
                        <?php if ($t['artists'] !== ''): ?><span class="track-artists"><?= h($t['artists']) ?></span><?php endif; ?>
                    </span>
                    <span class="track-time"><?= h($t['duration']) ?></span>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
<?php if (!empty($fetchedAt)): ?>
    <p class="muted small">Alinhamento do Discogs, obtido <?= h(ago($fetchedAt)) ?>.</p>
<?php endif; ?>
