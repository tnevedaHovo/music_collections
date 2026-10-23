<?php
use App\Core\View;

/** @var array $totals @var array $discogs @var array $lastfm @var string $lastSync */
$row = static fn(array $items, string $metric = '') => implode('', array_map(
    static fn($r) => View::partial('partials/card', ['r' => $r, 'metric' => $metric]), $items));
$albums = static fn(array $p) => url('collections/albums', $p);
$coverage = $totals['releases'] ? round($totals['lf_ok'] / $totals['releases'] * 100) : 0;
?>
<header class="page-head">
    <div>
        <h1>A tua coleção</h1>
        <p class="muted">
            <?php if ($discogs): ?>
                Discogs <a href="<?= h($discogs['url'] ?? '#') ?>" target="_blank" rel="noopener"><?= h($discogs['username'] ?? '') ?></a>
            <?php endif; ?>
            <?php if (!empty($lastfm['name'])): ?>
                · Last.fm <a href="<?= h($lastfm['url']) ?>" target="_blank" rel="noopener"><?= h($lastfm['name']) ?></a>
                (<?= num($lastfm['playcount']) ?> scrobbles no total)
            <?php endif; ?>
            · Sincronizado <?= h(ago($lastSync)) ?>
        </p>
    </div>
    <a class="btn" href="<?= url('collections/sync') ?>">Sincronizar</a>
</header>

<?php if ($totals['releases'] === 0): ?>
    <div class="empty">
        <h2>Ainda não há discos</h2>
        <p>Indica o teu utilizador Discogs e uma API key gratuita do Last.fm, e depois importa a coleção.</p>
        <a class="btn" href="<?= url('settings') ?>">Abrir definições</a>
        <a class="btn btn-ghost" href="<?= url('collections/sync') ?>">Sincronizar</a>
    </div>
    <?php return; ?>
<?php endif; ?>

<section class="tiles">
    <div class="tile">
        <div class="tile-label">Discos</div>
        <div class="tile-value"><?= num($totals['releases']) ?></div>
    </div>
    <div class="tile">
        <div class="tile-label">Artistas</div>
        <div class="tile-value"><?= num($totals['artists']) ?></div>
    </div>
    <?php if ($totals['has_userplays']): ?>
        <div class="tile">
            <div class="tile-label">As tuas audições destes discos</div>
            <div class="tile-value"><?= short_num($totals['userplays']) ?></div>
        </div>
        <a class="tile tile-link" href="<?= h($albums(['lf' => 'unheard'])) ?>">
            <div class="tile-label">Nunca ouvidos (Last.fm)</div>
            <div class="tile-value"><?= num($totals['unheard']) ?></div>
            <div class="tile-note"><?= $totals['releases'] ? round($totals['unheard'] / $totals['releases'] * 100) : 0 ?>% da coleção</div>
        </a>
    <?php else: ?>
        <div class="tile">
            <div class="tile-label">Com dados do Last.fm</div>
            <div class="tile-value"><?= $coverage ?>%</div>
            <div class="tile-note"><?= num($totals['lf_ok']) ?> de <?= num($totals['releases']) ?></div>
        </div>
    <?php endif; ?>
</section>

<?php if ($totals['lf_done'] < $totals['releases']): ?>
    <div class="notice">
        Faltam obter dados do Last.fm para <?= num($totals['releases'] - $totals['lf_done']) ?> discos.
        <a href="<?= url('collections/sync') ?>">Sincronizar agora</a>
    </div>
<?php endif; ?>

<?php if ($topMine): ?>
    <section class="section">
        <div class="section-head"><h2>Os que mais ouves</h2><a href="<?= h($albums(['sort' => 'myplays'])) ?>">Ver todos</a></div>
        <div class="grid grid-row"><?= $row($topMine, 'myplays') ?></div>
    </section>
<?php endif; ?>

<?php if ($topGlobal): ?>
    <section class="section">
        <div class="section-head"><h2>Os mais ouvidos no mundo</h2><a href="<?= h($albums(['sort' => 'listeners'])) ?>">Ver todos</a></div>
        <div class="grid grid-row"><?= $row($topGlobal, 'listeners') ?></div>
    </section>
<?php endif; ?>

<section class="panels">
    <div class="panel">
        <h2>Géneros</h2>
        <?= View::partial('partials/bars', ['rows' => $genres, 'href' => static fn($g) => $albums(['genre' => $g])]) ?>
    </div>
    <div class="panel">
        <h2>Décadas</h2>
        <?= View::partial('partials/bars', ['rows' => $decades, 'href' => static fn($d) => $albums(['decade' => $d]), 'label' => static fn($d) => "Anos $d"]) ?>
    </div>
    <div class="panel">
        <h2>Formatos</h2>
        <?= View::partial('partials/bars', ['rows' => $formats, 'href' => static fn($f) => $albums(['format' => $f])]) ?>
    </div>
    <div class="panel">
        <?php if ($artistsMine): ?>
            <h2>Artistas que mais ouves</h2>
            <ol class="ranking">
                <?php foreach ($artistsMine as $a): ?>
                    <li><a href="<?= url('collections/artist', ['id' => $a['id']]) ?>"><?= h($a['name']) ?></a>
                        <span><?= num($a['lf_userplaycount']) ?> audições</span></li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <h2>Artistas com mais discos</h2>
            <ol class="ranking">
                <?php foreach ($artistsOwned as $a): ?>
                    <li><a href="<?= url('collections/artist', ['id' => $a['id']]) ?>"><?= h($a['name']) ?></a>
                        <span><?= num($a['n_releases']) ?> discos</span></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>

<?php if ($unheard): ?>
    <section class="section">
        <div class="section-head">
            <h2>Esquecidos na prateleira</h2>
            <a href="<?= h($albums(['lf' => 'unheard'])) ?>">Ver todos</a>
        </div>
        <p class="muted section-note">Discos que tens mas nunca ouviste segundo o Last.fm. Escolhidos ao acaso.</p>
        <div class="grid grid-row"><?= $row($unheard) ?></div>
    </section>
<?php endif; ?>

<section class="section">
    <div class="section-head"><h2>Adicionados recentemente</h2><a href="<?= h($albums([])) ?>">Ver todos</a></div>
    <div class="grid grid-row"><?= $row($recent) ?></div>
</section>
