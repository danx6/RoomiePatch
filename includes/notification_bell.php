<?php
// Notification bell + dropdown panel in the top bar (students only).
// Included by header.php, which already has $conn and the session.
// Shows the 10 latest notifications, newest first, with an unread count on the bell.
// live.js keeps it up to date through api/notifications.php (no page refresh needed).

$bell_user = (int) $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->bind_param('i', $bell_user);
$stmt->execute();
$bell_unread = (int) $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare('SELECT notification_id, type, message, is_read, created_at
                        FROM notifications WHERE user_id = ?
                        ORDER BY created_at DESC, notification_id DESC LIMIT 10');
$stmt->bind_param('i', $bell_user);
$stmt->execute();
$bell_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Database time now: the live updates ask for anything changed after this moment
$bell_since = $conn->query('SELECT NOW() AS t')->fetch_assoc()['t'];
?>
<div class="dropdown notif-dropdown" data-notifications data-since="<?= e($bell_since) ?>">
    <button type="button" class="icon-btn notif-bell" data-dropdown-toggle aria-haspopup="true" aria-expanded="false"
            aria-label="Notifications">
        <?= icon('bell') ?>
        <span class="notif-count" <?= $bell_unread > 0 ? '' : 'hidden' ?>><?= $bell_unread > 9 ? '9+' : $bell_unread ?></span>
    </button>

    <div class="dropdown-panel notif-panel">
        <div class="notif-panel-head">
            <strong>Notifications</strong>
            <form method="post" action="<?= BASE_URL ?>actions/notifications_read.php" id="notif-read-all" <?= $bell_unread > 0 ? '' : 'hidden' ?>>
                <?= csrf_field() ?>
                <button type="submit" class="link-btn">Mark all as read</button>
            </form>
        </div>

        <p class="notif-empty" <?= $bell_items ? 'hidden' : '' ?>>No notifications yet. Replies to your questions, messages and meetup requests will show up here.</p>

        <ul class="notif-list" <?= $bell_items ? '' : 'hidden' ?>>
            <?php foreach ($bell_items as $n): ?>
                <?php include __DIR__ . '/notification_item.php'; ?>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
