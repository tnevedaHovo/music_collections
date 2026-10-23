<?php
/** @var array $counts @var bool $hasApi @var array $countries @var bool $hasArtists */
$c = $counts;
?>
<header class="page-head">
    <div>
        <h1>Procurar concertos</h1>
        <p class="muted">
            Importa datas da <strong>BOL</strong> e da <strong>Ticketmaster Discovery API</strong> (gratuita, 5000 pedidos/dia) para a agenda.
            Concertos que edites à mão deixam de ser alterados pelas sincronizações.
        </p>
    </div>
</header>

<?php if (!$hasApi): ?>
    <div class="notice">A BOL funciona já; para a Ticketmaster falta a API key. <a href="<?= url('settings') ?>">Abrir definições</a></div>
<?php endif; ?>

<div class="sync-grid">
    <section class="panel" id="sync-bol">
        <h2>BOL (Portugal)</h2>
        <p class="muted">
            Importa os concertos e festivais de música à venda na <strong>BOL</strong> (cerca de 700 eventos, num só pedido, sem chave).
            A BOL não indica o artista: os da tua coleção são reconhecidos pelo nome do evento.
        </p>
        <label class="check">
            <input type="checkbox" data-bol-only checked>
            Só concertos de artistas da minha coleção
        </label>
        <div class="progress" hidden><div class="progress-bar"></div></div>
        <p class="sync-status muted small" aria-live="polite"></p>
        <div class="actions">
            <button class="btn" data-bol>Importar da BOL</button>
        </div>
    </section>

    <section class="panel" id="sync-concerts">
        <h2>Ticketmaster · artistas da coleção</h2>
        <p class="muted">
            Procura cada artista da tua coleção (os mais ouvidos primeiro) e importa as próximas datas
            <?= $countries ? 'em <strong>' . h(implode(', ', $countries)) . '</strong>' : 'em <strong>todos os países</strong>' ?>.
            <a href="<?= url('settings') ?>">Alterar países</a>
        </p>
        <table class="table table-compact">
            <thead><tr><th class="num">Encontrados</th><th class="num">Sem registo</th><th class="num">Erros</th><th class="num">Pendentes</th></tr></thead>
            <tbody>
                <tr data-concert-counts>
                    <td class="num" data-k="ok"><?= num($c['ok']) ?></td>
                    <td class="num" data-k="notfound"><?= num($c['notfound']) ?></td>
                    <td class="num" data-k="error"><?= num($c['error']) ?></td>
                    <td class="num" data-k="pending"><?= num($c['pending']) ?></td>
                </tr>
            </tbody>
        </table>
        <div class="progress" hidden><div class="progress-bar"></div></div>
        <p class="sync-status muted small" aria-live="polite"></p>
        <div class="actions">
            <button class="btn" data-concerts="step" <?= $hasApi && $hasArtists ? '' : 'disabled' ?>>Procurar pendentes</button>
            <button class="btn btn-ghost" data-concerts="errors" <?= $hasApi && $hasArtists ? '' : 'disabled' ?>>Repetir erros</button>
            <button class="btn btn-ghost" data-concerts="all" <?= $hasApi && $hasArtists ? '' : 'disabled' ?>>Atualizar todos</button>
        </div>
        <?php if (!$hasArtists): ?><p class="muted small">Importa primeiro a coleção em <a href="<?= url('collections/sync') ?>">Coleções › Sincronizar</a>.</p><?php endif; ?>
    </section>

    <section class="panel" id="sync-area">
        <h2>Ticketmaster · agenda de uma região</h2>
        <p class="muted">Importa todos os concertos de música de um país ou cidade (até 1000 eventos). Artistas da coleção que constem do cartaz são reconhecidos.</p>
        <form class="filters area-form" data-area>
            <input name="country" value="<?= h($countries[0] ?? 'PT') ?>" maxlength="2" size="3" aria-label="País (código ISO)" title="Código do país com 2 letras, ex.: PT, ES" required>
            <input name="city" placeholder="Cidade (opcional), ex.: Lisboa" aria-label="Cidade">
            <button class="btn" type="submit" <?= $hasApi ? '' : 'disabled' ?>>Importar</button>
        </form>
        <div class="progress" hidden><div class="progress-bar"></div></div>
        <p class="sync-status muted small" aria-live="polite"></p>
    </section>
</div>

<p class="muted small">
    <?= num($c['concerts']) ?> concertos por vir na agenda. <a href="<?= url('concerts') ?>">Ver agenda</a> ·
    Linha de comandos: <code>C:\xampp\php\php.exe sync.php --concerts</code>
</p>
