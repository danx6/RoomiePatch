<?php
// One chat message bubble. Used by student/messages.php and api/messages.php.
// Expects: $m (message_id, sender_id, content, image_path, created_at) and $my_id.

$mine = (int) $m['sender_id'] === (int) $my_id;
?>
<div class="bubble-row<?= $mine ? ' mine' : '' ?>" data-message-id="<?= (int) $m['message_id'] ?>">
    <div class="bubble">
        <?php if ($m['image_path'] !== null): ?>
            <a href="<?= BASE_URL ?>uploads/messages/<?= e($m['image_path']) ?>" data-lightbox title="Enlarge image">
                <img src="<?= BASE_URL ?>uploads/messages/<?= e($m['image_path']) ?>" alt="Image sent in chat" loading="lazy">
            </a>
        <?php endif; ?>
        <?php if ($m['content'] !== null && $m['content'] !== ''): ?>
            <p><?= nl2br(e($m['content'])) ?></p>
        <?php endif; ?>
    </div>
    <time class="bubble-time" datetime="<?= e($m['created_at']) ?>"><?= e(message_time($m['created_at'])) ?></time>
</div>
