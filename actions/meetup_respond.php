<?php
// The recipient accepts or rejects a meetup request.
// Accepting requires the Meetup Liability & Safety Notice to be acknowledged (every time, never saved).

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/meetups.php');
}
check_csrf();

$my_id = (int) $_SESSION['user_id'];
$meetup_id = (int) post_text('meetup_id');
$decision = post_text('decision');

$stmt = $conn->prepare('SELECT meetup_id, requester_id, recipient_id, status FROM meetup_requests WHERE meetup_id = ?');
$stmt->bind_param('i', $meetup_id);
$stmt->execute();
$meetup = $stmt->get_result()->fetch_assoc();

// Only the student who received the request can answer it, and only once
if (!$meetup || (int) $meetup['recipient_id'] !== $my_id) {
    set_flash('That meetup request was not found.', 'error');
    redirect('student/meetups.php');
}
if ($meetup['status'] !== 'Pending') {
    set_flash('You already replied to this meetup request.', 'error');
    redirect('student/meetups.php');
}
if ($decision !== 'accept' && $decision !== 'reject') {
    set_flash('Unknown action.', 'error');
    redirect('student/meetups.php');
}
if ($decision === 'accept' && post_text('safety_ack') !== '1') {
    set_flash('Please read and agree to the Meetup Liability & Safety Notice before accepting.', 'error');
    redirect('student/meetups.php');
}

$new_status = $decision === 'accept' ? 'Accepted' : 'Rejected';

// "AND status = Pending" stops a double click from answering twice
$stmt = $conn->prepare("UPDATE meetup_requests SET status = ? WHERE meetup_id = ? AND status = 'Pending'");
$stmt->bind_param('si', $new_status, $meetup_id);
$stmt->execute();

// Tell the student who sent the request
add_notification(
    $conn,
    (int) $meetup['requester_id'],
    $decision === 'accept' ? 'meetup_accepted' : 'meetup_rejected',
    $_SESSION['username'] . ($decision === 'accept' ? ' accepted your meetup request' : ' declined your meetup request'),
    'student/meetups.php?tab=sent#meetup-' . $meetup_id
);

if ($decision === 'accept') {
    set_flash('Meetup accepted. Send a message to arrange the details.');
} else {
    set_flash('Meetup request rejected.');
}
redirect('student/meetups.php#meetup-' . $meetup_id);
