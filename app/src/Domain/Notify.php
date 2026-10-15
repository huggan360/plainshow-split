<?php

namespace Split\Domain;

/**
 * Notices for the people in a group. They appear under Notiser in the blog
 * and as a push on the phone app, through the blog's signed internal API.
 * Without config/notify.php (a dev server) nothing is sent.
 */
final class Notify
{
    public static function people(array $userIds, string $text, int $groupId): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), fn ($id) => $id > 0)));
        $file = dirname(__DIR__, 3) . '/config/notify.php';
        if (!$ids || !is_file($file)) {
            return;
        }
        $config = require $file;
        $body = json_encode([
            'users' => $ids,
            'text' => mb_substr($text, 0, 240),
            'link' => 'https://split.plainshow.se/group?id=' . $groupId,
            'source' => 'split',
            'time' => time(),
        ], JSON_UNESCAPED_UNICODE);
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nX-PlainShow-Signature: " . hash_hmac('sha256', $body, $config['secret']) . "\r\n",
            'content' => $body,
            'timeout' => 3,
            'ignore_errors' => true,
        ]]);
        if (@file_get_contents($config['url'], false, $context) === false) {
            error_log('Split notify failed for group ' . $groupId);
        }
    }
}
