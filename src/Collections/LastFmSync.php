<?php
declare(strict_types=1);

namespace App\Collections;

use App\Core\ApiException;
use App\Core\Db;
use App\Core\Settings;

/** Enriquece discos e artistas da coleção com dados de audição do Last.fm. */
final class LastFmSync
{
    /** Pausa entre pedidos: o Last.fm pede no máximo ~5 pedidos/segundo. */
    private const PAUSE_US = 250_000;
    private const IGNORED = '(' . Names::VARIOUS_ID . ',' . Names::UNKNOWN_ID . ')';

    public function __construct(private LastFmClient $client)
    {
    }

    public static function counts(): array
    {
        $albums = Db::all('SELECT COALESCE(lf_status, \'pending\') AS s, COUNT(*) AS n FROM releases GROUP BY s');
        $artists = Db::all('SELECT COALESCE(lf_status, \'pending\') AS s, COUNT(*) AS n FROM artists WHERE id NOT IN ' . self::IGNORED . ' GROUP BY s');
        $fold = static function (array $rows): array {
            $out = ['pending' => 0, 'ok' => 0, 'notfound' => 0, 'error' => 0];
            foreach ($rows as $r) {
                $out[$r['s']] = (int) $r['n'];
            }
            $out['total'] = array_sum($out);
            return $out;
        };
        return ['albums' => $fold($albums), 'artists' => $fold($artists)];
    }

    /** Processa um lote de pendentes; pára aos $limit itens ou ~20 s. */
    public function step(string $kind, int $limit = 15): array
    {
        $started = microtime(true);
        $rows = $kind === 'artists'
            ? Db::all('SELECT * FROM artists WHERE lf_status IS NULL AND id NOT IN ' . self::IGNORED . ' LIMIT ' . $limit)
            : Db::all('SELECT * FROM releases WHERE lf_status IS NULL ORDER BY date_added DESC LIMIT ' . $limit);

        $processed = 0;
        foreach ($rows as $row) {
            $kind === 'artists' ? $this->updateArtist($row) : $this->updateAlbum($row);
            $processed++;
            if (microtime(true) - $started > 20) {
                break;
            }
        }
        return ['processed' => $processed, 'counts' => self::counts()];
    }

    public function updateAlbum(array $row): string
    {
        try {
            $info = null;
            foreach (Names::titleCandidates($row['title']) as $title) {
                $info = $this->client->albumInfo($row['main_artist'], $title);
                usleep(self::PAUSE_US);
                if ($info) {
                    break;
                }
            }
            $status = $info ? 'ok' : 'notfound';
        } catch (ApiException $e) {
            if (in_array($e->getCode(), LastFmClient::FATAL_ERRORS, true)) {
                throw $e;
            }
            $info = null;
            $status = 'error';
        }
        $this->save('releases', 'instance_id', (int) $row['instance_id'], $status, $info);
        return $status;
    }

    public function updateArtist(array $row): string
    {
        try {
            $info = $this->client->artistInfo($row['name']);
            usleep(self::PAUSE_US);
            $status = $info ? 'ok' : 'notfound';
        } catch (ApiException $e) {
            if (in_array($e->getCode(), LastFmClient::FATAL_ERRORS, true)) {
                throw $e;
            }
            $info = null;
            $status = 'error';
        }
        $this->save('artists', 'id', (int) $row['id'], $status, $info);
        return $status;
    }

    public function refreshProfile(): ?array
    {
        $profile = $this->client->userInfo();
        Settings::set('lastfm_profile', $profile ?? []);
        return $profile;
    }

    /** Volta a marcar itens como pendentes. $scope: all | errors | notfound */
    public static function reset(string $scope): void
    {
        $where = match ($scope) {
            'errors' => "WHERE lf_status = 'error'",
            'notfound' => "WHERE lf_status = 'notfound'",
            default => '',
        };
        Db::run("UPDATE releases SET lf_status = NULL $where");
        Db::run("UPDATE artists SET lf_status = NULL $where");
    }

    private function save(string $table, string $pk, int $id, string $status, ?array $info): void
    {
        Db::run(
            "UPDATE $table SET lf_status = :status, lf_name = :name, lf_url = :url, lf_listeners = :listeners,
                lf_playcount = :playcount, lf_userplaycount = :userplaycount, lf_tags = :tags, lf_summary = :summary,
                lf_updated_at = :now WHERE $pk = :id",
            [
                'status' => $status,
                'name' => $info['name'] ?? null,
                'url' => $info['url'] ?? null,
                'listeners' => $info['listeners'] ?? null,
                'playcount' => $info['playcount'] ?? null,
                'userplaycount' => $info['userplaycount'] ?? null,
                'tags' => $info ? json_encode($info['tags'], JSON_UNESCAPED_UNICODE) : null,
                'summary' => ($info['summary'] ?? '') !== '' ? $info['summary'] : null,
                'now' => date('c'),
                'id' => $id,
            ]
        );
    }
}
