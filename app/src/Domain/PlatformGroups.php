<?php

namespace Split\Domain;

use PlainShow\Kit\Widgets;
use Split\Api\ApiException;

final class PlatformGroups
{
    public static function response(int $userId): array
    {
        static $cache = [];
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }
        $file = dirname(__DIR__, 3) . '/config/widgets.php';
        $secret = is_file($file) ? (string) ((require $file)['secret'] ?? '') : '';
        if ($secret === '') {
            throw new ApiException(ps_t('PlainShow groups are unavailable.'), 503);
        }
        // Core runs on this server: the request goes straight to it.
        $core = \PlainShow\Kit\Platform::require('core');
        $host = (string) parse_url($core, PHP_URL_HOST);
        $url = $core.'/api/core/v1/groups/for-user?'.http_build_query(
            ['extension' => 'split'] + Widgets::sign($userId, 'groups', $secret),
        );
        $connection = curl_init($url);
        curl_setopt_array($connection, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_RESOLVE => ["$host:443:127.0.0.1"],
        ]);
        $body = curl_exec($connection);
        $status = curl_getinfo($connection, CURLINFO_HTTP_CODE);
        curl_close($connection);
        $decoded = is_string($body) ? json_decode($body, true) : null;
        if ($status === 403) {
            throw new ApiException(ps_t('Du har inte tillgång till Split.'), 403);
        }
        if ($status !== 200 || !is_array($decoded) || !is_array($decoded['groups'] ?? null) || !is_array($decoded['people'] ?? null) || !is_array($decoded['viewer'] ?? null)) {
            throw new ApiException(ps_t('PlainShow groups could not be checked.'), 503);
        }

        $decoded['groups'] = array_column($decoded['groups'], null, 'id');
        return $cache[$userId] = $decoded;
    }

    public static function forUser(int $userId): array
    {
        return self::response($userId)['groups'];
    }

    public static function people(int $userId): array
    {
        return self::response($userId)['people'];
    }

    public static function can(int $userId, string $capability): bool
    {
        $granted = array_map('strval', self::response($userId)['viewer']['capabilities'] ?? []);
        return in_array('*', $granted, true) || in_array($capability, $granted, true);
    }
}
