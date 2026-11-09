<?php
// Live replies endpoint (JSON), used by live.js on student/post.php.
//
// GET api/replies.php?post=ID&after=LAST_REPLY_ID
//     Returns only replies newer than LAST_REPLY_ID, the total reply count,
//     and the post status (so the page can tell when it was marked resolved).
// Students and staff can both view posts, so both can use this.

require_once '../includes/api.php';
api_require_role(['student', 'admin']);

$user_id = (int) $_SESSION['user_id'];
session_write_close(); // read-only: release the session lock straight away

$post_id = (int) get_text('post');
$after = max(0, (int) get_text('after'));

$stmt = $conn->prepare('SELECT post_id, user_id, status FROM posts WHERE post_id = ?');
$stmt->bind_param('i', $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    json_out(['ok' => false, 'error' => 'This post has been removed.', 'removed' => true], 404);
}

$is_author = (int) $post['user_id'] === $user_id;
$is_resolved = $post['status'] === 'Resolved';

// Only replies newer than the last one the page already shows (max 50 at a time)
$stmt = $conn->prepare('
    SELECT r.reply_id, r.user_id, r.content, r.is_resolved, r.created_at, u.username
    FROM replies r
    JOIN users u ON u.user_id = r.user_id
    WHERE r.post_id = ? AND r.reply_id > ?
    ORDER BY r.reply_id ASC
    LIMIT 50');
$stmt->bind_param('ii', $post_id, $after);
$stmt->execute();

$new = [];
$last_id = $after;
foreach ($stmt->get_result() as $reply) {
    $new[] = [
        'id'   => (int) $reply['reply_id'],
        'html' => render_partial(__DIR__ . '/../includes/reply_item.php', [
            'reply'       => $reply,
            'post'        => $post,
            'user_id'     => $user_id,
            'is_author'   => $is_author,
            'is_resolved' => $is_resolved,
        ]),
    ];
    $last_id = (int) $reply['reply_id'];
}

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM replies WHERE post_id = ?');
$stmt->bind_param('i', $post_id);
$stmt->execute();
$count = (int) $stmt->get_result()->fetch_assoc()['total'];

// The author is looking at their own post: new reply notifications count as seen
if ($is_author && $new) {
    $link_like = 'student/post.php?id=' . $post_id . '#%';
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = "reply" AND link LIKE ?');
    $stmt->bind_param('is', $user_id, $link_like);
    $stmt->execute();
}

json_out([
    'ok'      => true,
    'replies' => $new,
    'last_id' => $last_id,
    'count'   => $count,
    'status'  => $post['status'],
]);
