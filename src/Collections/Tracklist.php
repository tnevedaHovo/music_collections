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

    /**
     * Obtém e guarda o alinhamento e os vídeos YouTube de um disco da coleção.
     * @return array{tracks:array, videos:array}
     */
    public function fetch(array $row): array
    {
        $release = $this->client->release((int) $row['release_id']);
        $tracks = self::normalize($release['tracklist'] ?? []);
        $videos = self::videos($release['videos'] ?? []);
        $json = static fn(array $v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        Db::run('UPDATE releases SET tracklist = ?, videos = ?, tracklist_at = ? WHERE instance_id = ?', [
            $json($tracks), $json($videos), date('c'), (int) $row['instance_id'],
        ]);
        return ['tracks' => $tracks, 'videos' => $videos];
    }

    /** Vídeos do Discogs → [['id' => id YouTube, 'title' => ...]] */
    public static function videos(array $list): array
    {
        $out = [];
        foreach ($list as $v) {
            if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})~', (string) ($v['uri'] ?? ''), $m)) {
                $out[$m[1]] = ['id' => $m[1], 'title' => trim((string) ($v['title'] ?? ''))];
            }
        }
        return array_values($out);
    }

    /**
     * Associa vídeos às faixas pelo título ("Artista - Faixa (Official Video)" contém "Faixa").
     * Acrescenta 'yt' (id do vídeo ou null) a cada faixa e devolve [faixas, vídeos sem faixa].
     */
    public static function attachVideos(array $tracks, array $videos): array
    {
        $keys = array_map(static fn($v) => Names::key($v['title']), $videos);
        $used = [];
        foreach ($tracks as $i => $t) {
            $tracks[$i]['yt'] = null;
            if ($t['kind'] === 'heading') {
                continue;
            }
            $k = Names::trackKey($t['title']);
            if (mb_strlen($k) < 3) {
                continue;
            }
            // Entre os vídeos que contêm o título, o mais curto é o que menos coisa a mais tem.
            $best = null;
            foreach ($keys as $j => $vk) {
                if (!isset($used[$j]) && str_contains($vk, $k) && ($best === null || strlen($vk) < strlen($keys[$best]))) {
                    $best = $j;
                }
            }
            if ($best !== null) {
                $used[$best] = true;
                $tracks[$i]['yt'] = $videos[$best]['id'];
            }
        }
        return [$tracks, array_values(array_diff_key($videos, $used))];
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
