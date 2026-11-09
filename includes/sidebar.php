<?php
// Left sidebar. Included by header.php, which already set $is_admin and $conn.

// One sidebar link. $key is compared with the page's $active to highlight it.
// $badge: a number shown on the right (e.g. unread messages); null = this link has no badge.
// The badge is always printed (hidden when 0) so live.js can update it without a reload.
function nav_link($key, $href, $icon_name, $label, $active, $badge = null)
{
    $class = $key === $active ? 'nav-link active' : 'nav-link';
    $current = $key === $active ? ' aria-current="page"' : '';
    $badge_html = '';
    if ($badge !== null) {
        $badge_html = '<span class="nav-badge" data-badge="' . $key . '"' . ($badge > 0 ? '' : ' hidden') . '>'
            . ($badge > 99 ? '99+' : $badge) . '</span>';
    }
    return '<a class="' . $class . '" href="' . BASE_URL . $href . '"' . $current . '>'
        . icon($icon_name) . '<span>' . e($label) . '</span>' . $badge_html . '</a>';
}
?>
<nav class="sidebar" aria-label="Main">
    <button type="button" class="icon-btn sidebar-close" data-menu-toggle aria-label="Close menu">
        <?= icon('close') ?>
    </button>

    <!-- Only shown on phones, where the top bar has no room for search -->
    <form class="sidebar-search" action="<?= BASE_URL ?>student/browse.php" method="get" role="search">
        <?= icon('search', 18) ?>
        <input type="search" name="q" placeholder="Search questions" aria-label="Search questions">
    </form>

    <?php if ($is_admin): ?>
        <!-- Staff: moderation, plus read-only browsing -->
        <div class="nav-group">
            <?= nav_link('reports', 'admin/dashboard.php', 'flag', 'Reports', $active) ?>
            <?= nav_link('meetups', 'admin/meetups.php', 'meetup', 'Meetups', $active) ?>
            <?= nav_link('browse', 'student/browse.php', 'list', 'Browse Posts', $active) ?>
        </div>
    <?php else: ?>
        <div class="nav-group">
            <?= nav_link('dashboard', 'student/dashboard.php', 'home', 'Dashboard', $active) ?>
            <?= nav_link('browse', 'student/browse.php', 'list', 'Browse Posts', $active) ?>
            <?= nav_link('my_posts', 'student/my_posts.php', 'file', 'My Posts', $active) ?>
            <?= nav_link('messages', 'student/messages.php', 'message', 'Messages', $active, unread_message_count($conn, (int) $_SESSION['user_id'])) ?>
            <?= nav_link('meetups', 'student/meetups.php', 'meetup', 'Meetup Requests', $active, pending_meetup_count($conn, (int) $_SESSION['user_id'])) ?>
            <?= nav_link('profile', 'student/profile.php?id=' . (int) $_SESSION['user_id'], 'user', 'Profile', $active) ?>
        </div>
    <?php endif; ?>

    <div class="nav-group">
        <h2 class="nav-heading">Categories</h2>
        <?php
        $categories = $conn->query('SELECT category_id, category_name FROM categories ORDER BY category_id');
        while ($cat = $categories->fetch_assoc()):
        ?>
            <a class="nav-link nav-link-plain" href="<?= BASE_URL ?>student/browse.php?category=<?= (int) $cat['category_id'] ?>">
                <?= e($cat['category_name']) ?>
            </a>
        <?php endwhile; ?>
    </div>
</nav>
