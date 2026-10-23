<?php
declare(strict_types=1);

namespace App\Collections;

use App\Core\Db;
use App\Core\Settings;

/**
 * Importa a coleção Discogs página a página (100 discos por pedido), para que o
 * browser mostre o progresso e nenhum pedido PHP exceda o tempo limite.
 */
final class DiscogsSync
{
    public function __construct(private DiscogsClient $client)
    {
    }

    /** @return array{page:int, pages:int, items:int, done:bool, removed:int} */
    public function syncPage(int $page): array
    {
        if ($page <= 1) {
            $page = 1;
            Settings::set('discogs_sync_token', bin2hex(random_bytes(6)));
            $p = $this->client->profile();
            Settings::set('discogs_profile', [
                'username' => $p['username'] ?? '',
                'name' => $p['name'] ?? '',
                'avatar' => $p['avatar_url'] ?? '',
                'url' => $p['uri'] ?? '',
                'num_collection' => $p['num_collection'] ?? null,
            ]);
        }
        $token = Settings::get('discogs_sync_token');

        $data = $this->client->collectionPage($page);
        $releases = $data['releases'] ?? [];
        Db::transaction(function () use ($releases, $token): void {
            foreach ($releases as $item) {
                $this->upsert($item, $token);
            }
        });

        $pages = max(1, (int) ($data['pagination']['pages'] ?? 1));
        $done = $page >= $pages;
        $removed = 0;
        if ($done) {
            $removed = $this->finish($token);
        }

        return [
            'page' => $page,
            'pages' => $pages,
            'items' => (int) ($data['pagination']['items'] ?? count($releases)),
            'done' => $done,
            'removed' => $removed,
        ];
    }

    /** Sincronização completa (para a linha de comandos). */
    public function syncAll(?callable $progress = null): array
    {
        $page = 1;
        do {
            $r = $this->syncPage($page);
            if ($progress) {
                $progress($r);
            }
            $page++;
        } while (!$r['done']);
        return $r;
    }

    private function upsert(array $item, string $token): void
    {
        $bi = $item['basic_information'] ?? [];
        $artists = $bi['artists'] ?? [];
        $first = $artists[0] ?? ['id' => 0, 'name' => ''];
        $mainArtist = (int) ($first['id'] ?? 0) === Names::VARIOUS_ID ? 'Various Artists' : Names::artist((string) ($first['name'] ?? ''));

        $formats = [];
        foreach ($bi['formats'] ?? [] as $f) {
            $qty = (int) ($f['qty'] ?? 1);
            $part = ($qty > 1 ? $qty . '× ' : '') . ($f['name'] ?? '');
            if (!empty($f['descriptions'])) {
                $part .= ' · ' . implode(', ', $f['descriptions']);
            }
            $formats[] = $part;
        }
        $labels = array_values(array_unique(array_map(static fn($l) => Names::artist((string) ($l['name'] ?? '')), $bi['labels'] ?? [])));

        Db::run(<<<'SQL'
            INSERT INTO releases (instance_id, release_id, master_id, folder_id, title, artist, main_artist, year,
                format_name, formats, labels, catno, genres, styles, thumb, cover, rating, date_added, sync_token)
            VALUES (:instance_id, :release_id, :master_id, :folder_id, :title, :artist, :main_artist, :year,
                :format_name, :formats, :labels, :catno, :genres, :styles, :thumb, :cover, :rating, :date_added, :sync_token)
            ON CONFLICT(instance_id) DO UPDATE SET
                release_id = excluded.release_id, master_id = excluded.master_id, folder_id = excluded.folder_id,
                -- Se o título ou o artista mudaram no Discogs, os dados Last.fm deixam de ser válidos.
                lf_status = CASE WHEN releases.title <> excluded.title OR releases.main_artist <> excluded.main_artist
                                 THEN NULL ELSE releases.lf_status END,
                tracklist = CASE WHEN releases.release_id <> excluded.release_id THEN NULL ELSE releases.tracklist END,
                title = excluded.title, artist = excluded.artist, main_artist = excluded.main_artist, year = excluded.year,
                format_name = excluded.format_name, formats = excluded.formats, labels = excluded.labels,
                catno = excluded.catno, genres = excluded.genres, styles = excluded.styles, thumb = excluded.thumb,
                cover = excluded.cover, rating = excluded.rating, date_added = excluded.date_added,
                sync_token = excluded.sync_token
            SQL, [
            'instance_id' => (int) $item['instance_id'],
            'release_id' => (int) ($bi['id'] ?? $item['id'] ?? 0),
            'master_id' => ($bi['master_id'] ?? 0) ?: null,
            'folder_id' => $item['folder_id'] ?? null,
            'title' => (string) ($bi['title'] ?? ''),
            'artist' => Names::display($artists),
            'main_artist' => $mainArtist,
            'year' => (int) ($bi['year'] ?? 0),
            'format_name' => $bi['formats'][0]['name'] ?? null,
            'formats' => implode(' + ', $formats),
            'labels' => implode(', ', $labels),
            'catno' => $bi['labels'][0]['catno'] ?? null,
            'genres' => json_encode($bi['genres'] ?? [], JSON_UNESCAPED_UNICODE),
            'styles' => json_encode($bi['styles'] ?? [], JSON_UNESCAPED_UNICODE),
            'thumb' => $bi['thumb'] ?? null,
            'cover' => $bi['cover_image'] ?? null,
            'rating' => (int) ($item['rating'] ?? 0),
            'date_added' => $item['date_added'] ?? null,
            'sync_token' => $token,
        ]);

        Db::run('DELETE FROM release_artists WHERE instance_id = ?', [(int) $item['instance_id']]);
        foreach ($artists as $pos => $a) {
            $id = (int) ($a['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            Db::run('INSERT INTO artists (id, name) VALUES (?, ?) ON CONFLICT(id) DO UPDATE SET name = excluded.name',
                [$id, Names::artist((string) $a['name'])]);
            Db::run('INSERT OR IGNORE INTO release_artists (instance_id, artist_id, position) VALUES (?, ?, ?)',
                [(int) $item['instance_id'], $id, $pos]);
        }
    }

    /** Remove o que saiu da coleção desde a última sincronização. */
    private function finish(string $token): int
    {
        return Db::transaction(static function () use ($token): int {
            $removed = Db::run('DELETE FROM releases WHERE sync_token IS NOT ?', [$token])->rowCount();
            Db::run('DELETE FROM artists WHERE id NOT IN (SELECT artist_id FROM release_artists)');
            Settings::set('discogs_last_sync', date('c'));
            return $removed;
        });
    }
}
