<?php

namespace Split\Domain;

/** PlainShow accounts as Split shows them: name, picture and Swish number. Read-only. */
final class People
{
    /** @return array<int, array{id: int, name: string, first: string, initials: string, avatar: ?string, phone: ?string}> */
    public static function many(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        if (!$ids) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = splitDb()->prepare("SELECT id, username, first_name, last_name, avatar_path, phone, updated_at FROM `" . self::accounts() . "`.users WHERE id IN ($marks)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $people = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $people[(int) $row['id']] = self::shape($row);
        }
        foreach ($ids as $id) {
            $people[$id] ??= ['id' => $id, 'name' => 'Borttagen person', 'first' => 'Borttagen', 'initials' => '?', 'avatar' => null, 'phone' => null];
        }
        return $people;
    }

    public static function one(int $id): array
    {
        return self::many([$id])[$id];
    }

    /** Active accounts with access to Split, for adding to groups. */
    public static function directory(): array
    {
        $rows = splitDb()->query("SELECT id, username, first_name, last_name, avatar_path, phone, updated_at FROM `" . self::accounts() . "`.users
            WHERE status = 'active' AND role <> 'guest' AND username NOT LIKE 'e2e%' ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);
        return array_map([self::class, 'shape'], $rows);
    }

    public static function exists(int $id): bool
    {
        $stmt = splitDb()->prepare("SELECT 1 FROM `" . self::accounts() . "`.users WHERE id = ? AND status = 'active'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_row();
    }

    /** A Swedish mobile number as Swish wants it: 46701234567. */
    public static function swishNumber(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '46' . substr($digits, 1);
        }
        return strlen($digits) >= 9 && strlen($digits) <= 15 ? $digits : null;
    }

    private static function shape(array $row): array
    {
        $first = trim((string) $row['first_name']);
        $last = trim((string) $row['last_name']);
        $name = trim("$first $last") ?: (string) $row['username'];
        $initials = mb_strtoupper(mb_substr($first ?: $name, 0, 1) . mb_substr($last, 0, 1));
        return [
            'id' => (int) $row['id'],
            'name' => $name,
            'first' => $first ?: $name,
            'initials' => $initials ?: '?',
            'avatar' => $row['avatar_path'] ? 'https://plainshow.se/avatars/' . (int) $row['id'] . '?v=' . substr(md5((string) $row['updated_at']), 0, 8) : null,
            'phone' => self::swishNumber($row['phone']),
        ];
    }

    private static function accounts(): string
    {
        return preg_replace('/[^a-z0-9_]/', '', splitConfig('database')['accounts'] ?? 'plainshow');
    }
}
