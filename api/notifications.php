<?php
// Live notification bell endpoint (JSON), used by live.js on every student page.
//
// GET  api/notifications.php?since=YYYY-MM-DD HH:MM:SS&shown=1,2,3
//      Returns only notifications created (or moved to the top) since that time,
//      the unread count, which of the shown ids are still unread, and the sidebar badge counts.
//
// POST api/notifications.php   (csrf_token)  -> mark all my notifications as read

require_once '../includes/api.php';
api_require_role('student');

$user_id = (int) $_SESSION['user_id'];

// ---------- Mark all as read ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    api_check_csrf();
    session_write_close();

    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    json_out(['ok' => true]);
}

// ---------- Poll ----------
session_write_close(); // read-only: release the session lock straight away

// "since" must look like a MySQL date-time; anything else means "only from now on"
$since = get_text('since', 19);
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
    $since = date('Y-m-d H:i:s');
}

// Database time now, sent back so the next poll asks from here
$now = $conn->query('SELECT NOW() AS t')->fetch_assoc()['t'];

// Only MY notifications that are new or were moved to the top since the last poll
$stmt = $conn->prepare('SELECT notification_id, type, message, is_read, created_at
                        FROM notifications
                        WHERE user_id = ? AND created_at >= ?
                        ORDER BY created_at ASC, notification_id ASC
                        LIMIT 10');
$stmt->bind_param('is', $user_id, $since);
$stmt->execute();

$items = [];
foreach ($stmt->get_result() as $n) {
    $items[] = [
        'id'   => (int) $n['notification_id'],
        'html' => render_partial(__DIR__ . '/../includes/notification_item.php', ['n' => $n]),
    ];
}

// Which of the notifications already on screen are still unread
// (they may have been read in another tab, or by opening the chat or meetup page)
$still_unread = [];
$shown = array_slice(array_filter(array_map('intval', explode(',', get_text('shown', 200)))), 0, 20);
if ($shown) {
    $placeholders = implode(',', array_fill(0, count($shown), '?'));
    $stmt = $conn->prepare("SELECT notification_id FROM notifications
                            WHERE user_id = ? AND is_read = 0 AND notification_id IN ($placeholders)");
    $stmt->bind_param('i' . str_repeat('i', count($shown)), $user_id, ...$shown);
    $stmt->execute();
    foreach ($stmt->get_result() as $row) {
        $still_unread[] = (int) $row['notification_id'];
    }
}

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$unread = (int) $stmt->get_result()->fetch_assoc()['total'];

json_out([
    'ok'           => true,
    'server_time'  => $now,
    'items'        => $items,
    'unread'       => $unread,
    'still_unread' => $still_unread,
    'badges'       => [
        'messages' => unread_message_count($conn, $user_id),
        'meetups'  => pending_meetup_count($conn, $user_id),
    ],
]);
