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

    /** Chave de comparação de nomes: sem acentos, maiúsculas, "The " nem pontuação. */
    public static function key(string $name): string
    {
        $s = mb_strtolower(trim($name));
        $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', '&' => 'and', '+' => 'and']);
        $s = preg_replace('/^the\s+/u', '', $s) ?? $s;
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $s) ?? $s;
    }

    /** Chave de um título de faixa, sem versões: "Airbag (Remastered) - 2017 Mix" → "airbag". */
    public static function trackKey(string $title): string
    {
        $t = preg_replace('/\s*[\(\[][^\)\]]*[\)\]]/u', '', $title) ?? $title;
        $t = preg_replace('/\s+-\s+.*\b(remaster\w*|version|mix|edit|live|mono|stereo|demo|bonus)\b.*$/iu', '', $t) ?? $t;
        return self::key($t !== '' ? $t : $title);
    }

    /** Variantes do título a tentar no Last.fm, da mais exata à mais solta. */
    public static function titleCandidates(string $title): array
    {
        $candidates = [trim($title)];
        $candidates[] = trim((string) preg_replace('/\s*[\(\[][^\)\]]*[\)\]]/u', '', $title));
        // "God's Country - 2025 Reissue Black Vinyl" → "God's Country"
        $candidates[] = trim((string) preg_replace('/\s+-\s+.*\b(reissue|vinyl|remaster\w*|edition|deluxe|anniversary|expanded)\b.*$/iu', '', $title));
        return array_values(array_unique(array_filter($candidates, static fn($t) => $t !== '')));
    }
}
