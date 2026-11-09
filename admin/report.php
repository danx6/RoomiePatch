<?php
// Review one report: shows the full reported post or reply (with the post's images),
// plus the Remove / Dismiss buttons.

require_once '../includes/auth.php';
require_login('admin');
require_once '../config/db.php';

$report_id = (int) get_text('id');

$stmt = $conn->prepare(REPORT_LIST_SQL . ' WHERE r.report_id = ?');
$stmt->bind_param('i', $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    set_flash('That report does not exist.', 'error');
    redirect('admin/dashboard.php');
}

// Images of the reported post, if it is a post
$images = [];
if ($report['post_title'] !== null) {
    $stmt = $conn->prepare('SELECT image_path FROM post_images WHERE post_id = ? ORDER BY image_id');
    $stmt->bind_param('i', $report['reported_post_id']);
    $stmt->execute();
    $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Other reports about the same content, so the admin sees if several students complained
$other_reports = 0;
if ($report['reported_post_id'] !== null || $report['reported_reply_id'] !== null) {
    $column = $report['reported_post_id'] !== null ? 'reported_post_id' : 'reported_reply_id';
    $content_id = $report['reported_post_id'] ?? $report['reported_reply_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM reports WHERE $column = ? AND report_id <> ? AND status = 'Pending'");
    $stmt->bind_param('ii', $content_id, $report_id);
    $stmt->execute();
    $other_reports = (int) $stmt->get_result()->fetch_assoc()['total'];
}

$page_title = 'Review report';
$active = 'reports';
include '../includes/header.php';
?>

<main class="content">
    <a class="back-link" href="<?= BASE_URL ?>admin/dashboard.php<?= $report['status'] === 'Pending' ? '' : '?tab=handled' ?>">
        <?= icon('chevron-left', 16) ?>Back to reports
    </a>

    <div class="page-head">
        <h1>Review report</h1>
        <?php if ($other_reports > 0): ?>
            <p><?= $other_reports ?> other pending <?= $other_reports === 1 ? 'report is' : 'reports are' ?> about the same content.
               Removing it closes all of them.</p>
        <?php endif; ?>
    </div>

    <?php $full_view = true; include '../includes/report_item.php'; ?>

    <?php
    // Link to the post as students see it, to check the reported content in context
    $context_post_id = $report['post_title'] !== null ? $report['reported_post_id'] : $report['reply_post_id'];
    ?>
    <?php if ($context_post_id !== null): ?>
        <p>
            <a href="<?= BASE_URL ?>student/post.php?id=<?= (int) $context_post_id ?><?= $report['post_title'] === null ? '#reply-' . (int) $report['reported_reply_id'] : '' ?>"
               target="_blank" rel="noopener">View the full thread</a>
            (opens in a new tab)
        </p>
    <?php endif; ?>

    <?php if ($images): ?>
        <h2 class="replies-heading">Attached images</h2>
        <div class="thread-images">
            <?php foreach ($images as $image): ?>
                <a href="<?= BASE_URL ?>uploads/posts/<?= e($image['image_path']) ?>" data-lightbox title="Enlarge image">
                    <img src="<?= BASE_URL ?>uploads/posts/<?= e($image['image_path']) ?>" alt="Image attached to the reported post" loading="lazy">
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>
