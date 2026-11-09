<?php
// Include this at the top of every page that needs a logged-in user.
// It starts the session and provides require_login().

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- CSRF protection ----------
// Every form that changes data includes a hidden secret token. When the form is
// submitted we check the token matches, so other websites cannot submit
// forms on behalf of a logged-in student.

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Put this inside every <form method="post">.
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

// Call this before saving anything from a POST form.
function check_csrf()
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

// ---------- Flash messages ----------
// A one-time message shown on the next page, e.g. after a form is saved.

// $type is 'success' (green) or 'error' (red).
function set_flash($message, $type = 'success')
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

// Prints the message (if there is one) and removes it so it only shows once.
function show_flash()
{
    if (!empty($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'] === 'error' ? 'error' : 'success';
        echo '<div class="alert alert-' . $type . '" role="status">' . e($_SESSION['flash']['message']) . '</div>';
        unset($_SESSION['flash']);
    }
}

// Is anyone logged in?
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

// Stop visitors who are not logged in, or who have the wrong role.
//   require_login('student')             student-only pages (asking, replying, messages...)
//   require_login('admin')               admin-only pages (report queue)
//   require_login(['student', 'admin'])  pages staff may also view (browse, post, profile)
function require_login($roles)
{
    if (!is_logged_in()) {
        redirect('login.php');
    }

    // Logged in, but this page is not for their role: send them to their own dashboard.
    if (!in_array($_SESSION['role'], (array) $roles, true)) {
        redirect(dashboard_for($_SESSION['role']));
    }
}

// Is the logged-in user university staff/admin?
// Staff can read posts and profiles, but cannot ask, reply, report, message or request meetups.
function is_staff()
{
    return ($_SESSION['role'] ?? '') === 'admin';
}
