<?php
// One meetup request card on student/meetups.php. Also sent by api/meetups.php for live updates.
// Expects: $m (meetup row + other_id, other_name) and $tab ('received' or 'sent').

$status_class = strtolower($m['status']);
?>
<article class="meetup-card <?= $status_class ?>" id="meetup-<?= (int) $m['meetup_id'] ?>" data-meetup-id="<?= (int) $m['meetup_id'] ?>">
    <div class="meetup-head">
        <a class="author-chip" href="<?= BASE_URL ?>student/profile.php?id=<?= (int) $m['other_id'] ?>">
            <?= avatar($m['other_name'], 'sm') ?><?= e($m['other_name']) ?>
        </a>
        <span class="muted"><?= $tab === 'received' ? 'wants to meet you' : 'request sent' ?></span>
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

    <?php if ($m['status'] === 'Pending' && $tab === 'received'): ?>
        <div class="report-actions">
            <!-- Accept shows the Meetup Safety Notice first -->
            <form method="post" action="<?= BASE_URL ?>actions/meetup_respond.php" data-safety-notice>
                <?= csrf_field() ?>
                <input type="hidden" name="meetup_id" value="<?= (int) $m['meetup_id'] ?>">
                <input type="hidden" name="decision" value="accept">
                <input type="hidden" name="safety_ack" value="">
                <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 16) ?>Accept</button>
            </form>
            <form method="post" action="<?= BASE_URL ?>actions/meetup_respond.php"
                  onsubmit="return confirm('Reject this meetup request?');">
                <?= csrf_field() ?>
                <input type="hidden" name="meetup_id" value="<?= (int) $m['meetup_id'] ?>">
                <input type="hidden" name="decision" value="reject">
                <button type="submit" class="btn btn-secondary btn-sm">Reject</button>
            </form>
        </div>
    <?php elseif ($m['status'] === 'Pending'): ?>
        <p class="meetup-note">Waiting for <?= e($m['other_name']) ?> to reply.</p>
    <?php elseif ($m['status'] === 'Accepted'): ?>
        <div class="meetup-note accepted">
            Accepted. Arrange the remaining details by message.
            <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>student/messages.php?user=<?= (int) $m['other_id'] ?>">
                <?= icon('message', 16) ?>Message <?= e($m['other_name']) ?>
            </a>
        </div>
    <?php endif; ?>
</article>
