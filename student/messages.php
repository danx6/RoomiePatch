<?php
// Direct messages (inbox).
//   messages.php            conversation list
//   messages.php?user=ID    conversation list + chat with that student
// Desktop: list on the left, chat on the right. Phones: one view at a time.
// While the page is open, main.js sends and receives messages through api/messages.php
// without reloading. The normal form still works if JavaScript is off.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

$my_id = (int) $_SESSION['user_id'];
$other_id = (int) get_text('user');
$other = null;
$chat = [];

// ---------- The open conversation (if any) ----------
if ($other_id > 0) {
    // You can only message other students (not yourself, not staff)
    $other = find_message_partner($conn, $my_id, $other_id);
    if (!$other) {
        set_flash('That student cannot be messaged.', 'error');
        redirect('student/messages.php');
    }

    mark_conversation_read($conn, $my_id, $other_id);

    // All messages between the two of us that I have not deleted, oldest first
    $stmt = $conn->prepare('
        SELECT message_id, sender_id, content, image_path, created_at
        FROM messages
        WHERE ((sender_id = ? AND receiver_id = ? AND sender_deleted = 0)
            OR (sender_id = ? AND receiver_id = ? AND receiver_deleted = 0))
        ORDER BY message_id ASC');
    $stmt->bind_param('iiii', $my_id, $other_id, $other_id, $my_id);
    $stmt->execute();
    $chat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$conversations = get_conversations($conn, $my_id);
$open_id = $other ? $other_id : 0;

// "List version" = my newest message id. The live updates compare it to know
// when the conversation list needs redrawing, instead of resending it every time.
$list_version = message_list_version($conn, $my_id);

$last_message_id = $chat ? (int) end($chat)['message_id'] : 0;

$page_title = $other ? 'Messages with ' . $other['username'] : 'Messages';
$active = 'messages';
include '../includes/header.php';
?>

<main class="content">
    <?php show_flash(); ?>

    <div class="inbox<?= $other ? ' has-chat' : '' ?>"
         data-inbox
         data-user="<?= $open_id ?>"
         data-last-id="<?= $last_message_id ?>"
         data-list-version="<?= $list_version ?>">

        <!-- ===== Left: conversation list ===== -->
        <section class="inbox-list" aria-label="Conversations">
            <h1 class="inbox-title">Messages</h1>
            <div id="conversation-list">
                <?php include '../includes/conversation_list.php'; ?>
            </div>
        </section>

        <!-- ===== Right: the open chat ===== -->
        <section class="inbox-chat" aria-label="Conversation">
            <?php if (!$other): ?>
                <div class="chat-placeholder">
                    <?= icon('message', 36) ?>
                    <strong>Your messages</strong>
                    Choose a conversation on the left.
                </div>
            <?php else: ?>
                <header class="chat-head">
                    <a class="icon-btn chat-back" href="messages.php" aria-label="Back to conversations"><?= icon('chevron-left') ?></a>
                    <a class="author-chip" href="<?= BASE_URL ?>student/profile.php?id=<?= $other_id ?>">
                        <?= avatar($other['username']) ?><?= e($other['username']) ?>
                    </a>
                    <a class="chat-profile-link" href="<?= BASE_URL ?>student/profile.php?id=<?= $other_id ?>">View profile</a>
                    <form method="post" action="<?= BASE_URL ?>actions/delete_conversation.php"
                          onsubmit="return confirm(<?= e(json_encode('Delete your chat with ' . $other['username'] . '? It is only removed from your messages.')) ?>);">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= $other_id ?>">
                        <button type="submit" class="btn btn-danger-ghost btn-sm"><?= icon('trash', 15) ?>Delete chat</button>
                    </form>
                </header>

                <div class="chat-messages" id="chat-messages" aria-live="polite">
                    <?php if (!$chat): ?>
                        <p class="chat-start">This is the start of your conversation with <?= e($other['username']) ?>.</p>
                    <?php endif; ?>

                    <?php foreach ($chat as $m): ?>
                        <?php include '../includes/message_bubble.php'; ?>
                    <?php endforeach; ?>
                </div>

                <form class="chat-compose" id="chat-compose" method="post" action="<?= BASE_URL ?>actions/send_message.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="receiver_id" value="<?= $other_id ?>">

                    <label class="icon-btn chat-attach" for="chat-image" title="Attach an image">
                        <?= icon('image') ?><span class="sr-only">Attach an image</span>
                    </label>
                    <input class="file-input" type="file" id="chat-image" name="image[]"
                           accept="image/jpeg,image/png,image/gif,image/webp" data-image-preview="chat-preview" data-max-files="1">

                    <div class="chat-input-wrap">
                        <p class="chat-error" id="chat-error" role="alert" hidden></p>
                        <div class="image-preview chat-preview" id="chat-preview"></div>
                        <label class="sr-only" for="chat-text">Message</label>
                        <textarea class="input chat-input" id="chat-text" name="content" rows="1" maxlength="2000"
                                  placeholder="Write a message" data-enter-submit></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="chat-send">Send</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
