<?php
declare(strict_types=1);

namespace App\Collections;

use App\Core\ApiException;
use App\Core\Http;
use App\Core\Settings;

/**
 * API do Last.fm (gratuita, só requer API key). Com um utilizador definido,
 * album.getInfo e artist.getInfo devolvem também as audições desse utilizador.
 */
final class LastFmClient
{
    private const BASE = 'https://ws.audioscrobbler.com/2.0/';
    /** Erros que invalidam toda a sincronização (key inválida, suspensa, sem autenticação). */
    public const FATAL_ERRORS = [4, 10, 26];

    public function __construct(private string $apiKey, private string $username = '')
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('Define a API key do Last.fm nas Definições.');
        }
    }

    public static function fromSettings(): self
    {
        return new self(Settings::get('lastfm_api_key'), Settings::get('lastfm_username'));
    }

    public function hasUser(): bool
    {
        return $this->username !== '';
    }

    public function albumInfo(string $artist, string $album): ?array
    {
        $json = $this->call('album.getinfo', $this->withUser(['artist' => $artist, 'album' => $album, 'autocorrect' => 1]));
        $a = $json['album'] ?? null;
        if (!is_array($a)) {
            return null;
        }
        return [
            'name' => (string) ($a['name'] ?? $album),
            'url' => (string) ($a['url'] ?? ''),
            'listeners' => (int) ($a['listeners'] ?? 0),
            'playcount' => (int) ($a['playcount'] ?? 0),
            'userplaycount' => $this->hasUser() ? (int) ($a['userplaycount'] ?? 0) : null,
            'tags' => self::tags($a['tags'] ?? null),
            'summary' => self::summary($a['wiki']['summary'] ?? ''),
        ];
    }

    public function artistInfo(string $artist): ?array
    {
        $json = $this->call('artist.getinfo', $this->withUser(['artist' => $artist, 'autocorrect' => 1]));
        $a = $json['artist'] ?? null;
        if (!is_array($a)) {
            return null;
        }
        $stats = $a['stats'] ?? [];
        return [
            'name' => (string) ($a['name'] ?? $artist),
            'url' => (string) ($a['url'] ?? ''),
            'listeners' => (int) ($stats['listeners'] ?? 0),
            'playcount' => (int) ($stats['playcount'] ?? 0),
            'userplaycount' => $this->hasUser() ? (int) ($stats['userplaycount'] ?? 0) : null,
            'tags' => self::tags($a['tags'] ?? null),
            'summary' => self::summary($a['bio']['summary'] ?? ''),
        ];
    }

    public function userInfo(): ?array
    {
        if (!$this->hasUser()) {
            return null;
        }
        $u = $this->call('user.getinfo', ['user' => $this->username])['user'] ?? null;
        if (!is_array($u)) {
            return null;
        }
        $image = '';
        foreach ($u['image'] ?? [] as $img) {
            $image = (string) ($img['#text'] ?? $image) ?: $image;
        }
        return [
            'name' => (string) ($u['name'] ?? $this->username),
            'url' => (string) ($u['url'] ?? ''),
            'playcount' => (int) ($u['playcount'] ?? 0),
            'artist_count' => (int) ($u['artist_count'] ?? 0),
            'album_count' => (int) ($u['album_count'] ?? 0),
            'registered' => (int) ($u['registered']['unixtime'] ?? 0),
            'image' => $image,
        ];
    }

    private function withUser(array $params): array
    {
        if ($this->hasUser()) {
            $params['username'] = $this->username;
        }
        return $params;
    }

    /** Devolve null quando o Last.fm não conhece o item (erro 6). */
    private function call(string $method, array $params): ?array
    {
        $url = self::BASE . '?' . http_build_query(['method' => $method, 'api_key' => $this->apiKey, 'format' => 'json'] + $params);

        for ($attempt = 0; ; $attempt++) {
            $res = Http::get($url);
            $json = $res['json'];
            $error = is_array($json) && isset($json['error']) ? (int) $json['error'] : 0;
            // 8 operação falhou · 11/16 serviço indisponível · 29 rate limit
            $transient = in_array($error, [8, 11, 16, 29], true) || $res['status'] === 0 || $res['status'] >= 500;
            if ($transient && $attempt < 3) {
                sleep(2 * ($attempt + 1));
                continue;
            }
            break;
        }

        if ($error === 6) {
            return null;
        }
        if ($error !== 0) {
            $msg = $error === 10 ? 'API key inválida.' : (string) ($json['message'] ?? 'erro ' . $error);
            throw new ApiException('Last.fm: ' . $msg, $error);
        }
        if ($res['status'] === 0) {
            throw new ApiException('Last.fm: erro de rede (' . $res['error'] . ')');
        }
        if ($res['status'] >= 400 || !is_array($json)) {
            throw new ApiException('Last.fm: resposta inválida (HTTP ' . $res['status'] . ')', $res['status']);
        }
        return $json;
    }

    private static function tags(mixed $tags): array
    {
        if (!is_array($tags) || !isset($tags['tag']) || !is_array($tags['tag'])) {
            return [];
        }
        $list = isset($tags['tag']['name']) ? [$tags['tag']] : $tags['tag'];
        $names = array_map(static fn($t) => is_array($t) ? trim((string) ($t['name'] ?? '')) : '', $list);
        return array_slice(array_values(array_filter($names)), 0, 8);
    }

    private static function summary(mixed $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s*Read more on Last\.fm\.?\s*$/u', '', trim($text)) ?? $text;
        return trim($text);
    }
}
