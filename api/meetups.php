<?php
// Live Meetup Requests endpoint (JSON), used by live.js on student/meetups.php.
//
// GET api/meetups.php?tab=received|sent&since=YYYY-MM-DD HH:MM:SS
//     Returns only MY meetup requests on that tab that are new or changed
//     (e.g. accepted or rejected) since that time, plus the tab counts.

require_once '../includes/api.php';
api_require_role('student');

$my_id = (int) $_SESSION['user_id'];
session_write_close(); // read-only (apart from the DB): release the session lock

$tab = get_text('tab') === 'sent' ? 'sent' : 'received';
$since = get_text('since', 19);
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
    $since = date('Y-m-d H:i:s');
}

$now = $conn->query('SELECT NOW() AS t')->fetch_assoc()['t'];

// The page is open, so new meetup notifications count as seen
mark_meetup_notifications_read($conn, $my_id);

$cards = [];
foreach (get_meetups($conn, $my_id, $tab, $since) as $m) {
    $cards[] = [
        'id'     => (int) $m['meetup_id'],
        'status' => $m['status'],
        'html'   => render_partial(__DIR__ . '/../includes/meetup_card.php', ['m' => $m, 'tab' => $tab]),
    ];
}

json_out([
    'ok'          => true,
    'server_time' => $now,
    'cards'       => $cards,
    'counts'      => get_meetup_counts($conn, $my_id),
]);
