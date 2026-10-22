<?php
/** Page frame: the PlainShow sidebar (groups, and you at the foot), top bar and content. */

function split_layout_start(string $title, array $options = []): void
{
    $identity = current_identity();
    $me = $identity ? Split\Domain\People::one($identity->id()) : null;
    ?>
<!doctype html>
<html lang="<?= e(\PlainShow\Kit\Lang::current()) ?>" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark light">
    <meta name="theme-color" content="#151515">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?> | Split</title>
    <link rel="icon" href="/ui/brand/favicon.ico?v=<?= e(split_ui_version()) ?>" sizes="any">
    <link rel="apple-touch-icon" href="/ui/brand/apple-touch-icon.png?v=<?= e(split_ui_version()) ?>">
    <link rel="preload" href="/ui/fonts/source-serif-4-latin-wght.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/ui/fonts/outfit-latin-wght.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/ui/css/boxicons.css?v=<?= e(split_ui_version()) ?>">
    <link rel="stylesheet" href="/ui/css/plainshow.css?v=<?= e(split_ui_version()) ?>">
    <link rel="stylesheet" href="<?= e(split_asset('assets/split/split.css')) ?>">
    <script>try { var t = localStorage.getItem('plainshow-theme'); if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t); } catch (e) {}</script>
    <script>window.PS_I18N = <?= json_encode((object) \PlainShow\Kit\Lang::dictionary(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>; window.psT = function (s, p) { var o = window.PS_I18N[s] || s; if (p) Object.keys(p).forEach(function (k) { o = o.split('{' + k + '}').join(String(p[k])); }); return o; };</script>
    <script src="/ui/js/plainshow.js?v=<?= e(split_ui_version()) ?>" defer></script>
    <script src="/ui/js/backdrop.js?v=<?= e(split_ui_version()) ?>" defer></script>
    <script src="<?= e(split_asset('assets/split/split.js')) ?>" defer></script>
</head>
<body class="ps split">
<canvas class="ps-backdrop" aria-hidden="true"></canvas>
<div class="ps-shell" data-ps-shell>
    <aside class="ps-sidebar" aria-label="<?= ps_h('Split') ?>">
        <div class="ps-sidebar__brand">
            <a class="ps-brand" href="/">
                <img class="ps-brand__logo" src="/ui/brand/plainshow-logo.webp?v=<?= e(split_ui_version()) ?>" alt="<?= ps_h('PlainShow') ?>" width="92" height="30">
                <span class="ps-brand__suffix"><?= ps_h('Split') ?></span>
            </a>
        </div>
        <?php if ($me): $groups = Split\Domain\Groups::forUser($identity->id()); $current = (int) ($options['group'] ?? 0); ?>
        <nav class="ps-nav">
            <a class="ps-nav__item<?= $current === 0 ? ' is-active' : '' ?>" href="/"><i class="bx bx-wallet"></i><?= ps_h('Grupper') ?></a>
        </nav>
        <?php if ($groups): ?>
        <div class="ps-sidebar__section">
            <p class="ps-eyebrow ps-sidebar__label"><?= ps_h('Dina grupper') ?></p>
            <nav class="ps-nav">
                <?php foreach ($groups as $group): ?>
                <a class="ps-nav__item<?= $current === (int) $group['id'] ? ' is-active' : '' ?>" href="/group?id=<?= (int) $group['id'] ?>"><i class="bx bx-group"></i><span class="split-nav__name"><?= e($group['name']) ?></span></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php endif; ?>
        <div class="ps-sidebar__foot">
            <div class="ps-dropdown ps-me" data-ps-dropdown>
                <button type="button" class="ps-me__button" data-ps-dropdown-trigger aria-expanded="false" aria-label="<?= ps_h('Konto') ?>">
                    <?= avatar($me, 'sm') ?>
                    <span class="ps-me__text"><span class="ps-me__name"><?= e($me['name']) ?></span><span class="ps-me__meta"><?= $me['phone'] ? 'Swish +' . e($me['phone']) : ps_t('Inget Swish-nummer') ?></span></span>
                    <i class="bx bx-chevron-up ps-me__chevron" aria-hidden="true"></i>
                </button>
                <div class="ps-dropdown__menu ps-dropdown__menu--up" data-ps-dropdown-menu hidden>
                    <a class="ps-dropdown__item" href="https://plainshow.se/account"><i class="bx bx-user"></i><?= ps_h('Konto') ?></a>
                    <?= \PlainShow\Kit\Lang::menuSwitch() ?>
                    <button type="button" class="ps-dropdown__item" data-ps-theme-toggle><i class="bx bx-adjust"></i><span data-ps-theme-label><?= ps_h('Ljust tema') ?></span></button>
                    <div class="ps-dropdown__sep"></div>
                    <a class="ps-dropdown__item" href="/auth/logout"><i class="bx bx-log-out"></i><?= ps_h('Logga ut') ?></a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </aside>
    <div class="ps-main">
        <header class="ps-topbar split-topbar">
            <button type="button" class="ps-btn ps-btn--ghost ps-btn--icon ps-menu-toggle" data-ps-menu-toggle aria-label="<?= ps_h('Öppna menyn') ?>"><i class="bx bx-menu"></i></button>
            <a class="ps-brand split-topbar__mark" href="/" aria-label="<?= ps_h('Split') ?>"><img class="ps-brand__mark" src="/ui/brand/plainshow-icon.webp?v=<?= e(split_ui_version()) ?>" alt=""></a>
            <span class="ps-topbar__spacer"></span>
            <?php if ($me): ?>
            <div class="ps-dropdown" data-ps-dropdown>
                <button type="button" class="ps-btn ps-btn--ghost ps-btn--icon ps-switcher__trigger" data-ps-dropdown-trigger aria-expanded="false" aria-label="<?= ps_h('Byt tjänst') ?>"><i class="bx bx-grid"></i></button>
                <div class="ps-switcher__panel" data-ps-dropdown-menu hidden>
                    <div class="ps-switcher__grid">
                        <a class="ps-switcher__app" href="https://plainshow.se"><i class="bx bx-grid-alt"></i><?= ps_h('Dashboard') ?></a>
                        <?php if (split_has_service('blog')): ?><a class="ps-switcher__app" href="https://blog.plainshow.se"><i class="bx bx-news"></i><?= ps_h('Blog') ?></a><?php endif; ?>
                        <a class="ps-switcher__app is-current" href="/"><i class="bx bx-wallet"></i><?= ps_h('Split') ?></a>
                        <?php if (split_has_service('latex')): ?><a class="ps-switcher__app" href="https://latex.plainshow.se"><i class="bx bx-code-curly"></i><?= ps_h('LaTeX') ?></a><?php endif; ?>
                        <?php if (split_has_service('web')): ?><a class="ps-switcher__app" href="https://web.plainshow.se"><i class="bx bx-server"></i><?= ps_h('Web') ?></a><?php endif; ?>
                        <?php if (split_has_service('cloud')): ?><a class="ps-switcher__app" href="https://cloud.plainshow.se"><i class="bx bx-cloud"></i><?= ps_h('Cloud') ?></a><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </header>
        <main class="ps-content split-page">
<?php
}

function split_layout_end(string $scripts = ''): void
{
    ?>
        </main>
    </div>
</div>
<div class="ps-toasts" aria-live="polite" data-toasts></div>
<?= $scripts ?>
</body>
</html>
<?php
}
