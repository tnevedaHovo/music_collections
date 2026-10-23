<?php
declare(strict_types=1);

namespace App\Collections;

use App\Core\ApiException;
use App\Core\Db;
use App\Core\Http;

/**
 * API pública do Deezer (gratuita, sem chave). Dá excertos MP3 de 30 s de cada faixa.
 * Os links dos excertos expiram ao fim de algumas horas, por isso só se guarda o id do álbum.
 */
final class DeezerClient
{
    private const BASE = 'https://api.deezer.com/';

    /**
     * Faixas do disco no Deezer, com a posição no alinhamento do Discogs quando a encontra.
     * @return array{found:bool, link?:string, tracks?:array}
     */
    public function albumAudio(array $row): array
    {
        $id = $row['deezer_id'] === null ? $this->findAndStore($row) : (int) $row['deezer_id'];
        if ($id <= 0) {
            return ['found' => false];
        }
        $json = $this->get('album/' . $id . '/tracks', ['limit' => 300]);
        $tracks = [];
        foreach ($json['data'] ?? [] as $t) {
            if (empty($t['preview']) || ($t['readable'] ?? true) === false) {
                continue;
            }
            $tracks[] = [
                'title' => (string) $t['title'],
                'artist' => (string) ($t['artist']['name'] ?? ''),
                'duration' => (int) ($t['duration'] ?? 0),
                'preview' => (string) $t['preview'],
                'index' => null,
            ];
        }
        return [
            'found' => true,
            'link' => 'https://www.deezer.com/album/' . $id,
            'tracks' => self::match($tracks, json_list($row['tracklist'])),
        ];
    }

    /** Volta a procurar o álbum (depois de mudar o título no Discogs, por exemplo). */
    public static function forget(int $instanceId): void
    {
        Db::run('UPDATE releases SET deezer_id = NULL WHERE instance_id = ?', [$instanceId]);
    }

    private function findAndStore(array $row): int
    {
        $id = $this->findAlbum($row['main_artist'], $row['title']);
        Db::run('UPDATE releases SET deezer_id = ?, deezer_at = ? WHERE instance_id = ?', [$id, date('c'), (int) $row['instance_id']]);
        return $id;
    }

    /** Id do álbum no Deezer, ou 0 se não houver um do mesmo artista com o mesmo título. */
    private function findAlbum(string $artist, string $title): int
    {
        $various = $artist === 'Various Artists';
        $queries = [];
        foreach (Names::titleCandidates($title) as $candidate) {
            $queries[] = [$candidate, $various ? 'album:"' . $candidate . '"' : 'artist:"' . $artist . '" album:"' . $candidate . '"'];
        }
        // A pesquisa por campos é exigente com a grafia; a livre apanha "GHOSTWOMAN" para "Ghost Woman".
        foreach (Names::titleCandidates($title) as $candidate) {
            $queries[] = [$candidate, ($various ? '' : $artist . ' ') . $candidate];
        }
        foreach ($queries as [$candidate, $q]) {
            $results = $this->get('search/album', ['q' => $q, 'limit' => 25])['data'] ?? [];
            $titleKey = Names::trackKey($candidate);
            $best = null;
            foreach ($results as $a) {
                if (!$various && !self::sameArtist((string) ($a['artist']['name'] ?? ''), $artist)) {
                    continue;
                }
                $k = Names::trackKey((string) ($a['title'] ?? ''));
                // Título igual ganha; senão aceita "OK Computer OKNOTOK" para "OK Computer".
                $score = $k === $titleKey ? 2 : (str_starts_with($k, $titleKey) ? 1 : 0);
                if ($score > 0 && ($best === null || $score > $best[0])) {
                    $best = [$score, (int) $a['id']];
                }
            }
            if ($best) {
                return $best[1];
            }
        }
        return 0;
    }

    /** "††† (Crosses)" serve para "†††"; nomes só com símbolos não têm chave e comparam-se em bruto. */
    private static function sameArtist(string $theirs, string $ours): bool
    {
        $k = Names::key($ours);
        if ($k !== '') {
            return Names::key($theirs) === $k || Names::key(preg_replace('/\s*\([^)]*\)$/u', '', $theirs) ?? $theirs) === $k;
        }
        return str_starts_with(trim($theirs), trim($ours));
    }

    /** Liga cada faixa do Deezer à linha do alinhamento com o mesmo título (ou pela ordem). */
    private static function match(array $deezer, array $tracklist): array
    {
        $ours = array_filter($tracklist, static fn($t) => $t['kind'] !== 'heading');
        $free = [];
        foreach ($ours as $i => $t) {
            $free[$i] = Names::trackKey($t['title']);
        }
        foreach ($deezer as $n => $t) {
            $i = array_search(Names::trackKey($t['title']), $free, true);
            if ($i !== false) {
                $deezer[$n]['index'] = $i;
                unset($free[$i]);
            }
        }
        // Mesma quantidade de faixas: as que sobram casam pela ordem.
        if (count($deezer) === count(array_filter($ours, static fn($t) => $t['kind'] === 'track'))) {
            $left = array_keys(array_filter($free, static fn($i) => $tracklist[$i]['kind'] === 'track', ARRAY_FILTER_USE_KEY));
            foreach ($deezer as $n => $t) {
                if ($t['index'] === null && $left) {
                    $deezer[$n]['index'] = array_shift($left);
                }
            }
        }
        return $deezer;
    }

    private function get(string $path, array $query): array
    {
        $url = self::BASE . $path . '?' . http_build_query($query);
        for ($attempt = 0; ; $attempt++) {
            $res = Http::get($url, [], 15);
            $code = (int) ($res['json']['error']['code'] ?? 0);
            // 4 = quota (50 pedidos / 5 s)
            if (($code === 4 || $res['status'] === 0 || $res['status'] >= 500) && $attempt < 2) {
                sleep(2);
                continue;
            }
            break;
        }
        if ($res['status'] === 0) {
            throw new ApiException('Deezer: erro de rede (' . $res['error'] . ')');
        }
        if ($code === 800) {
            return []; // "no data"
        }
        if ($code !== 0 || !is_array($res['json'])) {
            throw new ApiException('Deezer: ' . ($res['json']['error']['message'] ?? 'HTTP ' . $res['status']));
        }
        return $res['json'];
    }
}
