<?php
// Staff decisions on IT Knowledge Repository submissions.
//   approve    certified technician only, Pending -> Approved + published (with edited text)
//   reject     certified technician only, Pending -> Rejected (reason required)
//   unpublish  any staff, hides an approved entry from students
//   republish  certified technician only, shows an unpublished approved entry again
// Every update includes the expected current state in its WHERE clause, so two staff
// acting at the same time cannot both decide the same submission.

require_once '../includes/auth.php';
require_login('admin');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/repository.php');
}
check_csrf();

$staff_id = (int) $_SESSION['user_id'];
$entry_id = (int) post_text('entry_id');
$action = post_text('action');
$back = 'admin/repository_review.php?id=' . $entry_id;
$technician = is_certified_technician($conn);

$stmt = $conn->prepare('SELECT entry_id, status, is_published FROM repository_entries WHERE entry_id = ?');
$stmt->bind_param('i', $entry_id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();
if (!$entry) {
    set_flash('That submission does not exist.', 'error');
    redirect('admin/repository.php');
}

if (in_array($action, ['approve', 'reject', 'republish'], true) && !$technician) {
    set_flash('Only university-certified technicians can approve, reject or publish repository entries.', 'error');
    redirect($back);
}

if ($action === 'approve' || $action === 'reject') {
    if ($entry['status'] !== 'Pending') {
        set_flash('This submission has already been evaluated.', 'error');
        redirect($back);
    }
    $notes = post_text('review_notes');
    $notes = $notes === '' ? null : mb_substr($notes, 0, 2000);

    if ($action === 'reject') {
        $reason = post_text('rejection_reason');
        if (mb_strlen($reason) < 10) {
            set_flash('Write a rejection reason (at least 10 characters) so the decision is recorded clearly.', 'error');
            redirect($back);
        }
        $stmt = $conn->prepare("UPDATE repository_entries
            SET status = 'Rejected', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ?, review_notes = ?, is_published = 0
            WHERE entry_id = ? AND status = 'Pending'");
        $stmt->bind_param('issi', $staff_id, $reason, $notes, $entry_id);
        $stmt->execute();
        set_flash($stmt->affected_rows === 1 ? 'Submission rejected. It will never appear in the repository.' : 'This submission has already been evaluated.', $stmt->affected_rows === 1 ? 'success' : 'error');
        redirect($back);
    }

    // Approve: the technician's reviewed text is what gets published
    $title = post_text('title');
    $category = post_text('category');
    $summary = post_text('summary');
    $problem = post_text('problem');
    $solution = post_text('solution');
    $keywords = mb_substr(post_text('keywords'), 0, 255);
    $errors = [];
    if (mb_strlen($title) < 10 || mb_strlen($title) > 150) { $errors[] = 'title (10 to 150 characters)'; }
    if (!in_array($category, REPO_CATEGORIES, true)) { $errors[] = 'category'; }
    if (mb_strlen($summary) < 20 || mb_strlen($summary) > 300) { $errors[] = 'short description (20 to 300 characters)'; }
    if (mb_strlen($problem) < 20) { $errors[] = 'problem description'; }
    if (mb_strlen($solution) < 20) { $errors[] = 'verified solution'; }
    if ($errors) {
        set_flash('Please complete: ' . implode(', ', $errors) . '.', 'error');
        redirect($back);
    }
    $keywords = $keywords === '' ? null : $keywords;
    $stmt = $conn->prepare("UPDATE repository_entries
        SET status = 'Approved', title = ?, category = ?, summary = ?, problem = ?, solution = ?, keywords = ?,
            reviewed_by = ?, reviewed_at = NOW(), review_notes = ?, rejection_reason = NULL,
            is_published = 1, published_at = NOW(), unpublished_at = NULL
        WHERE entry_id = ? AND status = 'Pending'");
    $stmt->bind_param('ssssssisi', $title, $category, $summary, $problem, $solution, $keywords, $staff_id, $notes, $entry_id);
    $stmt->execute();
    set_flash($stmt->affected_rows === 1 ? 'Approved and published permanently in the IT Knowledge Repository.' : 'This submission has already been evaluated.', $stmt->affected_rows === 1 ? 'success' : 'error');
    redirect($back);
}

if ($action === 'unpublish') {
    $stmt = $conn->prepare("UPDATE repository_entries SET is_published = 0, unpublished_at = NOW()
                            WHERE entry_id = ? AND status = 'Approved' AND is_published = 1");
    $stmt->bind_param('i', $entry_id);
    $stmt->execute();
    set_flash($stmt->affected_rows === 1 ? 'Entry unpublished. Students can no longer see it.' : 'Only published, approved entries can be unpublished.', $stmt->affected_rows === 1 ? 'success' : 'error');
    redirect($back);
}

if ($action === 'republish') {
    $stmt = $conn->prepare("UPDATE repository_entries SET is_published = 1, unpublished_at = NULL
                            WHERE entry_id = ? AND status = 'Approved' AND is_published = 0");
    $stmt->bind_param('i', $entry_id);
    $stmt->execute();
    set_flash($stmt->affected_rows === 1 ? 'Entry published again.' : 'Only unpublished, approved entries can be published again.', $stmt->affected_rows === 1 ? 'success' : 'error');
    redirect($back);
}

set_flash('Unknown action.', 'error');
redirect($back);
