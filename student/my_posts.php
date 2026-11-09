<?php
// My Posts: only the logged-in student's questions, with Open / Resolved tabs. Newest first.

require_once '../includes/auth.php';
require_login('student');

$page_title = 'My Posts';
$active = 'my_posts';
include '../includes/header.php';

$user_id = (int) $_SESSION['user_id'];
$status = get_text('status') === 'resolved' ? 'Resolved' : 'Open';   // Open is the default tab

// Counts for the tab labels
$stmt = $conn->prepare('SELECT status, COUNT(*) AS total FROM posts WHERE user_id = ? GROUP BY status');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$counts = ['Open' => 0, 'Resolved' => 0];
foreach ($stmt->get_result() as $row) {
    $counts[$row['status']] = (int) $row['total'];
}

// This student's posts for the current tab
$stmt = $conn->prepare(POST_LIST_SQL . ' WHERE p.user_id = ? AND p.status = ? ORDER BY p.created_at DESC');
$stmt->bind_param('is', $user_id, $status);
$stmt->execute();
$posts = $stmt->get_result();
?>

<main class="content">
    <div class="page-head page-head-row">
        <div>
            <h1>My Posts</h1>
            <p>Questions you have asked.</p>
        </div>
        <a class="btn btn-primary" href="<?= BASE_URL ?>student/create_post.php"><?= icon('plus', 18) ?>Ask a question</a>
    </div>

    <?php show_flash(); ?>

    <div class="tabs-row">
        <nav class="tabs" aria-label="Post status">
            <a class="tab<?= $status === 'Open' ? ' active' : '' ?>" href="my_posts.php"
               <?= $status === 'Open' ? 'aria-current="page"' : '' ?>>
                Open Questions <span class="tab-count"><?= $counts['Open'] ?></span>
            </a>
            <a class="tab<?= $status === 'Resolved' ? ' active' : '' ?>" href="my_posts.php?status=resolved"
               <?= $status === 'Resolved' ? 'aria-current="page"' : '' ?>>
                Resolved <span class="tab-count"><?= $counts['Resolved'] ?></span>
            </a>
        </nav>
        <span class="sort-label">Newest first</span>
    </div>

    <div class="feed">
        <?php if ($posts->num_rows === 0): ?>
            <div class="empty-state">
                <?php if ($status === 'Open'): ?>
                    <strong>No open questions</strong>
                    Stuck on an IT problem? <a href="<?= BASE_URL ?>student/create_post.php">Ask a question</a>.
                <?php else: ?>
                    <strong>No resolved questions yet</strong>
                    When a reply fixes your problem, mark it as resolved and the question moves here.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php while ($post = $posts->fetch_assoc()): ?>
                <?php include '../includes/post_card.php'; ?>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
