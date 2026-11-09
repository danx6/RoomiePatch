<?php
// Meetup Requests: requests received from other students (Accept / Reject)
// and requests this student has sent. Newest first.
// While the page is open, live.js checks api/meetups.php for new or changed requests.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

$my_id = (int) $_SESSION['user_id'];
$tab = get_text('tab') === 'sent' ? 'sent' : 'received';

// Visiting this page counts as seeing meetup notifications
mark_meetup_notifications_read($conn, $my_id);

$counts = get_meetup_counts($conn, $my_id);
$meetups = get_meetups($conn, $my_id, $tab);
$since = $conn->query('SELECT NOW() AS t')->fetch_assoc()['t'];

$page_title = 'Meetup Requests';
$active = 'meetups';
include '../includes/header.php';
?>

<main class="content" data-meetups data-tab="<?= $tab ?>" data-since="<?= e($since) ?>">
    <div class="page-head">
        <h1>Meetup Requests</h1>
        <p>Meet another student in person for help. Once a request is accepted, arrange the details by message.</p>
    </div>

    <?php show_flash(); ?>

    <div class="tabs-row">
        <nav class="tabs" aria-label="Meetup requests">
            <a class="tab<?= $tab === 'received' ? ' active' : '' ?>" href="meetups.php" <?= $tab === 'received' ? 'aria-current="page"' : '' ?>>
                Received <span class="tab-count" data-count="received_total"><?= $counts['received_total'] ?></span>
            </a>
            <a class="tab<?= $tab === 'sent' ? ' active' : '' ?>" href="meetups.php?tab=sent" <?= $tab === 'sent' ? 'aria-current="page"' : '' ?>>
                Sent <span class="tab-count" data-count="sent_total"><?= $counts['sent_total'] ?></span>
            </a>
        </nav>
        <?php if ($tab === 'received'): ?>
            <span class="sort-label" data-pending-label <?= $counts['received_pending'] > 0 ? '' : 'hidden' ?>>
                <span data-count="received_pending"><?= $counts['received_pending'] ?></span> waiting for your reply
            </span>
        <?php endif; ?>
    </div>

    <div class="feed" data-meetups-empty <?= $meetups ? 'hidden' : '' ?>>
        <div class="empty-state">
            <?php if ($tab === 'received'): ?>
                <strong>No meetup requests yet</strong>
                When another student asks to meet you, it will appear here.
            <?php else: ?>
                <strong>You have not sent any meetup requests</strong>
                Open a student's profile and click Request Meetup.
            <?php endif; ?>
        </div>
    </div>

    <div class="report-list" id="meetup-list">
        <?php foreach ($meetups as $m): ?>
            <?php include '../includes/meetup_card.php'; ?>
        <?php endforeach; ?>
    </div>
</main>

<?php include '../includes/safety_modal.php'; ?>
<?php include '../includes/footer.php'; ?>
