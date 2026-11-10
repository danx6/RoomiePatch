<?php
// Submit a resolved Q&A discussion to the IT Knowledge Repository for technician evaluation.
// Allowed: the original poster, the student whose reply was accepted, or any staff member.
// The submission starts as Pending and is never shown to students until a certified
// technician approves it. A post can only ever have one submission (UNIQUE post_id).

require_once '../includes/auth.php';
require_login(['student', 'admin']);
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/browse.php');
}
check_csrf();

$user_id = (int) $_SESSION['user_id'];
$post_id = (int) post_text('post_id');
$post_page = 'student/post.php?id=' . $post_id;

$stmt = $conn->prepare('
    SELECT p.post_id, p.user_id, p.title, p.description, p.tags, p.status, p.accepted_reply_id,
           c.category_name, r.user_id AS solver_id, r.content AS solution
    FROM posts p
    JOIN categories c ON c.category_id = p.category_id
    LEFT JOIN replies r ON r.reply_id = p.accepted_reply_id
    WHERE p.post_id = ?');
$stmt->bind_param('i', $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    set_flash('That post no longer exists.', 'error');
    redirect('student/browse.php');
}
if ($post['status'] !== 'Resolved' || $post['accepted_reply_id'] === null) {
    set_flash('Only resolved discussions with an accepted reply can be submitted.', 'error');
    redirect($post_page);
}
$allowed = is_staff() || (int) $post['user_id'] === $user_id || (int) $post['solver_id'] === $user_id;
if (!$allowed) {
    set_flash('Only the original poster or the student whose reply solved it can submit this discussion.', 'error');
    redirect($post_page);
}
if (repository_entry_for_post($conn, $post_id)) {
    set_flash('This discussion has already been submitted for evaluation.', 'error');
    redirect($post_page);
}

// Starting text for the reviewer: taken from the original question and accepted reply.
// The technician can refine it before approving. The discussion itself is not copied:
// the entry keeps post_id and accepted_reply_id references to it.
$category = repo_category_for($post['category_name']);
$summary = excerpt($post['description'], 280);
$stmt = $conn->prepare('
    INSERT INTO repository_entries
        (post_id, accepted_reply_id, category, title, summary, problem, solution, keywords,
         contributor_id, solver_id, submitted_by, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Pending")');
$stmt->bind_param('iissssssiii', $post['post_id'], $post['accepted_reply_id'], $category, $post['title'], $summary,
    $post['description'], $post['solution'], $post['tags'], $post['user_id'], $post['solver_id'], $user_id);

try {
    $stmt->execute();
} catch (mysqli_sql_exception $e) {
    // Someone else submitted it at the same moment (UNIQUE post_id)
    set_flash('This discussion has already been submitted for evaluation.', 'error');
    redirect($post_page);
}

set_flash('Submitted. A university-certified technician will evaluate it before it can appear in the IT Knowledge Repository.');
redirect($post_page);
