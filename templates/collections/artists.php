<?php
use App\Collections\Repository;
use App\Core\View;

/** @var array $filters @var array $result @var int $page @var int $perPage */
$sort = $filters['sort'] ?? 'releases';
$sortLink = static fn(string $s) => h(url('collections/artists', ['q' => $filters['q'] ?? '', 'sort' => $s]));
$th = static fn(string $s, string $label) => '<a href="' . $sortLink($s) . '"' . ($sort === $s ? ' class="sorted"' : '') . '>' . h($label) . '</a>';
?>
<header class="page-head">
    <div>
        <h1>Artistas</h1>
        <p class="muted"><?= num($result['total']) ?> artistas na coleção</p>
    </div>
</header>

<form class="filters" method="get" action="index.php">
    <input type="hidden" name="r" value="collections/artists">
    <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" placeholder="Pesquisar artista…">
    <select name="sort" data-autosubmit>
        <?php foreach (Repository::ARTIST_SORTS as $v => [$label]): ?>
            <option value="<?= $v ?>" <?= $sort === $v ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filtrar</button>
</form>

<?php if (!$result['rows']): ?>
    <div class="empty"><p>Nenhum artista encontrado.</p></div>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead>
        <tr>
            <th><?= $th('name', 'Artista') ?></th>
            <th class="num"><?= $th('releases', 'Discos') ?></th>
            <th class="num"><?= $th('listeners', 'Ouvintes') ?></th>
            <th class="num"><?= $th('playcount', 'Reproduções') ?></th>
            <th class="num"><?= $th('myplays', 'As tuas audições') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($result['rows'] as $a): ?>
            <tr>
                <td>
                    <a class="artist-cell" href="<?= url('collections/artist', ['id' => $a['id']]) ?>">
                        <span class="mini-cover"><?php if ($a['thumb']): ?><img src="<?= h($a['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?></span>
                        <?= h($a['name']) ?>
                    </a>
                </td>
                <td class="num"><?= num($a['n_releases']) ?></td>
                <td class="num"><?= num($a['lf_listeners']) ?></td>
                <td class="num"><?= num($a['lf_playcount']) ?></td>
                <td class="num"><?= num($a['lf_userplaycount']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= View::partial('partials/pager', [
    'page' => $page,
    'pages' => (int) ceil($result['total'] / $perPage),
    'route' => 'collections/artists',
    'params' => $filters,
]) ?>
<?php endif; ?>
