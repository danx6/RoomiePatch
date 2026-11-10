<?php
// Admin dashboard: the report queue.
// Pending tab = reports waiting for a decision. Handled tab = removed or dismissed.

require_once '../includes/auth.php';
require_login('admin');

$page_title = 'Reports';
$active = 'reports';
include '../includes/header.php';

$tab = get_text('tab') === 'handled' ? 'handled' : 'pending';

// Counts for the tab labels
$counts = $conn->query("
    SELECT SUM(status = 'Pending') AS pending, SUM(status <> 'Pending') AS handled FROM reports
")->fetch_assoc();
$pending_count = (int) $counts['pending'];
$handled_count = (int) $counts['handled'];

// Pending: oldest first, so nothing waits too long. Handled: newest first.
if ($tab === 'pending') {
    $reports = $conn->query(REPORT_LIST_SQL . " WHERE r.status = 'Pending' ORDER BY r.created_at ASC");
} else {
    $reports = $conn->query(REPORT_LIST_SQL . " WHERE r.status <> 'Pending' ORDER BY r.created_at DESC");
}
?>

<main class="content">
    <div class="page-head">
        <h1>Reports</h1>
        <p>Posts and replies reported by students. Remove content that breaks the rules, or dismiss the report.</p>
    </div>

    <?php show_flash(); ?>

    <?php $repo_waiting = (int) $conn->query("SELECT COUNT(*) FROM repository_entries WHERE status = 'Pending'")->fetch_row()[0]; ?>
    <a class="repo-dash-link" href="<?= BASE_URL ?>admin/repository.php">
        <?= icon('book', 20) ?>
        <span><strong>Knowledge Repository</strong> <?= $repo_waiting ?> <?= $repo_waiting === 1 ? 'submission is' : 'submissions are' ?> waiting for technician evaluation</span>
        <span class="repo-dash-go">Open</span>
    </a>

    <div class="tabs-row">
        <nav class="tabs" aria-label="Report status">
            <a class="tab<?= $tab === 'pending' ? ' active' : '' ?>" href="dashboard.php" <?= $tab === 'pending' ? 'aria-current="page"' : '' ?>>
                Pending <span class="tab-count"><?= $pending_count ?></span>
            </a>
            <a class="tab<?= $tab === 'handled' ? ' active' : '' ?>" href="dashboard.php?tab=handled" <?= $tab === 'handled' ? 'aria-current="page"' : '' ?>>
                Handled <span class="tab-count"><?= $handled_count ?></span>
            </a>
        </nav>
        <span class="sort-label"><?= $tab === 'pending' ? 'Oldest first' : 'Newest first' ?></span>
    </div>

    <div class="report-list">
        <?php if ($reports->num_rows === 0): ?>
            <div class="feed">
                <div class="empty-state">
                    <?php if ($tab === 'pending'): ?>
                        <strong>No reports waiting for review</strong>
                        New reports from students will appear here.
                    <?php else: ?>
                        <strong>No handled reports yet</strong>
                        Reports you remove or dismiss will appear here.
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php while ($report = $reports->fetch_assoc()): ?>
                <?php include '../includes/report_item.php'; ?>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
