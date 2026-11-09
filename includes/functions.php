<?php
// Small helper functions used across the site.

// Web address of the project folder. Change this if the folder name changes.
define('BASE_URL', '/roomiepatch/');

// Link to a CSS/JS file with its last-changed time added, e.g. assets/js/main.js?v=1731100000
// so browsers load the new version after the file changes instead of an old cached copy.
function asset($path)
{
    $file = __DIR__ . '/../' . $path;
    $version = is_file($file) ? filemtime($file) : 0;
    return BASE_URL . $path . '?v=' . $version;
}

// Escape text before printing it into HTML, to prevent XSS.
function e($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

// Send the browser to another page inside the project and stop the script.
// Example: redirect('student/dashboard.php');
function redirect($path)
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

// The dashboard a user should land on, based on their role.
function dashboard_for($role)
{
    return $role === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php';
}

// Turn a MySQL date-time into "just now", "5m ago", "3h ago", "2d ago" or a date.
function time_ago($datetime)
{
    $seconds = time() - strtotime($datetime);

    if ($seconds < 60) {
        return 'just now';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . 'm ago';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . 'h ago';
    }
    if ($seconds < 7 * 86400) {
        return floor($seconds / 86400) . 'd ago';
    }
    return date('j M Y', strtotime($datetime));
}

// Time for chat messages: "2:45 PM" for today, "8 Nov, 2:45 PM" for older days.
function message_time($datetime)
{
    $time = strtotime($datetime);
    if (date('Y-m-d', $time) === date('Y-m-d')) {
        return date('g:i A', $time);
    }
    return date('j M, g:i A', $time);
}

// Number of unread direct messages for a user (shown as a badge in the sidebar).
function unread_message_count($conn, $user_id)
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM messages WHERE receiver_id = ? AND is_read = 0 AND receiver_deleted = 0');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['total'];
}

// Proposed meetup time, e.g. "Mon, 10 Nov 2026, 2:30 PM"
function meetup_time($datetime)
{
    return date('D, j M Y, g:i A', strtotime($datetime));
}

