<?php
// Staff: IT Knowledge Repository submissions. Tabs: Pending, Approved, Rejected.
// Every staff member can view this page; only certified technicians can evaluate.

require_once '../includes/auth.php';
require_login('admin');

$page_title = 'Knowledge Repository';
$active = 'repository_admin';
include '../includes/header.php';

$tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
$tab = array_key_exists(get_text('tab'), $tabs) ? get_text('tab') : 'pending';
$status = $tabs[$tab];
$technician = is_certified_technician($conn);

$counts = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
foreach ($conn->query('SELECT status, COUNT(*) AS n FROM repository_entries GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['n'];
}

// Pending: oldest first, so nothing waits too long. Others: most recent decision first.
$order = $status === 'Pending' ? 'e.submitted_at ASC' : 'e.reviewed_at DESC';
$stmt = $conn->prepare("
    SELECT e.*, p.resolved_at, p.status AS post_status,
           c.username AS contributor, sb.username AS submitter, rv.username AS reviewer
    FROM repository_entries e
    LEFT JOIN posts p ON p.post_id = e.post_id
    LEFT JOIN users c ON c.user_id = e.contributor_id
    LEFT JOIN users sb ON sb.user_id = e.submitted_by
    LEFT JOIN users rv ON rv.user_id = e.reviewed_by
    WHERE e.status = ?
    ORDER BY $order");
$stmt->bind_param('s', $status);
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<main class="content">
    <div class="page-head">
        <h1>Knowledge Repository</h1>
        <p>Resolved discussions submitted for the permanent IT Knowledge Repository. Students only ever see approved, published entries.</p>
    </div>

    <?php show_flash(); ?>

    <div class="tech-banner<?= $technician ? ' is-tech' : '' ?>">
        <?= icon($technician ? 'shield-check' : 'info', 18) ?>
        <p>
            <?php if ($technician): ?>
                You are a <strong>university-certified technician</strong>. You can approve or reject submissions.
            <?php else: ?>
                You can view submissions and unpublish entries. Only <strong>university-certified technicians</strong> can approve or reject them.
            <?php endif; ?>
        </p>
    </div>

    <div class="tabs-row">
        <nav class="tabs" aria-label="Submission status">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="tab<?= $tab === $key ? ' active' : '' ?>" href="repository.php?tab=<?= $key ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>>
                    <?= $label ?> <span class="tab-count"><?= $counts[$label] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <span class="sort-label"><?= $status === 'Pending' ? 'Oldest first' : 'Latest decision first' ?></span>
    </div>

    <?php if (!$entries): ?>
        <div class="feed">
            <div class="empty-state">
                <strong>No <?= strtolower($status) ?> submissions</strong>
                <?= $status === 'Pending' ? 'New submissions from resolved discussions will appear here.' : 'Decisions made by certified technicians appear here.' ?>
            </div>
        </div>
    <?php else: ?>
        <div class="report-list">
            <?php foreach ($entries as $e): ?>
                <?php $listed = $e['resolved_at'] !== null && strtotime($e['resolved_at']) >= time() - RESOLVED_VISIBLE_DAYS * 86400; ?>
                <article class="report-item repo-admin-item">
                    <div class="report-head">
                        <span class="type-badge"><?= e($e['category']) ?></span>
                        <?php if ($e['status'] === 'Approved'): ?>
                            <span class="repo-state <?= $e['is_published'] ? 'published' : 'unpublished' ?>"><?= $e['is_published'] ? 'Published' : 'Unpublished' ?></span>
                        <?php endif; ?>
                        <span class="report-by">
                            submitted by <strong><?= e($e['submitter'] ?? 'deleted user') ?></strong>
                            <span class="dot" aria-hidden="true"></span>
                            <time datetime="<?= e($e['submitted_at']) ?>"><?= e(time_ago($e['submitted_at'])) ?></time>
                        </span>
                    </div>
                    <h3 class="repo-admin-title"><?= e($e['title']) ?></h3>
                    <p class="muted"><?= e(excerpt($e['summary'], 200)) ?></p>
                    <ul class="repo-facts">
                        <li>Asked by <strong><?= e($e['contributor'] ?? 'deleted user') ?></strong></li>
                        <li>Resolved <?= $e['resolved_at'] ? e(date('j M Y', strtotime($e['resolved_at']))) : 'date unknown' ?></li>
                        <li><?= $e['post_id'] === null ? 'Original post removed' : ($listed ? 'Still in the Resolved tab' : 'No longer in the Resolved tab') ?></li>
                        <?php if ($e['reviewer']): ?>
                            <li><?= e($e['status']) ?> by <strong><?= e($e['reviewer']) ?></strong> on <?= e(date('j M Y', strtotime($e['reviewed_at']))) ?></li>
                        <?php endif; ?>
                    </ul>
                    <div class="report-actions">
                        <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>admin/repository_review.php?id=<?= (int) $e['entry_id'] ?>">
                            <?= $e['status'] === 'Pending' && $technician ? 'Evaluate' : 'Open' ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>
