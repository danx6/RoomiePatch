<?php
// Staff: one repository submission. Shows the original question, ALL its replies
// (accepted reply highlighted), and the evaluation form for certified technicians.

require_once '../includes/auth.php';
require_login('admin');
require_once '../config/db.php';

$entry_id = (int) get_text('id');
$stmt = $conn->prepare('
    SELECT e.*, c.username AS contributor, s.username AS solver, sb.username AS submitter, rv.username AS reviewer,
           p.title AS post_title, p.description AS post_description, p.status AS post_status,
           p.resolved_at, p.created_at AS post_created, p.device_os, p.tags, cat.category_name
    FROM repository_entries e
    LEFT JOIN users c ON c.user_id = e.contributor_id
    LEFT JOIN users s ON s.user_id = e.solver_id
    LEFT JOIN users sb ON sb.user_id = e.submitted_by
    LEFT JOIN users rv ON rv.user_id = e.reviewed_by
    LEFT JOIN posts p ON p.post_id = e.post_id
    LEFT JOIN categories cat ON cat.category_id = p.category_id
    WHERE e.entry_id = ?');
$stmt->bind_param('i', $entry_id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();

if (!$entry) {
    set_flash('That submission does not exist.', 'error');
    redirect('admin/repository.php');
}

$technician = is_certified_technician($conn);
$replies = $entry['post_id'] !== null ? get_replies($conn, (int) $entry['post_id'], (int) $_SESSION['user_id']) : [];

$page_title = 'Evaluate submission';
$active = 'repository_admin';
include '../includes/header.php';

$tab = strtolower($entry['status']);
?>

<main class="content">
    <a class="back-link" href="<?= BASE_URL ?>admin/repository.php?tab=<?= $tab ?>"><?= icon('chevron-left', 16) ?>Back to submissions</a>

    <div class="page-head page-head-row">
        <div>
            <h1>Repository submission</h1>
            <p>Submitted by <?= e($entry['submitter'] ?? 'deleted user') ?> on <?= e(date('j M Y, g:i A', strtotime($entry['submitted_at']))) ?></p>
        </div>
        <span class="repo-status <?= $tab ?>"><?= e($entry['status']) ?><?= $entry['status'] === 'Approved' && !$entry['is_published'] ? ' (unpublished)' : '' ?></span>
    </div>

    <?php show_flash(); ?>

    <?php if ($entry['status'] !== 'Pending'): ?>
        <div class="repo-decision <?= $tab ?>">
            <strong><?= e($entry['status']) ?></strong> by <?= e($entry['reviewer'] ?? 'unknown') ?> (university-certified technician)
            on <?= e(date('j M Y, g:i A', strtotime($entry['reviewed_at']))) ?>.
            <?php if ($entry['status'] === 'Approved' && $entry['published_at']): ?>
                Published <?= e(date('j M Y', strtotime($entry['published_at']))) ?><?= $entry['is_published'] ? '' : ', currently unpublished' ?>.
            <?php endif; ?>
            <?php if ($entry['rejection_reason']): ?>
                <p><span>Rejection reason:</span> <?= nl2br(e($entry['rejection_reason'])) ?></p>
            <?php endif; ?>
            <?php if ($entry['review_notes']): ?>
                <p><span>Evaluation notes:</span> <?= nl2br(e($entry['review_notes'])) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ===== The original discussion (read-only, all replies) ===== -->
    <h2 class="replies-heading">Original discussion</h2>
    <?php if ($entry['post_id'] === null): ?>
        <div class="feed"><div class="empty-state"><strong>The original post was removed</strong>The reviewed entry text below is kept.</div></div>
    <?php else: ?>
        <article class="thread<?= $entry['post_status'] === 'Resolved' ? ' is-resolved' : '' ?>">
            <span class="status-badge <?= $entry['post_status'] === 'Resolved' ? 'resolved' : 'open' ?>"><?= e($entry['post_status']) ?></span>
            <h3 class="thread-title"><?= e($entry['post_title']) ?></h3>
            <div class="thread-meta">
                <span>Asked by <strong><?= e($entry['contributor'] ?? 'deleted user') ?></strong></span>
                <span class="dot" aria-hidden="true"></span>
                <span><?= e(date('j M Y', strtotime($entry['post_created']))) ?></span>
                <span class="dot" aria-hidden="true"></span>
                <span><?= e($entry['category_name']) ?> / <?= e($entry['device_os']) ?></span>
                <span class="dot" aria-hidden="true"></span>
                <span>Resolved <?= $entry['resolved_at'] ? e(date('j M Y, g:i A', strtotime($entry['resolved_at']))) : '-' ?></span>
            </div>
            <div class="thread-body"><?= nl2br(e($entry['post_description'])) ?></div>
            <a href="<?= BASE_URL ?>student/post.php?id=<?= (int) $entry['post_id'] ?>" target="_blank" rel="noopener">Open the thread page</a>
        </article>

        <div class="reply-list review-replies">
            <?php foreach ($replies as $r): ?>
                <?php $accepted = (int) $r['reply_id'] === (int) $entry['accepted_reply_id']; ?>
                <article class="reply<?= $accepted ? ' resolved' : '' ?>">
                    <?php if ($accepted): ?>
                        <div class="resolved-label"><?= icon('check', 16) ?>Accepted by the original poster (reply #<?= (int) $r['reply_id'] ?>)</div>
                    <?php endif; ?>
                    <div class="reply-meta">
                        <strong><?= e($r['username']) ?></strong>
                        <span class="dot" aria-hidden="true"></span>
                        <span><?= e(date('j M Y', strtotime($r['created_at']))) ?></span>
                        <span class="dot" aria-hidden="true"></span>
                        <span>score <?= (int) $r['score'] ?></span>
                    </div>
                    <div class="reply-body"><?= nl2br(e($r['content'])) ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ===== Evaluation ===== -->
    <h2 class="replies-heading">Repository entry</h2>
    <?php if ($entry['status'] === 'Pending' && $technician): ?>
        <form class="form-card" method="post" action="<?= BASE_URL ?>actions/repository_review.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="entry_id" value="<?= $entry_id ?>">
            <p class="field-hint">Check the solution for technical accuracy, relevance and usefulness. Edit the text into a clear reference entry before approving.</p>

            <div class="form-row">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input class="input" id="title" name="title" maxlength="150" value="<?= e($entry['title']) ?>">
                </div>
                <div class="form-group">
                    <label for="category">Repository category</label>
                    <select class="input" id="category" name="category">
                        <?php foreach (REPO_CATEGORIES as $cat): ?>
                            <option <?= $cat === $entry['category'] ? 'selected' : '' ?>><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="summary">Short description</label>
                <textarea class="input" id="summary" name="summary" rows="2" maxlength="300"><?= e($entry['summary']) ?></textarea>
            </div>
            <div class="form-group">
                <label for="problem">Problem description</label>
                <textarea class="input" id="problem" name="problem" rows="4"><?= e($entry['problem']) ?></textarea>
            </div>
            <div class="form-group">
                <label for="solution">Verified solution / troubleshooting steps</label>
                <textarea class="input" id="solution" name="solution" rows="6"><?= e($entry['solution']) ?></textarea>
            </div>
            <div class="form-group">
                <label for="keywords">Keywords <span class="optional">comma separated</span></label>
                <input class="input" id="keywords" name="keywords" maxlength="255" value="<?= e($entry['keywords']) ?>">
            </div>
            <div class="form-group">
                <label for="review_notes">Evaluation notes <span class="optional">optional, staff only</span></label>
                <textarea class="input" id="review_notes" name="review_notes" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label for="rejection_reason">Rejection reason <span class="optional">required to reject</span></label>
                <textarea class="input" id="rejection_reason" name="rejection_reason" rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" name="action" value="approve" class="btn btn-resolve"
                        onclick="return confirm('Approve and publish this entry permanently in the IT Knowledge Repository?');"><?= icon('check', 16) ?>Approve and publish</button>
                <button type="submit" name="action" value="reject" class="btn btn-danger"
                        onclick="return confirm('Reject this submission? It will never appear in the repository.');">Reject</button>
            </div>
        </form>
    <?php else: ?>
        <article class="repo-preview">
            <span class="type-badge"><?= e($entry['category']) ?></span>
            <h3><?= e($entry['title']) ?></h3>
            <p class="muted"><?= nl2br(e($entry['summary'])) ?></p>
            <h4>Problem</h4>
            <p><?= nl2br(e($entry['problem'])) ?></p>
            <h4>Verified solution</h4>
            <p><?= nl2br(e($entry['solution'])) ?></p>
        </article>
        <?php if ($entry['status'] === 'Pending'): ?>
            <p class="muted">Waiting for a university-certified technician to evaluate it.</p>
        <?php endif; ?>
        <?php if ($entry['status'] === 'Approved'): ?>
            <div class="form-actions">
                <?php if ($entry['is_published']): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>student/repository_entry.php?id=<?= $entry_id ?>" target="_blank" rel="noopener">View as students see it</a>
                    <form method="post" action="<?= BASE_URL ?>actions/repository_review.php"
                          onsubmit="return confirm('Unpublish this entry? Students will no longer see it.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="entry_id" value="<?= $entry_id ?>">
                        <button type="submit" name="action" value="unpublish" class="btn btn-danger-ghost btn-sm"><?= icon('trash', 15) ?>Unpublish</button>
                    </form>
                <?php elseif ($technician): ?>
                    <form method="post" action="<?= BASE_URL ?>actions/repository_review.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="entry_id" value="<?= $entry_id ?>">
                        <button type="submit" name="action" value="republish" class="btn btn-primary btn-sm">Publish again</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>
