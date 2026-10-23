<?php
declare(strict_types=1);

namespace App\Concerts;

use App\Collections\Names;
use App\Core\ApiException;
use App\Core\Http;
use App\Core\Settings;

/**
 * Ticketmaster Discovery API (gratuita: API key, 5000 pedidos/dia, 5 pedidos/s).
 * Cobre a Ticketmaster e as bilheteiras parceiras em vários países europeus e nas Américas.
 */
final class TicketmasterClient
{
    private const BASE = 'https://app.ticketmaster.com/discovery/v2/';
    /** A API não devolve além do elemento 1000 (size × page). */
    public const MAX_PAGES = 5;
    public const PAGE_SIZE = 200;

    public function __construct(private string $apiKey)
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('Define a API key da Ticketmaster nas Definições.');
        }
    }

    public static function fromSettings(): self
    {
        return new self(Settings::get('tm_api_key'));
    }

    /** Países configurados ("PT, ES" → ['PT', 'ES']); vazio = todo o mundo. */
    public static function countries(): array
    {
        preg_match_all('/[A-Za-z]{2}/', Settings::get('tm_countries'), $m);
        return array_values(array_unique(array_map('strtoupper', $m[0])));
    }

    /** Procura o artista pelo nome exato; devolve ['id', 'name', 'upcoming'] ou null. */
    public function findAttraction(string $name): ?array
    {
        $json = $this->get('attractions.json', ['keyword' => $name, 'classificationName' => 'music', 'size' => 20]);
        $wanted = self::key($name);
        $best = null;
        foreach ($json['_embedded']['attractions'] ?? [] as $a) {
            if (self::key((string) ($a['name'] ?? '')) !== $wanted) {
                continue;
            }
            $upcoming = (int) ($a['upcomingEvents']['_total'] ?? 0);
            if ($best === null || $upcoming > $best['upcoming']) {
                $best = ['id' => (string) $a['id'], 'name' => (string) $a['name'], 'upcoming' => $upcoming];
            }
        }
        return $best;
    }

    /** @return array{events:array, pages:int, total:int} */
    public function events(array $query, int $page = 0): array
    {
        $json = $this->get('events.json', $query + ['size' => self::PAGE_SIZE, 'page' => $page, 'sort' => 'date,asc']);
        $events = [];
        foreach ($json['_embedded']['events'] ?? [] as $e) {
            if ($n = self::normalize($e)) {
                $events[] = $n;
            }
        }
        return [
            'events' => $events,
            'pages' => min(self::MAX_PAGES, (int) ($json['page']['totalPages'] ?? 0)),
            'total' => (int) ($json['page']['totalElements'] ?? count($events)),
        ];
    }

    /** Converte um evento da API nos campos da tabela concerts. */
    private static function normalize(array $e): ?array
    {
        $start = $e['dates']['start'] ?? [];
        $date = (string) ($start['localDate'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null; // data por anunciar
        }
        $noTime = !empty($start['timeTBA']) || !empty($start['noSpecificTime']) || empty($start['localTime']);
        $venue = $e['_embedded']['venues'][0] ?? [];
        $lineup = array_values(array_filter(array_map(
            static fn($a) => trim((string) ($a['name'] ?? '')),
            $e['_embedded']['attractions'] ?? []
        )));

        $price = null;
        if ($r = $e['priceRanges'][0] ?? null) {
            $cur = ($r['currency'] ?? '') === 'EUR' ? '€' : (string) ($r['currency'] ?? '');
            $min = isset($r['min']) ? self::money((float) $r['min']) : null;
            $max = isset($r['max']) ? self::money((float) $r['max']) : null;
            $price = trim(($min === $max || $max === null ? $min : $min . '–' . $max) . ' ' . $cur) ?: null;
        }

        return [
            'source_id' => (string) $e['id'],
            'title' => trim((string) ($e['name'] ?? '')),
            'lineup' => $lineup,
            'date' => $date,
            'time' => $noTime ? null : substr((string) $start['localTime'], 0, 5),
            'venue' => $venue['name'] ?? null,
            'city' => $venue['city']['name'] ?? null,
            'country' => $venue['country']['name'] ?? null,
            'country_code' => $venue['country']['countryCode'] ?? null,
            'url' => $e['url'] ?? null,
            'image' => self::image($e['images'] ?? []),
            'price' => $price,
            'event_status' => $e['dates']['status']['code'] ?? null,
            'genre' => ($g = $e['classifications'][0]['genre']['name'] ?? null) && $g !== 'Undefined' ? $g : null,
        ];
    }

    /** Imagem 16:9 com largura mais próxima de 640 px. */
    private static function image(array $images): ?string
    {
        $best = null;
        foreach ($images as $img) {
            $score = abs((int) ($img['width'] ?? 0) - 640) + (($img['ratio'] ?? '') === '16_9' ? 0 : 10_000);
            if ($best === null || $score < $best[0]) {
                $best = [$score, (string) ($img['url'] ?? '')];
            }
        }
        return $best[1] ?? null;
    }

    private static function money(float $v): string
    {
        return number_format($v, fmod($v, 1.0) === 0.0 ? 0 : 2, ',', "\u{202F}");
    }

    /** Chave de comparação de nomes (ver Names::key). */
    public static function key(string $name): string
    {
        return Names::key($name);
    }

    private function get(string $path, array $query): array
    {
        $url = self::BASE . $path . '?' . http_build_query(['apikey' => $this->apiKey] + $query);

        for ($attempt = 0; ; $attempt++) {
            $res = Http::get($url);
            $retry = $res['status'] === 429 || $res['status'] >= 500 || $res['status'] === 0;
            if ($retry && $attempt < 3) {
                sleep(1 + $attempt * 2);
                continue;
            }
            break;
        }

        if ($res['status'] === 0) {
            throw new ApiException('Ticketmaster: erro de rede (' . $res['error'] . ')');
        }
        if ($res['status'] >= 400) {
            $fault = (string) ($res['json']['fault']['faultstring'] ?? $res['json']['errors'][0]['detail'] ?? '');
            $msg = match (true) {
                $res['status'] === 401 || str_contains($fault, 'ApiKey') => 'API key inválida.',
                $res['status'] === 429 => 'Limite de pedidos atingido (5/s, 5000/dia). Tenta mais tarde.',
                default => $fault !== '' ? $fault : 'HTTP ' . $res['status'],
            };
            // 401/429 tornam inútil continuar a sincronização.
            throw new ApiException('Ticketmaster: ' . $msg, $res['status']);
        }
        if (!is_array($res['json'])) {
            throw new ApiException('Ticketmaster: resposta inválida.');
        }
        return $res['json'];
    }
}
