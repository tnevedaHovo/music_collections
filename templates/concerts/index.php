<?php
use App\Concerts\ConcertRepository;
use App\Core\View;

/**
 * @var array $filters @var string $view @var string $month @var array $days @var array $result
 * @var int $page @var int $perPage @var array $places @var array $totals @var bool $hasApi
 */
$f = static fn(string $k) => $filters[$k] ?? '';
$active = array_filter(array_diff_key($filters, ['when' => 1]), static fn($v) => $v !== '');
$when = $f('when') ?: 'upcoming';
$calendar = $view === 'calendar';
$monthCount = array_sum(array_map('count', $days));
$select = static function (string $name, string $placeholder, array $rows, array $labels = []) use ($f): string {
    $html = '<select name="' . $name . '" data-autosubmit><option value="">' . h($placeholder) . '</option>';
    foreach ($rows as $r) {
        $v = (string) $r['name'];
        $html .= '<option value="' . h($v) . '"' . ($f($name) === $v ? ' selected' : '') . '>' . h($labels[$v] ?? $v) . ' (' . num($r['n']) . ')</option>';
    }
    return $html . '</select>';
};
$views = [
    'list' => ['Lista', 'M3 5h18v2H3V5zm0 6h18v2H3v-2zm0 6h18v2H3v-2z'],
    'cards' => ['Cartões', 'M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z'],
    'calendar' => ['Calendário', 'M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2zm0 16H5V9h14v11z'],
];
?>
<header class="page-head">
    <div>
        <h1>Concertos</h1>
        <p class="muted">
            <?php if ($calendar): ?>
                <?= num($monthCount) ?> <?= $monthCount === 1 ? 'concerto' : 'concertos' ?> em <?= h(month_pt((int) substr($month, 5, 2))) ?> de <?= h(substr($month, 0, 4)) ?>
            <?php else: ?>
                <?= num($result['total']) ?> <?= $result['total'] === 1 ? 'concerto' : 'concertos' ?>
                <?= $when === 'past' ? 'passados' : ($when === 'all' ? 'na agenda' : 'por vir') ?>
            <?php endif; ?>
            <?= $active ? ' com os filtros atuais' : '' ?>
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
    <input type="hidden" name="view" value="<?= h($view) ?>">
    <?php if ($calendar): ?><input type="hidden" name="month" value="<?= h($month) ?>"><?php endif; ?>
    <nav class="view-switch" aria-label="Vista">
        <?php foreach ($views as $key => [$label, $icon]): ?>
            <a href="<?= h(url('concerts', ['view' => $key] + $filters)) ?>" class="<?= $view === $key ? 'active' : '' ?>"
               title="<?= h($label) ?>" <?= $view === $key ? 'aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $icon ?>"/></svg><span><?= h($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <input type="search" name="q" value="<?= h($f('q')) ?>" placeholder="Pesquisar artista, evento, sala, cidade…">
    <?php if (!$calendar): ?>
        <select name="when" data-autosubmit>
            <?php foreach (['upcoming' => 'Próximos', 'past' => 'Passados', 'all' => 'Todos'] as $v => $label): ?>
                <option value="<?= $v === 'upcoming' ? '' : $v ?>" <?= $when === $v ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <select name="mine" data-autosubmit>
        <?php foreach (['' => 'Todos os concertos', 'collection' => 'Artistas da coleção', 'any' => 'Marcados por mim'] + ConcertRepository::MY_STATUSES as $v => $label): ?>
            <option value="<?= $v ?>" <?= $f('mine') === $v ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
    </select>
    <?= $select('country', 'Todos os países', $places['countries']) ?>
    <?= $select('city', 'Todas as cidades', $places['cities']) ?>
    <?php if ($places['genres']): ?><?= $select('genre', 'Todos os géneros', $places['genres']) ?><?php endif; ?>
    <?php if (count($places['sources']) > 1): ?><?= $select('source', 'Todas as fontes', $places['sources'], ConcertRepository::SOURCES) ?><?php endif; ?>
    <button class="btn" type="submit">Filtrar</button>
    <?php if ($active): ?><a class="btn btn-ghost" href="<?= url('concerts', ['when' => $f('when'), 'month' => $calendar ? $month : null]) ?>">Limpar</a><?php endif; ?>
</form>

<?php if ($calendar): ?>
    <?= View::partial('concerts/calendar', ['month' => $month, 'days' => $days, 'filters' => $filters]) ?>
<?php elseif (!$result['rows']): ?>
    <div class="empty">
        <?php if ($active || $when !== 'upcoming'): ?>
            <p>Nenhum concerto corresponde aos filtros.</p>
        <?php else: ?>
            <p>A agenda está vazia. Adiciona concertos à mão ou procura-os na BOL e na Ticketmaster.</p>
            <a class="btn" href="<?= url('concerts/edit') ?>">Adicionar concerto</a>
            <a class="btn btn-ghost" href="<?= url('concerts/sync') ?>">Procurar concertos</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?= View::partial($view === 'cards' ? 'concerts/cards' : 'concerts/list', ['rows' => $result['rows']]) ?>
    <?= View::partial('partials/pager', [
        'page' => $page,
        'pages' => (int) ceil($result['total'] / $perPage),
        'route' => 'concerts',
        'params' => $filters,
    ]) ?>
<?php endif; ?>
