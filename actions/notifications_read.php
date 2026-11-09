<?php
// "Mark all as read" in the notification panel.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/dashboard.php');
}
check_csrf();

$user_id = (int) $_SESSION['user_id'];
$stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
$stmt->bind_param('i', $user_id);
$stmt->execute();

// Go back to the page the student was on (only if it is a RoomiePatch page)
$back = $_SERVER['HTTP_REFERER'] ?? '';
$base = 'http://' . $_SERVER['HTTP_HOST'] . BASE_URL;
if (strpos($back, $base) === 0) {
    header('Location: ' . $back);
    exit;
}
redirect('student/dashboard.php');
