<?php
// One group: expenses, balances, how to settle up, payments and activity.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Split\Api\ApiException;
use Split\Domain\Groups;
use Split\Domain\People;

$identity = require_identity();
$me = $identity->id();
try {
    $group = Groups::open((int) ($_GET['id'] ?? 0), $identity);
} catch (ApiException) {
    http_response_code(404);
    split_layout_start('Gruppen finns inte');
    echo '<div class="ps-empty split-missing"><i class="bx bx-wallet"></i><h1 class="ps-heading">Gruppen finns inte</h1><p>Den kan ha tagits bort, eller så är du inte med i den.</p><a class="ps-btn" href="/"><i class="bx bx-left-arrow-alt"></i>Till grupperna</a></div>';
    split_layout_end();
    exit;
}
$people = $group['people'];
$members = array_map(fn ($id) => $people[$id], $group['member_ids']);
$myBalance = $group['balances'][$me] ?? 0;
$total = array_sum(array_column($group['expenses'], 'amount'));
$maxBalance = max(1, ...array_map('abs', array_values($group['balances']) ?: [1]));
$directory = array_values(array_filter(People::directory(), fn ($p) => !in_array($p['id'], $group['member_ids'], true)));
$months = ['januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'];

// For the editor: members and each expense with whether you may change it.
$data = [
    'id' => (int) $group['id'],
    'name' => $group['name'],
    'me' => $me,
    'manage' => $group['can_manage'],
    'members' => array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'first' => $p['first'], 'phone' => $p['phone']], $members),
    'expenses' => array_map(fn ($x) => [
        'id' => (int) $x['id'], 'title' => $x['title'], 'amount' => $x['amount'], 'paid_by' => (int) $x['paid_by'],
        'spent_on' => $x['spent_on'], 'notes' => $x['notes'], 'mode' => $x['split_mode'], 'shares' => $x['shares'],
        'editable' => Groups::canEditExpense($group, $x, $identity),
    ], $group['expenses']),
];

split_layout_start($group['name']);
?>
<a class="split-back" href="/"><i class="bx bx-left-arrow-alt" aria-hidden="true"></i>Grupper</a>
<header class="split-head split-head--group">
    <div>
        <h1 class="ps-page-head__title"><?= e($group['name']) ?></h1>
        <div class="split-faces split-faces--lg"><?php foreach ($members as $person) { echo avatar($person, 'sm'); } ?></div>
    </div>
    <div class="ps-inline split-head__actions">
        <button type="button" class="ps-btn ps-btn--primary ps-btn--lg" data-expense-new><i class="bx bx-plus"></i><span>Utgift</span></button>
        <button type="button" class="ps-btn ps-btn--lg" data-payment-new><i class="bx bx-transfer-alt"></i><span>Gör upp</span></button>
        <div class="ps-dropdown" data-ps-dropdown>
            <button type="button" class="ps-btn ps-btn--lg ps-btn--icon" data-ps-dropdown-trigger aria-expanded="false" aria-label="Mer"><i class="bx bx-dots-horizontal-rounded"></i></button>
            <div class="ps-dropdown__menu" data-ps-dropdown-menu hidden>
                <button type="button" class="ps-dropdown__item" data-modal-open="members-add"><i class="bx bx-user-plus"></i>Lägg till personer</button>
                <?php if ($group['can_manage']): ?>
                    <button type="button" class="ps-dropdown__item" data-modal-open="group-rename"><i class="bx bx-edit-alt"></i>Byt namn</button>
                    <button type="button" class="ps-dropdown__item" data-modal-open="members-remove"><i class="bx bx-user-minus"></i>Ta bort personer</button>
                <?php endif; ?>
                <div class="ps-dropdown__sep"></div>
                <button type="button" class="ps-dropdown__item" data-group-leave><i class="bx bx-exit"></i>Lämna gruppen</button>
                <?php if ($group['can_manage']): ?><button type="button" class="ps-dropdown__item split-danger" data-group-delete><i class="bx bx-trash"></i>Ta bort gruppen</button><?php endif; ?>
            </div>
        </div>
    </div>
</header>

