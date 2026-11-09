<?php
// Send a direct message with a normal form post (used when JavaScript is off).
// With JavaScript on, the chat sends through api/messages.php instead, without reloading.
// Both use send_direct_message() in functions.php.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/messages.php');
}
check_csrf();

$receiver_id = (int) post_text('receiver_id');
$result = send_direct_message($conn, (int) $_SESSION['user_id'], $_SESSION['username'], $receiver_id, post_text('content'));

if (isset($result['error'])) {
    set_flash($result['error'], 'error');
}
redirect('student/messages.php' . ($receiver_id > 0 ? '?user=' . $receiver_id : ''));
