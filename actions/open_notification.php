<?php
// Clicking a notification: mark it as read, then go to the related post, chat or meetup request.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

$user_id = (int) $_SESSION['user_id'];
$notification_id = (int) get_text('id');

// Only the owner's own notifications can be opened
$stmt = $conn->prepare('SELECT link FROM notifications WHERE notification_id = ? AND user_id = ?');
$stmt->bind_param('ii', $notification_id, $user_id);
$stmt->execute();
$notification = $stmt->get_result()->fetch_assoc();

if (!$notification) {
    redirect('student/dashboard.php');
}

$stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ?');
$stmt->bind_param('i', $notification_id);
$stmt->execute();

// Links are always pages inside the student area (e.g. student/post.php?id=3#reply-5).
// Checking this means a notification can never send someone to another website.
$link = $notification['link'];
if (strpos($link, 'student/') !== 0) {
    $link = 'student/dashboard.php';
}

redirect($link);
