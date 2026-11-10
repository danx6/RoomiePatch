<?php
// Post page: one troubleshooting question, its images, its replies,
// the reply form, and (for the author) the Mark as resolved buttons.
// The reply form submits to this same page.
// Staff can view this page (read-only): no reply form, no report or resolve buttons.

require_once '../includes/auth.php';
require_login(['student', 'admin']);
require_once '../config/db.php';

$user_id = (int) $_SESSION['user_id'];
$post_id = (int) get_text('id');

// ---------- Load the post ----------
$stmt = $conn->prepare('
    SELECT p.*, u.username, c.category_name
    FROM posts p
    JOIN users u ON u.user_id = p.user_id
    JOIN categories c ON c.category_id = p.category_id
    WHERE p.post_id = ?');
$stmt->bind_param('i', $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    http_response_code(404);
    $page_title = 'Post not found';
    $active = 'browse';
    include '../includes/header.php';
    echo '<main class="content"><div class="feed"><div class="empty-state"><strong>Post not found</strong>'
        . 'It may have been removed. <a href="' . BASE_URL . 'student/browse.php">Back to Browse Posts</a></div></div></main>';
    include '../includes/footer.php';
    exit;
}

$is_author = (int) $post['user_id'] === $user_id;
$is_resolved = $post['status'] === 'Resolved';

// ---------- Handle a new reply ----------
$reply_content = '';
$reply_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    // Staff can read posts but not reply (checked here too, not only by hiding the form)
    if (is_staff()) {
        set_flash('Staff accounts can view posts but cannot reply.', 'error');
        redirect('student/post.php?id=' . $post_id);
    }

    // A resolved discussion is read-only. Checked on the server, so sending the form
    // directly (without the page) is refused too.
    if ($is_resolved) {
        set_flash('Submissions are closed as this issue has been resolved.', 'error');
        redirect('student/post.php?id=' . $post_id);
    }

    $reply_content = post_text('content');

    if (mb_strlen($reply_content) < 2) {
        $reply_error = 'Write a reply before posting.';
    } elseif (mb_strlen($reply_content) > 5000) {
        $reply_error = 'Reply must be 5000 characters or fewer.';
    } else {
        $stmt = $conn->prepare('INSERT INTO replies (post_id, user_id, content) VALUES (?, ?, ?)');
        $stmt->bind_param('iis', $post_id, $user_id, $reply_content);
        $stmt->execute();
        $reply_id = $conn->insert_id;

        // Tell the post author, unless they replied to their own post
        if (!$is_author) {
            add_notification(
                $conn,
                (int) $post['user_id'],
                'reply',
                $_SESSION['username'] . ' replied to your question: ' . $post['title'],
                'student/post.php?id=' . $post_id . '#reply-' . $reply_id
            );
        }

        set_flash('Your reply was posted.');
        redirect('student/post.php?id=' . $post_id . '#reply-' . $reply_id);
    }
}

// The author is looking at their post, so "X replied to your question" notifications
// for this post count as seen
if ($is_author) {
    $link_like = 'student/post.php?id=' . $post_id . '#%';
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = "reply" AND link LIKE ?');
    $stmt->bind_param('is', $user_id, $link_like);
    $stmt->execute();
}

// ---------- Load images and replies ----------
$stmt = $conn->prepare('SELECT image_path FROM post_images WHERE post_id = ? ORDER BY image_id');
$stmt->bind_param('i', $post_id);
$stmt->execute();
$images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// The resolved reply goes first, then the rest oldest to newest, like a conversation.
// Each reply comes with its vote score and the viewer's own vote.
$replies = get_replies($conn, $post_id, $user_id);
$reply_count = count($replies);

// Reputation and "recently helpful" labels for the post author and everyone who replied
$rep_info = get_reputation_info($conn, array_merge([(int) $post['user_id']], array_column($replies, 'user_id')));

$page_title = $post['title'];
$active = 'browse';
include '../includes/header.php';

$profile_url = BASE_URL . 'student/profile.php?id=';
?>

