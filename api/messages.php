<?php
// Live chat endpoint (JSON), used by main.js on student/messages.php.
//
// GET  api/messages.php?user=ID&after=LAST_ID&list=LIST_VERSION
//      Returns only messages newer than LAST_ID in my conversation with ID,
//      and the conversation list HTML only if it changed since LIST_VERSION.
//      user=0 means no chat is open (only the list is checked).
//
// POST api/messages.php   (csrf_token, receiver_id, content, image[])
//      Sends a message. The new message then arrives through the next GET.

require_once '../includes/api.php';
api_require_role('student');

$my_id = (int) $_SESSION['user_id'];
$my_name = $_SESSION['username'];

// ---------- Send ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    api_check_csrf();
    session_write_close(); // done with the session; do not block other requests

    $result = send_direct_message($conn, $my_id, $my_name, (int) post_text('receiver_id'), post_text('content'));
    if (isset($result['error'])) {
        json_out(['ok' => false, 'error' => $result['error']], 422);
    }
    json_out(['ok' => true, 'message_id' => $result['message_id']]);
}

// ---------- Poll ----------
session_write_close(); // read-only from here: release the session lock straight away

$other_id = (int) get_text('user');
$after = max(0, (int) get_text('after'));
$known_list = get_text('list', 40);

$response = ['ok' => true, 'messages' => [], 'last_id' => $after];

if ($other_id > 0) {
    // Only conversations with a real student partner; only MY messages with them
    if (!find_message_partner($conn, $my_id, $other_id)) {
        json_out(['ok' => false, 'error' => 'That student cannot be messaged.'], 403);
    }

    // The chat is open on screen, so anything they sent me counts as read
    mark_conversation_read($conn, $my_id, $other_id);

    // Only messages newer than the last one the page already has (max 100 at a time)
    $stmt = $conn->prepare('
        SELECT message_id, sender_id, content, image_path, created_at
        FROM messages
        WHERE message_id > ?
          AND ((sender_id = ? AND receiver_id = ? AND sender_deleted = 0)
            OR (sender_id = ? AND receiver_id = ? AND receiver_deleted = 0))
        ORDER BY message_id ASC
        LIMIT 100');
    $stmt->bind_param('iiiii', $after, $my_id, $other_id, $other_id, $my_id);
    $stmt->execute();

    foreach ($stmt->get_result() as $m) {
        $response['messages'][] = [
            'id'   => (int) $m['message_id'],
            'html' => render_partial(__DIR__ . '/../includes/message_bubble.php', ['m' => $m, 'my_id' => $my_id]),
        ];
        $response['last_id'] = (int) $m['message_id'];
    }
}

// Conversation list: only send it when something changed
$list_version = message_list_version($conn, $my_id);
$response['list_version'] = $list_version;
if ($list_version !== $known_list) {
    $response['list_html'] = render_partial(__DIR__ . '/../includes/conversation_list.php', [
        'conversations' => get_conversations($conn, $my_id),
        'my_id'         => $my_id,
        'open_id'       => $other_id,
    ]);
}

// Unread total for the sidebar badge
$response['unread_messages'] = unread_message_count($conn, $my_id);

json_out($response);