<div class="split-status <?= $myBalance > 0 ? 'is-plus' : ($myBalance < 0 ? 'is-minus' : '') ?>">
    <span class="split-status__icon"><i class="bx <?= $myBalance === 0 ? 'bx-check' : ($myBalance > 0 ? 'bx-down-arrow-alt' : 'bx-up-arrow-alt') ?>" aria-hidden="true"></i></span>
    <div>
        <strong><?= $myBalance === 0 ? 'Du är kvitt' : ($myBalance > 0 ? 'Du får tillbaka ' . e(money($myBalance)) : 'Du är skyldig ' . e(money(-$myBalance))) ?></strong>
        <span class="ps-muted ps-small">Gruppen har handlat för <?= e(money($total)) ?> totalt.</span>
    </div>
</div>

<div class="split-layout">
    <section class="ps-frame split-expenses" aria-labelledby="expenses-title">
        <div class="ps-frame__panel">
            <div class="ps-frame__head"><h2 class="ps-frame__title" id="expenses-title">Utgifter</h2><span class="ps-muted ps-small"><?= count($group['expenses']) ?> st</span></div>
            <?php if ($group['expenses']): $month = null; ?>
                <ul class="split-expense-list">
                    <?php foreach ($group['expenses'] as $x):
                        $key = substr($x['spent_on'], 0, 7);
                        if ($key !== $month): $month = $key; ?>
                            <li class="split-month"><?= e(ucfirst($months[(int) substr($key, 5, 2) - 1]) . ' ' . substr($key, 0, 4)) ?></li>
                        <?php endif;
                        $mine = $x['shares'][$me] ?? 0;
                        $payer = $people[(int) $x['paid_by']];
                        $lent = (int) $x['paid_by'] === $me ? $x['amount'] - $mine : -$mine; ?>
                        <li>
                            <button type="button" class="split-expense" data-expense="<?= (int) $x['id'] ?>">
                                <span class="split-expense__date"><strong><?= (int) substr($x['spent_on'], 8, 2) ?></strong><?= e(substr($months[(int) substr($x['spent_on'], 5, 2) - 1], 0, 3)) ?></span>
                                <span class="split-expense__body">
                                    <span class="split-expense__title"><?= e($x['title']) ?></span>
                                    <span class="split-expense__meta"><?= (int) $x['paid_by'] === $me ? 'Du' : e($payer['first']) ?> betalade <?= e(money($x['amount'])) ?><?= $x['split_mode'] !== 'equal' ? ', ' . ($x['split_mode'] === 'amount' ? 'egna belopp' : 'procent') : '' ?></span>
                                </span>
                                <span class="split-expense__mine <?= $lent > 0 ? 'is-plus' : ($lent < 0 ? 'is-minus' : '') ?>">
                                    <?php if ($lent > 0): ?><small>du lånade ut</small><?= e(money($lent)) ?>
                                    <?php elseif ($lent < 0): ?><small>du lånade</small><?= e(money(-$lent)) ?>
                                    <?php else: ?><small>inte med</small><?php endif; ?>
                                </span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="ps-empty"><i class="bx bx-receipt"></i><div>Inga utgifter ännu.</div><button type="button" class="ps-btn ps-btn--primary" data-expense-new><i class="bx bx-plus"></i>Lägg till den första</button></div>
            <?php endif; ?>
        </div>
    </section>

    <aside class="split-side">
        <section class="ps-frame" aria-labelledby="balances-title">
            <div class="ps-frame__panel">
                <h2 class="ps-frame__title" id="balances-title">Saldo</h2>
                <ul class="split-balances ps-frame__body">
                    <?php foreach ($group['balances'] as $id => $balance): $person = $people[$id]; ?>
                        <li class="split-balance-row">
                            <?= avatar($person, 'sm') ?>
                            <span class="split-balance-row__name"><?= $id === $me ? 'Du' : e($person['first']) ?><?= in_array($id, $group['member_ids'], true) ? '' : ' <span class="ps-badge">Har lämnat</span>' ?></span>
                            <span class="split-balance <?= $balance > 0 ? 'is-plus' : ($balance < 0 ? 'is-minus' : '') ?>"><?= $balance === 0 ? 'Kvitt' : e(money($balance, true)) ?></span>
                            <span class="split-bar"><span class="<?= $balance >= 0 ? 'is-plus' : 'is-minus' ?>" style="width: <?= round(abs($balance) / $maxBalance * 100) ?>%"></span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="ps-frame" data-glow="accent" aria-labelledby="settle-title">
            <div class="ps-frame__panel">
                <h2 class="ps-frame__title" id="settle-title">Så gör ni upp</h2>
                <?php if ($group['transfers']): ?>
                    <p class="ps-frame__text">Det minsta antalet betalningar som gör alla kvitt.</p>
                    <ul class="split-transfers ps-frame__body">
                        <?php foreach ($group['transfers'] as $t): $from = $people[$t['from']]; $to = $people[$t['to']]; ?>
                            <li class="split-transfer<?= in_array($me, [$t['from'], $t['to']], true) ? ' is-mine' : '' ?>">
                                <div class="split-transfer__who"><?= avatar($from, 'xs') ?><span><?= $t['from'] === $me ? 'Du' : e($from['first']) ?></span><i class="bx bx-right-arrow-alt" aria-hidden="true"></i><?= avatar($to, 'xs') ?><span><?= $t['to'] === $me ? 'dig' : e($to['first']) ?></span></div>
                                <strong class="split-transfer__amount"><?= e(money($t['amount'])) ?></strong>
                                <?php if ($t['from'] === $me): ?>
                                    <div class="split-transfer__actions">
                                        <?php if ($to['phone']): ?><a class="ps-btn ps-btn--sm ps-btn--primary" href="swish://payment?phone=<?= e($to['phone']) ?>&amp;amount=<?= e(number_format($t['amount'] / 100, 2, '.', '')) ?>&amp;message=<?= rawurlencode($group['name'] . ' splitbetalning') ?>" data-swish><i class="bx bx-mobile"></i>Swisha</a><?php else: ?><span class="ps-small ps-muted"><?= e($to['first']) ?> saknar Swish-nummer</span><?php endif; ?>
                                        <button type="button" class="ps-btn ps-btn--sm" data-settle="<?= $t['from'] ?>,<?= $t['to'] ?>,<?= $t['amount'] ?>">Jag har betalat</button>
                                    </div>
                                <?php elseif ($t['to'] === $me): ?>
                                    <div class="split-transfer__actions"><button type="button" class="ps-btn ps-btn--sm" data-settle="<?= $t['from'] ?>,<?= $t['to'] ?>,<?= $t['amount'] ?>">Jag har fått pengarna</button></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="ps-frame__text">Allt är redan jämnt.</p>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($group['settlements']): ?>
        <section class="ps-frame" aria-labelledby="payments-title">
            <div class="ps-frame__panel">
                <h2 class="ps-frame__title" id="payments-title">Betalningar</h2>
                <ul class="ps-list ps-frame__body">
                    <?php foreach ($group['settlements'] as $s): ?>
                        <li class="ps-row split-payment">
                            <span class="ps-row__icon"><i class="bx <?= $s['method'] === 'swish' ? 'bx-mobile' : 'bx-money' ?>"></i></span>
                            <div class="ps-row__body">
                                <div class="ps-row__title"><?= (int) $s['from_user'] === $me ? 'Du' : e($people[(int) $s['from_user']]['first']) ?> till <?= (int) $s['to_user'] === $me ? 'dig' : e($people[(int) $s['to_user']]['first']) ?></div>
                                <div class="ps-row__meta"><?= e(split_date($s['created_at'])) ?><?= $s['note'] ? ', ' . e($s['note']) : '' ?></div>
                            </div>
                            <strong><?= e(money((int) $s['amount'])) ?></strong>
                            <?php if ((int) $s['created_by'] === $me || $group['can_manage']): ?><button type="button" class="ps-btn ps-btn--sm ps-btn--ghost ps-btn--icon" data-payment-delete="<?= (int) $s['id'] ?>" aria-label="Ta bort betalningen" title="Ta bort"><i class="bx bx-x"></i></button><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>

        <section class="ps-frame" aria-labelledby="activity-title">
            <div class="ps-frame__panel">
                <h2 class="ps-frame__title" id="activity-title">Aktivitet</h2>
                <ul class="split-activity ps-frame__body">
                    <?php foreach ($group['activity'] as $a): $who = $people[(int) $a['user_id']]; ?>
                        <li><?= avatar($who, 'xs') ?><span><strong><?= (int) $a['user_id'] === $me ? 'Du' : e($who['first']) ?></strong> <?= e($a['summary']) ?> <span class="ps-muted"><?= e(split_ago($a['created_at'])) ?></span></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </aside>
