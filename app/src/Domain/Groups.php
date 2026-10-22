<?php

namespace Split\Domain;

use PlainShow\Kit\Identity;
use Split\Api\ApiException;

/**
 * Groups, their members, expenses and payments. Only members see a group.
 * The creator (or anyone with split.group.moderate) renames it, removes
 * people and deletes it. Anyone in the group adds expenses; an expense is
 * changed by whoever added or paid it, or a manager.
 */
final class Groups
{
    public const MODES = ['equal', 'amount', 'percent'];

    /* ---------- Reading ---------- */

    /** The person's groups with their own balance in each. */
    public static function forUser(int $userId): array
    {
        self::syncLinkedForUser($userId);
        $stmt = splitDb()->prepare('SELECT g.id, g.name, g.created_by, g.updated_at,
                (SELECT COUNT(*) FROM members m2 WHERE m2.group_id = g.id AND m2.source != \'excluded\') AS people,
                (SELECT MAX(a.created_at) FROM activity a WHERE a.group_id = g.id) AS active_at
            FROM `groups` g JOIN members m ON m.group_id = g.id AND m.user_id = ? AND m.source != \'excluded\'
            ORDER BY active_at DESC, g.id DESC');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($groups as &$group) {
            $snapshot = self::snapshot((int) $group['id']);
            $group['balance'] = $snapshot['balances'][$userId] ?? 0;
            $group['total'] = array_sum(array_column($snapshot['expenses'], 'amount'));
            $group['members'] = array_values($snapshot['people']);
        }
        return $groups;
    }

    /** Everything about one group, after checking the person is in it. */
    public static function open(int $groupId, Identity $identity): array
    {
        self::syncLinkedForUser($identity->id());
        $group = self::row($groupId);
        if (!$group || (!self::isMember($groupId, $identity->id()) && !PlatformGroups::can($identity->id(), 'split.group.moderate'))) {
            throw new ApiException(ps_t('Gruppen finns inte, eller så är du inte med i den.'), 404);
        }
        return $group + self::snapshot($groupId) + ['can_manage' => self::canManage($group, $identity)];
    }

    public static function snapshot(int $groupId): array
    {
        $db = splitDb();
        $memberIds = array_map('intval', array_column(self::query("SELECT user_id FROM members WHERE group_id = ? AND source != 'excluded' ORDER BY joined_at, user_id", $groupId), 'user_id'));
        $expenses = self::query('SELECT id, title, amount, paid_by, created_by, spent_on, notes, split_mode, created_at FROM expenses WHERE group_id = ? ORDER BY spent_on DESC, id DESC', $groupId);
        $shares = self::query('SELECT s.expense_id, s.user_id, s.amount FROM shares s JOIN expenses e ON e.id = s.expense_id WHERE e.group_id = ?', $groupId);
        $byExpense = [];
        foreach ($shares as $share) {
            $byExpense[(int) $share['expense_id']][(int) $share['user_id']] = (int) $share['amount'];
        }
        foreach ($expenses as &$expense) {
            $expense['amount'] = (int) $expense['amount'];
            $expense['shares'] = $byExpense[(int) $expense['id']] ?? [];
        }
        unset($expense);
        $settlements = self::query('SELECT id, from_user, to_user, amount, method, note, created_by, created_at FROM settlements WHERE group_id = ? ORDER BY created_at DESC, id DESC', $groupId);
        $activity = self::query('SELECT id, user_id, kind, summary, created_at FROM activity WHERE group_id = ? ORDER BY id DESC LIMIT 40', $groupId);

        $balances = Ledger::balances($memberIds, $expenses, $settlements);
        $ids = array_merge($memberIds, array_keys($balances), array_column($activity, 'user_id'));
        return [
            'member_ids' => $memberIds,
            'people' => People::many($ids),
            'expenses' => $expenses,
            'settlements' => $settlements,
            'activity' => $activity,
            'balances' => $balances,
            'transfers' => Ledger::transfers($balances),
        ];
    }

    /* ---------- Groups and members ---------- */

    public static function create(Identity $identity, string $name, array $memberIds, ?array $source = null): int
    {
        if (!PlatformGroups::can($identity->id(), 'split.group.create')) {
            throw new ApiException(ps_t('Du har inte behörighet att skapa grupper.'), 403);
        }
        $name = self::cleanName($name);
        $linkedMembers = $source === null ? [] : self::validPeople($source['memberIds'] ?? []);
        $members = self::validPeople(array_merge([$identity->id()], $memberIds, $linkedMembers));
        $db = splitDb();
        $db->begin_transaction();
        $creator = $identity->id();
        $sourceId = $source['id'] ?? null;
        $stmt = $db->prepare('INSERT INTO `groups` (name, created_by, plainshow_group_id) VALUES (?, ?, ?)');
        $stmt->bind_param('sii', $name, $creator, $sourceId);
        $stmt->execute();
        $groupId = (int) $db->insert_id;
        self::insertMembers($groupId, $linkedMembers, 'linked');
        self::insertMembers($groupId, array_values(array_diff($members, $linkedMembers)));
        self::log($groupId, $creator, 'created', 'skapade gruppen');
        $db->commit();
        Notify::people(array_diff($members, [$creator]), $identity->name() . ' lade till dig i ' . $name, $groupId);
        return $groupId;
    }

    public static function rename(int $groupId, Identity $identity, string $name): void
    {
        self::requireManager($groupId, $identity);
        $name = self::cleanName($name);
        $stmt = splitDb()->prepare('UPDATE `groups` SET name = ? WHERE id = ?');
        $stmt->bind_param('si', $name, $groupId);
        $stmt->execute();
        self::log($groupId, $identity->id(), 'renamed', 'bytte namn på gruppen till ' . $name);
    }

    public static function addMembers(int $groupId, Identity $identity, array $ids): int
    {
        $group = self::requireMember($groupId, $identity);
        $current = array_map('intval', array_column(self::query("SELECT user_id FROM members WHERE group_id = ? AND source != 'excluded'", $groupId), 'user_id'));
        $new = array_values(array_diff(self::validPeople($ids), $current));
        if (!$new) {
            return 0;
        }
        self::insertMembers($groupId, $new);
        $names = array_column(People::many($new), 'first');
        self::log($groupId, $identity->id(), 'members', 'lade till ' . self::list($names));
        Notify::people($new, $identity->name() . ' lade till dig i ' . $group['name'], $groupId);
        return count($new);
    }

    /** Removing someone (or leaving) needs their balance to be zero, so no debt disappears. */
    public static function removeMember(int $groupId, Identity $identity, int $userId): bool
    {
        $leaving = $userId === $identity->id();
        $leaving ? self::requireMember($groupId, $identity) : self::requireManager($groupId, $identity);
        if (!self::isMember($groupId, $userId)) {
            throw new ApiException(ps_t('Personen är inte med i gruppen.'), 404);
        }
        $balance = self::snapshot($groupId)['balances'][$userId] ?? 0;
        if ($balance !== 0) {
            $who = $leaving ? ps_t('Du') : People::one($userId)['first'];
            throw new ApiException("$who har " . ($balance > 0 ? 'pengar kvar att få' : 'en skuld') . ' på ' . money(abs($balance)) . '. Gör upp först.', 409);
        }
        $stmt = splitDb()->prepare("UPDATE members SET source = 'excluded' WHERE group_id = ? AND user_id = ? AND source = 'linked'");
        $stmt->bind_param('ii', $groupId, $userId);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            $delete = splitDb()->prepare('DELETE FROM members WHERE group_id = ? AND user_id = ?');
            $delete->bind_param('ii', $groupId, $userId);
            $delete->execute();
        }
        if ((int) self::query("SELECT COUNT(*) AS n FROM members WHERE group_id = ? AND source != 'excluded'", $groupId)[0]['n'] === 0) {
            self::deleteRow($groupId);
            return true;
        }
        self::log($groupId, $identity->id(), 'members', $leaving ? 'lämnade gruppen' : 'tog bort ' . People::one($userId)['first']);
        return false;
    }

