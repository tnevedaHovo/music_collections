<?php
use App\Collections\Repository;
use App\Core\View;

/** @var array $filters @var array $result @var int $page @var int $perPage */
$f = static fn(string $k) => $filters[$k] ?? '';
$sort = $f('sort') ?: 'added';
$metric = in_array($sort, ['myplays'], true) ? 'myplays' : (in_array($sort, ['listeners', 'playcount'], true) ? 'listeners' : '');
$active = array_filter(array_diff_key($filters, ['sort' => 1]), static fn($v) => $v !== '');
$select = static function (string $name, string $placeholder, array $rows, ?callable $label = null) use ($f): string {
    $html = '<select name="' . $name . '" data-autosubmit><option value="">' . h($placeholder) . '</option>';
    foreach ($rows as $r) {
        $v = (string) $r['name'];
        $html .= '<option value="' . h($v) . '"' . ($f($name) === $v ? ' selected' : '') . '>'
            . h($label ? $label($v) : $v) . ' (' . num($r['n']) . ')</option>';
    }
    return $html . '</select>';
};
?>
<header class="page-head">
    <div>
        <h1>Discos</h1>
        <p class="muted"><?= num($result['total']) ?> <?= $result['total'] === 1 ? 'disco' : 'discos' ?><?= $active ? ' com os filtros atuais' : ' na coleção' ?></p>
    </div>
</header>

<form class="filters" method="get" action="index.php">
    <input type="hidden" name="r" value="collections/albums">
    <input type="search" name="q" value="<?= h($f('q')) ?>" placeholder="Pesquisar título, artista, editora, n.º de catálogo…">
    <?= $select('genre', 'Todos os géneros', $genres) ?>
    <?= $select('style', 'Todos os estilos', $styles) ?>
    <?= $select('format', 'Todos os formatos', $formats) ?>
    <?= $select('decade', 'Todas as décadas', $decades, static fn($d) => "Anos $d") ?>
    <select name="lf" data-autosubmit>
        <?php foreach (['' => 'Last.fm: todos', 'heard' => 'Já ouvidos', 'unheard' => 'Nunca ouvidos', 'notfound' => 'Sem dados Last.fm', 'pending' => 'Por sincronizar'] as $v => $label): ?>
            <option value="<?= $v ?>" <?= $f('lf') === $v ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="sort" data-autosubmit>
        <?php foreach (Repository::ALBUM_SORTS as $v => [$label]): ?>
            <option value="<?= $v ?>" <?= $sort === $v ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filtrar</button>
    <?php if ($active): ?><a class="btn btn-ghost" href="<?= url('collections/albums', ['sort' => $f('sort')]) ?>">Limpar</a><?php endif; ?>
</form>

<?php if (!$result['rows']): ?>
    <div class="empty"><p>Nenhum disco corresponde aos filtros.</p></div>
<?php else: ?>
    <div class="grid grid-paged">
        <?php foreach ($result['rows'] as $r): ?>
            <?= View::partial('partials/card', ['r' => $r, 'metric' => $metric]) ?>
        <?php endforeach; ?>
    </div>
    <?= View::partial('partials/pager', [
        'page' => $page,
        'pages' => (int) ceil($result['total'] / $perPage),
        'route' => 'collections/albums',
        'params' => $filters,
    ]) ?>
<?php endif; ?>
