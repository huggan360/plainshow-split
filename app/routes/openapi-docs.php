<?php
// Notes for the Split API description (see PlainShow\Kit\OpenApi). Keys are "METHOD /path".
$expense = [
    'title' => ['string', 'What it was', true],
    'amount' => ['string', 'The total in kronor, such as 249,50', true],
    'paid_by' => ['integer', 'Who paid, by user id', true],
    'spent_on' => ['string', 'The date, YYYY-MM-DD', true],
    'mode' => ['string', 'How to split: equal, amount or percent', false],
    'people' => ['array', 'Who shares it, by user id', true],
    'inputs' => ['object', 'Per person amounts or percentages for amount and percent splits', false],
    'notes' => ['string', 'Up to 2000 characters', false],
];

return [
    'GET /health' => ['summary' => 'Whether Split answers', 'returns' => 'The service name and version'],
    'GET /groups' => ['summary' => 'Your groups', 'description' => 'Every group you are in, with your balance in each.'],
    'POST /groups' => ['summary' => 'Make a group', 'body' => ['name' => ['string', 'The group name', true], 'sourceGroupId' => ['integer', 'Optional PlainShow group id', false], 'members' => ['array', 'Extra people, by user id', false]], 'returns' => '`id`'],
    'GET /groups/{id}' => ['summary' => 'A group', 'description' => 'Members, balances, expenses, payments and the fewest payments that settle everyone.', 'params' => ['id' => 'The group']],
    'POST /groups/{id}' => ['summary' => 'Rename a group', 'params' => ['id' => 'The group'], 'body' => ['name' => ['string', 'The new name', true]]],
    'DELETE /groups/{id}' => ['summary' => 'Remove a group', 'description' => 'Only who made it, and only when everyone is settled.', 'params' => ['id' => 'The group']],
    'POST /groups/{id}/members' => ['summary' => 'Add people', 'params' => ['id' => 'The group'], 'body' => ['members' => ['array', 'User ids', true]]],
    'DELETE /groups/{id}/members/{user}' => ['summary' => 'Remove someone or leave', 'description' => 'Not while they owe or are owed money.', 'params' => ['id' => 'The group', 'user' => 'The person']],
    'POST /groups/{id}/expenses' => ['summary' => 'Add an expense', 'params' => ['id' => 'The group'], 'body' => $expense, 'returns' => '`id`'],
    'POST /groups/{id}/expenses/{expense}' => ['summary' => 'Change an expense', 'description' => 'By whoever added or paid it.', 'params' => ['id' => 'The group', 'expense' => 'The expense'], 'body' => $expense],
    'DELETE /groups/{id}/expenses/{expense}' => ['summary' => 'Remove an expense', 'params' => ['id' => 'The group', 'expense' => 'The expense']],
    'POST /groups/{id}/payments' => ['summary' => 'Record a payment', 'params' => ['id' => 'The group'], 'body' => [
        'from' => ['integer', 'Who paid, by user id', true], 'to' => ['integer', 'Who was paid', true], 'amount' => ['string', 'In kronor', true],
        'method' => ['string', 'swish (the default) or other', false], 'note' => ['string', 'Up to 255 characters', false],
    ], 'returns' => '`id`'],
    'DELETE /groups/{id}/payments/{payment}' => ['summary' => 'Remove a payment', 'params' => ['id' => 'The group', 'payment' => 'The payment']],
    'GET /people' => ['summary' => 'People you can add', 'description' => 'Everyone with a PlainShow account who can use Split.'],
];
