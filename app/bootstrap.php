<?php
/* Every Split page and API request starts here. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/* Texts in the visitor's language. Split is written in Swedish; lang/en.json
   and lang/nl.json are keyed by the Swedish text (kit Lang). */
\PlainShow\Kit\Lang::load(dirname(__DIR__) . '/lang', 'sv');
if (!function_exists('ps_t')) {
    function ps_t(string $text, array $params = []): string
    {
        return \PlainShow\Kit\Lang::t($text, $params);
    }
    function ps_h(string $text, array $params = []): string
    {
        return htmlspecialchars(ps_t($text, $params), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('ps_url')) {
    /** An address in a PlainShow app (kit Platform), or '' when that app is not installed here. */
    function ps_url(string $app, string $path = ''): string
    {
        $base = \PlainShow\Kit\Platform::url($app);

        return $base === null ? '' : $base . $path;
    }
}

date_default_timezone_set('Europe/Stockholm');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

splitSession();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
