<?php
// Report a post or a reply to university staff.
//   report.php?type=post&id=3   or   report.php?type=reply&id=7
// Students cannot report their own content, and cannot report the same thing twice
// while their earlier report is still waiting for review.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

$user_id = (int) $_SESSION['user_id'];
$type = get_text('type') === 'reply' ? 'reply' : 'post';
$id = (int) get_text('id');

// ---------- Load what is being reported ----------
if ($type === 'post') {
    $stmt = $conn->prepare('SELECT p.post_id, p.user_id, p.title, p.description AS content, u.username
                            FROM posts p JOIN users u ON u.user_id = p.user_id WHERE p.post_id = ?');
} else {
    $stmt = $conn->prepare('SELECT r.post_id, r.user_id, p.title, r.content, u.username
                            FROM replies r JOIN posts p ON p.post_id = r.post_id
                            JOIN users u ON u.user_id = r.user_id WHERE r.reply_id = ?');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();

if (!$target) {
    set_flash('That ' . $type . ' no longer exists.', 'error');
    redirect('student/browse.php');
}

$post_page = 'student/post.php?id=' . (int) $target['post_id'] . ($type === 'reply' ? '#reply-' . $id : '');

if ((int) $target['user_id'] === $user_id) {
    set_flash('You cannot report your own ' . $type . '.', 'error');
    redirect($post_page);
}

// Already reported by this student and still waiting?
$column = $type === 'post' ? 'reported_post_id' : 'reported_reply_id';
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM reports WHERE reporter_id = ? AND $column = ? AND status = 'Pending'");
$stmt->bind_param('ii', $user_id, $id);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()['total'] > 0) {
    set_flash('You already reported this ' . $type . '. Staff will review it soon.', 'error');
    redirect($post_page);
}

// ---------- Handle the form ----------
$reason = '';
$description = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $reason = post_text('reason');
    $description = post_text('description');

    if (!in_array($reason, REPORT_REASONS, true)) {
        $errors['reason'] = 'Choose a reason.';
    }
    if (mb_strlen($description) > 1000) {
        $errors['description'] = 'Keep the description to 1000 characters or fewer.';
    }

    if (!$errors) {
        $post_id  = $type === 'post' ? $id : null;
        $reply_id = $type === 'reply' ? $id : null;
        $description_to_save = $description === '' ? null : $description;

        $stmt = $conn->prepare('INSERT INTO reports (reporter_id, reported_post_id, reported_reply_id, reason, description) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('iiiss', $user_id, $post_id, $reply_id, $reason, $description_to_save);
        $stmt->execute();

        set_flash('Thanks. Your report was sent to university staff for review.');
        redirect($post_page);
    }
}

$page_title = 'Report ' . $type;
$active = 'browse';
include '../includes/header.php';
?>

<main class="content">
    <a class="back-link" href="<?= BASE_URL . $post_page ?>"><?= icon('chevron-left', 16) ?>Back to the post</a>

    <div class="page-head">
        <h1>Report this <?= $type ?></h1>
        <p>Reports go to university staff. The author is not told who reported it.</p>
    </div>

    <div class="reported-preview">
        <div class="reported-preview-label"><?= $type === 'post' ? 'Post' : 'Reply' ?> by <?= e($target['username']) ?></div>
        <?php if ($type === 'post'): ?>
            <strong><?= e($target['title']) ?></strong>
        <?php else: ?>
            <span class="muted">On: <?= e($target['title']) ?></span>
        <?php endif; ?>
        <p><?= e(excerpt($target['content'], 280)) ?></p>
    </div>

    <form class="form-card" method="post" action="report.php?type=<?= $type ?>&amp;id=<?= $id ?>" novalidate>
        <?= csrf_field() ?>

        <div class="form-group<?= isset($errors['reason']) ? ' has-error' : '' ?>">
            <label for="reason">Reason</label>
            <select class="input" id="reason" name="reason" required>
                <option value="">Choose a reason</option>
                <?php foreach (REPORT_REASONS as $option): ?>
                    <option value="<?= e($option) ?>" <?= $option === $reason ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['reason'])): ?><p class="field-error"><?= e($errors['reason']) ?></p><?php endif; ?>
        </div>

        <div class="form-group<?= isset($errors['description']) ? ' has-error' : '' ?>">
            <label for="description">Description <span class="optional">optional</span></label>
            <p class="field-hint">Anything that helps staff understand the problem.</p>
            <textarea class="input" id="description" name="description" rows="4" maxlength="1000"><?= e($description) ?></textarea>
            <?php if (isset($errors['description'])): ?><p class="field-error"><?= e($errors['description']) ?></p><?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-danger"><?= icon('flag', 16) ?>Submit report</button>
            <a class="btn btn-secondary" href="<?= BASE_URL . $post_page ?>">Cancel</a>
        </div>
    </form>
</main>

<?php include '../includes/footer.php'; ?>
