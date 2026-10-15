<?php
return [
    'issuer' => 'https://login.plainshow.se',
    'client_id' => '',
    'client_secret' => '',
    'redirect_uri' => (getenv('SPLIT_BASE_URL') ?: 'https://split.plainshow.se') . '/auth/callback',
    'extension' => 'split',
    'core_url' => 'https://plainshow.se',
    'cache_dir' => __DIR__ . '/../storage/cache',
];