</div>

<!-- Expense editor -->
<div class="ps-modal-backdrop" id="expense" hidden>
    <form class="ps-frame ps-modal split-expense-modal" role="dialog" aria-modal="true" aria-labelledby="expense-title" data-expense-form data-ps-no-loading novalidate>
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="expense-title">Ny utgift</h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="Stäng"><i class="bx bx-x"></i></button></div>
            <div class="ps-modal__body">
                <div class="split-form-grid">
                    <label class="ps-field split-form-grid__wide"><span class="ps-label">Vad</span><input class="ps-input" name="title" maxlength="190" required placeholder="Till exempel middag" autocomplete="off"></label>
                    <label class="ps-field"><span class="ps-label">Belopp (kr)</span><input class="ps-input split-amount" name="amount" inputmode="decimal" required placeholder="0,00" autocomplete="off"></label>
                    <label class="ps-field"><span class="ps-label">Datum</span><input class="ps-input" type="date" name="spent_on" required></label>
                    <label class="ps-field split-form-grid__wide"><span class="ps-label">Betalades av</span>
                        <select class="ps-select" name="paid_by"><?php foreach ($members as $person): ?><option value="<?= $person['id'] ?>"><?= $person['id'] === $me ? 'Du' : e($person['name']) ?></option><?php endforeach; ?></select>
                    </label>
                </div>
                <div class="split-mode">
                    <span class="ps-label">Dela</span>
                    <div class="ps-tabs" role="tablist" aria-label="Hur kostnaden delas">
                        <button type="button" class="ps-tab is-active" data-mode="equal">Lika</button>
                        <button type="button" class="ps-tab" data-mode="amount">Belopp</button>
                        <button type="button" class="ps-tab" data-mode="percent">Procent</button>
                    </div>
                </div>
                <ul class="split-shares" data-shares>
                    <?php foreach ($members as $person): ?>
                        <li class="split-share" data-person="<?= $person['id'] ?>">
                            <label class="split-share__who"><input type="checkbox" checked><?= avatar($person, 'xs') ?><span><?= $person['id'] === $me ? 'Du' : e($person['name']) ?></span></label>
                            <input class="ps-input split-share__input" inputmode="decimal" aria-label="Del för <?= e($person['name']) ?>">
                            <span class="split-share__result"></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="split-shares__sum" data-shares-sum></p>
                <label class="ps-field"><span class="ps-label">Anteckning</span><textarea class="ps-textarea" name="notes" rows="2" maxlength="2000"></textarea></label>
            </div>
            <div class="ps-modal__foot">
                <button type="button" class="ps-btn ps-btn--danger split-modal-left" data-expense-delete hidden><i class="bx bx-trash"></i>Ta bort</button>
                <button type="button" class="ps-btn ps-btn--ghost" data-modal-close>Avbryt</button>
                <button type="submit" class="ps-btn ps-btn--primary">Spara</button>
            </div>
        </div>
    </form>
