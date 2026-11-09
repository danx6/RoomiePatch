<?php
// The inbox conversation list. Used by student/messages.php and api/messages.php.
// Expects: $conversations (from get_conversations()), $my_id, and $open_id (the open chat, or 0).
?>
<?php if (!$conversations): ?>
    <div class="inbox-empty">
        <strong>No conversations yet</strong>
        Open a student's profile and click Message to start one.
    </div>
<?php else: ?>
    <ul class="conversation-list">
        <?php foreach ($conversations as $c): ?>
            <?php
            $is_open = (int) $c['other_id'] === (int) $open_id;
            $preview = ($c['content'] !== null && $c['content'] !== '') ? $c['content'] : 'Sent an image';
            if ((int) $c['sender_id'] === (int) $my_id) {
                $preview = 'You: ' . $preview;
            }
            ?>
            <li class="conversation-row">
                <a class="conversation<?= $is_open ? ' active' : '' ?><?= $c['unread'] > 0 ? ' unread' : '' ?>"
                   href="<?= BASE_URL ?>student/messages.php?user=<?= (int) $c['other_id'] ?>">
                    <?= avatar($c['username']) ?>
                    <span class="conversation-text">
                        <span class="conversation-top">
                            <span class="conversation-name"><?= e($c['username']) ?></span>
                            <time datetime="<?= e($c['created_at']) ?>"><?= e(time_ago($c['created_at'])) ?></time>
                        </span>
                        <span class="conversation-preview"><?= e(excerpt($preview, 60)) ?></span>
                    </span>
                    <?php if ($c['unread'] > 0): ?>
                        <span class="unread-badge" aria-label="<?= (int) $c['unread'] ?> unread"><?= (int) $c['unread'] ?></span>
                    <?php endif; ?>
                </a>
                <!-- Delete this chat from my inbox only -->
                <form class="conversation-delete" method="post" action="<?= BASE_URL ?>actions/delete_conversation.php"
                      onsubmit="return confirm(<?= e(json_encode('Delete your chat with ' . $c['username'] . '? It is only removed from your messages.')) ?>);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $c['other_id'] ?>">
                    <button type="submit" class="icon-btn" title="Delete chat" aria-label="Delete chat with <?= e($c['username']) ?>">
                        <?= icon('trash', 17) ?>
                    </button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