<main class="content">
    <a class="back-link" href="<?= BASE_URL ?>student/browse.php<?= $is_resolved ? '?status=resolved' : '' ?>">
        <?= icon('chevron-left', 16) ?>Back to questions
    </a>

    <?php show_flash(); ?>

    <!-- ===== The question ===== -->
    <article class="thread<?= $is_resolved ? ' is-resolved' : '' ?>">
        <header class="thread-head">
            <?php if ($is_resolved): ?>
                <span class="status-badge resolved"><?= icon('check', 14) ?>Resolved</span>
            <?php else: ?>
                <span class="status-badge open">Open</span>
            <?php endif; ?>
            <h1 class="thread-title"><?= e($post['title']) ?></h1>

            <div class="thread-meta">
                <a class="author-chip" href="<?= $profile_url . (int) $post['user_id'] ?>">
                    <?= avatar($post['username'], 'sm') ?><?= e($post['username']) ?>
                </a>
                <?= reputation_label((int) $post['user_id'], $rep_info) ?>
                <span class="dot" aria-hidden="true"></span>
                <span>asked <time datetime="<?= e($post['created_at']) ?>"><?= e(time_ago($post['created_at'])) ?></time></span>
                <span class="dot" aria-hidden="true"></span>
                <a href="<?= BASE_URL ?>student/browse.php?category=<?= (int) $post['category_id'] ?>"><?= e($post['category_name']) ?></a>
            </div>
        </header>

        <div class="thread-body"><?= nl2br(e($post['description'])) ?></div>

        <?php if ($images): ?>
            <div class="thread-images">
                <?php foreach ($images as $image): ?>
                    <a href="<?= BASE_URL ?>uploads/posts/<?= e($image['image_path']) ?>" data-lightbox
                       title="Enlarge image">
                        <img src="<?= BASE_URL ?>uploads/posts/<?= e($image['image_path']) ?>" alt="Image attached to the question" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="post-footer">
            <a class="chip chip-device" href="<?= BASE_URL ?>student/browse.php?device=<?= urlencode($post['device_os']) ?>"><?= e($post['device_os']) ?></a>
            <?php foreach (tag_list($post['tags']) as $post_tag): ?>
                <a class="chip" href="<?= BASE_URL ?>student/browse.php?tag=<?= urlencode($post_tag) ?>"><?= e($post_tag) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="thread-actions">
            <?php if (is_staff()): ?>
                <form method="post" action="<?= BASE_URL ?>actions/staff_remove.php"
                      onsubmit="return confirm('Remove this post as staff? Its replies and images will also be removed. This cannot be undone.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="type" value="post">
                    <input type="hidden" name="id" value="<?= $post_id ?>">
                    <button type="submit" class="btn btn-danger btn-sm"><?= icon('trash', 16) ?>Remove post</button>
                </form>
            <?php elseif ($is_author): ?>
                <form method="post" action="<?= BASE_URL ?>actions/delete_post.php"
                      onsubmit="return confirm('Delete this post? Its replies and images will also be deleted. This cannot be undone.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                    <button type="submit" class="btn btn-danger-ghost btn-sm"><?= icon('trash', 16) ?>Delete post</button>
                </form>
            <?php else: ?>
                <a class="btn btn-danger-ghost btn-sm" href="<?= BASE_URL ?>student/report.php?type=post&amp;id=<?= $post_id ?>">
                    <?= icon('flag', 16) ?>Report
                </a>
            <?php endif; ?>
        </div>
    </article>

    <!-- ===== Replies ===== -->
    <?php
    // live.js uses these to ask api/replies.php for replies newer than the last one shown
    $last_reply_id = 0;
    foreach ($replies as $r) {
        $last_reply_id = max($last_reply_id, (int) $r['reply_id']);
    }
    ?>
    <section data-thread data-post-id="<?= $post_id ?>" data-last-reply-id="<?= $last_reply_id ?>" data-status="<?= e($post['status']) ?>">
        <h2 class="replies-heading">
            <span data-reply-count><?= $reply_count ?></span>
            <span data-reply-word><?= $reply_count === 1 ? 'Reply' : 'Replies' ?></span>
        </h2>

        <div class="live-notice" data-thread-notice hidden></div>

        <div class="feed" data-replies-empty <?= $reply_count === 0 ? '' : 'hidden' ?>>
            <div class="empty-state">
                <strong>No replies yet</strong>
                <?php if (is_staff()): ?>
                    No student has replied to this question yet.
                <?php else: ?>
                    <?= $is_author ? 'Other students can reply to help you.' : 'Know how to fix this? Be the first to reply.' ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="reply-list" id="reply-list" <?= $reply_count === 0 ? 'hidden' : '' ?>>
            <?php foreach ($replies as $reply): ?>
                <?php include '../includes/reply_item.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===== Reply form (students only, open questions only) ===== -->
    <?php if ($is_resolved): ?>
        <div class="closed-note" role="status">
            <?= icon('check', 18) ?>
            <p>Submissions are closed as this issue has been resolved.</p>
        </div>
    <?php elseif (is_staff()): ?>
        <div class="staff-note">
            <?= icon('user', 18) ?>
            <p>
                You are viewing as university staff. Staff accounts cannot reply, but can remove
                a post or reply that breaks the rules. Student reports are on the
                <a href="<?= BASE_URL ?>admin/dashboard.php">Reports</a> page.
            </p>
        </div>
    <?php else: ?>
    <form class="form-card reply-form" method="post" action="post.php?id=<?= $post_id ?>#reply-form" id="reply-form" novalidate>
        <?= csrf_field() ?>
        <div class="form-group<?= $reply_error !== '' ? ' has-error' : '' ?>">
            <label for="content"><?= $is_author ? 'Add more details' : 'Your reply' ?></label>
            <?php if (!$is_author): ?>
                <p class="field-hint">Explain the steps clearly. If you are not sure, say so.</p>
            <?php endif; ?>
            <textarea class="input" id="content" name="content" rows="5" maxlength="5000" required><?= e($reply_content) ?></textarea>
            <?php if ($reply_error !== ''): ?>
                <p class="field-error"><?= e($reply_error) ?></p>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= icon('reply', 16) ?>Post reply</button>
        </div>
    </form>
    <!-- Shown by live.js if the author resolves the question while this page is open -->
    <div class="closed-note" data-closed-note role="status" hidden>
        <?= icon('check', 18) ?>
        <p>Submissions are closed as this issue has been resolved.</p>
    </div>
    <?php endif; ?>
