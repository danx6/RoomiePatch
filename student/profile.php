<?php
// User profile: username, bio, post history and resolved help contributions.
// Open with profile.php?id=USER_ID. On your own profile you can edit your bio.
// Staff can view profiles too (read-only).

require_once '../includes/auth.php';
require_login(['student', 'admin']);
require_once '../config/db.php';

$my_id = (int) $_SESSION['user_id'];
$profile_id = (int) get_text('id');
$tab = get_text('tab') === 'help' ? 'help' : 'posts';

// Only student profiles can be viewed (staff/admin accounts have no public profile)
$stmt = $conn->prepare('SELECT user_id, username, bio FROM users WHERE user_id = ? AND role = "student"');
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

$is_me = $profile && (int) $profile['user_id'] === $my_id;

if (!$profile) {
    http_response_code(404);
    $page_title = 'Profile not found';
    $active = '';
    include '../includes/header.php';
    echo '<main class="content"><div class="feed"><div class="empty-state"><strong>Profile not found</strong>'
        . 'This student does not exist. <a href="' . BASE_URL . 'student/browse.php">Back to Browse Posts</a></div></div></main>';
    include '../includes/footer.php';
    exit;
}

// ---------- Counts ----------
$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM posts WHERE user_id = ?');
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$post_count = (int) $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM replies WHERE user_id = ?');
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$reply_count = (int) $stmt->get_result()->fetch_assoc()['total'];

// Resolved help contributions = this user's replies that an author marked as resolved
$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM replies WHERE user_id = ? AND is_resolved = 1');
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$help_count = (int) $stmt->get_result()->fetch_assoc()['total'];

// ---------- List for the current tab ----------
if ($tab === 'help') {
    // Questions where this user's reply was the one that solved it
    $stmt = $conn->prepare(POST_LIST_SQL . '
        WHERE p.post_id IN (SELECT post_id FROM replies WHERE user_id = ? AND is_resolved = 1)
        ORDER BY p.created_at DESC');
} else {
    $stmt = $conn->prepare(POST_LIST_SQL . ' WHERE p.user_id = ? ORDER BY p.created_at DESC');
}
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$posts = $stmt->get_result();

$page_title = $profile['username'];
$active = $is_me ? 'profile' : '';
include '../includes/header.php';

$base = 'profile.php?id=' . $profile_id;
?>

<main class="content">
    <?php show_flash(); ?>

    <section class="profile-card">
        <?= avatar($profile['username'], 'lg') ?>
        <div class="profile-info">
            <h1><?= e($profile['username']) ?><?php if ($is_me): ?> <span class="author-tag">You</span><?php endif; ?></h1>

            <?php if ($profile['bio'] !== null && trim($profile['bio']) !== ''): ?>
                <p class="profile-bio"><?= nl2br(e($profile['bio'])) ?></p>
            <?php else: ?>
                <p class="profile-bio muted"><?= $is_me ? 'You have not written a bio yet.' : 'No bio yet.' ?></p>
            <?php endif; ?>

            <ul class="profile-stats">
                <li><strong><?= $post_count ?></strong> <?= $post_count === 1 ? 'question' : 'questions' ?></li>
                <li><strong><?= $reply_count ?></strong> <?= $reply_count === 1 ? 'reply' : 'replies' ?></li>
                <li class="stat-help"><?= icon('check', 16) ?><strong><?= $help_count ?></strong> resolved <?= $help_count === 1 ? 'help' : 'helps' ?></li>
            </ul>

            <?php if (!$is_me && !is_staff()): ?>
                <div class="profile-actions">
                    <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>student/messages.php?user=<?= $profile_id ?>">
                        <?= icon('message', 16) ?>Message
                    </a>
                    <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>student/meetup_request.php?user=<?= $profile_id ?>">
                        <?= icon('meetup', 16) ?>Request Meetup
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($is_me): ?>
                <details class="bio-edit">
                    <summary class="btn btn-secondary btn-sm">Edit bio</summary>
                    <form method="post" action="<?= BASE_URL ?>actions/update_bio.php">
                        <?= csrf_field() ?>
                        <label class="sr-only" for="bio">Bio</label>
                        <textarea class="input" id="bio" name="bio" rows="3" maxlength="300"
                                  placeholder="e.g. Diploma in IT student. Happy to help with Windows problems."><?= e($profile['bio'] ?? '') ?></textarea>
                        <p class="field-hint">Up to 300 characters. Do not include your phone number or address.</p>
                        <button type="submit" class="btn btn-primary btn-sm">Save bio</button>
                    </form>
                </details>
            <?php endif; ?>
        </div>
    </section>

    <div class="tabs-row">
        <nav class="tabs" aria-label="Profile sections">
            <a class="tab<?= $tab === 'posts' ? ' active' : '' ?>" href="<?= $base ?>" <?= $tab === 'posts' ? 'aria-current="page"' : '' ?>>
                Posts <span class="tab-count"><?= $post_count ?></span>
            </a>
            <a class="tab<?= $tab === 'help' ? ' active' : '' ?>" href="<?= $base ?>&amp;tab=help" <?= $tab === 'help' ? 'aria-current="page"' : '' ?>>
                Resolved help <span class="tab-count"><?= $help_count ?></span>
            </a>
        </nav>
        <span class="sort-label">Newest first</span>
    </div>

    <div class="feed">
        <?php if ($posts->num_rows === 0): ?>
            <div class="empty-state">
                <?php if ($tab === 'help'): ?>
                    <strong>No resolved help yet</strong>
                    <?= $is_me ? 'When your reply solves someone\'s problem, the question shows up here.' : 'Replies by ' . e($profile['username']) . ' that solve a problem show up here.' ?>
                <?php else: ?>
                    <strong>No questions yet</strong>
                    <?= $is_me ? 'Questions you ask will show up here.' : e($profile['username']) . ' has not asked any questions.' ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php while ($post = $posts->fetch_assoc()): ?>
                <?php include '../includes/post_card.php'; ?>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
