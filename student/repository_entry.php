<?php
// One IT Knowledge Repository entry, plus its original discussion in read-only form.
// Only approved + published entries can be opened (pending, rejected and unpublished
// entries return "not found", also for direct links).
// The discussion view shows the original question and ONLY the reply the original poster
// accepted (repository_entries.accepted_reply_id). Other replies stay in the database and
// on the original thread page; they are just not shown here.

require_once '../includes/auth.php';
require_login(['student', 'admin']);
require_once '../config/db.php';

$entry_id = (int) get_text('id');
$stmt = $conn->prepare('
    SELECT e.*, c.username AS contributor, s.username AS solver, rv.username AS reviewer,
           p.title AS post_title, p.description AS post_description, p.created_at AS post_created,
           p.resolved_at, p.device_os, p.user_id AS post_author_id, cat.category_name
    FROM repository_entries e
    LEFT JOIN users c ON c.user_id = e.contributor_id
    LEFT JOIN users s ON s.user_id = e.solver_id
    LEFT JOIN users rv ON rv.user_id = e.reviewed_by
    LEFT JOIN posts p ON p.post_id = e.post_id
    LEFT JOIN categories cat ON cat.category_id = p.category_id
    WHERE e.entry_id = ? AND ' . REPO_PUBLIC_SQL);
$stmt->bind_param('i', $entry_id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();

if (!$entry) {
    http_response_code(404);
    $page_title = 'Entry not found';
    $active = 'repository';
    include '../includes/header.php';
    echo '<main class="content"><div class="feed"><div class="empty-state"><strong>Entry not found</strong>'
        . 'This entry does not exist or is not published. <a href="' . BASE_URL . 'student/repository.php">Back to the repository</a></div></div></main>';
    include '../includes/footer.php';
    exit;
}

// The exact accepted reply that was recorded when the original poster resolved the post
$accepted = null;
if ($entry['accepted_reply_id'] !== null) {
    $stmt = $conn->prepare('SELECT r.reply_id, r.content, r.created_at, r.user_id, u.username
                            FROM replies r JOIN users u ON u.user_id = r.user_id WHERE r.reply_id = ?');
    $stmt->bind_param('i', $entry['accepted_reply_id']);
    $stmt->execute();
    $accepted = $stmt->get_result()->fetch_assoc();
}
$images = [];
if ($entry['post_id'] !== null) {
    $stmt = $conn->prepare('SELECT image_path FROM post_images WHERE post_id = ? ORDER BY image_id');
    $stmt->bind_param('i', $entry['post_id']);
    $stmt->execute();
    $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$page_title = $entry['title'];
$active = 'repository';
include '../includes/header.php';
?>

<main class="content">
    <a class="back-link" href="<?= BASE_URL ?>student/repository.php"><?= icon('chevron-left', 16) ?>Back to the repository</a>

    <article class="thread repo-entry">
        <div class="post-meta">
            <span class="type-badge"><?= e($entry['category']) ?></span>
            <span class="repo-verified"><?= icon('shield-check', 14) ?>Technician evaluated</span>
        </div>
        <h1 class="thread-title"><?= e($entry['title']) ?></h1>
        <p class="repo-summary"><?= nl2br(e($entry['summary'])) ?></p>

        <h2 class="repo-section-title">Problem</h2>
        <div class="repo-text"><?= nl2br(e($entry['problem'])) ?></div>

        <h2 class="repo-section-title">Verified solution</h2>
        <div class="repo-text repo-solution"><?= nl2br(e($entry['solution'])) ?></div>

        <?php if ($entry['keywords']): ?>
            <div class="post-footer">
                <?php foreach (tag_list($entry['keywords']) as $kw): ?>
                    <a class="chip" href="<?= BASE_URL ?>student/repository.php?q=<?= urlencode($kw) ?>"><?= e($kw) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <!-- ===== Original discussion (read-only) ===== -->
    <h2 class="replies-heading">Original discussion</h2>
    <?php if ($entry['post_id'] === null): ?>
        <div class="feed"><div class="empty-state"><strong>The original discussion is no longer available</strong>The verified solution above is kept in the repository.</div></div>
    <?php else: ?>
        <article class="thread is-resolved">
            <span class="status-badge resolved"><?= icon('check', 14) ?>Resolved</span>
            <h3 class="thread-title"><?= e($entry['post_title']) ?></h3>
            <div class="thread-meta">
                <a class="author-chip" href="<?= BASE_URL ?>student/profile.php?id=<?= (int) $entry['post_author_id'] ?>">
                    <?= avatar($entry['contributor'] ?? '?', 'sm') ?><?= e($entry['contributor'] ?? 'former student') ?>
                </a>
                <span class="dot" aria-hidden="true"></span>
                <span>asked <?= e(date('j M Y', strtotime($entry['post_created']))) ?></span>
                <span class="dot" aria-hidden="true"></span>
                <span><?= e($entry['category_name']) ?> / <?= e($entry['device_os']) ?></span>
            </div>
            <div class="thread-body"><?= nl2br(e($entry['post_description'])) ?></div>
            <?php if ($images): ?>
                <div class="thread-images">
                    <?php foreach ($images as $image): ?>
                        <a href="<?= BASE_URL ?>uploads/posts/<?= e($image['image_path']) ?>" data-lightbox title="Enlarge image">
                            <img src="<?= BASE_URL ?>uploads/posts/<?= e($image['image_path']) ?>" alt="Image attached to the question" loading="lazy">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p class="repo-resolution">
                <?= icon('check', 16) ?>
                Resolved by <?= e($entry['contributor'] ?? 'the original poster') ?>
                <?= $entry['resolved_at'] ? 'on ' . e(date('j M Y', strtotime($entry['resolved_at']))) : '' ?>,
                who selected the reply below as the solution.
            </p>
        </article>

        <?php if ($accepted): ?>
            <div class="reply-list">
                <article class="reply resolved">
                    <div class="resolved-label"><?= icon('check', 16) ?>Marked as resolved by author</div>
                    <div class="reply-meta">
                        <a class="author-chip" href="<?= BASE_URL ?>student/profile.php?id=<?= (int) $accepted['user_id'] ?>">
                            <?= avatar($accepted['username'], 'sm') ?><?= e($accepted['username']) ?>
                        </a>
                        <span class="dot" aria-hidden="true"></span>
                        <time datetime="<?= e($accepted['created_at']) ?>"><?= e(date('j M Y', strtotime($accepted['created_at']))) ?></time>
                    </div>
                    <div class="reply-body"><?= nl2br(e($accepted['content'])) ?></div>
                </article>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="closed-note" role="status">
        <?= icon('check', 18) ?>
        <p>Submissions are closed as this issue has been resolved.</p>
    </div>
</main>

<aside class="rail">
    <section class="rail-box">
        <h2>About this entry</h2>
        <dl class="info-list">
            <div><dt>Category</dt><dd><?= e($entry['category']) ?></dd></div>
            <div><dt>Asked by</dt><dd><?= e($entry['contributor'] ?? 'former student') ?></dd></div>
            <?php if ($entry['solver']): ?><div><dt>Solved by</dt><dd><?= e($entry['solver']) ?></dd></div><?php endif; ?>
            <div><dt>Published</dt><dd><?= e(date('j M Y', strtotime($entry['published_at']))) ?></dd></div>
        </dl>
    </section>
    <section class="rail-box rail-box-repo">
        <h2><?= icon('shield-check', 16) ?>Evaluation</h2>
        <p>Evaluated and approved by <strong><?= e($entry['reviewer'] ?? 'a certified technician') ?></strong>, university-certified technician, on <?= e(date('j M Y', strtotime($entry['reviewed_at']))) ?>.</p>
    </section>
</aside>

<?php include '../includes/footer.php'; ?>
