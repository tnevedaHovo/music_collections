<?php
/**
 * Lista de barras horizontais (uma só série → uma só cor, valor sempre visível).
 * @var array $rows [['name'=>..., 'n'=>...]] @var callable|null $href @var callable|null $label
 */
$max = max(1, ...array_map(static fn($r) => (int) $r['n'], $rows ?: [['n' => 1]]));
$total = array_sum(array_map(static fn($r) => (int) $r['n'], $rows));
?>
<?php if (!$rows): ?>
    <p class="muted">Sem dados.</p>
<?php else: ?>
<ul class="bars">
    <?php foreach ($rows as $row):
        $name = isset($label) ? $label($row['name']) : $row['name'];
        $pct = $total ? round($row['n'] / $total * 100) : 0; ?>
        <li title="<?= h($name) ?>: <?= num($row['n']) ?> (<?= $pct ?>%)">
            <a href="<?= isset($href) ? h($href($row['name'])) : '#' ?>">
                <span class="bar-label"><?= h($name) ?></span>
                <span class="bar-track"><span class="bar-fill" style="width: <?= max(1, round($row['n'] / $max * 100, 1)) ?>%"></span></span>
                <span class="bar-value"><?= num($row['n']) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
