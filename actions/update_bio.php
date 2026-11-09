<?php
// Save the logged-in student's bio. Students can only change their own bio.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/dashboard.php');
}
check_csrf();

$user_id = (int) $_SESSION['user_id'];
$bio = post_text('bio');
$profile_page = 'student/profile.php?id=' . $user_id;

if (mb_strlen($bio) > 300) {
    set_flash('Bio must be 300 characters or fewer.', 'error');
    redirect($profile_page);
}

// An empty bio is saved as NULL (no bio)
$bio_to_save = $bio === '' ? null : $bio;

$stmt = $conn->prepare('UPDATE users SET bio = ? WHERE user_id = ?');
$stmt->bind_param('si', $bio_to_save, $user_id);
$stmt->execute();

set_flash('Your bio was updated.');
redirect($profile_page);
