<?php

use PlainShow\Kit\Identity;

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function e_lines(?string $value): string
{
    return nl2br(e($value), false);
}

function current_identity(): ?Identity
{
    static $identity = false;
    if (gateway_identity() !== null) {
        return gateway_identity();
    }
    return $identity === false ? $identity = splitAuth()->user() : $identity;
}

/** The person a call through core's API gateway is for, once its signature has been checked. */
function gateway_identity(?Identity $set = null): ?Identity
{
    static $identity = null;
    if ($set !== null) {
        $identity = $set;
    }
    return $identity;
}

function require_identity(): Identity
{
    return splitAuth()->requireUser();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(): bool
{
    // Calls through core's API gateway are signed instead; there is no form to forge.
    if (gateway_identity() !== null) {
        return true;
    }
    $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? '');
    return $sent !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

/** Öre to "1 234,50 kr". */
function money(int $cents, bool $sign = false): string
{
    $text = number_format(abs($cents) / 100, 2, ',', ' ');
    $text = str_ends_with($text, ',00') ? substr($text, 0, -3) : $text;
    return ($sign && $cents > 0 ? '+' : ($cents < 0 ? '−' : '')) . $text . ' kr';
}

function split_date(?string $date): string
{
    if (!$date || ($time = strtotime($date)) === false) {
        return '';
    }
    $months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    $day = (int) date('j', $time) . ' ' . $months[(int) date('n', $time) - 1];
    return date('Y', $time) === date('Y') ? $day : $day . ' ' . date('Y', $time);
}

function split_ago(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if ($time === false) {
        return '';
    }
    $seconds = max(0, time() - $time);
    return match (true) {
        $seconds < 60 => 'nyss',
        $seconds < 3600 => intdiv($seconds, 60) . ' min sedan',
        $seconds < 86400 => intdiv($seconds, 3600) . ' tim sedan',
        $seconds < 604800 => intdiv($seconds, 86400) . ' d sedan',
        default => split_date($date),
    };
}

/** Avatar markup from a person array: id, name, initials, avatar. */
function avatar(array $person, string $size = ''): string
{
    $classes = 'ps-avatar' . ($size !== '' ? ' ps-avatar--' . $size : '');
    $inner = !empty($person['avatar'])
        ? '<img class="ps-avatar__img" src="' . e($person['avatar']) . '" alt="" loading="lazy">'
        : '<span class="ps-avatar__initials" aria-hidden="true">' . e($person['initials'] ?? '?') . '</span>';
    return '<span class="' . $classes . '" title="' . e($person['name'] ?? '') . '">' . $inner . '</span>';
}

function split_ui_version(): string
{
    static $version = null;
    return $version ??= substr((string) \Composer\InstalledVersions::getReference('plainshow/kit'), 0, 8) ?: 'dev';
}

function split_asset(string $path): string
{
    $file = dirname(__DIR__) . '/public/' . ltrim($path, '/');
    return '/' . ltrim($path, '/') . '?v=' . (is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : split_ui_version());
}

function redirect_to(string $path): never
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        $path = '/';
    }
    header('Location: ' . $path, true, 303);
    exit;
}
