<?php
declare(strict_types=1);

/**
 * Sincronização pela linha de comandos (útil no Agendador de Tarefas do Windows):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\musica\sync.php [--discogs] [--lastfm] [--concerts] [--bol] [--bol-all] [--refresh]
 * Sem opções corre tudo (Ticketmaster só se houver API key).
 * --bol importa da BOL só os concertos de artistas da coleção; --bol-all importa todos.
 * --refresh volta a obter os dados Last.fm e a procurar os concertos de todos os artistas.
 */

use App\Collections\DiscogsClient;
use App\Collections\DiscogsSync;
use App\Collections\LastFmClient;
use App\Collections\LastFmSync;
use App\Concerts\BolClient;
use App\Concerts\ConcertSync;
use App\Concerts\TicketmasterClient;
use App\Core\Settings;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Apenas linha de comandos.');
}

require __DIR__ . '/bootstrap.php';
set_time_limit(0);

$args = array_slice($argv, 1);
$picked = array_intersect($args, ['--discogs', '--lastfm', '--concerts', '--bol', '--bol-all']);
$doDiscogs = !$picked || in_array('--discogs', $args, true);
$doLastfm = !$picked || in_array('--lastfm', $args, true);
$doConcerts = $picked ? in_array('--concerts', $args, true) : Settings::get('tm_api_key') !== '';
$bolAll = in_array('--bol-all', $args, true);
$doBol = !$picked || $bolAll || in_array('--bol', $args, true);

try {
    if ($doDiscogs) {
        echo "Discogs: a importar coleção...\n";
        $r = (new DiscogsSync(DiscogsClient::fromSettings()))->syncAll(static function (array $p): void {
            echo "  página {$p['page']}/{$p['pages']}\n";
        });
        echo "  {$r['items']} discos; {$r['removed']} removidos.\n";
    }

    if ($doLastfm) {
        if (in_array('--refresh', $args, true)) {
            LastFmSync::reset('all');
        }
        $sync = new LastFmSync(LastFmClient::fromSettings());
        $sync->refreshProfile();
        foreach (['albums' => 'discos', 'artists' => 'artistas'] as $kind => $label) {
            echo "Last.fm: $label...\n";
            do {
                $r = $sync->step($kind);
                $c = $r['counts'][$kind];
                echo "  {$c['ok']} encontrados, {$c['notfound']} sem dados, {$c['error']} erros, {$c['pending']} pendentes\n";
            } while ($r['processed'] > 0);
        }
    }

    if ($doConcerts) {
        if (in_array('--refresh', $args, true)) {
            ConcertSync::reset('all');
        }
        echo "Ticketmaster: concertos dos artistas...\n";
        $sync = new ConcertSync(TicketmasterClient::fromSettings());
        $found = 0;
        do {
            $r = $sync->step();
            $found += $r['found'];
            $c = $r['counts'];
            echo "  {$c['ok']} encontrados, {$c['notfound']} sem registo, {$c['error']} erros, {$c['pending']} pendentes · $found datas\n";
        } while ($r['processed'] > 0);
    }

    if ($doBol) {
        echo 'BOL: concertos de música' . ($bolAll ? '' : ' de artistas da coleção') . "...\n";
        $r = (new ConcertSync())->importBol(new BolClient(), !$bolAll);
        echo "  {$r['total']} eventos na BOL, {$r['collection']} de artistas da coleção, {$r['imported']} importados.\n";
    }
    echo "Concluído.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}
