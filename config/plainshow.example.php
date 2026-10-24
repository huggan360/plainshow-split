<?php
// PlainShow sign-in for Split. Copy to plainshow.php; not in git: it holds the
// client secret (from `php artisan plainshow:client split ...` in Login).
// Login's address and the return address come from the platform file.
return [
    'client_id' => '',
    'client_secret' => '',
    'extension' => 'split',
    'cache_dir' => dirname(__DIR__) . '/storage/cache',
];
