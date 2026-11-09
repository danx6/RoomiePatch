<?php
// Top of every logged-in page: <head>, the top bar, and the left sidebar.
//
// Before including this file, the page sets:
//   $page_title  text for the browser tab, e.g. 'Dashboard'
//   $active      which sidebar link is highlighted, e.g. 'dashboard'
//
// After this file, the page prints <main class="content"> and, if it wants one,
// <aside class="rail"> for the right column. Then it includes footer.php.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/../config/db.php';

$is_admin = $_SESSION['role'] === 'admin';
$home_link = BASE_URL . dashboard_for($_SESSION['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> - RoomiePatch</title>
    <?php include __DIR__ . '/theme_head.php'; ?>
    <link rel="icon" type="image/png" href="<?= asset('assets/img/logo.png') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body data-base-url="<?= BASE_URL ?>">

<header class="topbar">
    <button type="button" class="icon-btn menu-toggle" data-menu-toggle aria-label="Open menu">
        <?= icon('menu') ?>
    </button>

    <a class="brand" href="<?= $home_link ?>">
        <img class="brand-logo" src="<?= asset('assets/img/logo.png') ?>" alt="" width="34" height="34">
        <span>RoomiePatch</span>
    </a>

    <form class="topbar-search" action="<?= BASE_URL ?>student/browse.php" method="get" role="search">
        <?= icon('search', 18) ?>
        <input type="search" name="q" placeholder="Search questions" aria-label="Search questions"
               value="<?= e(get_text('q')) ?>">
    </form>

    <div class="topbar-actions">
        <?php if (!$is_admin): ?>
            <a class="btn btn-primary btn-ask" href="<?= BASE_URL ?>student/create_post.php">
                <?= icon('plus', 18) ?><span>Ask a question</span>
            </a>
        <?php endif; ?>

        <?php if (!$is_admin): ?>
            <?php include __DIR__ . '/notification_bell.php'; ?>
        <?php endif; ?>

        <button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="Switch light or dark theme">
            <span class="icon-moon"><?= icon('moon') ?></span>
            <span class="icon-sun"><?= icon('sun') ?></span>
        </button>

        <div class="dropdown">
            <button type="button" class="user-menu-btn" data-dropdown-toggle aria-haspopup="true" aria-expanded="false">
                <?= avatar($_SESSION['username'], 'sm') ?>
                <span class="user-menu-name"><?= e($_SESSION['username']) ?></span>
                <?= icon('chevron-down', 16) ?>
            </button>
            <div class="dropdown-panel">
                <div class="dropdown-header">
                    Signed in as<br><strong><?= e($_SESSION['username']) ?></strong>
                </div>
                <?php if (!$is_admin): ?>
                    <a class="dropdown-item" href="<?= BASE_URL ?>student/profile.php?id=<?= (int) $_SESSION['user_id'] ?>">
                        <?= icon('user', 18) ?>Your profile
                    </a>
                <?php endif; ?>
                <a class="dropdown-item" href="<?= BASE_URL ?>logout.php"><?= icon('logout', 18) ?>Log out</a>
            </div>
        </div>
    </div>
</header>

<div class="menu-overlay" data-menu-toggle></div>

<div class="layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
