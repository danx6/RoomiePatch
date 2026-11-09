<?php
// Vote on a reply with a normal form post (used when JavaScript is off).
// With JavaScript on, live.js sends the same form to api/vote.php instead, without reloading.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/browse.php');
}
check_csrf();

$reply_id = (int) post_text('reply_id');
$result = cast_vote($conn, (int) $_SESSION['user_id'], $reply_id, post_text('vote'));

if (isset($result['error'])) {
    set_flash($result['error'], 'error');
}

// Back to the reply on its post page
$stmt = $conn->prepare('SELECT post_id FROM replies WHERE reply_id = ?');
$stmt->bind_param('i', $reply_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
redirect($row ? 'student/post.php?id=' . (int) $row['post_id'] . '#reply-' . $reply_id : 'student/browse.php');
