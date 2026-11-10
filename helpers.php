<?php
// /app/helpers.php
declare(strict_types=1);

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** One-shot flash message stored in the session */
function flash_set(string $type, string $msg): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function old(string $key, string $default = ''): string
{
    return $_POST[$key] ?? $default;
}

function route_link(string $r, array $params = []): string
{
    $q = array_merge(['r' => $r], $params);
    return '/?' . http_build_query($q);
}

/** Format a number for display: strip .5 → .5, keep 300 as 300, keep 82.5 as 82.5 */
function fmt_num($n): string
{
    if ($n === null || $n === '') return '—';
    $n = (float)$n;
    // Show as integer if whole
    if (abs($n - round($n)) < 0.0001) {
        return (string)(int)round($n);
    }
    // Up to 2 decimal places, no trailing zeros
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}