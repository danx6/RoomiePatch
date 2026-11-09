<?php
// One report in the admin queue. Used by admin/dashboard.php and admin/report.php.
// Expects $report: one row from REPORT_LIST_SQL (see functions.php).
// Set $full_view = true to show the whole post/reply text instead of a short preview.

$full_view = $full_view ?? false;

// What kind of content is it, and does it still exist?
if ($report['post_title'] !== null) {
    $kind = 'Post';
} elseif ($report['reply_content'] !== null) {
    $kind = 'Reply';
} else {
    $kind = null; // already deleted
}
$is_pending = $report['status'] === 'Pending';
?>
<article class="report-item">
    <div class="report-head">
        <?php if ($kind !== null): ?>
            <span class="type-badge"><?= $kind ?></span>
        <?php endif; ?>
        <span class="reason-badge"><?= icon('flag', 13) ?><?= e($report['reason']) ?></span>
        <span class="report-by">
            reported by <strong><?= e($report['reporter_name']) ?></strong>
            <span class="dot" aria-hidden="true"></span>
            <time datetime="<?= e($report['created_at']) ?>"><?= e(time_ago($report['created_at'])) ?></time>
        </span>
        <?php if (!$is_pending): ?>
            <span class="status-badge <?= $report['status'] === 'Removed' ? 'removed' : 'dismissed' ?>"><?= e($report['status']) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($kind === 'Post'): ?>
        <div class="reported-preview">
            <div class="reported-preview-label">Post by <?= e($report['post_author']) ?> <span class="dot" aria-hidden="true"></span> <?= e(time_ago($report['post_created'])) ?></div>
            <strong><?= e($report['post_title']) ?></strong>
            <p><?= $full_view ? nl2br(e($report['post_description'])) : e(excerpt($report['post_description'], 220)) ?></p>
        </div>
    <?php elseif ($kind === 'Reply'): ?>
        <div class="reported-preview">
            <div class="reported-preview-label">Reply by <?= e($report['reply_author']) ?> <span class="dot" aria-hidden="true"></span> <?= e(time_ago($report['reply_created'])) ?></div>
            <span class="muted">On: <?= e($report['reply_post_title']) ?></span>
            <p><?= $full_view ? nl2br(e($report['reply_content'])) : e(excerpt($report['reply_content'], 220)) ?></p>
        </div>
    <?php else: ?>
        <div class="reported-preview reported-gone">
            The reported content has been deleted<?= $report['status'] === 'Removed' ? ' by staff' : '' ?>.
        </div>
    <?php endif; ?>

    <?php if ($report['description'] !== null && $report['description'] !== ''): ?>
        <p class="report-note"><span>Reporter's note:</span> <?= e($report['description']) ?></p>
    <?php endif; ?>

    <?php if ($is_pending): ?>
        <div class="report-actions">
            <?php if (!$full_view && $kind !== null): ?>
                <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>admin/report.php?id=<?= (int) $report['report_id'] ?>">Review</a>
            <?php endif; ?>

            <?php if ($kind !== null): ?>
                <form method="post" action="<?= BASE_URL ?>actions/admin_action.php"
                      onsubmit="return confirm('Remove this <?= strtolower($kind) ?>? <?= $kind === 'Post' ? 'Its replies and images will also be removed. ' : '' ?>This cannot be undone.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="report_id" value="<?= (int) $report['report_id'] ?>">
                    <input type="hidden" name="action" value="remove">
                    <button type="submit" class="btn btn-danger btn-sm"><?= icon('trash', 15) ?>Remove <?= strtolower($kind) ?></button>
                </form>
            <?php endif; ?>

            <form method="post" action="<?= BASE_URL ?>actions/admin_action.php">
                <?= csrf_field() ?>
                <input type="hidden" name="report_id" value="<?= (int) $report['report_id'] ?>">
                <input type="hidden" name="action" value="dismiss">
                <button type="submit" class="btn btn-secondary btn-sm">Dismiss report</button>
            </form>
        </div>
    <?php endif; ?>
</article>
