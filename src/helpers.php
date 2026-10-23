<?php
declare(strict_types=1);

function h(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route = '', array $params = []): string
{
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
    $query = $route !== '' ? ['r' => $route] + $params : $params;
    return 'index.php' . ($query ? '?' . http_build_query($query) : '');
}

/** Número inteiro com separador de milhares (espaço fino, norma PT). */
function num(mixed $n): string
{
    if ($n === null || $n === '') {
        return '—';
    }
    return number_format((int) $n, 0, ',', "\u{202F}");
}

/** Número abreviado para mosaicos: 1,2 M · 45 mil · 980. */
function short_num(mixed $n): string
{
    if ($n === null || $n === '') {
        return '—';
    }
    $n = (int) $n;
    if ($n >= 1_000_000) {
        return number_format($n / 1_000_000, 1, ',', '') . ' M';
    }
    if ($n >= 10_000) {
        return number_format($n / 1000, 0, ',', '') . ' mil';
    }
    return num($n);
}

function date_pt(?string $iso): string
{
    if (!$iso) {
        return '—';
    }
    $t = strtotime($iso);
    return $t ? date('d/m/Y', $t) : '—';
}

function ago(?string $iso): string
{
    if (!$iso || !($t = strtotime($iso))) {
        return 'nunca';
    }
    $d = time() - $t;
    return match (true) {
        $d < 60 => 'agora mesmo',
        $d < 3600 => 'há ' . intdiv($d, 60) . ' min',
        $d < 86400 => 'há ' . intdiv($d, 3600) . ' h',
        $d < 86400 * 30 => 'há ' . intdiv($d, 86400) . ' dias',
        default => 'em ' . date('d/m/Y', $t),
    };
}

function json_list(?string $json): array
{
    $v = $json ? json_decode($json, true) : null;
    return is_array($v) ? $v : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function month_pt(int $m, bool $short = false): string
{
    $names = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $name = $names[($m - 1) % 12];
    return $short ? mb_substr($name, 0, 3) : $name;
}

function weekday_pt(string $isoDate): string
{
    return ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'][(int) date('w', strtotime($isoDate))];
}

/** Redireciona com Location relativo à aplicação e termina. */
function redirect(string $href): never
{
    header('Location: ' . $href);
    exit;
}
