<?php
// "Delete chat": remove a whole conversation from MY inbox.
// The other student still has their copy. If they message again, the chat comes back
// with only the new messages. See delete_conversation() in functions.php.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/messages.php');
}
check_csrf();

$my_id = (int) $_SESSION['user_id'];
$other_id = (int) post_text('user_id');

$other = find_message_partner($conn, $my_id, $other_id);
if (!$other) {
    set_flash('That chat could not be found.', 'error');
    redirect('student/messages.php');
}

delete_conversation($conn, $my_id, $other_id);

set_flash('Chat with ' . $other['username'] . ' deleted from your messages.');
redirect('student/messages.php');