    public static function delete(int $groupId, Identity $identity): void
    {
        self::requireManager($groupId, $identity);
        self::deleteRow($groupId);
    }

    /* ---------- Expenses ---------- */

    /** Adds or changes an expense. $data: title, amount (öre), paid_by, spent_on, notes, mode, people, inputs. */
    public static function saveExpense(int $groupId, Identity $identity, array $data, ?int $expenseId = null): int
    {
        $group = self::requireMember($groupId, $identity);
        $members = array_map('intval', array_column(self::query("SELECT user_id FROM members WHERE group_id = ? AND source != 'excluded'", $groupId), 'user_id'));
        $title = trim((string) $data['title']);
        if ($title === '' || mb_strlen($title) > 190) {
            throw new ApiException(ps_t('Skriv vad det gällde.'), 422);
        }
        $amount = (int) $data['amount'];
        if ($amount <= 0 || $amount > 100_000_000) {
            throw new ApiException(ps_t('Beloppet måste vara större än 0.'), 422);
        }
        $paidBy = (int) $data['paid_by'];
        if (!in_array($paidBy, $members, true)) {
            throw new ApiException(ps_t('Den som betalade måste vara med i gruppen.'), 422);
        }
        $mode = in_array($data['mode'], self::MODES, true) ? $data['mode'] : 'equal';
        $people = array_values(array_intersect(array_map('intval', (array) $data['people']), $members));
        if (count($people) !== count((array) $data['people'])) {
            throw new ApiException(ps_t('Alla som delar måste vara med i gruppen.'), 422);
        }
        $shares = Ledger::shares($amount, $mode, $people, (array) ($data['inputs'] ?? []));
        $spentOn = (string) $data['spent_on'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $spentOn) || !strtotime($spentOn)) {
            $spentOn = date('Y-m-d');
        }
        $notes = trim((string) ($data['notes'] ?? '')) ?: null;
        $actor = $identity->id();
        $isNew = $expenseId === null;
        if (!$isNew) {
            self::requireExpenseEditor($groupId, $expenseId, $identity, $group);
        }

