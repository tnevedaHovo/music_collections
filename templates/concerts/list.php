<?php
use App\Core\View;

/** @var array $rows concertos já ordenados; agrupados aqui por mês */
$month = null;
foreach ($rows as $c):
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
