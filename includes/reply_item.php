<?php
// One reply on a post page. Used by student/post.php and api/replies.php (live updates).
// Expects: $reply (reply_id, user_id, content, is_resolved, created_at, username, score, my_vote),
//          $post (the post row), $user_id (viewer), $is_author, $is_resolved,
//          $rep_info (from get_reputation_info(), for the label beside the username).

$own_reply = (int) $reply['user_id'] === (int) $user_id;
$can_vote = !$own_reply && !is_staff();
$my_vote = $reply['my_vote'] ?? null;
if ($own_reply) {
    $vote_note = 'You cannot vote on your own reply';
} elseif (is_staff()) {
    $vote_note = 'Only students can vote';
} else {
    $vote_note = '';
}
?>
<article class="reply<?= $reply['is_resolved'] ? ' resolved' : '' ?>" id="reply-<?= (int) $reply['reply_id'] ?>" data-reply-id="<?= (int) $reply['reply_id'] ?>">
    <?php if ($reply['is_resolved']): ?>
        <div class="resolved-label"><?= icon('check', 16) ?>Marked as resolved by author</div>
    <?php endif; ?>

    <div class="reply-layout">
        <!-- Voting: one active vote per student; clicking the active arrow again removes it -->
        <form class="vote-box" method="post" action="<?= BASE_URL ?>actions/vote.php" data-vote-form>
            <?= csrf_field() ?>
            <input type="hidden" name="reply_id" value="<?= (int) $reply['reply_id'] ?>">
            <button type="submit" name="vote" value="upvote"
                    class="vote-btn up<?= $my_vote === 'upvote' ? ' active' : '' ?>"
                    aria-pressed="<?= $my_vote === 'upvote' ? 'true' : 'false' ?>"
                    aria-label="Upvote this reply" title="<?= $vote_note !== '' ? e($vote_note) : 'Helpful: upvote' ?>"
                    <?= $can_vote ? '' : 'disabled' ?>><?= icon('arrow-up', 20) ?></button>
            <span class="vote-score<?= $reply['score'] > 0 ? ' positive' : ($reply['score'] < 0 ? ' negative' : '') ?>"
                  data-vote-score aria-label="Reply score"><?= (int) $reply['score'] ?></span>
            <button type="submit" name="vote" value="downvote"
                    class="vote-btn down<?= $my_vote === 'downvote' ? ' active' : '' ?>"
                    aria-pressed="<?= $my_vote === 'downvote' ? 'true' : 'false' ?>"
                    aria-label="Downvote this reply" title="<?= $vote_note !== '' ? e($vote_note) : 'Not helpful: downvote' ?>"
                    <?= $can_vote ? '' : 'disabled' ?>><?= icon('arrow-down', 20) ?></button>
        </form>

        <div class="reply-main">
            <div class="reply-meta">
                <a class="author-chip" href="<?= BASE_URL ?>student/profile.php?id=<?= (int) $reply['user_id'] ?>">
                    <?= avatar($reply['username'], 'sm') ?><?= e($reply['username']) ?>
                </a>
                <?= reputation_label((int) $reply['user_id'], $rep_info) ?>
                <?php if ((int) $reply['user_id'] === (int) $post['user_id']): ?>
                    <span class="author-tag">Author</span>
                <?php endif; ?>
                <span class="dot" aria-hidden="true"></span>
                <time datetime="<?= e($reply['created_at']) ?>"><?= e(time_ago($reply['created_at'])) ?></time>
                <?php if (!$own_reply && !is_staff()): ?>
                    <a class="reply-report" href="<?= BASE_URL ?>student/report.php?type=reply&amp;id=<?= (int) $reply['reply_id'] ?>"
                       title="Report this reply"><?= icon('flag', 14) ?>Report</a>
                <?php endif; ?>
            </div>

            <div class="reply-body"><?= nl2br(e($reply['content'])) ?></div>
            <p class="vote-error" data-vote-error role="alert" hidden></p>

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
            <?php if ($is_author && !$is_resolved && !$own_reply): ?>
                <form class="reply-actions" method="post" action="<?= BASE_URL ?>actions/resolve_reply.php"
                      onsubmit="return confirm('Mark this reply as the one that solved your problem? This closes the question.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="reply_id" value="<?= (int) $reply['reply_id'] ?>">
                    <button type="submit" class="btn btn-resolve btn-sm"><?= icon('check', 16) ?>Mark as resolved</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>
