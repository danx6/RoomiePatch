<?php
// One question in a feed. Used by Dashboard, Browse Posts, My Posts and Profile.
// Expects $post: one row from POST_LIST_SQL (see functions.php).
// Note: this file runs in the page's own scope, so its variable names must not
// clash with the page's (e.g. browse.php uses $tag for the tag filter).

$is_resolved = $post['status'] === 'Resolved';
$post_link = BASE_URL . 'student/post.php?id=' . (int) $post['post_id'];
$profile_link = BASE_URL . 'student/profile.php?id=' . (int) $post['user_id'];
$replies_label = $post['reply_count'] == 1 ? 'reply' : 'replies';
?>
<article class="post-card<?= $is_resolved ? ' is-resolved' : '' ?>">
    <div class="post-stat<?= $is_resolved ? ' resolved' : '' ?>"
         title="<?= $is_resolved ? 'Resolved' : 'Open' ?>, <?= (int) $post['reply_count'] ?> <?= $replies_label ?>">
        <?php if ($is_resolved): ?><?= icon('check', 16) ?><?php endif; ?>
        <span class="post-stat-count"><?= (int) $post['reply_count'] ?></span>
        <span class="post-stat-label"><?= $replies_label ?></span>
    </div>

    <div class="post-body">
        <div class="post-meta">
            <span class="post-category"><?= e($post['category_name']) ?></span>
            <span class="dot" aria-hidden="true"></span>
            <a class="post-author" href="<?= $profile_link ?>"><?= e($post['username']) ?></a>
            <span class="dot" aria-hidden="true"></span>
            <time datetime="<?= e($post['created_at']) ?>"><?= e(time_ago($post['created_at'])) ?></time>
            <?php if ($is_resolved): ?>
                <span class="status-badge resolved"><?= icon('check', 14) ?>Resolved</span>
            <?php else: ?>
                <span class="status-badge open">Open</span>
            <?php endif; ?>
        </div>

        <h3 class="post-title"><a href="<?= $post_link ?>"><?= e($post['title']) ?></a></h3>
        <p class="post-excerpt"><?= e(excerpt($post['description'])) ?></p>

        <div class="post-footer">
            <span class="chip chip-device"><?= e($post['device_os']) ?></span>
            <?php // $card_tag (not $tag) so we do not overwrite the page's own $tag filter ?>
            <?php foreach (tag_list($post['tags']) as $card_tag): ?>
                <a class="chip" href="<?= BASE_URL ?>student/browse.php?tag=<?= urlencode($card_tag) ?>"><?= e($card_tag) ?></a>
            <?php endforeach; ?>
            <?php if ($post['image_count'] > 0): ?>
                <span class="post-images-count" title="Has <?= (int) $post['image_count'] ?> image(s) attached">
                    <?= icon('image', 15) ?><?= (int) $post['image_count'] ?>
                </span>
            <?php endif; ?>
            <a class="post-replies-inline" href="<?= $post_link ?>">
                <?= icon('reply', 16) ?><?= (int) $post['reply_count'] ?> <?= $replies_label ?>
            </a>
        </div>
    </div>
</article>
