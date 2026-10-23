<?php
declare(strict_types=1);

namespace App\Concerts;

use App\Collections\Names;
use App\Core\ApiException;
use App\Core\Db;

/** Importa concertos da Ticketmaster para a agenda, sem tocar nos registos editados à mão. */
final class ConcertSync
{
    /** A Ticketmaster aceita no máximo 5 pedidos por segundo. */
    private const PAUSE_US = 250_000;
    private const IGNORED = '(' . Names::VARIOUS_ID . ',' . Names::UNKNOWN_ID . ')';
    /** Erros que invalidam a sincronização inteira (key inválida, quota esgotada). */
    private const FATAL = [401, 403, 429];

    /** @var array<string,int>|null nome normalizado → id do artista na coleção */
    private ?array $artistIndex = null;

    public function __construct(private TicketmasterClient $client)
    {
    }

    public static function counts(): array
    {
        $rows = Db::all('SELECT COALESCE(tm_status, \'pending\') AS s, COUNT(*) AS n FROM artists
                         WHERE id NOT IN ' . self::IGNORED . ' AND id IN (SELECT artist_id FROM release_artists) GROUP BY s');
        $out = ['pending' => 0, 'ok' => 0, 'notfound' => 0, 'error' => 0];
        foreach ($rows as $r) {
            $out[$r['s']] = (int) $r['n'];
        }
        $out['total'] = array_sum($out);
        $out['concerts'] = (int) Db::value("SELECT COUNT(*) FROM concerts WHERE hidden = 0 AND date >= date('now', 'localtime')");
        return $out;
    }

    /** Volta a marcar os artistas como pendentes. $scope: all | errors */
    public static function reset(string $scope): void
    {
        Db::run('UPDATE artists SET tm_status = NULL' . ($scope === 'errors' ? " WHERE tm_status = 'error'" : ''));
    }

    /** Procura concertos de um lote de artistas pendentes; pára aos $limit artistas ou ~20 s. */
    public function step(int $limit = 10): array
    {
        $started = microtime(true);
        $rows = Db::all('SELECT a.* FROM artists a WHERE a.tm_status IS NULL AND a.id NOT IN ' . self::IGNORED . '
                         AND a.id IN (SELECT artist_id FROM release_artists)
                         ORDER BY a.lf_userplaycount IS NULL, a.lf_userplaycount DESC LIMIT ' . $limit);
        $processed = 0;
        $found = 0;
        foreach ($rows as $row) {
            $found += $this->updateArtist($row);
            $processed++;
            if (microtime(true) - $started > 20) {
                break;
            }
        }
        return ['processed' => $processed, 'found' => $found, 'counts' => self::counts()];
    }

    /** Devolve o número de concertos encontrados para o artista. */
    public function updateArtist(array $row): int
    {
        $status = 'notfound';
        $tmId = null;
        $found = 0;
        try {
            $attraction = $this->client->findAttraction($row['name']);
            usleep(self::PAUSE_US);
            if ($attraction) {
                $status = 'ok';
                $tmId = $attraction['id'];
                // Sem eventos futuros, não vale a pena gastar mais pedidos.
                if ($attraction['upcoming'] > 0) {
                    foreach (TicketmasterClient::countries() ?: [null] as $country) {
                        $query = ['attractionId' => $tmId] + ($country ? ['countryCode' => $country] : []);
                        $r = $this->client->events($query);
                        usleep(self::PAUSE_US);
                        foreach ($r['events'] as $e) {
                            $this->upsert($e, (int) $row['id'], $attraction['name']);
                            $found++;
                        }
                    }
                }
            }
        } catch (ApiException $e) {
            if (in_array($e->getCode(), self::FATAL, true)) {
                throw $e;
            }
            $status = 'error';
        }
        Db::run('UPDATE artists SET tm_status = ?, tm_id = ?, tm_checked_at = ? WHERE id = ?', [$status, $tmId, date('c'), (int) $row['id']]);
        return $found;
    }

    /** Importa uma página da agenda geral de música de um país (e opcionalmente cidade). */
    public function importArea(string $country, string $city, int $page): array
    {
        $query = ['classificationName' => 'music', 'countryCode' => strtoupper($country)] + ($city !== '' ? ['city' => $city] : []);
        $r = $this->client->events($query, $page);
        Db::transaction(function () use ($r): void {
            foreach ($r['events'] as $e) {
                $this->upsert($e);
            }
        });
        $pages = max(1, $r['pages']);
        return ['page' => $page, 'pages' => $pages, 'total' => $r['total'], 'done' => $page + 1 >= $pages, 'counts' => self::counts()];
    }

    private function upsert(array $e, ?int $artistId = null, ?string $artistName = null): void
    {
        // Sem artista conhecido, tenta reconhecer alguém da coleção no cartaz.
        if ($artistId === null) {
            foreach ($e['lineup'] as $name) {
                if ($id = $this->artistIndex()[TicketmasterClient::key($name)] ?? null) {
                    [$artistId, $artistName] = [$id, $name];
                    break;
                }
            }
        }
        $now = date('c');
        Db::run(<<<'SQL'
            INSERT INTO concerts (source, source_id, artist, artist_id, title, lineup, date, time, venue, city, country,
                country_code, url, image, price, event_status, created_at, updated_at)
            VALUES ('ticketmaster', :source_id, :artist, :artist_id, :title, :lineup, :date, :time, :venue, :city, :country,
                :country_code, :url, :image, :price, :event_status, :now, :now)
            ON CONFLICT(source, source_id) DO UPDATE SET
                -- Um festival pode vir de vários artistas: fica o primeiro da coleção que o encontrou.
                artist = CASE WHEN concerts.artist_id IS NULL THEN excluded.artist ELSE concerts.artist END,
                artist_id = COALESCE(concerts.artist_id, excluded.artist_id),
                title = excluded.title, lineup = excluded.lineup, date = excluded.date, time = excluded.time,
                venue = excluded.venue, city = excluded.city, country = excluded.country,
                country_code = excluded.country_code, url = excluded.url, image = excluded.image,
                price = excluded.price, event_status = excluded.event_status, updated_at = excluded.updated_at
            WHERE concerts.edited = 0
            SQL, [
            'source_id' => $e['source_id'],
            'artist' => $artistName ?? ($e['lineup'][0] ?? $e['title']),
            'artist_id' => $artistId,
            'title' => $e['title'],
            'lineup' => implode(', ', $e['lineup']),
            'date' => $e['date'],
            'time' => $e['time'],
            'venue' => $e['venue'],
            'city' => $e['city'],
            'country' => $e['country'],
            'country_code' => $e['country_code'],
            'url' => $e['url'],
            'image' => $e['image'],
            'price' => $e['price'],
            'event_status' => $e['event_status'],
            'now' => $now,
        ]);
    }

    private function artistIndex(): array
    {
        return $this->artistIndex ??= ConcertRepository::artistIndex();
    }
}
