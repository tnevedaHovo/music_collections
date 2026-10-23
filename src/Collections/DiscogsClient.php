<?php
declare(strict_types=1);

namespace App\Collections;

use App\Core\ApiException;
use App\Core\Http;
use App\Core\Settings;

/**
 * API pública do Discogs. Coleções públicas leem-se sem autenticação (25 pedidos/min);
 * com um token pessoal (gratuito) lê-se também a coleção privada do próprio, a 60 pedidos/min.
 */
final class DiscogsClient
{
    private const BASE = 'https://api.discogs.com';

    public function __construct(private string $username, private string $token = '')
    {
    }

    public static function fromSettings(): self
    {
        return new self(Settings::get('discogs_username'), Settings::get('discogs_token'));
    }

    public function profile(): array
    {
        return $this->get('/users/' . rawurlencode($this->user()));
    }

    public function collectionPage(int $page, int $perPage = 100): array
    {
        return $this->get('/users/' . rawurlencode($this->user()) . '/collection/folders/0/releases', [
            'page' => $page,
            'per_page' => $perPage,
            'sort' => 'added',
            'sort_order' => 'desc',
        ]);
    }

    /** Ficha completa de uma edição (inclui o alinhamento). Não precisa de utilizador. */
    public function release(int $id): array
    {
        return $this->get('/releases/' . $id);
    }

    private function user(): string
    {
        if ($this->username === '') {
            throw new \RuntimeException('Define o utilizador Discogs nas Definições.');
        }
        return $this->username;
    }

    private function get(string $path, array $query = []): array
    {
        $url = self::BASE . $path . ($query ? '?' . http_build_query($query) : '');
        $headers = ['Accept: application/vnd.discogs.v2.discogs+json'];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Discogs token=' . $this->token;
        }

        for ($attempt = 0; ; $attempt++) {
            $res = Http::get($url, $headers);
            $retry = $res['status'] === 429 || $res['status'] >= 500 || $res['status'] === 0;
            if ($retry && $attempt < 3) {
                sleep(5 * ($attempt + 1));
                continue;
            }
            break;
        }

        // Abranda antes de esgotar a janela de 60 s do rate limit.
        $remaining = $res['headers']['x-discogs-ratelimit-remaining'] ?? null;
        if ($remaining !== null && (int) $remaining <= 2) {
            sleep(4);
        }

        if ($res['status'] === 0) {
            throw new ApiException('Discogs: erro de rede (' . $res['error'] . ')');
        }
        if ($res['status'] >= 400) {
            $msg = match ($res['status']) {
                401, 403 => 'Coleção privada ou token inválido. Torna a coleção pública no Discogs ou define um token pessoal nas Definições.',
                404 => str_starts_with($path, '/releases/') ? 'Edição não encontrada.' : 'Utilizador Discogs "' . $this->username . '" não encontrado.',
                429 => 'Limite de pedidos do Discogs atingido. Tenta de novo dentro de um minuto.',
                default => $res['json']['message'] ?? 'HTTP ' . $res['status'],
            };
            throw new ApiException('Discogs: ' . $msg, $res['status']);
        }
        if (!is_array($res['json'])) {
            throw new ApiException('Discogs: resposta inválida.');
        }
        return $res['json'];
    }
}
