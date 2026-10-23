<?php
declare(strict_types=1);

use App\Collections\LastFmSync;
use App\Collections\Repository;
use App\Concerts\ConcertRepository;
use App\Concerts\ConcertSync;
use App\Concerts\TicketmasterClient;
use App\Core\Db;
use App\Core\Settings;
use App\Core\View;

require __DIR__ . '/bootstrap.php';

$route = (string) ($_GET['r'] ?? 'collections');
$page = max(1, (int) ($_GET['page'] ?? 1));

switch ($route) {
    case 'collections':
        View::page('collections/overview', [
            'totals' => Repository::totals(),
            'discogs' => Settings::json('discogs_profile'),
            'lastfm' => Settings::json('lastfm_profile'),
            'lastSync' => Settings::get('discogs_last_sync'),
            'genres' => array_slice(Repository::genres(), 0, 10),
            'decades' => Repository::decades(),
            'formats' => Repository::formats(),
            'topMine' => Repository::topAlbums('myplays', 12),
            'topGlobal' => Repository::topAlbums('listeners', 12),
            'unheard' => Repository::unheard(12),
            'recent' => Repository::recent(12),
            'artistsMine' => Repository::topArtists('myplays', 10),
            'artistsOwned' => Repository::topArtists('releases', 10),
        ], 'Coleção', 'overview');
        break;

    case 'collections/albums':
        $filters = array_map('strval', array_intersect_key($_GET, array_flip(['q', 'genre', 'style', 'format', 'decade', 'lf', 'sort'])));
        View::page('collections/albums', [
            'filters' => $filters,
            'result' => Repository::albums($filters, $page),
            'page' => $page,
            'perPage' => 48,
            'genres' => Repository::genres(),
            'styles' => Repository::styles(),
            'formats' => Repository::formats(),
            'decades' => Repository::decades(),
        ], 'Discos', 'albums');
        break;

    case 'collections/album':
        $id = (int) ($_GET['id'] ?? 0);
        $album = Repository::album($id);
        if (!$album) {
            http_response_code(404);
            View::page('not-found', [], 'Não encontrado');
            break;
        }
        View::page('collections/album', [
            'album' => $album,
            'artists' => Repository::albumArtists($id),
            'related' => Repository::related($id),
        ], $album['title'], 'albums');
        break;

    case 'collections/artists':
        $filters = array_map('strval', array_intersect_key($_GET, array_flip(['q', 'sort'])));
        View::page('collections/artists', [
            'filters' => $filters,
            'result' => Repository::artists($filters, $page),
            'page' => $page,
            'perPage' => 60,
        ], 'Artistas', 'artists');
        break;

    case 'collections/artist':
        $id = (int) ($_GET['id'] ?? 0);
        $artist = Repository::artist($id);
        if (!$artist) {
            http_response_code(404);
            View::page('not-found', [], 'Não encontrado');
            break;
        }
        View::page('collections/artist', [
            'artist' => $artist,
            'releases' => Repository::artistReleases($id),
            'concerts' => ConcertRepository::forArtist($id),
        ], $artist['name'], 'artists');
        break;

    case 'collections/sync':
        View::page('collections/sync', [
            'counts' => LastFmSync::counts(),
            'discogs' => Settings::json('discogs_profile'),
            'lastSync' => Settings::get('discogs_last_sync'),
            'hasDiscogs' => Settings::get('discogs_username') !== '',
            'hasLastfm' => Settings::get('lastfm_api_key') !== '',
        ], 'Sincronizar', 'sync');
        break;

    case 'concerts':
        $filters = array_map('strval', array_intersect_key($_GET, array_flip(['q', 'when', 'country', 'city', 'mine'])));
        View::page('concerts/index', [
            'filters' => $filters,
            'result' => ConcertRepository::list($filters, $page),
            'page' => $page,
            'perPage' => 50,
            'places' => ConcertRepository::places(),
            'totals' => ConcertRepository::totals(),
            'hasApi' => Settings::get('tm_api_key') !== '',
        ], 'Concertos', 'concerts');
        break;

    case 'concerts/edit':
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $concert = $id !== null ? ConcertRepository::find($id) : null;
        if ($id !== null && !$concert) {
            http_response_code(404);
            View::page('not-found', [], 'Não encontrado');
            break;
        }
        $back = url('concerts');
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['csrf'] ?? null)) {
                flash('Sessão expirada. Tenta de novo.', 'error');
                redirect(url('concerts/edit', ['id' => $id]));
            }
            $op = (string) ($_POST['op'] ?? 'save');
            if ($id !== null && $op === 'delete') {
                ConcertRepository::delete($id);
                flash('Concerto removido da agenda.');
                redirect($back);
            }
            if ($id !== null && $op === 'revert') {
                ConcertRepository::revert($id);
                flash('A próxima sincronização volta a atualizar este concerto.');
                redirect(url('concerts/edit', ['id' => $id]));
            }
            [$data, $errors] = ConcertRepository::validate($_POST);
            if (!$errors) {
                ConcertRepository::save($data, $id);
                flash($id === null ? 'Concerto adicionado.' : 'Concerto guardado.');
                redirect($back);
            }
            $concert = $data + ($concert ?? []);
        }
        // Atalho a partir da página do artista: ?artist=Nome
        $concert ??= ['artist' => trim((string) ($_GET['artist'] ?? ''))];
        View::page('concerts/edit', [
            'id' => $id,
            'c' => $concert,
            'errors' => $errors,
            'artistNames' => array_column(ConcertRepository::artistNames(), 'name'),
        ], $id === null ? 'Novo concerto' : 'Editar concerto', 'concerts');
        break;

    case 'concerts/sync':
        View::page('concerts/sync', [
            'counts' => ConcertSync::counts(),
            'hasApi' => Settings::get('tm_api_key') !== '',
            'countries' => TicketmasterClient::countries(),
            'hasArtists' => (bool) Db::value('SELECT 1 FROM release_artists LIMIT 1'),
        ], 'Sincronizar concertos', 'concerts-sync');
        break;

    case 'settings':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['csrf'] ?? null)) {
                flash('Sessão expirada. Tenta de novo.', 'error');
            } else {
                $oldUser = Settings::get('discogs_username');
                $oldLfUser = Settings::get('lastfm_username');
                $oldCountries = TicketmasterClient::countries();
                foreach (['discogs_username', 'discogs_token', 'lastfm_api_key', 'lastfm_username', 'tm_api_key', 'tm_countries'] as $key) {
                    Settings::set($key, trim((string) ($_POST[$key] ?? '')));
                }
                if ($oldCountries !== TicketmasterClient::countries()) {
                    // Outros países: é preciso voltar a procurar os concertos de cada artista.
                    ConcertSync::reset('all');
                }
                if (strcasecmp($oldLfUser, Settings::get('lastfm_username')) !== 0) {
                    // As audições pessoais dependem do utilizador: voltam a ser obtidas.
                    LastFmSync::reset('all');
                    Settings::set('lastfm_profile', []);
                }
                if (strcasecmp($oldUser, Settings::get('discogs_username')) !== 0) {
                    Settings::set('discogs_profile', []);
                }
                flash('Definições guardadas.');
            }
            header('Location: ' . url('settings'));
            exit;
        }
        View::page('settings', [
            'values' => [
                'discogs_username' => Settings::get('discogs_username'),
                'discogs_token' => Settings::get('discogs_token'),
                'lastfm_api_key' => Settings::get('lastfm_api_key'),
                'lastfm_username' => Settings::get('lastfm_username'),
                'tm_api_key' => Settings::get('tm_api_key'),
                'tm_countries' => Settings::get('tm_countries'),
            ],
        ], 'Definições', 'settings');
        break;

    default:
        http_response_code(404);
        View::page('not-found', [], 'Não encontrado');
}
