<?php
// Shared helpers for the JSON endpoints in the api/ folder.
// These endpoints are called by JavaScript fetch(), so they always answer with JSON
// (never an HTML page or a redirect), including for errors.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';   // the HTML templates use icon()
require_once __DIR__ . '/../config/db.php';

// Send a JSON response and stop.
function json_out($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

// Like require_login(), but answers with a JSON error instead of redirecting.
// $roles can be one role ('student') or several (['student', 'admin']).
function api_require_role($roles)
{
    if (!is_logged_in()) {
        json_out(['ok' => false, 'error' => 'Please log in again.'], 401);
    }
    if (!in_array($_SESSION['role'], (array) $roles, true)) {
        json_out(['ok' => false, 'error' => 'Not allowed.'], 403);
    }
}

// CSRF check for POST requests sent by fetch(). The token comes in the form data.
function api_check_csrf()
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        json_out(['ok' => false, 'error' => 'Your session expired. Refresh the page and try again.'], 400);
    }
}

// Run a PHP template (e.g. includes/message_bubble.php) and return its HTML as a string,
// so the API sends exactly the same markup as the normal page.
function render_partial($file, $vars)
{
    extract($vars);
    ob_start();
    include $file;
    return ob_get_clean();
}