</main>

<aside class="rail">
    <section class="rail-box">
        <h2>About this question</h2>
        <dl class="info-list">
            <div><dt>Status</dt><dd><?= $is_resolved ? 'Resolved' : 'Open' ?></dd></div>
            <div><dt>Asked by</dt><dd><a href="<?= $profile_url . (int) $post['user_id'] ?>"><?= e($post['username']) ?></a></dd></div>
            <div><dt>Category</dt><dd><?= e($post['category_name']) ?></dd></div>
            <div><dt>Device / OS</dt><dd><?= e($post['device_os']) ?></dd></div>
            <div><dt>Replies</dt><dd data-reply-count><?= $reply_count ?></dd></div>
        </dl>
    </section>

    <?php if ($is_resolved): ?>
        <?php
        // IT Knowledge Repository: who may submit this resolved discussion for evaluation.
        // The original poster, the student whose reply was accepted, or any staff member.
        $repo_entry = repository_entry_for_post($conn, $post_id);
        $accepted_author = (int) one_value($conn, 'SELECT user_id FROM replies WHERE reply_id = ?', (int) $post['accepted_reply_id']);
        $can_submit = !$repo_entry && $post['accepted_reply_id'] !== null
            && ($is_author || $accepted_author === $user_id || is_staff());
        ?>
        <?php if ($repo_entry && $repo_entry['status'] === 'Approved' && (int) $repo_entry['is_published'] === 1): ?>
            <section class="rail-box rail-box-repo">
                <h2><?= icon('book', 16) ?>In the IT Knowledge Repository</h2>
                <p>A certified technician reviewed this solution and added it to the permanent repository.</p>
                <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>student/repository_entry.php?id=<?= (int) $repo_entry['entry_id'] ?>">View repository entry</a>
            </section>
        <?php elseif ($can_submit): ?>
            <section class="rail-box rail-box-repo">
                <h2><?= icon('book', 16) ?>Keep this solution</h2>
                <p>Resolved posts are listed for <?= RESOLVED_VISIBLE_DAYS ?> days. Submit it to the IT Knowledge Repository so a university-certified technician can evaluate it for permanent publication.</p>
                <form method="post" action="<?= BASE_URL ?>actions/repository_submit.php"
                      onsubmit="return confirm('Submit this resolved discussion for technician evaluation?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('book', 16) ?>Submit for evaluation</button>
                </form>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($is_author && !$is_resolved): ?>
        <section class="rail-box rail-box-highlight">
            <h2>Did a reply fix it?</h2>
            <p>Click <strong>Mark as resolved</strong> on the reply that solved your problem. It moves to the top so other students can find the fix.</p>
        </section>
    <?php endif; ?>
</aside>

<?php include '../includes/footer.php'; ?>
