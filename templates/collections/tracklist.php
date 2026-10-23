<?php
use App\Collections\Tracklist;

/** @var array $tracks @var array $videos @var string|null $fetchedAt */
[$tracks, $otherVideos] = Tracklist::attachVideos($tracks, $videos ?? []);
$count = count(array_filter($tracks, static fn($t) => $t['kind'] === 'track'));
$total = Tracklist::totalSeconds($tracks);
$hasYt = (bool) array_filter($tracks, static fn($t) => $t['yt']) || $otherVideos;
$ytIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23 7.2a3 3 0 00-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 001 7.2 31 31 0 00.5 12 31 31 0 001 16.8a3 3 0 002.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 002.1-2.1 31 31 0 00.5-4.8 31 31 0 00-.5-4.8zM9.7 15.1V8.9L15.5 12l-5.8 3.1z"/></svg>';
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
    <div class="listen">
        <button class="btn btn-sm" type="button" data-play-all="deezer" disabled>▶ Excertos (Deezer)</button>
        <?php if ($hasYt): ?>
            <button class="btn btn-sm btn-ghost" type="button" data-play-all="yt"><?= $ytIcon ?> Ouvir no YouTube</button>
        <?php endif; ?>
        <a class="listen-link small" data-deezer-link target="_blank" rel="noopener" hidden>Abrir no Deezer ↗</a>
        <span class="listen-status muted small" aria-live="polite">A procurar no Deezer…</span>
    </div>
    <ol class="tracklist">
        <?php foreach ($tracks as $i => $t): ?>
            <?php if ($t['kind'] === 'heading'): ?>
                <li class="track-heading"><?= h($t['title']) ?></li>
            <?php else: ?>
                <li class="track<?= $t['kind'] === 'sub' ? ' track-sub' : '' ?>" data-index="<?= $i ?>"
                    data-title="<?= h($t['title']) ?>" data-artists="<?= h($t['artists']) ?>"<?= $t['yt'] ? ' data-yt="' . h($t['yt']) . '"' : '' ?>>
                    <span class="track-pos"><?= h($t['position']) ?></span>
                    <button class="track-play" type="button" data-play-track title="Ouvir excerto de 30 s" aria-label="Ouvir excerto de <?= h($t['title']) ?>" hidden>▶</button>
                    <span class="track-title">
                        <?= h($t['title']) ?>
                        <?php if ($t['artists'] !== ''): ?><span class="track-artists"><?= h($t['artists']) ?></span><?php endif; ?>
                    </span>
                    <?php if ($t['yt']): ?>
                        <button class="track-yt" type="button" data-play-yt title="Ouvir completa no YouTube" aria-label="Ouvir <?= h($t['title']) ?> no YouTube"><?= $ytIcon ?></button>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <span class="track-time"><?= h($t['duration']) ?></span>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
    <?php if ($otherVideos): ?>
        <div class="other-videos">
            <span class="muted small">Outros vídeos no Discogs:</span>
            <?php foreach ($otherVideos as $v): ?>
                <button class="chip chip-muted chip-btn" type="button" data-video="<?= h($v['id']) ?>" data-title="<?= h($v['title']) ?>"><?= $ytIcon ?> <?= h($v['title']) ?></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php if (!empty($fetchedAt)): ?>
    <p class="muted small">Alinhamento e vídeos do Discogs, obtidos <?= h(ago($fetchedAt)) ?>. Excertos: Deezer.</p>
<?php endif; ?>
