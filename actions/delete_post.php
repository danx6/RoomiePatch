<?php
// A student deletes one of their own posts.
// Removes the post, its replies, its images and related notifications.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/my_posts.php');
}
check_csrf();

$user_id = (int) $_SESSION['user_id'];
$post_id = (int) post_text('post_id');

$stmt = $conn->prepare('SELECT post_id, user_id, title FROM posts WHERE post_id = ?');
$stmt->bind_param('i', $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    set_flash('That post no longer exists.', 'error');
    redirect('student/my_posts.php');
}

// Students may only delete their own posts
if ((int) $post['user_id'] !== $user_id) {
    set_flash('You can only delete your own posts.', 'error');
    redirect('student/post.php?id=' . $post_id);
}

delete_post($conn, $post_id);

set_flash('Your post "' . $post['title'] . '" was deleted.');
redirect('student/my_posts.php');