</div>

<!-- Expense details for people who cannot change it -->
<div class="ps-modal-backdrop" id="expense-view" hidden>
    <div class="ps-frame ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="expense-view-title">
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="expense-view-title"></h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="Stäng"><i class="bx bx-x"></i></button></div>
            <div class="ps-modal__body" data-expense-view></div>
        </div>
    </div>
</div>

<!-- Payment -->
<div class="ps-modal-backdrop" id="payment" hidden>
    <form class="ps-frame ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="payment-title" data-payment-form data-ps-no-loading>
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="payment-title">Registrera betalning</h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="Stäng"><i class="bx bx-x"></i></button></div>
            <div class="ps-modal__body">
                <div class="split-form-grid">
                    <label class="ps-field"><span class="ps-label">Från</span><select class="ps-select" name="from"><?php foreach ($members as $person): ?><option value="<?= $person['id'] ?>"><?= $person['id'] === $me ? 'Du' : e($person['name']) ?></option><?php endforeach; ?></select></label>
                    <label class="ps-field"><span class="ps-label">Till</span><select class="ps-select" name="to"><?php foreach ($members as $person): ?><option value="<?= $person['id'] ?>"><?= $person['id'] === $me ? 'Du' : e($person['name']) ?></option><?php endforeach; ?></select></label>
                    <label class="ps-field"><span class="ps-label">Belopp (kr)</span><input class="ps-input" name="amount" inputmode="decimal" required></label>
                    <label class="ps-field"><span class="ps-label">Hur</span><select class="ps-select" name="method"><option value="swish">Swish</option><option value="other">Kontant eller annat</option></select></label>
                </div>
                <label class="ps-field"><span class="ps-label">Anteckning</span><input class="ps-input" name="note" maxlength="255"></label>
            </div>
            <div class="ps-modal__foot"><button type="button" class="ps-btn ps-btn--ghost" data-modal-close>Avbryt</button><button type="submit" class="ps-btn ps-btn--primary">Spara betalning</button></div>
        </div>
    </form>
