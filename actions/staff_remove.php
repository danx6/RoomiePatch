<?php
// Staff remove a post or reply directly from the post page, without a student report.
//   type=post&id=POST_ID   or   type=reply&id=REPLY_ID
// Any pending reports about the same content are closed as "Removed".

require_once '../includes/auth.php';
require_login('admin');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/browse.php');
}
check_csrf();

$type = post_text('type');
$id = (int) post_text('id');

if ($type === 'post') {
    $stmt = $conn->prepare('SELECT post_id FROM posts WHERE post_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        set_flash('That post no longer exists.', 'error');
        redirect('student/browse.php');
    }

    $conn->begin_transaction();
    staff_remove_post($conn, $id);
    $conn->commit();

    set_flash('Post removed. Its replies and images were removed too.');
    redirect('student/browse.php');
}

if ($type === 'reply') {
    $stmt = $conn->prepare('SELECT post_id FROM replies WHERE reply_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $reply = $stmt->get_result()->fetch_assoc();
    if (!$reply) {
        set_flash('That reply no longer exists.', 'error');
        redirect('student/browse.php');
    }

    $conn->begin_transaction();
    staff_remove_reply($conn, $id);
    $conn->commit();

    set_flash('Reply removed.');
    redirect('student/post.php?id=' . (int) $reply['post_id']);
}

set_flash('Unknown action.', 'error');
redirect('student/browse.php');