// Meetup requests for one tab: 'received' (sent to me) or 'sent' (sent by me).
// "other" = the student on the other side. With $since, only requests created or
// changed (accepted/rejected) since that time are returned, for live updates.
function get_meetups($conn, $my_id, $tab, $since = null)
{
    if ($tab === 'received') {
        $sql = "SELECT m.*, u.user_id AS other_id, u.username AS other_name
                FROM meetup_requests m JOIN users u ON u.user_id = m.requester_id
                WHERE m.recipient_id = ?";
    } else {
        $sql = "SELECT m.*, u.user_id AS other_id, u.username AS other_name
                FROM meetup_requests m JOIN users u ON u.user_id = m.recipient_id
                WHERE m.requester_id = ?";
    }

    if ($since !== null) {
        $stmt = $conn->prepare($sql . ' AND m.updated_at >= ? ORDER BY m.created_at ASC LIMIT 50');
        $stmt->bind_param('is', $my_id, $since);
    } else {
        $order = $tab === 'received' ? "(m.status = 'Pending') DESC, m.created_at DESC" : 'm.created_at DESC';
        $stmt = $conn->prepare($sql . ' ORDER BY ' . $order);
        $stmt->bind_param('i', $my_id);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Tab counts for the Meetup Requests page
function get_meetup_counts($conn, $my_id)
{
    $stmt = $conn->prepare("SELECT
            COALESCE(SUM(recipient_id = ? AND status = 'Pending'), 0) AS received_pending,
            COALESCE(SUM(requester_id = ?), 0) AS sent_total,
            COALESCE(SUM(recipient_id = ?), 0) AS received_total
        FROM meetup_requests WHERE recipient_id = ? OR requester_id = ?");
    $stmt->bind_param('iiiii', $my_id, $my_id, $my_id, $my_id, $my_id);
    $stmt->execute();
    return array_map('intval', $stmt->get_result()->fetch_assoc());
}

// The student is looking at the Meetup Requests page, so meetup notifications count as seen
function mark_meetup_notifications_read($conn, $my_id)
{
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0 AND link LIKE 'student/meetups.php%'");
    $stmt->bind_param('i', $my_id);
    $stmt->execute();
}

// Number of meetup requests waiting for this user's answer (sidebar badge).
function pending_meetup_count($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM meetup_requests WHERE recipient_id = ? AND status = 'Pending'");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['total'];
}

// ---------- Direct messages ----------

// SQL condition: messages this user can still see (not removed with "Delete chat").
// Needs the user id bound twice. $t is the table alias, e.g. 'm'.
function visible_messages_sql($t = 'messages')
{
    return "(($t.sender_id = ? AND $t.sender_deleted = 0) OR ($t.receiver_id = ? AND $t.receiver_deleted = 0))";
}

// One row per person this user has messaged with: their latest message and the unread count.
// Chats the user deleted are left out (until the other student sends something new).
function get_conversations($conn, $my_id)
{
    $stmt = $conn->prepare('
        SELECT c.other_id, u.username, m.content, m.image_path, m.created_at, m.sender_id,
               (SELECT COUNT(*) FROM messages x
                WHERE x.sender_id = c.other_id AND x.receiver_id = ? AND x.is_read = 0 AND x.receiver_deleted = 0) AS unread
        FROM (
            SELECT IF(sender_id = ?, receiver_id, sender_id) AS other_id, MAX(message_id) AS last_id
            FROM messages
            WHERE ' . visible_messages_sql() . '
            GROUP BY other_id
        ) c
        JOIN messages m ON m.message_id = c.last_id
        JOIN users u ON u.user_id = c.other_id
        ORDER BY m.message_id DESC');
    $stmt->bind_param('iiii', $my_id, $my_id, $my_id, $my_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// The newest visible message id, the unread count and the number of visible messages.
// If this changes (new message, read, or a chat deleted), the conversation list has changed.
function message_list_version($conn, $my_id)
{
    $stmt = $conn->prepare('SELECT CONCAT(COALESCE(MAX(message_id), 0), "-",
                                   COALESCE(SUM(receiver_id = ? AND is_read = 0), 0), "-", COUNT(*)) AS v
                            FROM messages WHERE ' . visible_messages_sql());
    $stmt->bind_param('iii', $my_id, $my_id, $my_id);
    $stmt->execute();
    return (string) $stmt->get_result()->fetch_assoc()['v'];
}

// "Delete chat": hide the whole conversation with $other_id for $my_id only.
// The other student keeps their copy. Messages both sides have deleted are removed
// from the database completely, together with any attached image files.
function delete_conversation($conn, $my_id, $other_id)
{
    $stmt = $conn->prepare('UPDATE messages SET sender_deleted = 1 WHERE sender_id = ? AND receiver_id = ?');
    $stmt->bind_param('ii', $my_id, $other_id);
    $stmt->execute();

    // Their messages to me also count as read, so they stop showing as unread anywhere
    $stmt = $conn->prepare('UPDATE messages SET receiver_deleted = 1, is_read = 1 WHERE sender_id = ? AND receiver_id = ?');
    $stmt->bind_param('ii', $other_id, $my_id);
    $stmt->execute();

    // Messages deleted by both students: remove image files, then the rows
    $both_sides = '((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                   AND sender_deleted = 1 AND receiver_deleted = 1';
    $stmt = $conn->prepare("SELECT image_path FROM messages WHERE image_path IS NOT NULL AND $both_sides");
    $stmt->bind_param('iiii', $my_id, $other_id, $other_id, $my_id);
    $stmt->execute();
    foreach ($stmt->get_result() as $row) {
        $file = __DIR__ . '/../uploads/messages/' . basename($row['image_path']);
        if (is_file($file)) {
            unlink($file);
        }
    }
    $stmt = $conn->prepare("DELETE FROM messages WHERE $both_sides");
    $stmt->bind_param('iiii', $my_id, $other_id, $other_id, $my_id);
    $stmt->execute();

    // The "X sent you a message" notification no longer needs attention
    $link = 'student/messages.php?user=' . $other_id;
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND link = ?');
    $stmt->bind_param('is', $my_id, $link);
    $stmt->execute();
}

// Opening a chat: mark the other student's messages to me as read,
// and the "sent you a message" notification from them too.
function mark_conversation_read($conn, $my_id, $other_id)
{
    $stmt = $conn->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0');
    $stmt->bind_param('ii', $other_id, $my_id);
    $stmt->execute();

    $link = 'student/messages.php?user=' . $other_id;
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND link = ? AND is_read = 0');
    $stmt->bind_param('is', $my_id, $link);
    $stmt->execute();
}

// Can $my_id message $other_id? Returns the other student's row, or null.
function find_message_partner($conn, $my_id, $other_id)
{
    $stmt = $conn->prepare('SELECT user_id, username FROM users WHERE user_id = ? AND role = "student" AND user_id <> ?');
    $stmt->bind_param('ii', $other_id, $my_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Send a direct message (text, one image, or both) and notify the receiver.
// Used by the normal form (actions/send_message.php) and by fetch() (api/messages.php).
// Returns ['error' => '...'] on failure, or ['message_id' => 123] on success.
function send_direct_message($conn, $my_id, $my_name, $receiver_id, $content)
{
    if (!find_message_partner($conn, $my_id, $receiver_id)) {
        return ['error' => 'That student cannot be messaged.'];
    }
    if (mb_strlen($content) > 2000) {
        return ['error' => 'Messages must be 2000 characters or fewer.'];
    }

    // One optional image per message
    $images = check_image_uploads('image', 1, $image_error);
    if ($image_error !== '') {
        return ['error' => $image_error];
    }
    if ($content === '' && !$images) {
        return ['error' => 'Write a message or attach an image.'];
    }

    $image_path = $images ? save_image($images[0], 'uploads/messages') : null;
    $content_to_save = $content === '' ? null : $content;

    $stmt = $conn->prepare('INSERT INTO messages (sender_id, receiver_id, content, image_path) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('iiss', $my_id, $receiver_id, $content_to_save, $image_path);
    $stmt->execute();
    $message_id = $conn->insert_id;

    // One notification per conversation: if the receiver already has an unread one from me,
    // just move it to the top instead of adding another.
    $link = 'student/messages.php?user=' . $my_id;
    $stmt = $conn->prepare('SELECT notification_id FROM notifications WHERE user_id = ? AND link = ? AND is_read = 0 LIMIT 1');
    $stmt->bind_param('is', $receiver_id, $link);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $stmt = $conn->prepare('UPDATE notifications SET created_at = NOW() WHERE notification_id = ?');
        $stmt->bind_param('i', $existing['notification_id']);
        $stmt->execute();
    } else {
        add_notification($conn, $receiver_id, 'message', $my_name . ' sent you a message', $link);
    }

    return ['message_id' => $message_id];
}

// Shorten long text for previews, e.g. post descriptions on cards.
function excerpt($text, $length = 160)
{
    $text = trim($text);
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length)) . '...';
}

// Split the comma separated tags column into a clean list.
function tag_list($tags)
{
    $list = [];
    foreach (explode(',', (string) $tags) as $tag) {
        $tag = trim($tag);
        if ($tag !== '') {
            $list[] = $tag;
        }
    }
    return $list;
}

// A round avatar showing the first letter of the username.
// The colour is picked from the username, so each user always gets the same colour.
function avatar($username, $size = 'md')
{
    $colours = ['#2563eb', '#0891b2', '#4f46e5', '#0d9488', '#7c3aed', '#1d4ed8'];
    $colour = $colours[crc32($username) % count($colours)];
    $letter = strtoupper(mb_substr($username, 0, 1));

    return '<span class="avatar avatar-' . e($size) . '" style="background:' . $colour . '" aria-hidden="true">'
        . e($letter) . '</span>';
}

// Read a text value from the URL (?name=value), trimmed and cut to $max characters.
// Returns '' if it is missing or not plain text (e.g. someone sends name[]=x).
function get_text($name, $max = 100)
{
    $value = $_GET[$name] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return mb_substr(trim($value), 0, $max);
}

// Same as get_text(), but for values sent by a POST form. Returns '' if not plain text.
function post_text($name)
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

// Save a notification for a user. It appears in their notification bell.
// $type: 'message', 'meetup_request', 'meetup_accepted', 'meetup_rejected' or 'reply'
// $link: page inside the project to open when clicked, e.g. 'student/post.php?id=3'
function add_notification($conn, $user_id, $type, $message, $link)
{
    $message = mb_substr($message, 0, 255);
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $user_id, $type, $message, $link);
    $stmt->execute();
}

// Delete a post completely: its image files, its notifications, and the post row.
// The database removes its replies and post_images rows automatically (ON DELETE CASCADE),
// and reports about it keep existing with the post link set to NULL.
// Used when a student deletes their own post, and later by admin moderation.
function delete_post($conn, $post_id)
{
    // 1. Remove the image files from the uploads folder
    $stmt = $conn->prepare('SELECT image_path FROM post_images WHERE post_id = ?');
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
    foreach ($stmt->get_result() as $image) {
        $file = __DIR__ . '/../uploads/posts/' . basename($image['image_path']);
        if (is_file($file)) {
            unlink($file);
        }
    }

    // 2. Remove notifications that point to this post (they would lead nowhere)
    $link = 'student/post.php?id=' . $post_id;
    $link_like = $link . '#%';
    $stmt = $conn->prepare('DELETE FROM notifications WHERE link = ? OR link LIKE ?');
    $stmt->bind_param('ss', $link, $link_like);
    $stmt->execute();

    // 3. Remove the post itself
    $stmt = $conn->prepare('DELETE FROM posts WHERE post_id = ?');
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
}

// Delete one reply (used by admin moderation).
// If it was the reply that resolved the post, the post goes back to Open.
function delete_reply($conn, $reply_id)
{
    $stmt = $conn->prepare('SELECT post_id, is_resolved FROM replies WHERE reply_id = ?');
    $stmt->bind_param('i', $reply_id);
    $stmt->execute();
    $reply = $stmt->get_result()->fetch_assoc();
    if (!$reply) {
        return;
    }

    // Remove the "X replied to your question" notification for this reply
    $link = 'student/post.php?id=' . $reply['post_id'] . '#reply-' . $reply_id;
    $stmt = $conn->prepare('DELETE FROM notifications WHERE link = ?');
    $stmt->bind_param('s', $link);
    $stmt->execute();

    $stmt = $conn->prepare('DELETE FROM replies WHERE reply_id = ?');
    $stmt->bind_param('i', $reply_id);
    $stmt->execute();

    if ($reply['is_resolved']) {
        $stmt = $conn->prepare('UPDATE posts SET status = "Open" WHERE post_id = ?');
        $stmt->bind_param('i', $reply['post_id']);
        $stmt->execute();
    }
}

// ---------- Staff removal ----------
// Used both from the report queue and when staff remove content directly from a post page.
// Pending reports about the content are marked "Removed" first, because deleting
// the content sets the report links to NULL.

function staff_remove_post($conn, $post_id)
{
    $stmt = $conn->prepare("
        UPDATE reports SET status = 'Removed'
        WHERE status = 'Pending'
          AND (reported_post_id = ?
               OR reported_reply_id IN (SELECT reply_id FROM replies WHERE post_id = ?))");
    $stmt->bind_param('ii', $post_id, $post_id);
    $stmt->execute();

    delete_post($conn, $post_id);
}

function staff_remove_reply($conn, $reply_id)
{
    $stmt = $conn->prepare("UPDATE reports SET status = 'Removed' WHERE status = 'Pending' AND reported_reply_id = ?");
    $stmt->bind_param('i', $reply_id);
    $stmt->execute();

    delete_reply($conn, $reply_id);
}

// ---------- Reports ----------
// Must match the ENUM in the reports table.
const REPORT_REASONS = ['Spam', 'Harassment', 'Inappropriate content', 'Misinformation', 'Other'];

// Shared SELECT for the admin report pages. Brings in the reporter, and the reported
// post or reply with its author. Columns are NULL if the content was already deleted.
const REPORT_LIST_SQL = "
    SELECT r.report_id, r.reason, r.description, r.status, r.created_at,
           r.reported_post_id, r.reported_reply_id,
           reporter.username AS reporter_name,
           p.title AS post_title, p.description AS post_description, p.created_at AS post_created,
           post_author.username AS post_author,
           rp.content AS reply_content, rp.created_at AS reply_created, rp.post_id AS reply_post_id,
           reply_author.username AS reply_author,
           reply_post.title AS reply_post_title
    FROM reports r
    JOIN users reporter ON reporter.user_id = r.reporter_id
    LEFT JOIN posts p ON p.post_id = r.reported_post_id
    LEFT JOIN users post_author ON post_author.user_id = p.user_id
    LEFT JOIN replies rp ON rp.reply_id = r.reported_reply_id
    LEFT JOIN users reply_author ON reply_author.user_id = rp.user_id
    LEFT JOIN posts reply_post ON reply_post.post_id = rp.post_id
";

// ---------- Image uploads ----------
// Allowed image types: real file type => extension we save it with.
const IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5 MB

// Check the images sent in a file input such as <input type="file" name="images[]" multiple>.
// Returns a list of valid images ready to save. If something is wrong, $error is set.
// Nothing is saved here, so a form with other errors does not leave stray files.
function check_image_uploads($field, $max_count, &$error)
{
    $error = '';
    $images = [];

    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
        return [];
    }

    $files = $_FILES[$field];
    foreach ($files['name'] as $i => $name) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue; // the student did not pick a file for this slot
        }
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $error = 'One of the images could not be uploaded. Please try again.';
            return [];
        }
        if ($files['size'][$i] > MAX_IMAGE_SIZE) {
            $error = 'Each image must be 5 MB or smaller.';
            return [];
        }

        // Check the real file contents, not the file name, so a renamed file is caught.
        $type = (new finfo(FILEINFO_MIME_TYPE))->file($files['tmp_name'][$i]);
        if (!isset(IMAGE_TYPES[$type]) || @getimagesize($files['tmp_name'][$i]) === false) {
            $error = 'Only JPG, PNG, GIF or WebP images can be attached.';
            return [];
        }

        $images[] = ['tmp_name' => $files['tmp_name'][$i], 'ext' => IMAGE_TYPES[$type]];
    }

    if (count($images) > $max_count) {
        $error = $max_count === 1 ? 'You can attach only one image.' : 'You can attach up to ' . $max_count . ' images.';
        return [];
    }

    return $images;
}

// Move one checked image into an uploads folder with a random, safe file name.
// $folder is relative to the project, e.g. 'uploads/posts'. Returns the new file name.
function save_image($image, $folder)
{
    $file_name = bin2hex(random_bytes(16)) . '.' . $image['ext'];
    move_uploaded_file($image['tmp_name'], __DIR__ . '/../' . $folder . '/' . $file_name);
    return $file_name;
}

// Device / OS choices. Must match the ENUM in the posts table.
const DEVICE_OPTIONS = ['Windows', 'macOS', 'Linux', 'Android', 'iOS', 'Other'];

// Escape % and _ so they are treated as normal characters inside a LIKE search.
function like_escape($text)
{
    return addcslashes($text, '%_\\');
}

// Shared SELECT for post lists (dashboard, browse, my posts, profile).
// Add your own WHERE / ORDER BY / LIMIT after it.
const POST_LIST_SQL = "
    SELECT p.post_id, p.title, p.description, p.device_os, p.tags, p.status, p.created_at,
           u.user_id, u.username, c.category_name,
           (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.post_id) AS reply_count,
           (SELECT COUNT(*) FROM post_images i WHERE i.post_id = p.post_id) AS image_count
    FROM posts p
    JOIN users u ON u.user_id = p.user_id
    JOIN categories c ON c.category_id = p.category_id
";
