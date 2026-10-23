<?php
declare(strict_types=1);

namespace App\Concerts;

use App\Core\ApiException;
use App\Core\Http;

/**
 * BOL (bol.pt). Não tem API, mas a página de pesquisa de "Música & Festivais" lista todos os eventos
 * de uma vez, cada um com os dados schema.org (JSON-LD) e o género. Um só pedido por importação.
 */
final class BolClient
{
    public const MUSIC_URL = 'https://www.bol.pt/Comprar/pesquisa/2-0-0-0-0-0/musica';
    private const CATEGORY = 'Música & Festivais';

    /** @return list<array> eventos no formato de TicketmasterClient::normalize, mais 'genre' */
    public function musicEvents(): array
    {
        $res = Http::get(self::MUSIC_URL, ['Accept: text/html', 'Accept-Language: pt-PT,pt;q=0.9'], 90);
        if ($res['status'] === 0) {
            throw new ApiException('BOL: erro de rede (' . $res['error'] . ')');
        }
        if ($res['status'] >= 400) {
            throw new ApiException('BOL: HTTP ' . $res['status'], $res['status']);
        }

        $events = [];
        // Cada evento é um bloco "item-montra" com a categoria visível e o JSON-LD.
        foreach (array_slice(preg_split('/<div class="item-montra/', $res['body']) ?: [], 1) as $block) {
            if (!preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $block, $m)) {
                continue;
            }
            $e = json_decode($m[1], true);
            // "Product" são passes e assinaturas, sem data.
            if (!is_array($e) || ($e['@type'] ?? '') !== 'Event' || empty($e['startDate'])) {
                continue;
            }
            preg_match('#<span class="descricao"[^>]*>(.*?)</span>#s', $block, $d);
            $category = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($d[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if (!str_starts_with($category, self::CATEGORY)) {
                continue; // Família, Formação, Teatro… que aparecem como "relacionados"
            }
            if ($n = self::normalize($e, trim(substr($category, strlen(self::CATEGORY))))) {
                $events[$n['source_id']] ??= $n;
            }
        }
        if (!$events) {
            throw new ApiException('BOL: não encontrei eventos na página (o site pode ter mudado).');
        }
        return array_values($events);
    }

    private static function normalize(array $e, string $genre): ?array
    {
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', (string) $e['startDate'], $start)) {
            return null;
        }
        preg_match('#/Comprar/Bilhetes/(\d+)-#', (string) ($e['url'] ?? ''), $id);
        $sourceId = $id[1] ?? preg_replace('/^\d+_/', '', (string) ($e['productID'] ?? ''));
        if ($sourceId === '') {
            return null;
        }
        $name = trim(html_entity_decode((string) ($e['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = $name;
        // Várias sessões (ex.: festival de 3 dias): a BOL dá a primeira e a última.
        $end = substr((string) ($e['endDate'] ?? ''), 0, 10);
        if ($end !== '' && $end !== $start[1]) {
            $title .= ' · até ' . date('d/m', strtotime($end));
        }
        $place = $e['location'] ?? [];
        $address = $place['address'] ?? [];
        $offer = $e['offers'] ?? [];
        $price = match (true) {
            !is_numeric($offer['price'] ?? null) => null,
            (float) $offer['price'] === 0.0 => 'Entrada livre',
            default => number_format((float) $offer['price'], fmod((float) $offer['price'], 1.0) === 0.0 ? 0 : 2, ',', "\u{202F}") . ' €',
        };
        $performers = is_array($e['performers'] ?? null) ? $e['performers'] : [];

        return [
            'source_id' => $sourceId,
            'name' => $name,
            'title' => $title,
            'lineup' => array_values(array_filter(array_map(static fn($p) => trim((string) ($p['name'] ?? '')), $performers))),
            'date' => $start[1],
            'time' => $start[2] === '00:00' ? null : $start[2],
            'venue' => trim((string) ($place['name'] ?? '')) ?: null,
            'city' => trim((string) ($address['addressLocality'] ?? '')) ?: null,
            'country' => ($address['addressCountry'] ?? 'PT') === 'PT' ? 'Portugal' : ($address['addressCountry'] ?? null),
            'country_code' => $address['addressCountry'] ?? 'PT',
            'url' => $e['url'] ?? null,
            'image' => $e['image'] ?? null,
            'price' => $price,
            'event_status' => str_contains((string) ($offer['availability'] ?? ''), 'SoldOut') ? 'offsale' : null,
            'genre' => $genre !== '' && $genre !== 'Música' ? $genre : null,
        ];
    }
}