        $db = splitDb();
        $db->begin_transaction();
        if ($isNew) {
            $stmt = $db->prepare('INSERT INTO expenses (group_id, title, amount, paid_by, created_by, spent_on, notes, split_mode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isiiisss', $groupId, $title, $amount, $paidBy, $actor, $spentOn, $notes, $mode);
            $stmt->execute();
            $expenseId = (int) $db->insert_id;
        } else {
            $stmt = $db->prepare('UPDATE expenses SET title = ?, amount = ?, paid_by = ?, spent_on = ?, notes = ?, split_mode = ? WHERE id = ? AND group_id = ?');
            $stmt->bind_param('siisssii', $title, $amount, $paidBy, $spentOn, $notes, $mode, $expenseId, $groupId);
            $stmt->execute();
            $delete = $db->prepare('DELETE FROM shares WHERE expense_id = ?');
            $delete->bind_param('i', $expenseId);
            $delete->execute();
        }
        $insert = $db->prepare('INSERT INTO shares (expense_id, user_id, amount) VALUES (?, ?, ?)');
        foreach ($shares as $userId => $share) {
            $insert->bind_param('iii', $expenseId, $userId, $share);
            $insert->execute();
        }
        self::log($groupId, $actor, 'expense', ($isNew ? 'lade till ' : 'ändrade ') . $title . ', ' . money($amount));
        $db->commit();
        if ($isNew) {
            Notify::people(array_diff(array_keys($shares), [$actor]), $identity->name() . ' lade till ' . $title . ' (' . money($amount) . ') i ' . $group['name'], $groupId);
        }
        return $expenseId;
    }

    public static function deleteExpense(int $groupId, int $expenseId, Identity $identity): void
    {
        $group = self::requireMember($groupId, $identity);
        $expense = self::requireExpenseEditor($groupId, $expenseId, $identity, $group);
        $stmt = splitDb()->prepare('DELETE FROM expenses WHERE id = ? AND group_id = ?');
        $stmt->bind_param('ii', $expenseId, $groupId);
        $stmt->execute();
        self::log($groupId, $identity->id(), 'expense', 'tog bort ' . $expense['title'] . ', ' . money((int) $expense['amount']));
    }

    /* ---------- Payments ---------- */

    /** Records a payment. Either side of it may record it: "I paid" or "I got paid". */
    public static function settle(int $groupId, Identity $identity, int $from, int $to, int $amount, string $method, string $note): int
    {
        $group = self::requireMember($groupId, $identity);
        $actor = $identity->id();
        if (!in_array($actor, [$from, $to], true) && !self::canManage($group, $identity)) {
            throw new ApiException(ps_t('Du kan bara registrera betalningar du själv är med i.'), 403);
        }
        if ($from === $to || !self::isMember($groupId, $from) || !self::isMember($groupId, $to)) {
            throw new ApiException(ps_t('Båda måste vara med i gruppen.'), 422);
        }
        if ($amount <= 0) {
            throw new ApiException(ps_t('Beloppet måste vara större än 0.'), 422);
        }
        $method = $method === 'other' ? 'other' : 'swish';
        $note = mb_substr(trim($note), 0, 255) ?: null;
        $stmt = splitDb()->prepare('INSERT INTO settlements (group_id, from_user, to_user, amount, method, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiiissi', $groupId, $from, $to, $amount, $method, $note, $actor);
        $stmt->execute();
        $id = (int) splitDb()->insert_id;
        $people = People::many([$from, $to]);
        $summary = match ($actor) {
            $from => 'betalade ' . money($amount) . ' till ' . $people[$to]['first'],
            $to => 'fick ' . money($amount) . ' av ' . $people[$from]['first'],
            default => 'registrerade att ' . $people[$from]['first'] . ' betalade ' . money($amount) . ' till ' . $people[$to]['first'],
        };
        self::log($groupId, $actor, 'payment', $summary);
        $other = $actor === $from ? $to : $from;
        Notify::people([$other], $identity->name() . ($actor === $from ? ' har betalat dig ' : ' har fått ') . money($amount) . ' i ' . $group['name'], $groupId);
        return $id;
    }

    public static function deleteSettlement(int $groupId, int $settlementId, Identity $identity): void
    {
        $group = self::requireMember($groupId, $identity);
        $rows = self::query('SELECT id, from_user, to_user, amount, created_by FROM settlements WHERE group_id = ? AND id = ' . (int) $settlementId, $groupId);
        if (!$rows) {
            throw new ApiException(ps_t('Betalningen finns inte.'), 404);
        }
        $row = $rows[0];
        if ((int) $row['created_by'] !== $identity->id() && !self::canManage($group, $identity)) {
            throw new ApiException(ps_t('Bara den som registrerade betalningen kan ta bort den.'), 403);
        }
        $stmt = splitDb()->prepare('DELETE FROM settlements WHERE id = ? AND group_id = ?');
        $stmt->bind_param('ii', $settlementId, $groupId);
        $stmt->execute();
        $people = People::many([(int) $row['from_user'], (int) $row['to_user']]);
        self::log($groupId, $identity->id(), 'payment', 'tog bort betalningen ' . $people[(int) $row['from_user']]['first'] . ' till ' . $people[(int) $row['to_user']]['first'] . ', ' . money((int) $row['amount']));
    }

    /* ---------- Rules and helpers ---------- */

    public static function canManage(array $group, Identity $identity): bool
    {
        return (int) $group['created_by'] === $identity->id() || PlatformGroups::can($identity->id(), 'split.group.moderate');
    }

    public static function canEditExpense(array $group, array $expense, Identity $identity): bool
    {
        return in_array($identity->id(), [(int) $expense['created_by'], (int) $expense['paid_by']], true) || self::canManage($group, $identity);
    }

    private static function requireMember(int $groupId, Identity $identity): array
    {
        self::syncLinkedForUser($identity->id());
        $group = self::row($groupId);
        if (!$group || !self::isMember($groupId, $identity->id())) {
            throw new ApiException(ps_t('Gruppen finns inte, eller så är du inte med i den.'), 404);
        }
        return $group;
    }

    private static function requireManager(int $groupId, Identity $identity): array
    {
        self::syncLinkedForUser($identity->id());
        $group = self::row($groupId);
        if (!$group || (!self::isMember($groupId, $identity->id()) && !PlatformGroups::can($identity->id(), 'split.group.moderate'))) {
            throw new ApiException(ps_t('Gruppen finns inte.'), 404);
        }
        if (!self::canManage($group, $identity)) {
            throw new ApiException(ps_t('Bara den som skapade gruppen kan göra det.'), 403);
        }
        return $group;
    }

    private static function requireExpenseEditor(int $groupId, int $expenseId, Identity $identity, array $group): array
    {
        $rows = self::query('SELECT id, title, amount, paid_by, created_by FROM expenses WHERE group_id = ? AND id = ' . (int) $expenseId, $groupId);
        if (!$rows) {
            throw new ApiException(ps_t('Utgiften finns inte.'), 404);
        }
        if (!self::canEditExpense($group, $rows[0], $identity)) {
            throw new ApiException(ps_t('Bara den som lade till eller betalade utgiften kan ändra den.'), 403);
        }
        return $rows[0];
    }

    private static function row(int $groupId): ?array
    {
        return self::query('SELECT id, name, currency, created_by, created_at FROM `groups` WHERE id = ?', $groupId)[0] ?? null;
    }

    private static function isMember(int $groupId, int $userId): bool
    {
        $stmt = splitDb()->prepare("SELECT 1 FROM members WHERE group_id = ? AND user_id = ? AND source != 'excluded'");
        $stmt->bind_param('ii', $groupId, $userId);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_row();
    }

    private static function insertMembers(int $groupId, array $ids, string $source = 'manual'): void
    {
        $stmt = splitDb()->prepare('INSERT IGNORE INTO members (group_id, user_id, source) VALUES (?, ?, ?)');
        foreach ($ids as $id) {
            $stmt->bind_param('iis', $groupId, $id, $source);
            $stmt->execute();
        }
    }

    private static function syncLinkedForUser(int $userId): void
    {
        if ((int) splitDb()->query('SELECT COUNT(*) FROM `groups` WHERE plainshow_group_id IS NOT NULL')->fetch_row()[0] === 0) {
            return;
        }
        $platform = PlatformGroups::forUser($userId);
        $stmt = splitDb()->prepare('SELECT g.id, g.plainshow_group_id, m.source FROM `groups` g LEFT JOIN members m ON m.group_id = g.id AND m.user_id = ? WHERE g.plainshow_group_id IS NOT NULL');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $group) {
            $groupId = (int) $group['id'];
            $source = $platform[(int) $group['plainshow_group_id']] ?? null;
            if ($source === null) {
                if ($group['source'] === 'linked') {
                    $remove = splitDb()->prepare("DELETE FROM members WHERE group_id = ? AND user_id = ? AND source = 'linked'");
                    $remove->bind_param('ii', $groupId, $userId);
                    $remove->execute();
                }
                continue;
            }
            $memberIds = array_values(array_unique(array_map('intval', $source['memberIds'] ?? [])));
            $existing = self::query("SELECT user_id, source FROM members WHERE group_id = ?", $groupId);
            $linked = array_map('intval', array_column(array_filter($existing, fn ($member) => $member['source'] === 'linked'), 'user_id'));
            foreach (array_diff($linked, $memberIds) as $removedId) {
                $remove = splitDb()->prepare("DELETE FROM members WHERE group_id = ? AND user_id = ? AND source = 'linked'");
                $remove->bind_param('ii', $groupId, $removedId);
                $remove->execute();
            }
            self::insertMembers($groupId, array_values(array_diff($memberIds, array_map('intval', array_column($existing, 'user_id')))), 'linked');
        }
    }

    private static function validPeople(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        foreach ($ids as $id) {
            if (!People::exists($id)) {
                throw new ApiException(ps_t('En av personerna finns inte.'), 422);
            }
        }
        return $ids;
    }

    private static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '' || mb_strlen($name) > 150) {
            throw new ApiException(ps_t('Ge gruppen ett namn (högst 150 tecken).'), 422);
        }
        return $name;
    }

    private static function deleteRow(int $groupId): void
    {
        $stmt = splitDb()->prepare('DELETE FROM `groups` WHERE id = ?');
        $stmt->bind_param('i', $groupId);
        $stmt->execute();
    }

    private static function log(int $groupId, int $userId, string $kind, string $summary): void
    {
        $summary = mb_substr($summary, 0, 255);
        $stmt = splitDb()->prepare('INSERT INTO activity (group_id, user_id, kind, summary) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iiss', $groupId, $userId, $kind, $summary);
        $stmt->execute();
        $touch = splitDb()->prepare('UPDATE `groups` SET updated_at = NOW() WHERE id = ?');
        $touch->bind_param('i', $groupId);
        $touch->execute();
    }

    private static function list(array $names): string
    {
        if (count($names) < 2) {
            return (string) ($names[0] ?? '');
        }
        $last = array_pop($names);
        return implode(', ', $names) . ' och ' . $last;
    }

    private static function query(string $sql, int $groupId): array
    {
        $stmt = splitDb()->prepare($sql);
        $stmt->bind_param('i', $groupId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
