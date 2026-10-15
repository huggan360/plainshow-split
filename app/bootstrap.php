<?php
/* Every Split page and API request starts here. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

date_default_timezone_set('Europe/Stockholm');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

splitSession();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
