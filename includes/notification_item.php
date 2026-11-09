<?php
// One notification in the bell dropdown. Used by includes/notification_bell.php and api/notifications.php.
// Expects: $n (notification_id, type, message, is_read, created_at).

$notif_icons = [
    'message'         => 'message',
    'meetup_request'  => 'meetup',
    'meetup_accepted' => 'check',
    'meetup_rejected' => 'x-circle',
    'reply'           => 'reply',
];
?>
<li data-notification-id="<?= (int) $n['notification_id'] ?>">
    <a class="notif-item<?= $n['is_read'] ? '' : ' unread' ?>"
       href="<?= BASE_URL ?>actions/open_notification.php?id=<?= (int) $n['notification_id'] ?>">
        <span class="notif-icon type-<?= e($n['type']) ?>"><?= icon($notif_icons[$n['type']] ?? 'bell', 16) ?></span>
        <span class="notif-text">
            <span class="notif-message"><?= e($n['message']) ?></span>
            <time datetime="<?= e($n['created_at']) ?>"><?= e(time_ago($n['created_at'])) ?></time>
        </span>
        <span class="notif-dot" aria-label="Unread"></span>
    </a>
</li>
