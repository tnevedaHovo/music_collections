<?php
declare(strict_types=1);

namespace App\Core;

final class Http
{
    /**
     * GET simples com cURL. Por omissão pede JSON; um cabeçalho Accept em $headers substitui-o.
     * @return array{status:int, headers:array<string,string>, json:mixed, body:string, url:string, error:string}
     */
    public static function get(string $url, array $headers = [], int $timeout = 25): array
    {
        $responseHeaders = [];
        $hasAccept = (bool) preg_grep('/^accept:/i', $headers);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_HTTPHEADER => $hasAccept ? $headers : array_merge(['Accept: application/json'], $headers),
            CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        $body = curl_exec($ch);
        $error = $body === false ? curl_error($ch) : '';
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'json' => is_string($body) ? json_decode($body, true) : null,
            'body' => is_string($body) ? $body : '',
            'url' => $finalUrl,
            'error' => $error,
        ];
    }
}
