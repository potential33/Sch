<?php
// /app/settings.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function setting_get(string $key, ?string $default = null): ?string
{
    $v = fetch_val("SELECT value FROM settings WHERE key = ?", [$key]);
    return $v === null ? $default : (string) $v;
}

function setting_set(string $key, ?string $value): void
{
    q("INSERT INTO settings (key, value) VALUES (?, ?)
       ON CONFLICT(key) DO UPDATE SET value = excluded.value",
      [$key, $value]);
}

/** All settings as ['key' => 'value', ...] */
function settings_all(): array
{
    $rows = fetch_all("SELECT key, value FROM settings ORDER BY key");
    $out = [];
    foreach ($rows as $r) $out[$r['key']] = $r['value'];
    return $out;
}