<?php
// Admin decision on a report:
//   action=remove   delete the reported post or reply, and close every pending report about it
//   action=dismiss  close this report without changing anything

require_once '../includes/auth.php';
require_login('admin');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/dashboard.php');
}
check_csrf();

$report_id = (int) post_text('report_id');
$action = post_text('action');

$stmt = $conn->prepare('SELECT report_id, reported_post_id, reported_reply_id, status FROM reports WHERE report_id = ?');
$stmt->bind_param('i', $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    set_flash('That report does not exist.', 'error');
    redirect('admin/dashboard.php');
}
if ($report['status'] !== 'Pending') {
    set_flash('This report was already handled.', 'error');
    redirect('admin/dashboard.php');
}

// ---------- Dismiss ----------
if ($action === 'dismiss') {
    $stmt = $conn->prepare("UPDATE reports SET status = 'Dismissed' WHERE report_id = ?");
    $stmt->bind_param('i', $report_id);
    $stmt->execute();

    set_flash('Report dismissed. The content was left as it is.');
    redirect('admin/dashboard.php');
}

// ---------- Remove ----------
if ($action === 'remove') {
    $conn->begin_transaction();

    // These helpers also close every other pending report about the same content
    if ($report['reported_post_id'] !== null) {
        staff_remove_post($conn, (int) $report['reported_post_id']);
        $message = 'Post removed. Its replies and images were removed too.';
    } elseif ($report['reported_reply_id'] !== null) {
        staff_remove_reply($conn, (int) $report['reported_reply_id']);
        $message = 'Reply removed.';
    } else {
        // The content was already deleted by its author
        $conn->rollback();
        set_flash('That content no longer exists. Dismiss the report instead.', 'error');
        redirect('admin/dashboard.php');
    }

    $conn->commit();
    set_flash($message);
    redirect('admin/dashboard.php');
}

set_flash('Unknown action.', 'error');
redirect('admin/dashboard.php');
