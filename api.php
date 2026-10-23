<?php
declare(strict_types=1);

use App\Collections\DeezerClient;
use App\Collections\DiscogsClient;
use App\Collections\DiscogsSync;
use App\Collections\LastFmClient;
use App\Collections\LastFmSync;
use App\Collections\Repository;
use App\Collections\Tracklist;
use App\Concerts\BolClient;
use App\Concerts\ConcertRepository;
use App\Concerts\ConcertSync;
use App\Concerts\EventPage;
use App\Concerts\TicketmasterClient;
use App\Core\Settings;
use App\Core\View;

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(180);

function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    respond(['error' => 'Pedido inválido (sessão expirada?). Recarrega a página.'], 403);
}
// Liberta o lock da sessão para não bloquear outras páginas durante sincronizações longas.
session_write_close();

$action = (string) ($_GET['action'] ?? '');
$id = (int) ($_GET['id'] ?? 0);

try {
    switch ($action) {
        case 'discogs_page':
            $sync = new DiscogsSync(DiscogsClient::fromSettings());
            respond($sync->syncPage(max(1, (int) ($_GET['page'] ?? 1))));

        case 'lastfm_counts':
            respond(LastFmSync::counts());

        case 'lastfm_profile':
            $sync = new LastFmSync(LastFmClient::fromSettings());
            respond(['profile' => $sync->refreshProfile()]);

        case 'lastfm_step':
            $sync = new LastFmSync(LastFmClient::fromSettings());
            respond($sync->step(($_GET['kind'] ?? '') === 'artists' ? 'artists' : 'albums'));

        case 'lastfm_reset':
            LastFmSync::reset((string) ($_GET['scope'] ?? 'errors'));
            respond(LastFmSync::counts());

        case 'lastfm_album':
            $row = Repository::album($id) ?? respond(['error' => 'Disco não encontrado.'], 404);
            $status = (new LastFmSync(LastFmClient::fromSettings()))->updateAlbum($row);
            respond(['status' => $status]);

        case 'lastfm_artist':
            $row = Repository::artist($id) ?? respond(['error' => 'Artista não encontrado.'], 404);
            $status = (new LastFmSync(LastFmClient::fromSettings()))->updateArtist($row);
            respond(['status' => $status]);

        case 'tracklist':
            $row = Repository::album($id) ?? respond(['error' => 'Disco não encontrado.'], 404);
            $r = (new Tracklist(DiscogsClient::fromSettings()))->fetch($row);
            respond(['html' => View::partial('collections/tracklist', $r + ['fetchedAt' => date('c')])]);

        case 'album_audio':
            $row = Repository::album($id) ?? respond(['error' => 'Disco não encontrado.'], 404);
            if (!empty($_GET['retry'])) {
                DeezerClient::forget($id);
                $row['deezer_id'] = null;
            }
            respond((new DeezerClient())->albumAudio($row) + [
                'album' => ['title' => $row['title'], 'artist' => $row['artist'], 'cover' => $row['thumb'] ?: $row['cover']],
            ]);

        case 'concerts_counts':
            respond(ConcertSync::counts());

        case 'concerts_step':
            respond((new ConcertSync(TicketmasterClient::fromSettings()))->step());

        case 'concerts_reset':
            ConcertSync::reset((string) ($_GET['scope'] ?? 'errors'));
            respond(ConcertSync::counts());

        case 'concerts_bol':
            respond((new ConcertSync())->importBol(new BolClient(), !empty($_GET['only'])));

        case 'concerts_area':
            $country = (string) ($_GET['country'] ?? '');
            if (!preg_match('/^[A-Za-z]{2}$/', $country)) {
                respond(['error' => 'Indica um código de país com 2 letras (ex.: PT).'], 422);
            }
            $sync = new ConcertSync(TicketmasterClient::fromSettings());
            respond($sync->importArea($country, trim((string) ($_GET['city'] ?? '')), max(0, (int) ($_GET['page'] ?? 0))));

        case 'concert_from_url':
            $r = EventPage::read((string) ($_GET['url'] ?? ''));
            $dup = ConcertRepository::findByUrl((string) $r['fields']['url']);
            respond($r + ['duplicate' => $dup ? url('concerts/edit', ['id' => $dup['id']]) : null]);

        case 'concert_status':
            ConcertRepository::find($id) ?? respond(['error' => 'Concerto não encontrado.'], 404);
            ConcertRepository::setMyStatus($id, ($_GET['status'] ?? '') ?: null);
            respond(['ok' => true]);

        case 'test':
            $out = [];
            try {
                $p = DiscogsClient::fromSettings()->profile();
                $out['discogs'] = ['ok' => true, 'msg' => sprintf('Ligado como %s — %s discos na coleção.', $p['username'] ?? '?', num($p['num_collection'] ?? null))];
            } catch (Throwable $e) {
                $out['discogs'] = ['ok' => false, 'msg' => $e->getMessage()];
            }
            try {
                $lf = LastFmClient::fromSettings();
                $probe = $lf->artistInfo('Radiohead');
                $user = $lf->userInfo();
                $msg = $probe ? 'API key válida.' : 'API key aceite.';
                if ($user) {
                    $msg .= sprintf(' Utilizador %s com %s scrobbles.', $user['name'], num($user['playcount']));
                    Settings::set('lastfm_profile', $user);
                } elseif (Settings::get('lastfm_username') !== '') {
                    $msg .= ' Atenção: utilizador Last.fm não encontrado.';
                }
                $out['lastfm'] = ['ok' => true, 'msg' => $msg];
            } catch (Throwable $e) {
                $out['lastfm'] = ['ok' => false, 'msg' => $e->getMessage()];
            }
            if (Settings::get('tm_api_key') !== '') {
                try {
                    $r = TicketmasterClient::fromSettings()->events(['classificationName' => 'music', 'size' => 1]
                        + (($c = TicketmasterClient::countries()) ? ['countryCode' => $c[0]] : []));
                    $out['ticketmaster'] = ['ok' => true, 'msg' => sprintf('API key válida — %s concertos%s.', num($r['total']), $c ? ' em ' . $c[0] : '')];
                } catch (Throwable $e) {
                    $out['ticketmaster'] = ['ok' => false, 'msg' => $e->getMessage()];
                }
            }
            respond($out);

        default:
            respond(['error' => 'Ação desconhecida.'], 404);
    }
} catch (Throwable $e) {
    respond(['error' => $e->getMessage()], 500);
}
