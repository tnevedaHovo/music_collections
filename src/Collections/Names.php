<?php
declare(strict_types=1);

namespace App\Collections;

/** Normalização de nomes do Discogs para pesquisas no Last.fm. */
final class Names
{
    public const VARIOUS_ID = 194;      // "Various" no Discogs
    public const UNKNOWN_ID = 355;      // "Unknown Artist"
    public const IGNORED_ARTISTS = [self::VARIOUS_ID, self::UNKNOWN_ID];

    /** "Prince (2)" → "Prince"; "Bowie*" → "Bowie". */
    public static function artist(string $name): string
    {
        $name = preg_replace('/\s+\(\d+\)$/u', '', trim($name)) ?? $name;
        return trim(rtrim($name, '*'));
    }

    /** Nome de apresentação a partir da lista de artistas do Discogs (usa ANV e "join"). */
    public static function display(array $artists): string
    {
        $out = '';
        $last = count($artists) - 1;
        foreach (array_values($artists) as $i => $a) {
            $anv = trim((string) ($a['anv'] ?? ''));
            $out .= $anv !== '' ? $anv : self::artist((string) ($a['name'] ?? ''));
            if ($i < $last) {
                $join = trim((string) ($a['join'] ?? ''));
                $out .= match ($join) {
                    '', ',' => ', ',
                    default => ' ' . $join . ' ',
                };
            }
        }
        return $out !== '' ? $out : 'Desconhecido';
    }

    /** Variantes do título a tentar no Last.fm, da mais exata à mais solta. */
    public static function titleCandidates(string $title): array
    {
        $candidates = [trim($title)];
        $candidates[] = trim((string) preg_replace('/\s*[\(\[][^\)\]]*[\)\]]/u', '', $title));
        return array_values(array_unique(array_filter($candidates, static fn($t) => $t !== '')));
    }
}
