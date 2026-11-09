<?php
// Staff: monitor all meetup requests between students (read-only).
// Tabs filter by status. Newest first. Staff cannot change or answer requests here.

require_once '../includes/auth.php';
require_login('admin');

$page_title = 'Meetups';
$active = 'meetups';
include '../includes/header.php';

$statuses = ['Pending', 'Accepted', 'Rejected'];
$status = ucfirst(strtolower(get_text('status')));
if (!in_array($status, $statuses, true)) {
    $status = '';   // "All" tab
}

// Counts per status for the tab labels
$counts = ['Pending' => 0, 'Accepted' => 0, 'Rejected' => 0];
foreach ($conn->query('SELECT status, COUNT(*) AS total FROM meetup_requests GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['total'];
}
$total = array_sum($counts);

$sql = 'SELECT m.*, req.username AS requester_name, rec.username AS recipient_name
        FROM meetup_requests m
        JOIN users req ON req.user_id = m.requester_id
        JOIN users rec ON rec.user_id = m.recipient_id';
if ($status !== '') {
    $stmt = $conn->prepare($sql . ' WHERE m.status = ? ORDER BY m.created_at DESC');
    $stmt->bind_param('s', $status);
} else {
    $stmt = $conn->prepare($sql . ' ORDER BY m.created_at DESC');
}
$stmt->execute();
$meetups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$profile_url = BASE_URL . 'student/profile.php?id=';
?>

<main class="content">
    <div class="page-head">
        <h1>Meetups</h1>
        <p>All meetup requests between students, for safety monitoring. This page is view only.</p>
    </div>

    <div class="tabs-row">
        <nav class="tabs" aria-label="Meetup status">
            <a class="tab<?= $status === '' ? ' active' : '' ?>" href="meetups.php" <?= $status === '' ? 'aria-current="page"' : '' ?>>
                All <span class="tab-count"><?= $total ?></span>
            </a>
            <?php foreach ($statuses as $s): ?>
                <a class="tab<?= $status === $s ? ' active' : '' ?>" href="meetups.php?status=<?= strtolower($s) ?>"
                   <?= $status === $s ? 'aria-current="page"' : '' ?>>
                    <?= $s ?> <span class="tab-count"><?= $counts[$s] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <span class="sort-label">Newest first</span>
    </div>

    <?php if (!$meetups): ?>
        <div class="feed">
            <div class="empty-state">
                <strong>No meetup requests<?= $status !== '' ? ' with this status' : '' ?></strong>
                Meetup requests sent between students will appear here.
            </div>
        </div>
    <?php else: ?>
        <div class="report-list">
            <?php foreach ($meetups as $m): ?>
                <?php $status_class = strtolower($m['status']); ?>
                <article class="meetup-card <?= $status_class ?>">
                    <div class="meetup-head">
                        <a class="author-chip" href="<?= $profile_url . (int) $m['requester_id'] ?>">
                            <?= avatar($m['requester_name'], 'sm') ?><?= e($m['requester_name']) ?>
                        </a>
                        <span class="muted">requested a meetup with</span>
                        <a class="author-chip" href="<?= $profile_url . (int) $m['recipient_id'] ?>">
                            <?= avatar($m['recipient_name'], 'sm') ?><?= e($m['recipient_name']) ?>
                        </a>
                        <span class="dot" aria-hidden="true"></span>
                        <time class="muted" datetime="<?= e($m['created_at']) ?>"><?= e(time_ago($m['created_at'])) ?></time>
                        <span class="meetup-status <?= $status_class ?>"><?= e($m['status']) ?></span>
                    </div>

                    <dl class="meetup-details">
                        <div><dt>When</dt><dd><?= e(meetup_time($m['proposed_datetime'])) ?></dd></div>
                        <div><dt>Where</dt><dd><?= e($m['location']) ?></dd></div>
                    </dl>

                    <?php if ($m['message'] !== null && $m['message'] !== ''): ?>
                        <p class="meetup-message"><?= nl2br(e($m['message'])) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>
