<?php
/** @var string $month 'AAAA-MM' @var array $days 'AAAA-MM-DD' => concertos @var array $filters */
$first = new DateTimeImmutable($month . '-01');
$prev = $first->modify('-1 month')->format('Y-m');
$next = $first->modify('+1 month')->format('Y-m');
// A grelha começa na segunda-feira da semana do dia 1 e acaba no domingo da semana do último dia.
$start = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
$last = $first->modify('last day of this month');
$end = $last->modify('+' . (7 - (int) $last->format('N')) . ' days');
$today = date('Y-m-d');
$nav = static fn(string $m) => url('concerts', ['view' => 'calendar', 'month' => $m] + $filters);
$visible = 3; // concertos por dia antes de "+N"
?>
<div class="cal-head">
    <a class="btn btn-ghost btn-sm" href="<?= h($nav($prev)) ?>" aria-label="Mês anterior">‹</a>
    <h2><?= h(ucfirst(month_pt((int) $first->format('n')))) ?> <?= $first->format('Y') ?></h2>
    <a class="btn btn-ghost btn-sm" href="<?= h($nav($next)) ?>" aria-label="Mês seguinte">›</a>
    <?php if ($month !== date('Y-m')): ?><a class="btn btn-ghost btn-sm" href="<?= h($nav(date('Y-m'))) ?>">Hoje</a><?php endif; ?>
</div>

<div class="calendar<?= $days ? '' : ' calendar-empty' ?>" role="grid" aria-label="Concertos de <?= h(month_pt((int) $first->format('n'))) ?>">
    <?php foreach (['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'] as $wd): ?>
        <div class="cal-wd" role="columnheader"><?= $wd ?></div>
    <?php endforeach; ?>
    <?php for ($d = $start; $d <= $end; $d = $d->modify('+1 day')):
        $iso = $d->format('Y-m-d');
        $gigs = $days[$iso] ?? [];
        $classes = ['cal-day'];
        if ($d->format('m') !== $first->format('m')) $classes[] = 'cal-out';
        if ($iso === $today) $classes[] = 'cal-today';
        if ($iso < $today) $classes[] = 'cal-past';
        if ($gigs) $classes[] = 'cal-has'; ?>
        <div class="<?= implode(' ', $classes) ?>" role="gridcell">
            <div class="cal-num">
                <span class="cal-wd-inline"><?= h(weekday_pt($iso)) ?></span> <?= $d->format('j') ?>
            </div>
            <?php foreach ($gigs as $i => $c):
                if ($i === $visible && count($gigs) > $visible + 1): ?>
                    <details class="cal-more"><summary>+<?= count($gigs) - $visible ?> mais</summary>
                <?php endif;
                $off = in_array($c['event_status'], ['cancelled', 'postponed'], true);
                $tip = implode(' · ', array_filter([$c['time'], $c['artist'], $c['title'] !== $c['artist'] ? $c['title'] : null, $c['venue'], $c['city'], $c['price']])); ?>
                <a class="cal-gig<?= $c['artist_id'] ? ' cal-col' : '' ?><?= $c['my_status'] ? ' cal-mine' : '' ?><?= $off ? ' cal-off' : '' ?>"
                   href="<?= url('concerts/edit', ['id' => $c['id']]) ?>" title="<?= h($tip) ?>">
                    <?php if ($c['time']): ?><span class="cal-time"><?= h($c['time']) ?></span><?php endif; ?>
                    <span class="cal-name"><?= h($c['artist']) ?></span>
                    <span class="cal-place"><?= h($c['city'] ?? '') ?></span>
                </a>
            <?php endforeach; ?>
            <?php if (count($gigs) > $visible + 1): ?></details><?php endif; ?>
        </div>
    <?php endfor; ?>
</div>
<?php if (!$days): ?>
    <p class="muted cal-empty-note">Nenhum concerto neste mês<?= array_filter($filters) ? ' com os filtros atuais' : '' ?>.</p>
<?php endif; ?>
<p class="muted small cal-legend">
    <span class="cal-key cal-col"></span> artista da coleção
    <span class="cal-key cal-mine"></span> marcado por mim
</p>
