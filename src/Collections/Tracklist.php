<?php
declare(strict_types=1);

namespace App\Collections;

use App\Core\Db;

/** Alinhamento dos discos, obtido do Discogs na primeira visita e guardado em cache. */
final class Tracklist
{
    public function __construct(private DiscogsClient $client)
    {
    }

    /** Obtém e guarda o alinhamento de um disco da coleção. */
    public function fetch(array $row): array
    {
        $release = $this->client->release((int) $row['release_id']);
        $tracks = self::normalize($release['tracklist'] ?? []);
        Db::run('UPDATE releases SET tracklist = ?, tracklist_at = ? WHERE instance_id = ?', [
            json_encode($tracks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            date('c'),
            (int) $row['instance_id'],
        ]);
        return $tracks;
    }

    /**
     * Converte o formato do Discogs numa lista simples. Cada linha:
     * ['kind' => track|heading|sub, 'position', 'title', 'duration', 'artists'].
     * As faixas "index" (suites, medleys) ficam como faixa seguida das sub-faixas.
     */
    public static function normalize(array $list): array
    {
        $out = [];
        foreach ($list as $t) {
            $type = (string) ($t['type_'] ?? 'track');
            $out[] = self::row($t, $type === 'heading' ? 'heading' : 'track');
            foreach ($type === 'index' ? ($t['sub_tracks'] ?? []) : [] as $sub) {
                $out[] = self::row($sub, 'sub');
            }
        }
        return $out;
    }

    /** Duração total em segundos (0 se o Discogs não tiver durações). */
    public static function totalSeconds(array $tracks): int
    {
        $total = 0;
        foreach ($tracks as $t) {
            if ($t['kind'] === 'track') {
                $total += self::seconds($t['duration']);
            }
        }
        return $total;
    }

    /** "5:12" → 312 · "1:02:03" → 3723 */
    public static function seconds(string $duration): int
    {
        if (!preg_match('/^\d+(:\d{1,2}){1,2}$/', trim($duration))) {
            return 0;
        }
        $s = 0;
        foreach (explode(':', trim($duration)) as $part) {
            $s = $s * 60 + (int) $part;
        }
        return $s;
    }

    public static function formatSeconds(int $s): string
    {
        return $s >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60)
            : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }

    private static function row(array $t, string $kind): array
    {
        return [
            'kind' => $kind,
            'position' => trim((string) ($t['position'] ?? '')),
            'title' => trim((string) ($t['title'] ?? '')),
            'duration' => trim((string) ($t['duration'] ?? '')),
            // Só existe em compilações ou faixas com convidados.
            'artists' => !empty($t['artists']) ? Names::display($t['artists']) : '',
        ];
    }
}
