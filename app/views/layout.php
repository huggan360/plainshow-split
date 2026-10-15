<?php
/** Page frame: PlainShow top bar, content and phone tab bar. */

function split_layout_start(string $title, array $options = []): void
{
    $identity = current_identity();
    $me = $identity ? Split\Domain\People::one($identity->id()) : null;
    ?>
<!doctype html>
<html lang="sv" data-theme="dark">
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
    <script src="/ui/js/plainshow.js?v=<?= e(split_ui_version()) ?>" defer></script>
    <script src="<?= e(split_asset('assets/split/split.js')) ?>" defer></script>
</head>
<body class="ps split">
<header class="ps-topnav">
    <div class="ps-topnav__inner">
        <a class="ps-brand ps-topnav__brand" href="/">
            <img class="ps-brand__logo" src="/ui/brand/plainshow-logo.webp?v=<?= e(split_ui_version()) ?>" alt="PlainShow" width="92" height="30">
            <span class="ps-brand__suffix">Split</span>
        </a>
        <span class="ps-topnav__spacer"></span>
        <?php if ($me): ?>
        <div class="ps-topnav__actions">
            <div class="ps-dropdown" data-ps-dropdown>
                <button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-ps-dropdown-trigger aria-expanded="false" aria-label="Byt tjänst"><i class="bx bx-grid-small"></i></button>
                <div class="ps-switcher__panel" data-ps-dropdown-menu hidden>
                    <div class="ps-switcher__grid">
                        <a class="ps-switcher__app" href="https://plainshow.se"><i class="bx bx-grid-alt"></i>Dashboard</a>
                        <a class="ps-switcher__app" href="https://blog.plainshow.se"><i class="bx bx-news"></i>Blog</a>
                        <a class="ps-switcher__app is-current" href="/"><i class="bx bx-wallet"></i>Split</a>
                        <a class="ps-switcher__app" href="https://latex.plainshow.se"><i class="bx bx-code-curly"></i>LaTeX</a>
                        <a class="ps-switcher__app" href="https://cloud.plainshow.se"><i class="bx bx-cloud"></i>Cloud</a>
                    </div>
                </div>
            </div>
            <div class="ps-dropdown" data-ps-dropdown>
                <button type="button" class="ps-btn ps-btn--ghost split-account" data-ps-dropdown-trigger aria-expanded="false" aria-label="Konto"><?= avatar($me, 'sm') ?></button>
                <div class="ps-dropdown__menu" data-ps-dropdown-menu hidden>
                    <div class="ps-dropdown__head"><?= avatar($me) ?><div class="split-account__who"><strong><?= e($me['name']) ?></strong><span class="ps-muted ps-small"><?= $me['phone'] ? 'Swish +' . e($me['phone']) : 'Inget Swish-nummer' ?></span></div></div>
                    <div class="ps-dropdown__sep"></div>
                    <a class="ps-dropdown__item" href="https://plainshow.se/account"><i class="bx bx-id-card"></i>Konto och telefonnummer</a>
                    <button type="button" class="ps-dropdown__item" data-ps-theme-toggle><i class="bx bx-adjust"></i><span data-ps-theme-label>Ljust tema</span></button>
                    <div class="ps-dropdown__sep"></div>
                    <a class="ps-dropdown__item" href="/auth/logout"><i class="bx bx-log-out"></i>Logga ut</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</header>
<main class="ps-page split-page">
<?php
}

function split_layout_end(string $scripts = ''): void
{
    ?>
</main>
<div class="ps-toasts" aria-live="polite" data-toasts></div>
<?= $scripts ?>
</body>
</html>
<?php
}
