<?php
// Student dashboard: a short welcome, the "Ask a question" box,
// the newest questions from everyone, and the student's own open questions.

require_once '../includes/auth.php';
require_login('student');

$page_title = 'Dashboard';
$active = 'dashboard';
include '../includes/header.php';

$user_id = (int) $_SESSION['user_id'];

// 10 newest questions from all students
$recent_posts = $conn->query(POST_LIST_SQL . ' ORDER BY p.created_at DESC LIMIT 10');

// This student's own questions that are still open
$stmt = $conn->prepare('SELECT post_id, title, created_at FROM posts WHERE user_id = ? AND status = "Open" ORDER BY created_at DESC LIMIT 5');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$my_open = $stmt->get_result();
?>

<main class="content">
    <div class="page-head">
        <h1>Welcome back, <?= e($_SESSION['username']) ?></h1>
        <p>See what other students are stuck on, or ask about a problem of your own.</p>
    </div>

    <div class="ask-box">
        <?= avatar($_SESSION['username']) ?>
        <a class="ask-box-input" href="<?= BASE_URL ?>student/create_post.php">Describe your IT problem...</a>
    </div>

    <div class="feed-head">
        <h2>Recent questions</h2>
        <a href="<?= BASE_URL ?>student/browse.php">Browse all</a>
    </div>

    <div class="feed">
        <?php if ($recent_posts->num_rows === 0): ?>
            <div class="empty-state">
                <strong>No questions yet</strong>
                Be the first to ask about an IT problem.
            </div>
        <?php else: ?>
            <?php while ($post = $recent_posts->fetch_assoc()): ?>
                <?php include '../includes/post_card.php'; ?>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</main>

<aside class="rail">
    <section class="rail-box">
        <h2>How RoomiePatch works</h2>
        <ol class="steps">
            <li><strong>Ask</strong> about a problem with your own laptop, phone or software.</li>
            <li><strong>Get replies</strong> from other students who have fixed it before.</li>
            <li><strong>Mark as resolved</strong> on the reply that solved it, so others can find the fix.</li>
        </ol>
    </section>

    <section class="rail-box">
        <h2>Your open questions</h2>
        <?php if ($my_open->num_rows === 0): ?>
            <p>You have no open questions.</p>
        <?php else: ?>
            <ul class="mini-list">
                <?php while ($mine = $my_open->fetch_assoc()): ?>
                    <li>
                        <a href="<?= BASE_URL ?>student/post.php?id=<?= (int) $mine['post_id'] ?>"><?= e($mine['title']) ?></a>
                        <small><?= e(time_ago($mine['created_at'])) ?></small>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php endif; ?>
    </section>
</aside>

<?php include '../includes/footer.php'; ?>
