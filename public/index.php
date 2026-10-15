<?php
// Your groups, with what you owe or are owed in each.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Split\Domain\Groups;
use Split\Domain\People;

$identity = require_identity();
$me = People::one($identity->id());
$groups = Groups::forUser($identity->id());
$owed = array_sum(array_filter(array_column($groups, 'balance'), fn ($b) => $b > 0));
$owe = -array_sum(array_filter(array_column($groups, 'balance'), fn ($b) => $b < 0));
$directory = array_values(array_filter(People::directory(), fn ($p) => $p['id'] !== $identity->id()));

split_layout_start('Grupper');
?>
<div class="split-head">
    <div>
        <h1 class="ps-page-head__title">Split</h1>
        <p class="ps-page-head__text">Dela kostnader i grupper och gör upp med Swish.</p>
    </div>
    <?php if ($identity->can('split.group.create')): ?><button type="button" class="ps-btn ps-btn--primary ps-btn--lg" data-modal-open="group-new"><i class="bx bx-plus"></i><span>Ny grupp</span></button><?php endif; ?>
</div>

<?php if (!$me['phone']): ?>
<div class="ps-alert split-phone">
    <i class="bx bx-mobile-alt" aria-hidden="true"></i>
    <div><strong>Lägg till ditt telefonnummer</strong><br>Då kan de andra betala dig med Swish direkt från Split.</div>
    <a class="ps-btn ps-btn--sm" href="https://plainshow.se/account">Till kontot<i class="bx bx-right-top-arrow-circle"></i></a>
</div>
<?php endif; ?>

<div class="split-totals">
    <div class="split-total split-total--plus"><span>Du får tillbaka</span><strong><?= e(money($owed)) ?></strong></div>
    <div class="split-total split-total--minus"><span>Du är skyldig</span><strong><?= e(money($owe)) ?></strong></div>
</div>

<?php if ($groups): ?>
<div class="split-groups">
    <?php foreach ($groups as $group): $balance = (int) $group['balance']; ?>
        <a class="ps-frame split-group" href="/group?id=<?= (int) $group['id'] ?>">
            <div class="ps-frame__panel">
                <div class="split-group__top">
                    <h2 class="split-group__name"><?= e($group['name']) ?></h2>
                    <i class="bx bx-chevron-right" aria-hidden="true"></i>
                </div>
                <div class="split-faces"><?php foreach (array_slice($group['members'], 0, 6) as $person) { echo avatar($person, 'sm'); } ?><?php if (count($group['members']) > 6): ?><span class="split-faces__more">+<?= count($group['members']) - 6 ?></span><?php endif; ?></div>
                <div class="split-group__foot">
                    <span class="ps-muted ps-small"><?= (int) $group['people'] ?> personer, totalt <?= e(money((int) $group['total'])) ?></span>
                    <span class="split-balance <?= $balance > 0 ? 'is-plus' : ($balance < 0 ? 'is-minus' : '') ?>"><?= $balance === 0 ? 'Kvitt' : ($balance > 0 ? 'Du får ' : 'Du är skyldig ') . e(money(abs($balance))) ?></span>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="ps-empty"><i class="bx bx-wallet"></i><div>Du är inte med i någon grupp ännu.</div></div>
<?php endif; ?>

<div class="ps-modal-backdrop" id="group-new" hidden>
    <form class="ps-frame ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="group-new-title" data-group-new data-ps-no-loading>
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="group-new-title">Ny grupp</h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="Stäng"><i class="bx bx-x"></i></button></div>
            <div class="ps-modal__body">
                <label class="ps-field"><span class="ps-label">Namn</span><input class="ps-input" name="name" maxlength="150" required placeholder="Till exempel Bali 2026" autocomplete="off"></label>
                <span class="ps-label">Vilka är med?</span>
                <input class="ps-input split-people-filter" type="search" placeholder="Sök" aria-label="Sök personer" data-people-filter>
                <div class="split-people" data-people>
                    <?php foreach ($directory as $person): ?>
                        <label class="split-person" data-name="<?= e(mb_strtolower($person['name'])) ?>"><input type="checkbox" name="members" value="<?= $person['id'] ?>"><?= avatar($person, 'xs') ?><span><?= e($person['name']) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <p class="ps-help">Du är alltid med i grupper du skapar. Fler kan läggas till senare.</p>
            </div>
            <div class="ps-modal__foot"><button type="button" class="ps-btn ps-btn--ghost" data-modal-close>Avbryt</button><button type="submit" class="ps-btn ps-btn--primary">Skapa grupp</button></div>
        </div>
    </form>
</div>
<?php split_layout_end('<script src="' . e(split_asset('assets/split/groups.js')) . '" defer></script>');
