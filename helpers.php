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