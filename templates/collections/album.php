<?php
use App\Core\View;

/** @var array $album @var array $artists @var array $related */
$a = $album;
$genres = json_list($a['genres']);
$styles = json_list($a['styles']);
$tags = json_list($a['lf_tags']);
?>
<a class="back" href="<?= url('collections/albums') ?>">‹ Discos</a>

<article class="detail">
    <div class="detail-cover">
        <?php if ($a['cover'] || $a['thumb']): ?>
            <img src="<?= h($a['cover'] ?: $a['thumb']) ?>" alt="Capa de <?= h($a['title']) ?>" referrerpolicy="no-referrer">
        <?php else: ?>
            <div class="cover cover-empty"></div>
        <?php endif; ?>
    </div>
    <div class="detail-body">
        <p class="eyebrow"><?= h($a['format_name'] ?? 'Disco') ?><?= $a['year'] ? ' · ' . h($a['year']) : '' ?></p>
        <h1><?= h($a['title']) ?></h1>
        <p class="detail-artists">
            <?php foreach ($artists as $i => $ar): ?>
                <?= $i ? ' · ' : '' ?><a href="<?= url('collections/artist', ['id' => $ar['id']]) ?>"><?= h($ar['name']) ?></a>
            <?php endforeach; ?>
            <?php if (!$artists): ?><?= h($a['artist']) ?><?php endif; ?>
        </p>

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
                <p class="muted">
                    <?= $a['lf_status'] === 'notfound'
                        ? 'O Last.fm não encontrou este disco com “' . h($a['main_artist']) . ' — ' . h($a['title']) . '”.'
                        : 'Ocorreu um erro ao consultar o Last.fm.' ?>
                </p>
            <?php endif; ?>
        </section>

        <dl class="facts">
            <?php if ($a['labels']): ?><dt>Editora</dt><dd><?= h($a['labels']) ?><?= $a['catno'] ? ' — ' . h($a['catno']) : '' ?></dd><?php endif; ?>
            <?php if ($a['formats']): ?><dt>Formato</dt><dd><?= h($a['formats']) ?></dd><?php endif; ?>
            <?php if ($genres): ?>
                <dt>Géneros</dt>
                <dd class="chips"><?php foreach ($genres as $g): ?><a class="chip" href="<?= h(url('collections/albums', ['genre' => $g])) ?>"><?= h($g) ?></a><?php endforeach; ?></dd>
            <?php endif; ?>
            <?php if ($styles): ?>
                <dt>Estilos</dt>
                <dd class="chips"><?php foreach ($styles as $s): ?><a class="chip" href="<?= h(url('collections/albums', ['style' => $s])) ?>"><?= h($s) ?></a><?php endforeach; ?></dd>
            <?php endif; ?>
            <?php if ($tags): ?>
                <dt>Tags Last.fm</dt>
                <dd class="chips"><?php foreach ($tags as $t): ?><span class="chip chip-muted"><?= h($t) ?></span><?php endforeach; ?></dd>
            <?php endif; ?>
            <?php if ($a['rating']): ?><dt>Classificação</dt><dd><?= str_repeat('★', (int) $a['rating']) . str_repeat('☆', 5 - (int) $a['rating']) ?></dd><?php endif; ?>
            <dt>Adicionado</dt><dd><?= h(date_pt($a['date_added'])) ?></dd>
        </dl>

        <?php if ($a['lf_summary']): ?>
            <p class="summary"><?= nl2br(h($a['lf_summary'])) ?></p>
        <?php endif; ?>

        <div class="actions">
            <a class="btn btn-ghost" href="https://www.discogs.com/release/<?= (int) $a['release_id'] ?>" target="_blank" rel="noopener">Ver no Discogs</a>
            <?php if ($a['lf_url']): ?>
                <a class="btn btn-ghost" href="<?= h($a['lf_url']) ?>" target="_blank" rel="noopener">Ver no Last.fm</a>
            <?php endif; ?>
            <button class="btn btn-ghost" data-refresh="lastfm_album" data-id="<?= (int) $a['instance_id'] ?>">Atualizar Last.fm</button>
            <button class="btn btn-ghost" data-tracklist-refresh>Atualizar alinhamento</button>
        </div>
        <?php if ($a['lf_updated_at']): ?>
            <p class="muted small">Last.fm atualizado <?= h(ago($a['lf_updated_at'])) ?>.</p>
        <?php endif; ?>

        <?php // Sem cache, o alinhamento é pedido ao Discogs depois de a página abrir (ver app.js). ?>
        <?php // Discos com alinhamento de antes dos vídeos (videos NULL) voltam a ser lidos uma vez. ?>
        <?php $cached = $a['tracklist'] !== null && $a['videos'] !== null; ?>
        <section class="section tracklist-section" data-tracklist="<?= (int) $a['instance_id'] ?>" data-loaded="<?= $cached ? '1' : '0' ?>"
                 data-album-title="<?= h($a['title']) ?>" data-album-artist="<?= h($a['artist']) ?>" data-cover="<?= h($a['thumb'] ?: $a['cover']) ?>">
            <?php if ($cached): ?>
                <?= View::partial('collections/tracklist', ['tracks' => json_list($a['tracklist']), 'videos' => json_list($a['videos']), 'fetchedAt' => $a['tracklist_at']]) ?>
            <?php else: ?>
                <div class="section-head"><h2>Alinhamento</h2></div>
                <p class="muted tracklist-status">A obter o alinhamento do Discogs…</p>
            <?php endif; ?>
        </section>
    </div>
</article>

<?php if ($related): ?>
    <section class="section">
        <div class="section-head"><h2>Também na coleção</h2></div>
        <div class="grid grid-row">
            <?php foreach ($related as $r): ?><?= View::partial('partials/card', ['r' => $r]) ?><?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
