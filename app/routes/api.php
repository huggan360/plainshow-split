<?php
/*
 * Split API, version 1: https://split.plainshow.se/api/v1/...
 * Also at https://plainshow.se/api/split/v1/... through the gateway.
 * Amounts are sent and returned in whole öre.
 */

use Split\Api\ApiException;
use Split\Api\Request;
use Split\Api\Router;
use Split\Domain\Groups;
use Split\Domain\Ledger;
use Split\Domain\People;
use Split\Domain\PlatformGroups;

/** @var Router $router */

$router->add('GET', '/health', fn () => ['service' => 'split', 'version' => json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/plainshow.json'), true)['version'] ?? null], null);

$router->add('GET', '/groups', fn () => ['groups' => array_map(fn ($g) => [
    'id' => (int) $g['id'], 'name' => $g['name'], 'people' => (int) $g['people'], 'balance' => $g['balance'], 'total' => $g['total'],
], Groups::forUser(current_identity()->id()))]);

$router->add('POST', '/groups', function (Request $request) {
    $sourceId = $request->int('sourceGroupId');
    $source = $sourceId > 0 ? (PlatformGroups::forUser(current_identity()->id())[$sourceId] ?? null) : null;
    if ($sourceId > 0 && !$source) {
        throw new ApiException('Du är inte med i den PlainShow-gruppen.', 403);
    }
    $id = Groups::create(current_identity(), $request->string('name', 150) ?: ($source['name'] ?? ''), (array) $request->input('members', []), $source);
    return ['id' => $id, 'redirect' => "/group?id=$id"];
}, 'split.group.create');

$router->add('GET', '/groups/{id}', function (Request $request) {
    $group = Groups::open($request->param('id'), current_identity());
    unset($group['people']);
    return ['group' => $group];
});

$router->add('POST', '/groups/{id}', function (Request $request) {
    Groups::rename($request->param('id'), current_identity(), $request->string('name', 150));
    return [];
});

$router->add('DELETE', '/groups/{id}', function (Request $request) {
    Groups::delete($request->param('id'), current_identity());
    return ['redirect' => '/'];
});

$router->add('POST', '/groups/{id}/members', function (Request $request) {
    return ['added' => Groups::addMembers($request->param('id'), current_identity(), (array) $request->input('members', []))];
});

$router->add('DELETE', '/groups/{id}/members/{user}', function (Request $request) {
    $deleted = Groups::removeMember($request->param('id'), current_identity(), $request->param('user'));
    $leaving = $request->param('user') === current_identity()->id();
    return ['redirect' => $deleted || $leaving ? '/' : null];
});

/** Expense fields from a request: amounts typed in kronor become öre. */
$expense = function (Request $request): array {
    $mode = $request->string('mode', 10) ?: 'equal';
    $inputs = [];
    foreach ((array) $request->input('inputs', []) as $id => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $inputs[(int) $id] = $mode === 'amount' ? Ledger::parseAmount($value) : (float) str_replace(',', '.', (string) $value);
    }
    return [
        'title' => $request->string('title', 190),
        'amount' => Ledger::parseAmount($request->input('amount', '')),
        'paid_by' => $request->int('paid_by'),
        'spent_on' => $request->string('spent_on', 10),
        'notes' => $request->string('notes', 2000),
        'mode' => $mode,
        'people' => (array) $request->input('people', []),
        'inputs' => $inputs,
    ];
};

$router->add('POST', '/groups/{id}/expenses', function (Request $request) use ($expense) {
    return ['id' => Groups::saveExpense($request->param('id'), current_identity(), $expense($request))];
});

$router->add('POST', '/groups/{id}/expenses/{expense}', function (Request $request) use ($expense) {
    return ['id' => Groups::saveExpense($request->param('id'), current_identity(), $expense($request), $request->param('expense'))];
});

$router->add('DELETE', '/groups/{id}/expenses/{expense}', function (Request $request) {
    Groups::deleteExpense($request->param('id'), $request->param('expense'), current_identity());
    return [];
});

$router->add('POST', '/groups/{id}/payments', function (Request $request) {
    return ['id' => Groups::settle(
        $request->param('id'),
        current_identity(),
        $request->int('from'),
        $request->int('to'),
        Ledger::parseAmount($request->input('amount', '')),
        $request->string('method', 10),
        $request->string('note', 255),
    )];
});

$router->add('DELETE', '/groups/{id}/payments/{payment}', function (Request $request) {
    Groups::deleteSettlement($request->param('id'), $request->param('payment'), current_identity());
    return [];
});

$router->add('GET', '/people', fn () => ['people' => array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'initials' => $p['initials'], 'avatar' => $p['avatar']], People::directory())]);

// The PlainShow dashboard widget, asked for by core with a signed request.
$router->add('GET', '/widgets/balances', function () {
    $file = dirname(__DIR__, 2) . '/config/widgets.php';
    try {
        $userId = PlainShow\Kit\Widgets::verify($_GET, 'balances', is_file($file) ? (string) (require $file)['secret'] : '');
    } catch (PlainShow\Kit\KitException $error) {
        throw new ApiException($error->getMessage(), 403);
    }
    $groups = Groups::forUser($userId);
    $owed = array_sum(array_filter(array_column($groups, 'balance'), fn ($b) => $b > 0));
    $owe = -array_sum(array_filter(array_column($groups, 'balance'), fn ($b) => $b < 0));
    return [
        'type' => 'metrics',
        'metrics' => [
            ['label' => 'Du får tillbaka', 'value' => money($owed), 'tone' => $owed > 0 ? 'plus' : null],
            ['label' => 'Du är skyldig', 'value' => money($owe), 'tone' => $owe > 0 ? 'minus' : null],
        ],
        'items' => array_map(fn ($g) => [
            'title' => $g['name'],
            'meta' => (int) $g['people'] . ' personer',
            'url' => 'https://split.plainshow.se/group?id=' . (int) $g['id'],
            'icon' => 'bx-wallet',
            'badge' => $g['balance'] === 0 ? 'Kvitt' : money((int) $g['balance'], true),
            'tone' => $g['balance'] > 0 ? 'plus' : ($g['balance'] < 0 ? 'minus' : null),
        ], array_slice($groups, 0, 5)),
        'empty' => 'Du är inte med i någon grupp ännu.',
        'more' => ['label' => 'Öppna Split', 'url' => 'https://split.plainshow.se/'],
    ];
}, null);
