<?php /** @var array $values */ ?>
<header class="page-head">
    <div>
        <h1>Definições</h1>
        <p class="muted">Contas usadas pelos módulos de Coleções e Concertos. Ficam guardadas apenas neste computador (<code>data/musica.sqlite</code>).</p>
    </div>
</header>

<form method="post" action="<?= url('settings') ?>" class="settings">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <fieldset class="panel">
        <legend>Discogs</legend>
        <label>
            <span>Utilizador Discogs <em>obrigatório</em></span>
            <input name="discogs_username" value="<?= h($values['discogs_username']) ?>" placeholder="ex.: o-teu-utilizador" autocomplete="off">
        </label>
        <label>
            <span>Token pessoal <em>opcional</em></span>
            <input name="discogs_token" type="password" value="<?= h($values['discogs_token']) ?>" autocomplete="off">
            <small>Só é preciso se a tua coleção for privada. Também sobe o limite de 25 para 60 pedidos/min.
                Gera-o gratuitamente em <a href="https://www.discogs.com/settings/developers" target="_blank" rel="noopener">discogs.com/settings/developers</a> → “Generate new token”.</small>
        </label>
    </fieldset>

    <fieldset class="panel">
        <legend>Last.fm</legend>
        <label>
            <span>API key <em>obrigatório</em></span>
            <input name="lastfm_api_key" type="password" value="<?= h($values['lastfm_api_key']) ?>" autocomplete="off">
            <small>Gratuita: cria uma conta de API em <a href="https://www.last.fm/api/account/create" target="_blank" rel="noopener">last.fm/api/account/create</a>
                (basta nome da aplicação e email) e copia a “API key”.</small>
        </label>
        <label>
            <span>Utilizador Last.fm <em>opcional</em></span>
            <input name="lastfm_username" value="<?= h($values['lastfm_username']) ?>" autocomplete="off">
            <small>Com o teu utilizador, cada disco e artista mostra também quantas vezes <strong>tu</strong> o ouviste.</small>
        </label>
    </fieldset>

    <fieldset class="panel">
        <legend>Ticketmaster (concertos)</legend>
        <label>
            <span>API key <em>opcional</em></span>
            <input name="tm_api_key" type="password" value="<?= h($values['tm_api_key']) ?>" autocomplete="off">
            <small>Gratuita (5000 pedidos/dia): regista-te em <a href="https://developer-acct.ticketmaster.com/user/register" target="_blank" rel="noopener">developer.ticketmaster.com</a>
                e copia a “Consumer Key” da app criada automaticamente. Sem ela, a agenda funciona só com concertos adicionados à mão.</small>
        </label>
        <label>
            <span>Países <em>opcional</em></span>
            <input name="tm_countries" value="<?= h($values['tm_countries']) ?>" placeholder="ex.: PT, ES" autocomplete="off">
            <small>Códigos de 2 letras separados por vírgulas. Vazio = procura em todos os países.</small>
        </label>
    </fieldset>

    <div class="actions">
        <button class="btn" type="submit">Guardar</button>
        <button class="btn btn-ghost" type="button" data-test>Testar ligações</button>
    </div>
    <ul class="test-results" aria-live="polite"></ul>
</form>
