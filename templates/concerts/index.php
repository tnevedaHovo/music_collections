<?php
use App\Concerts\ConcertRepository;
use App\Core\View;

/** @var array $filters @var array $result @var int $page @var int $perPage @var array $places @var array $totals @var bool $hasApi */
$f = static fn(string $k) => $filters[$k] ?? '';
$active = array_filter(array_diff_key($filters, ['when' => 1]), static fn($v) => $v !== '');
$when = $f('when') ?: 'upcoming';
$select = static function (string $name, string $placeholder, array $rows) use ($f): string {
    $html = '<select name="' . $name . '" data-autosubmit><option value="">' . h($placeholder) . '</option>';
    foreach ($rows as $r) {
        $v = (string) $r['name'];
        $html .= '<option value="' . h($v) . '"' . ($f($name) === $v ? ' selected' : '') . '>' . h($v) . ' (' . num($r['n']) . ')</option>';
    }
    return $html . '</select>';
};
?>
<header class="page-head">
    <div>
        <h1>Concertos</h1>
        <p class="muted">
            <?= num($result['total']) ?> <?= $result['total'] === 1 ? 'concerto' : 'concertos' ?>
            <?= $when === 'past' ? 'passados' : ($when === 'all' ? 'na agenda' : 'por vir') ?><?= $active ? ' com os filtros atuais' : '' ?>
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="<?= url('concerts/sync') ?>">Procurar concertos</a>
        <a class="btn" href="<?= url('concerts/edit') ?>">+ Adicionar concerto</a>
    </div>
</header>

<?php if ($totals['upcoming']): ?>
    <div class="tiles">
        <a class="tile tile-link" href="<?= url('concerts') ?>"><div class="tile-label">Próximos concertos</div><div class="tile-value"><?= num($totals['upcoming']) ?></div></a>
        <a class="tile tile-link" href="<?= url('concerts', ['mine' => 'collection']) ?>"><div class="tile-label">De artistas da coleção</div><div class="tile-value"><?= num($totals['collection']) ?></div></a>
        <a class="tile tile-link" href="<?= url('concerts', ['mine' => 'any']) ?>"><div class="tile-label">Vou / tenho bilhete</div><div class="tile-value"><?= num($totals['mine']) ?></div></a>
    </div>
<?php endif; ?>

<form class="filters" method="get" action="index.php">
    <input type="hidden" name="r" value="concerts">
    <input type="search" name="q" value="<?= h($f('q')) ?>" placeholder="Pesquisar artista, evento, sala, cidade…">
    <select name="when" data-autosubmit>
        <?php foreach (['upcoming' => 'Próximos', 'past' => 'Passados', 'all' => 'Todos'] as $v => $label): ?>
            <option value="<?= $v === 'upcoming' ? '' : $v ?>" <?= $when === $v ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="mine" data-autosubmit>
        <?php foreach (['' => 'Todos os concertos', 'collection' => 'Artistas da coleção', 'any' => 'Marcados por mim'] + ConcertRepository::MY_STATUSES as $v => $label): ?>
            <option value="<?= $v ?>" <?= $f('mine') === $v ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
    </select>
    <?= $select('country', 'Todos os países', $places['countries']) ?>
    <?= $select('city', 'Todas as cidades', $places['cities']) ?>
    <button class="btn" type="submit">Filtrar</button>
    <?php if ($active): ?><a class="btn btn-ghost" href="<?= url('concerts', ['when' => $f('when')]) ?>">Limpar</a><?php endif; ?>
</form>

<?php if (!$result['rows']): ?>
    <div class="empty">
        <?php if ($active || $when !== 'upcoming'): ?>
            <p>Nenhum concerto corresponde aos filtros.</p>
        <?php else: ?>
            <p>A agenda está vazia. Adiciona concertos à mão ou procura-os na Ticketmaster<?= $hasApi ? '' : ' (precisa de uma API key gratuita)' ?>.</p>
            <a class="btn" href="<?= url('concerts/edit') ?>">Adicionar concerto</a>
            <a class="btn btn-ghost" href="<?= url($hasApi ? 'concerts/sync' : 'settings') ?>"><?= $hasApi ? 'Procurar concertos' : 'Configurar Ticketmaster' ?></a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php $month = null; foreach ($result['rows'] as $c):
        $m = substr($c['date'], 0, 7);
        if ($m !== $month):
            if ($month !== null): ?></ul><?php endif;
            $month = $m; ?>
            <h2 class="month-head"><?= h(ucfirst(month_pt((int) substr($m, 5, 2)))) ?> <?= h(substr($m, 0, 4)) ?></h2>
            <ul class="gigs">
        <?php endif; ?>
        <?= View::partial('concerts/row', ['c' => $c]) ?>
    <?php endforeach; ?>
    </ul>
    <?= View::partial('partials/pager', [
        'page' => $page,
        'pages' => (int) ceil($result['total'] / $perPage),
        'route' => 'concerts',
        'params' => $filters,
    ]) ?>
<?php endif; ?>
