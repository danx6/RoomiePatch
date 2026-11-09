<?php
// Mark a reply as the one that solved the problem.
// Only the post's author can do this, only once, and not on their own reply.
// The reply gets is_resolved = 1 and the post status becomes Resolved.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/browse.php');
}
check_csrf();

$user_id  = (int) $_SESSION['user_id'];
$reply_id = (int) post_text('reply_id');

// Find the reply together with its post
$stmt = $conn->prepare('
    SELECT r.reply_id, r.user_id AS reply_author, p.post_id, p.user_id AS post_author, p.status
    FROM replies r
    JOIN posts p ON p.post_id = r.post_id
    WHERE r.reply_id = ?');
$stmt->bind_param('i', $reply_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    set_flash('That reply no longer exists.', 'error');
    redirect('student/browse.php');
}

$post_page = 'student/post.php?id=' . (int) $row['post_id'];

// ---------- Permission checks ----------
if ((int) $row['post_author'] !== $user_id) {
    set_flash('Only the author of this question can mark a reply as resolved.', 'error');
    redirect($post_page);
}
if ($row['status'] === 'Resolved') {
    set_flash('This question is already resolved.', 'error');
    redirect($post_page);
}
if ((int) $row['reply_author'] === $user_id) {
    set_flash('You cannot mark your own reply as the solution.', 'error');
    redirect($post_page);
}

// ---------- Update both tables together ----------
// A transaction makes sure either both changes are saved or neither is.
$conn->begin_transaction();

// Only the status changes. created_at stays the same, so the post keeps its place in the newest-first list.
// "AND status = Open" means a double click cannot resolve the post twice.
$stmt = $conn->prepare('UPDATE posts SET status = "Resolved" WHERE post_id = ? AND status = "Open"');
$stmt->bind_param('i', $row['post_id']);
$stmt->execute();

if ($stmt->affected_rows !== 1) {
    $conn->rollback();
    set_flash('This question is already resolved.', 'error');
    redirect($post_page);
}

$stmt = $conn->prepare('UPDATE replies SET is_resolved = 1 WHERE reply_id = ?');
$stmt->bind_param('i', $reply_id);
$stmt->execute();

$conn->commit();

set_flash('Marked as resolved. Thanks for letting everyone know what fixed it.');
redirect($post_page . '#reply-' . $reply_id);
