<?php
/** @var array $counts @var array $discogs @var string $lastSync @var bool $hasDiscogs @var bool $hasLastfm */
$c = $counts;
?>
<header class="page-head">
    <div>
        <h1>Sincronizar</h1>
        <p class="muted">Importa a coleção do Discogs e junta-lhe os dados de audição do Last.fm. As duas fontes são gratuitas.</p>
    </div>
</header>

<?php if (!$hasDiscogs || !$hasLastfm): ?>
    <div class="notice">
        Falta configurar <?= !$hasDiscogs ? 'o utilizador Discogs' : '' ?><?= !$hasDiscogs && !$hasLastfm ? ' e ' : '' ?><?= !$hasLastfm ? 'a API key do Last.fm' : '' ?>.
        <a href="<?= url('settings') ?>">Abrir definições</a>
    </div>
<?php endif; ?>

<div class="sync-grid">
    <section class="panel" id="sync-discogs">
        <h2>1. Coleção Discogs</h2>
        <p class="muted">
            <?php if ($discogs): ?>
                Utilizador <strong><?= h($discogs['username'] ?? '') ?></strong> · última sincronização <?= h(ago($lastSync)) ?>.
            <?php else: ?>
                Ainda não sincronizado.
            <?php endif; ?>
            Lê a coleção completa, 100 discos por pedido (máx. 25 pedidos/min sem token).
        </p>
        <div class="progress" hidden><div class="progress-bar"></div></div>
        <p class="sync-status muted small" aria-live="polite"></p>
        <button class="btn" data-sync="discogs" <?= $hasDiscogs ? '' : 'disabled' ?>>Importar coleção</button>
    </section>

    <section class="panel" id="sync-lastfm">
        <h2>2. Dados de audição Last.fm</h2>
        <p class="muted">Ouvintes e reproduções globais de cada disco e artista e, com um utilizador Last.fm definido, as tuas audições.</p>
        <table class="table table-compact">
            <thead><tr><th></th><th class="num">Com dados</th><th class="num">Sem dados</th><th class="num">Erros</th><th class="num">Pendentes</th></tr></thead>
            <tbody>
            <?php foreach (['albums' => 'Discos', 'artists' => 'Artistas'] as $k => $label): ?>
                <tr data-counts="<?= $k ?>">
                    <th><?= $label ?></th>
                    <td class="num" data-k="ok"><?= num($c[$k]['ok']) ?></td>
                    <td class="num" data-k="notfound"><?= num($c[$k]['notfound']) ?></td>
                    <td class="num" data-k="error"><?= num($c[$k]['error']) ?></td>
                    <td class="num" data-k="pending"><?= num($c[$k]['pending']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="progress" hidden><div class="progress-bar"></div></div>
        <p class="sync-status muted small" aria-live="polite"></p>
        <div class="actions">
            <button class="btn" data-sync="lastfm" <?= $hasLastfm ? '' : 'disabled' ?>>Obter pendentes</button>
            <button class="btn btn-ghost" data-reset="errors" <?= $hasLastfm ? '' : 'disabled' ?>>Repetir erros e sem dados</button>
            <button class="btn btn-ghost" data-reset="all" <?= $hasLastfm ? '' : 'disabled' ?>>Atualizar tudo</button>
        </div>
    </section>
</div>

<section class="panel">
    <h2>Sincronização automática</h2>
    <p class="muted">Para manter tudo atualizado sem abrir o browser, agenda este comando no Agendador de Tarefas do Windows (por exemplo, uma vez por dia):</p>
    <pre class="code">C:\xampp\php\php.exe <?= h(APP_ROOT . DIRECTORY_SEPARATOR . 'sync.php') ?></pre>
    <p class="muted small">Opções: <code>--discogs</code>, <code>--lastfm</code> e <code>--refresh</code> (volta a obter os dados Last.fm de todos os discos).</p>
</section>
