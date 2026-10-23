<?php
declare(strict_types=1);

namespace App\Concerts;

use App\Core\Http;

/**
 * Lê um evento a partir do link de uma página pública (Songkick, Viral Agenda), usando os dados
 * schema.org (JSON-LD) que estes sites publicam para os motores de busca. Nenhum dos dois tem
 * API gratuita: é um pedido por evento, só quando o utilizador cola o link.
 */
final class EventPage
{
    public const SOURCES = ['songkick.com' => 'Songkick', 'viralagenda.com' => 'Viral Agenda'];

    private const COUNTRIES = [
        'PT' => 'Portugal', 'ES' => 'Spain', 'FR' => 'France', 'GB' => 'United Kingdom', 'IE' => 'Ireland',
        'DE' => 'Germany', 'NL' => 'Netherlands', 'BE' => 'Belgium', 'IT' => 'Italy', 'CH' => 'Switzerland',
        'AT' => 'Austria', 'BR' => 'Brazil', 'US' => 'United States',
    ];

    private const STATUSES = [
        'EventCancelled' => 'cancelled',
        'EventPostponed' => 'postponed',
        'EventRescheduled' => 'rescheduled',
        'EventMovedOnline' => null,
        'EventScheduled' => null,
    ];

    /** @return array{source:string, fields:array<string,?string>} campos com os nomes do formulário */
    public static function read(string $url): array
    {
        $url = trim($url);
        $site = self::site($url) ?? throw new \RuntimeException('Cola o link de um evento do Songkick ou da Viral Agenda.');

        $res = Http::get($url, ['Accept: text/html,application/xhtml+xml', 'Accept-Language: pt-PT,pt;q=0.9,en;q=0.8']);
        if ($res['status'] === 0) {
            throw new \RuntimeException(self::SOURCES[$site] . ': erro de rede (' . $res['error'] . ')');
        }
        if ($res['status'] >= 400) {
            throw new \RuntimeException(self::SOURCES[$site] . ': ' . ($res['status'] === 404 ? 'evento não encontrado.' : 'HTTP ' . $res['status']));
        }
        if (self::site($res['url']) !== $site) {
            throw new \RuntimeException('O link redirecionou para outro site.');
        }

        $event = self::pick(self::events($res['body']), $res['url'])
            ?? throw new \RuntimeException('Não encontrei os dados do evento nesta página. É mesmo o link de um concerto?');

        $fields = $site === 'songkick.com' ? self::songkick($event) : self::viralAgenda($event, $res['body']);
        $fields['url'] = strtok($res['url'], '?#') ?: $url;
        return ['source' => self::SOURCES[$site], 'fields' => $fields];
    }

