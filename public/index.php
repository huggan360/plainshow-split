<?php
// Your groups, with what you owe or are owed in each.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Split\Domain\Groups;
use Split\Domain\People;
use Split\Domain\PlatformGroups;
use Split\Api\ApiException;

$identity = require_identity();
$me = People::one($identity->id());
$groups = Groups::forUser($identity->id());
$owed = array_sum(array_filter(array_column($groups, 'balance'), fn ($b) => $b > 0));
$owe = -array_sum(array_filter(array_column($groups, 'balance'), fn ($b) => $b < 0));
$directory = array_values(array_filter(People::directory(), fn ($p) => $p['id'] !== $identity->id()));
$plainshowGroups = [];
try {
    $plainshowGroups = PlatformGroups::forUser($identity->id());
} catch (ApiException) {
}

split_layout_start('Grupper');
?>
<div class="split-head ps-page-head ps-page-head--gradient">
    <div>
        <h1 class="ps-page-head__title"><?= ps_h('Split') ?></h1>
        <p class="ps-page-head__text"><?= ps_h('Dela kostnader i grupper och gör upp med Swish.') ?></p>
    </div>
    <?php if (split_can('split.group.create')): ?><button type="button" class="ps-btn ps-btn--primary ps-btn--lg" data-modal-open="group-new"><i class="bx bx-plus"></i><span><?= ps_h('Ny grupp') ?></span></button><?php endif; ?>
</div>

<?php if (!$me['phone']): ?>
<div class="ps-alert split-phone">
    <i class="bx bx-mobile-alt" aria-hidden="true"></i>
    <div><strong><?= ps_h('Lägg till ditt telefonnummer') ?></strong><br><?= ps_h('Då kan de andra betala dig med Swish direkt från Split.') ?></div>
    <a class="ps-btn ps-btn--sm" href="<?= e(ps_url('core', '/account')) ?>"><?= ps_h('Till kontot') ?><i class="bx bx-right-top-arrow-circle"></i></a>
</div>
<?php endif; ?>

<div class="split-totals">
    <div class="split-total split-total--plus"><span><?= ps_h('Du får tillbaka') ?></span><strong><?= e(money($owed)) ?></strong></div>
    <div class="split-total split-total--minus"><span><?= ps_h('Du är skyldig') ?></span><strong><?= e(money($owe)) ?></strong></div>
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
                    <span class="ps-muted ps-small"><?= (int) $group['people'] ?> <?= ps_h('personer, totalt') ?> <?= e(money((int) $group['total'])) ?></span>
                    <span class="split-balance <?= $balance > 0 ? 'is-plus' : ($balance < 0 ? 'is-minus' : '') ?>"><?= $balance === 0 ? ps_t('Kvitt') : ($balance > 0 ? ps_t('Du får ') : ps_t('Du är skyldig ')) . e(money(abs($balance))) ?></span>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="ps-empty"><i class="bx bx-wallet"></i><div><?= ps_h('Du är inte med i någon grupp ännu.') ?></div></div>
<?php endif; ?>

<?php if (split_can('split.group.create')): ?><div class="ps-modal-backdrop" id="group-new" hidden>
    <form class="ps-frame ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="group-new-title" data-group-new data-ps-no-loading>
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="group-new-title"><?= ps_h('Ny grupp') ?></h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="<?= ps_h('Stäng') ?>"><i class="bx bx-x"></i></button></div>
            <div class="ps-modal__body">
                <label class="ps-field"><span class="ps-label"><?= ps_h('Namn') ?></span><input class="ps-input" name="name" maxlength="150" required placeholder="<?= ps_h('Till exempel Bali 2026') ?>" autocomplete="off"></label>
                <div class="ps-input-wrap split-people-filter"><i class="bx bx-search" aria-hidden="true"></i><input class="ps-input" type="search" placeholder="<?= ps_h('Sök personer och grupper') ?>" aria-label="<?= ps_h('Sök personer') ?>" data-people-filter></div>
                <div class="split-people" data-people>
                    <?php if ($plainshowGroups): ?><div class="split-picker-heading"><?= ps_h('Grupper') ?></div><?php foreach ($plainshowGroups as $source): ?><label class="split-person split-person--group" data-name="<?= e(mb_strtolower($source['name'])) ?>"><input type="radio" name="sourceGroupId" value="<?= (int) $source['id'] ?>"><span class="split-group-avatar"><i class="bx bx-group"></i></span><span class="split-person__text"><strong><?= e($source['name']) ?></strong></span><span class="ps-badge ps-badge--accent"><?= ps_h('Grupp') ?></span></label><?php endforeach; ?><?php endif; ?>
                    <?php if ($directory): ?><div class="split-picker-heading"><?= ps_h('Föreslagna personer') ?></div><?php endif; ?>
                    <?php foreach ($directory as $index => $person): ?>
                        <?php if ($index === 3): ?><div class="split-picker-heading"><?= ps_h('Personer') ?></div><?php endif; ?>
                        <label class="split-person" data-name="<?= e(mb_strtolower($person['name'] . ' ' . $person['username'])) ?>"><input type="checkbox" name="members" value="<?= $person['id'] ?>"><?= avatar($person, 'xs') ?><span class="split-person__text"><strong><?= e($person['name']) ?></strong><small>@<?= e($person['username']) ?></small></span></label>
                    <?php endforeach; ?>
                </div>
                <p class="ps-help"><?= ps_h('Du är alltid med i grupper du skapar. Fler kan läggas till senare.') ?></p>
            </div>
            <div class="ps-modal__foot"><button type="button" class="ps-btn ps-btn--ghost" data-modal-close><?= ps_h('Avbryt') ?></button><button type="submit" class="ps-btn ps-btn--primary"><?= ps_h('Skapa grupp') ?></button></div>
        </div>
    </form>
</div><?php endif; ?>
<?php split_layout_end('<script src="' . e(split_asset('assets/split/groups.js')) . '" defer></script>');
