<?php
declare(strict_types=1);

namespace App\Collections;

use App\Core\Db;

/** Consultas de leitura usadas pelas páginas do módulo. */
final class Repository
{
    private const IGNORED = '(' . Names::VARIOUS_ID . ',' . Names::UNKNOWN_ID . ')';

    public const ALBUM_SORTS = [
        'added' => ['Adicionados recentemente', 'date_added DESC'],
        'artist' => ['Artista (A–Z)', 'artist COLLATE NOCASE ASC, year ASC'],
        'title' => ['Título (A–Z)', 'title COLLATE NOCASE ASC'],
        'year_desc' => ['Ano (mais recente)', '(year IS NULL OR year = 0), year DESC'],
        'year_asc' => ['Ano (mais antigo)', '(year IS NULL OR year = 0), year ASC'],
        'myplays' => ['As minhas audições', 'lf_userplaycount IS NULL, lf_userplaycount DESC'],
        'listeners' => ['Ouvintes no Last.fm', 'lf_listeners IS NULL, lf_listeners DESC'],
        'playcount' => ['Reproduções no Last.fm', 'lf_playcount IS NULL, lf_playcount DESC'],
        'rating' => ['A minha classificação', 'rating DESC, date_added DESC'],
    ];

    public const ARTIST_SORTS = [
        'releases' => ['N.º de discos', 'n_releases DESC, a.name COLLATE NOCASE'],
        'name' => ['Nome (A–Z)', 'a.name COLLATE NOCASE ASC'],
        'myplays' => ['As minhas audições', 'a.lf_userplaycount IS NULL, a.lf_userplaycount DESC'],
        'listeners' => ['Ouvintes no Last.fm', 'a.lf_listeners IS NULL, a.lf_listeners DESC'],
        'playcount' => ['Reproduções no Last.fm', 'a.lf_playcount IS NULL, a.lf_playcount DESC'],
    ];

    // ── Visão geral ──────────────────────────────────────────────

    public static function totals(): array
    {
        return [
            'releases' => (int) Db::value('SELECT COUNT(*) FROM releases'),
            'artists' => (int) Db::value('SELECT COUNT(DISTINCT artist_id) FROM release_artists WHERE artist_id NOT IN ' . self::IGNORED),
            'userplays' => (int) Db::value('SELECT COALESCE(SUM(lf_userplaycount), 0) FROM releases'),
            'unheard' => (int) Db::value('SELECT COUNT(*) FROM releases WHERE lf_userplaycount = 0'),
            'lf_ok' => (int) Db::value("SELECT COUNT(*) FROM releases WHERE lf_status = 'ok'"),
            'lf_done' => (int) Db::value('SELECT COUNT(*) FROM releases WHERE lf_status IS NOT NULL'),
            'has_userplays' => (bool) Db::value('SELECT 1 FROM releases WHERE lf_userplaycount IS NOT NULL LIMIT 1'),
        ];
    }

    public static function topAlbums(string $by, int $limit): array
    {
        $col = $by === 'myplays' ? 'lf_userplaycount' : 'lf_listeners';
        return Db::all("SELECT * FROM releases WHERE $col > 0 ORDER BY $col DESC LIMIT " . $limit);
    }

    public static function unheard(int $limit): array
    {
        return Db::all('SELECT * FROM releases WHERE lf_userplaycount = 0 ORDER BY RANDOM() LIMIT ' . $limit);
    }

    public static function recent(int $limit): array
    {
        return Db::all('SELECT * FROM releases ORDER BY date_added DESC LIMIT ' . $limit);
    }

    public static function topArtists(string $by, int $limit): array
    {
        $order = $by === 'myplays' ? 'a.lf_userplaycount DESC' : 'n_releases DESC, a.name COLLATE NOCASE';
        $where = $by === 'myplays' ? 'AND a.lf_userplaycount > 0' : '';
        return Db::all(
            'SELECT a.*, COUNT(ra.instance_id) AS n_releases FROM artists a
             JOIN release_artists ra ON ra.artist_id = a.id
             WHERE a.id NOT IN ' . self::IGNORED . " $where
             GROUP BY a.id ORDER BY $order LIMIT " . $limit
        );
    }

    // ── Facetas ──────────────────────────────────────────────────

    public static function genres(): array
    {
        return Db::all('SELECT j.value AS name, COUNT(*) AS n FROM releases r, json_each(r.genres) j GROUP BY j.value ORDER BY n DESC');
    }

    public static function styles(int $limit = 60): array
    {
        return Db::all('SELECT j.value AS name, COUNT(*) AS n FROM releases r, json_each(r.styles) j GROUP BY j.value ORDER BY n DESC LIMIT ' . $limit);
    }

    public static function formats(): array
    {
        return Db::all("SELECT COALESCE(format_name, 'Outro') AS name, COUNT(*) AS n FROM releases GROUP BY name ORDER BY n DESC");
    }