    /** Domínio suportado a que o link pertence, ou null. */
    public static function site(string $url): ?string
    {
        $p = parse_url($url);
        if (!in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }
        $host = strtolower($p['host'] ?? '');
        foreach (array_keys(self::SOURCES) as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return $domain;
            }
        }
        return null;
    }

    // ── Songkick ─────────────────────────────────────────────────

    private static function songkick(array $e): array
    {
        // Songkick: hora local sem fuso ("2026-10-27T21:00:00") e artistas em "performer".
        [$date, $time] = self::dateTime((string) ($e['startDate'] ?? ''));
        $performers = self::list($e['performer'] ?? []);
        $lineup = array_values(array_filter(array_map(static fn($p) => trim((string) ($p['name'] ?? '')), $performers)));

        $price = null;
        if ($offer = self::list($e['offers'] ?? [])[0] ?? null) {
            $price = self::price($offer['lowPrice'] ?? $offer['price'] ?? null, $offer['highPrice'] ?? null, (string) ($offer['priceCurrency'] ?? ''));
        }
        return self::common($e, $date, $time, $lineup) + ['price' => $price];
    }

    // ── Viral Agenda ─────────────────────────────────────────────

    private static function viralAgenda(array $e, string $html): array
    {
        [$date, $time] = self::dateTime((string) ($e['startDate'] ?? ''), true);
        // O JSON-LD da Viral Agenda traz a hora UTC com o fuso errado; o botão "adicionar ao
        // calendário" (data-atcb) da mesma página tem a data e hora locais corretas.
        if (preg_match_all('/data-atcb="([^"]+)"/', $html, $m)) {
            foreach ($m[1] as $raw) {
                $cal = json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
                if (is_array($cal) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($cal['startDate'] ?? ''))) {
                    $date = $cal['startDate'];
                    $time = preg_match('/^\d{2}:\d{2}$/', (string) ($cal['startTime'] ?? '')) ? $cal['startTime'] : $time;
                    break;
                }
            }
        }
        return self::common($e, $date, $time, self::lineupFromTitle((string) ($e['name'] ?? ''))) + ['price' => null];
    }

    /** "Mind Conflict + Desperate Stab (NL) @ Vortex" → ['Mind Conflict', 'Desperate Stab'] */
    private static function lineupFromTitle(string $title): array
    {
        $title = preg_replace('/\s+@\s+.*$/u', '', $title) ?? $title;
        $names = preg_split('/\s+[+\/|]\s+|\s*,\s+/u', $title) ?: [];
        $names = array_map(static fn($n) => trim(preg_replace('/\s*\((?:[A-Z]{2,4}|[A-Z]{2,4}\/[A-Z]{2,4})\)\s*$/u', '', $n) ?? $n), $names);
        return array_values(array_filter($names, static fn($n) => $n !== ''));
    }

    // ── Comum ────────────────────────────────────────────────────

    private static function common(array $e, ?string $date, ?string $time, array $lineup): array
    {
        $place = self::list($e['location'] ?? [])[0] ?? [];
        $address = is_array($place['address'] ?? null) ? $place['address'] : [];
        $country = trim((string) (is_array($address['addressCountry'] ?? null) ? ($address['addressCountry']['name'] ?? '') : ($address['addressCountry'] ?? '')));
        $country = self::COUNTRIES[strtoupper($country)] ?? $country;
        $status = (string) ($e['eventStatus'] ?? '');
        $status = self::STATUSES[preg_replace('#^.*/#', '', $status)] ?? null;

        // Artista principal: o primeiro do cartaz que esteja na coleção; senão o primeiro.
        $index = ConcertRepository::artistIndex();
        $artist = $lineup[0] ?? null;
        foreach ($lineup as $name) {
            if (isset($index[TicketmasterClient::key($name)])) {
                $artist = $name;
                break;
            }
        }
        $name = trim(html_entity_decode((string) ($e['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $others = array_values(array_filter($lineup, static fn($n) => $n !== $artist));
        // Título só quando acrescenta algo ao nome do artista (festivais, digressões).
        $plain = preg_replace('/\s+@\s+.*$/u', '', $name) ?? $name;
        $title = $plain !== '' && $artist !== null && !str_contains(TicketmasterClient::key($plain), TicketmasterClient::key($artist))
            ? $plain : null;

        return [
            'artist' => $artist ?? ($plain ?: null),
            'title' => $title,
            'lineup' => $others ? implode(', ', $others) : null,
            'date' => $date,
            'time' => $time,
            'venue' => trim((string) ($place['name'] ?? '')) ?: null,
            'city' => trim((string) ($address['addressLocality'] ?? '')) ?: null,
            'country' => $country ?: null,
            'event_status' => $status,
        ];
    }

    /**
     * Data e hora locais a partir de um ISO 8601. Com $utc, a hora é lida como UTC e
     * convertida para Lisboa (correção do JSON-LD da Viral Agenda).
     * @return array{0:?string, 1:?string}
     */
    private static function dateTime(string $iso, bool $utc = false): array
    {
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:T(\d{2}:\d{2}))?/', $iso, $m)) {
            return [null, null];
        }
        if (!isset($m[2])) {
            return [$m[1], null];
        }
        if ($utc) {
            $d = new \DateTimeImmutable($m[1] . ' ' . $m[2], new \DateTimeZone('UTC'));
            $d = $d->setTimezone(new \DateTimeZone('Europe/Lisbon'));
            return [$d->format('Y-m-d'), $d->format('H:i')];
        }
        return [$m[1], $m[2] === '00:00' ? null : $m[2]];
    }

    private static function price(mixed $low, mixed $high, string $currency): ?string
    {
        if (!is_numeric($low) && !is_numeric($high)) {
            return null;
        }
        $fmt = static fn($v) => number_format((float) $v, fmod((float) $v, 1.0) === 0.0 ? 0 : 2, ',', "\u{202F}");
        $range = is_numeric($low) && is_numeric($high) && (float) $low !== (float) $high
            ? $fmt($low) . '–' . $fmt($high)
            : $fmt(is_numeric($low) ? $low : $high);
        return trim($range . ' ' . ($currency === 'EUR' ? '€' : $currency));
    }

    /** Todos os objetos do tipo *Event nos blocos JSON-LD da página. */
    private static function events(string $html): array
    {
        preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#si', $html, $m);
        $out = [];
        foreach ($m[1] as $raw) {
            $json = json_decode(trim($raw), true);
            if (!is_array($json)) {
                continue;
            }
            $items = array_is_list($json) ? $json : ($json['@graph'] ?? [$json]);
            foreach ($items as $item) {
                $types = (array) ($item['@type'] ?? []);
                if (is_array($item) && preg_grep('/Event$/', $types)) {
                    $out[] = $item;
                }
            }
        }
        return $out;
    }

    /** O evento da própria página (as páginas listam também outros eventos da sala). */
    private static function pick(array $events, string $url): ?array
    {
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
        foreach ($events as $e) {
            if (rtrim((string) parse_url((string) ($e['url'] ?? ''), PHP_URL_PATH), '/') === $path) {
                return $e;
            }
        }
        return $events[0] ?? null;
    }

    /** Normaliza "um objeto ou uma lista de objetos" do schema.org para lista. */
    private static function list(mixed $v): array
    {
        if (!is_array($v) || !$v) {
            return [];
        }
        return array_is_list($v) ? array_values(array_filter($v, 'is_array')) : [$v];
    }
}
