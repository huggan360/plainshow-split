<?php
/*
 * Maintenance commands:
 *   php app/console.php publish-ui      copy the PlainShow UI kit to public/ui
 *   php app/console.php migrate         run new files in app/migrations
 *   php app/console.php import-legacy   copy groups and expenses from the blog's split_* tables (once)
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/db.php';

$command = $argv[1] ?? '';

if ($command === 'publish-ui') {
    $source = dirname(__DIR__) . '/vendor/plainshow/kit/ui';
    $target = dirname(__DIR__) . '/public/ui';
    $copy = function (string $from, string $to) use (&$copy): void {
        is_dir($to) || mkdir($to, 0775, true);
        foreach (scandir($from) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                is_dir("$from/$entry") ? $copy("$from/$entry", "$to/$entry") : copy("$from/$entry", "$to/$entry");
            }
        }
    };
    foreach (['css', 'fonts', 'js', 'brand'] as $directory) {
        $copy("$source/$directory", "$target/$directory");
    }
    exit("PlainShow UI published.\n");
}

if ($command === 'migrate') {
    $db = splitDb();
    $db->query("CREATE TABLE IF NOT EXISTS `migrations` (`name` VARCHAR(190) NOT NULL PRIMARY KEY, `ran_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = array_column($db->query('SELECT name FROM migrations')->fetch_all(), 0);
    $files = glob(__DIR__ . '/migrations/*.sql') ?: [];
    sort($files);
    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $done, true)) {
            continue;
        }
        $db->multi_query((string) file_get_contents($file));
        do {
            if ($result = $db->store_result()) {
                $result->free();
            }
        } while ($db->more_results() && $db->next_result());
        $stmt = $db->prepare('INSERT INTO migrations (name) VALUES (?)');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        echo "Migrated $name\n";
    }
    exit("Migrations are up to date.\n");
}

if ($command === 'import-legacy') {
    $db = splitDb();
    if ((int) $db->query('SELECT COUNT(*) FROM `groups`')->fetch_row()[0] > 0) {
        exit("Split already has groups; nothing imported.\n");
    }
    $cents = 'CAST(ROUND(%s * 100) AS UNSIGNED)';
    $db->begin_transaction();
    $db->query('INSERT INTO `groups` (id, name, created_by, created_at, updated_at) SELECT group_id, group_name, created_by, created_at, created_at FROM woc.split_groups');
    $db->query('INSERT INTO members (group_id, user_id, joined_at) SELECT group_id, user_id, joined_at FROM woc.split_group_members');
    $db->query('INSERT INTO expenses (id, group_id, title, amount, paid_by, created_by, spent_on, notes, split_mode, created_at, updated_at)
        SELECT expense_id, group_id, title, ' . sprintf($cents, 'amount') . ', paid_by, created_by, expense_date, notes, split_mode, created_at, updated_at FROM woc.split_expenses');
    $db->query('INSERT INTO shares (expense_id, user_id, amount) SELECT expense_id, user_id, ' . sprintf($cents, 'share_amount') . ' FROM woc.split_expense_shares');
    $db->query("INSERT INTO settlements (id, group_id, from_user, to_user, amount, method, note, created_by, created_at)
        SELECT settlement_id, group_id, from_user_id, to_user_id, " . sprintf($cents, 'amount') . ", 'swish', note, created_by, created_at FROM woc.split_settlements");
    $db->query("INSERT INTO activity (group_id, user_id, kind, summary, created_at) SELECT id, created_by, 'imported', 'flyttade gruppen från bloggen till PlainShow Split', NOW() FROM `groups`");
    $db->commit();
    foreach (['groups', 'members', 'expenses', 'shares', 'settlements'] as $table) {
        echo $table, ': ', $db->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0], "\n";
    }
    exit("Imported.\n");
}

fwrite(STDERR, "Usage: php app/console.php publish-ui|migrate|import-legacy\n");
exit(1);
