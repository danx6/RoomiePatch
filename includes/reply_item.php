<?php
// One reply on a post page. Used by student/post.php and api/replies.php (live updates).
// Expects: $reply (reply_id, user_id, content, is_resolved, created_at, username),
//          $post (the post row), $user_id (viewer), $is_author, $is_resolved.
?>
<article class="reply<?= $reply['is_resolved'] ? ' resolved' : '' ?>" id="reply-<?= (int) $reply['reply_id'] ?>" data-reply-id="<?= (int) $reply['reply_id'] ?>">
    <?php if ($reply['is_resolved']): ?>
        <div class="resolved-label"><?= icon('check', 16) ?>Marked as resolved by author</div>
    <?php endif; ?>

    <div class="reply-meta">
        <a class="author-chip" href="<?= BASE_URL ?>student/profile.php?id=<?= (int) $reply['user_id'] ?>">
            <?= avatar($reply['username'], 'sm') ?><?= e($reply['username']) ?>
        </a>
        <?php if ((int) $reply['user_id'] === (int) $post['user_id']): ?>
            <span class="author-tag">Author</span>
        <?php endif; ?>
        <span class="dot" aria-hidden="true"></span>
        <time datetime="<?= e($reply['created_at']) ?>"><?= e(time_ago($reply['created_at'])) ?></time>
        <?php if ((int) $reply['user_id'] !== (int) $user_id && !is_staff()): ?>
            <a class="reply-report" href="<?= BASE_URL ?>student/report.php?type=reply&amp;id=<?= (int) $reply['reply_id'] ?>"
               title="Report this reply"><?= icon('flag', 14) ?>Report</a>
        <?php endif; ?>
    </div>

    <div class="reply-body"><?= nl2br(e($reply['content'])) ?></div>

    <?php if (is_staff()): ?>
        <form class="reply-actions" method="post" action="<?= BASE_URL ?>actions/staff_remove.php"
              onsubmit="return confirm('Remove this reply as staff? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="type" value="reply">
            <input type="hidden" name="id" value="<?= (int) $reply['reply_id'] ?>">
            <button type="submit" class="btn btn-danger-ghost btn-sm"><?= icon('trash', 15) ?>Remove reply</button>
        </form>
    <?php endif; ?>

    <?php // Only the author of an open post sees this, and not on their own replies ?>
    <?php if ($is_author && !$is_resolved && (int) $reply['user_id'] !== (int) $user_id): ?>
        <form class="reply-actions" method="post" action="<?= BASE_URL ?>actions/resolve_reply.php"
              onsubmit="return confirm('Mark this reply as the one that solved your problem? This closes the question.');">
            <?= csrf_field() ?>
            <input type="hidden" name="reply_id" value="<?= (int) $reply['reply_id'] ?>">
            <button type="submit" class="btn btn-resolve btn-sm"><?= icon('check', 16) ?>Mark as resolved</button>
        </form>
    <?php endif; ?>
</article>