</div>

<!-- Members -->
<div class="ps-modal-backdrop" id="members-add" hidden>
    <form class="ps-frame ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="members-add-title" data-members-add data-ps-no-loading>
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="members-add-title">Lägg till personer</h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="Stäng"><i class="bx bx-x"></i></button></div>
            <div class="ps-modal__body">
                <?php if ($directory): ?>
                    <input class="ps-input split-people-filter" type="search" placeholder="Sök" aria-label="Sök personer" data-people-filter>
                    <div class="split-people" data-people><?php foreach ($directory as $person): ?><label class="split-person" data-name="<?= e(mb_strtolower($person['name'])) ?>"><input type="checkbox" name="members" value="<?= $person['id'] ?>"><?= avatar($person, 'xs') ?><span><?= e($person['name']) ?></span></label><?php endforeach; ?></div>
                <?php else: ?><p class="ps-muted">Alla är redan med.</p><?php endif; ?>
            </div>
            <div class="ps-modal__foot"><button type="button" class="ps-btn ps-btn--ghost" data-modal-close>Avbryt</button><button type="submit" class="ps-btn ps-btn--primary"<?= $directory ? '' : ' disabled' ?>>Lägg till</button></div>
        </div>
    </form>
</div>
<?php if ($group['can_manage']): ?>
<div class="ps-modal-backdrop" id="members-remove" hidden>
    <div class="ps-frame ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="members-remove-title">
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="members-remove-title">Ta bort personer</h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="Stäng"><i class="bx bx-x"></i></button></div>
            <ul class="ps-list ps-modal__body">
                <?php foreach ($members as $person): if ($person['id'] === $me) { continue; } $balance = $group['balances'][$person['id']] ?? 0; ?>
                    <li class="ps-row"><?= avatar($person, 'sm') ?><div class="ps-row__body"><div class="ps-row__title"><?= e($person['name']) ?></div><div class="ps-row__meta"><?= $balance === 0 ? 'Kvitt' : e(money($balance, true)) ?></div></div><button type="button" class="ps-btn ps-btn--sm ps-btn--danger" data-member-remove="<?= $person['id'] ?>"<?= $balance !== 0 ? ' disabled title="Måste vara kvitt först"' : '' ?>>Ta bort</button></li>
                <?php endforeach; ?>
            </ul>
            <p class="ps-help split-modal-note">Bara den som är kvitt kan tas bort, så att ingen skuld försvinner.</p>
        </div>
    </div>
</div>
<div class="ps-modal-backdrop" id="group-rename" hidden>
    <form class="ps-frame ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="group-rename-title" data-group-rename data-ps-no-loading>
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="group-rename-title">Byt namn</h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="Stäng"><i class="bx bx-x"></i></button></div>
            <div class="ps-modal__body"><label class="ps-field"><span class="ps-label">Nytt gruppnamn</span><input class="ps-input" name="name" maxlength="150" required value="<?= e($group['name']) ?>"></label></div>
            <div class="ps-modal__foot"><button type="button" class="ps-btn ps-btn--ghost" data-modal-close>Avbryt</button><button type="submit" class="ps-btn ps-btn--primary">Spara</button></div>
        </div>
    </form>
</div>
<?php endif; ?>
<script type="application/json" id="group-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php split_layout_end('<script src="' . e(split_asset('assets/split/groups.js')) . '" defer></script><script src="' . e(split_asset('assets/split/group.js')) . '" defer></script>');