    public static function decades(): array
    {
        return Db::all('SELECT (year / 10) * 10 AS name, COUNT(*) AS n FROM releases WHERE year > 0 GROUP BY name ORDER BY name');
    }

    // ── Discos ───────────────────────────────────────────────────

    /** @return array{rows:array, total:int} */
    public static function albums(array $f, int $page, int $perPage = 48): array
    {
        $where = [];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where[] = '(title LIKE :q OR artist LIKE :q OR labels LIKE :q OR catno LIKE :q)';
            $params['q'] = '%' . $f['q'] . '%';
        }
        if (($f['genre'] ?? '') !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM json_each(releases.genres) WHERE value = :genre)';
            $params['genre'] = $f['genre'];
        }
        if (($f['style'] ?? '') !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM json_each(releases.styles) WHERE value = :style)';
            $params['style'] = $f['style'];
        }
        if (($f['format'] ?? '') !== '') {
            $where[] = 'format_name = :format';
            $params['format'] = $f['format'];
        }
        if (($f['decade'] ?? '') !== '' && ctype_digit((string) $f['decade'])) {
            $where[] = 'year BETWEEN :d1 AND :d2';
            $params['d1'] = (int) $f['decade'];
            $params['d2'] = (int) $f['decade'] + 9;
        }
        $where[] = match ($f['lf'] ?? '') {
            'unheard' => 'lf_userplaycount = 0',
            'heard' => 'lf_userplaycount > 0',
            'notfound' => "lf_status IN ('notfound', 'error')",
            'pending' => 'lf_status IS NULL',
            default => '1 = 1',
        };

        $sql = ' FROM releases WHERE ' . implode(' AND ', $where);
        $order = self::ALBUM_SORTS[$f['sort'] ?? ''][1] ?? self::ALBUM_SORTS['added'][1];
        $offset = max(0, ($page - 1) * $perPage);

        return [
            'total' => (int) Db::value('SELECT COUNT(*)' . $sql, $params),
            'rows' => Db::all('SELECT *' . $sql . " ORDER BY $order LIMIT $perPage OFFSET $offset", $params),
        ];
    }

    public static function album(int $instanceId): ?array
    {
        return Db::one('SELECT * FROM releases WHERE instance_id = ?', [$instanceId]);
    }

    public static function albumArtists(int $instanceId): array
    {
        return Db::all(
            'SELECT a.* FROM artists a JOIN release_artists ra ON ra.artist_id = a.id
             WHERE ra.instance_id = ? ORDER BY ra.position',
            [$instanceId]
        );
    }

    /** Outros discos dos mesmos artistas na coleção. */
    public static function related(int $instanceId, int $limit = 12): array
    {
        return Db::all(
            'SELECT DISTINCT r.* FROM releases r JOIN release_artists ra ON ra.instance_id = r.instance_id
             WHERE ra.artist_id IN (SELECT artist_id FROM release_artists WHERE instance_id = :id AND artist_id NOT IN ' . self::IGNORED . ')
               AND r.instance_id <> :id
             ORDER BY r.year LIMIT ' . $limit,
            ['id' => $instanceId]
        );
    }

    // ── Artistas ─────────────────────────────────────────────────

    /** @return array{rows:array, total:int} */
    public static function artists(array $f, int $page, int $perPage = 60): array
    {
        $where = 'a.id NOT IN ' . self::IGNORED;
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where .= ' AND a.name LIKE :q';
            $params['q'] = '%' . $f['q'] . '%';
        }
        $order = self::ARTIST_SORTS[$f['sort'] ?? ''][1] ?? self::ARTIST_SORTS['releases'][1];
        $offset = max(0, ($page - 1) * $perPage);

        return [
            'total' => (int) Db::value("SELECT COUNT(*) FROM artists a WHERE $where AND a.id IN (SELECT artist_id FROM release_artists)", $params),
            'rows' => Db::all(
                "SELECT a.*, COUNT(ra.instance_id) AS n_releases,
                        (SELECT r.thumb FROM releases r JOIN release_artists x ON x.instance_id = r.instance_id
                          WHERE x.artist_id = a.id AND r.thumb <> '' ORDER BY r.year LIMIT 1) AS thumb
                 FROM artists a JOIN release_artists ra ON ra.artist_id = a.id
                 WHERE $where GROUP BY a.id ORDER BY $order LIMIT $perPage OFFSET $offset",
                $params
            ),
        ];
    }

    public static function artist(int $id): ?array
    {
        return Db::one('SELECT * FROM artists WHERE id = ?', [$id]);
    }

    public static function artistReleases(int $id): array
    {
        return Db::all(
            'SELECT r.* FROM releases r JOIN release_artists ra ON ra.instance_id = r.instance_id
             WHERE ra.artist_id = ? ORDER BY (r.year = 0), r.year, r.title',
            [$id]
        );
    }
}
