<?php
use App\Concerts\ConcertRepository;

/** @var int|null $id @var array $c @var array $errors @var array $artistNames */
$v = static fn(string $k) => h($c[$k] ?? '');
$err = static fn(string $k) => isset($errors[$k]) ? '<small class="error">' . h($errors[$k]) . '</small>' : '';
$imported = ($c['source'] ?? 'manual') !== 'manual';
?>
<a class="back" href="<?= url('concerts') ?>">‹ Concertos</a>

<header class="page-head">
    <div>
        <h1><?= $id === null ? 'Novo concerto' : 'Editar concerto' ?></h1>
        <?php if ($imported): ?>
            <p class="muted">
                Importado da Ticketmaster.
                <?= !empty($c['edited'])
                    ? 'Como já o alteraste, as sincronizações seguintes não lhe tocam.'
                    : 'Se alterares os dados do evento, deixa de ser atualizado pelas sincronizações (a tua marcação e notas nunca são alteradas).' ?>
            </p>
        <?php endif; ?>
    </div>
</header>

<?php if ($id === null): ?>
    <section class="panel import-link" data-import-link>
        <h2>Importar de um link <span class="muted small">opcional</span></h2>
        <p class="muted small">Cola o link de um concerto do <strong>Songkick</strong> ou da <strong>Viral Agenda</strong> e o formulário é preenchido. Revê os dados antes de adicionar.</p>
        <div class="filters">
            <input type="url" placeholder="https://www.songkick.com/concerts/… ou https://www.viralagenda.com/pt/events/…" aria-label="Link do evento">
            <button class="btn btn-ghost" type="button">Ler evento</button>
        </div>
        <p class="import-status small" aria-live="polite"></p>
    </section>
<?php endif; ?>

<form method="post" class="settings concert-form">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <fieldset class="panel">
        <legend>Evento</legend>
        <div class="form-grid">
            <label class="span-2">
                <span>Artista <em>obrigatório</em></span>
                <input name="artist" value="<?= $v('artist') ?>" list="artist-names" required autocomplete="off">
                <small>Escreve o nome tal como na coleção para o concerto ficar ligado à página do artista.</small>
                <?= $err('artist') ?>
            </label>
            <label class="span-2">
                <span>Nome do evento <em>opcional</em></span>
                <input name="title" value="<?= $v('title') ?>" placeholder="ex.: digressão, festival">
            </label>
            <label class="span-2">
                <span>Cartaz <em>opcional</em></span>
                <input name="lineup" value="<?= $v('lineup') ?>" placeholder="Outros artistas, separados por vírgulas">
            </label>
            <label>
                <span>Data <em>obrigatório</em></span>
                <input name="date" type="date" value="<?= $v('date') ?>" required>
                <?= $err('date') ?>
            </label>
            <label>
                <span>Hora</span>
                <input name="time" type="time" value="<?= $v('time') ?>">
                <?= $err('time') ?>
            </label>
            <label class="span-2">
                <span>Sala / recinto</span>
                <input name="venue" value="<?= $v('venue') ?>">
            </label>
            <label>
                <span>Cidade</span>
                <input name="city" value="<?= $v('city') ?>">
            </label>
            <label>
                <span>País</span>
                <input name="country" value="<?= $v('country') ?>" placeholder="Portugal">
            </label>
            <label>
                <span>Preço</span>
                <input name="price" value="<?= $v('price') ?>" placeholder="ex.: 25–40 €">
            </label>
            <label>
                <span>Estado</span>
                <select name="event_status">
                    <option value="">—</option>
                    <?php foreach (ConcertRepository::EVENT_STATUSES as $k => $label): ?>
                        <option value="<?= $k ?>" <?= ($c['event_status'] ?? '') === $k ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="span-2">
                <span>Link dos bilhetes</span>
                <input name="url" type="url" value="<?= $v('url') ?>" placeholder="https://">
                <?= $err('url') ?>
            </label>
        </div>
    </fieldset>

    <fieldset class="panel">
        <legend>Para mim</legend>
        <div class="form-grid">
            <label>
                <span>Marcação</span>
                <select name="my_status">
                    <option value="">—</option>
                    <?php foreach (ConcertRepository::MY_STATUSES as $k => $label): ?>
                        <option value="<?= $k ?>" <?= ($c['my_status'] ?? '') === $k ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="span-2">
                <span>Notas</span>
                <textarea name="notes" rows="3" placeholder="Com quem vou, lugar, transporte…"><?= $v('notes') ?></textarea>
            </label>
        </div>
    </fieldset>

    <div class="actions">
        <button class="btn" type="submit" name="op" value="save"><?= $id === null ? 'Adicionar' : 'Guardar' ?></button>
        <a class="btn btn-ghost" href="<?= url('concerts') ?>">Cancelar</a>
        <?php if ($id !== null): ?>
            <?php if ($imported && !empty($c['edited'])): ?>
                <button class="btn btn-ghost" type="submit" name="op" value="revert" formnovalidate>Voltar a sincronizar</button>
            <?php endif; ?>
            <button class="btn btn-ghost btn-danger" type="submit" name="op" value="delete" formnovalidate
                    data-confirm="<?= $imported ? 'Remover este concerto da agenda? Não volta a ser importado.' : 'Apagar este concerto?' ?>">Apagar</button>
        <?php endif; ?>
    </div>
</form>

<datalist id="artist-names">
    <?php foreach ($artistNames as $name): ?><option value="<?= h($name) ?>"><?php endforeach; ?>
</datalist>
