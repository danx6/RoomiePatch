<?php
// Vote on a reply without reloading the page (JSON), used by live.js.
//
// POST api/vote.php   (csrf_token, reply_id, vote = upvote | downvote)
// Returns the viewer's vote after the click, the reply's score, and the author's
// updated reputation label. The reputation change itself is worked out on the server
// (cast_vote() in functions.php); the browser never sends a reputation value.

require_once '../includes/api.php';
api_require_role('student');   // staff can read posts but do not vote

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}
api_check_csrf();

$voter_id = (int) $_SESSION['user_id'];
session_write_close();

$reply_id = (int) post_text('reply_id');
$result = cast_vote($conn, $voter_id, $reply_id, post_text('vote'));

if (isset($result['error'])) {
    json_out(['ok' => false, 'error' => $result['error']], 422);
}

$info = get_reputation_info($conn, [$result['author_id']]);

json_out([
    'ok'         => true,
    'reply_id'   => $reply_id,
    'my_vote'    => $result['my_vote'],
    'score'      => $result['score'],
    'author_id'  => $result['author_id'],
    'reputation' => $info[$result['author_id']]['reputation'],
    'rep_html'   => reputation_label($result['author_id'], $info),
]);
