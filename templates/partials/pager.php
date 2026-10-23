<?php
/** @var int $page @var int $pages @var string $route @var array $params */
if ($pages <= 1) {
    return;
}
$window = array_unique(array_filter([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages], static fn($p) => $p >= 1 && $p <= $pages));
sort($window);
?>
<nav class="pager" aria-label="Páginas">
    <?php if ($page > 1): ?><a href="<?= h(url($route, ['page' => $page - 1] + $params)) ?>">‹ Anterior</a><?php endif; ?>
    <?php $prev = 0; foreach ($window as $p): ?>
        <?php if ($p - $prev > 1): ?><span class="gap">…</span><?php endif; ?>
        <?php if ($p === $page): ?>
            <span class="current"><?= $p ?></span>
        <?php else: ?>
            <a href="<?= h(url($route, ['page' => $p] + $params)) ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php $prev = $p; endforeach; ?>
    <?php if ($page < $pages): ?><a href="<?= h(url($route, ['page' => $page + 1] + $params)) ?>">Seguinte ›</a><?php endif; ?>
</nav>
