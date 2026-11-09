<?php
// Log the user out by clearing and destroying the session.

require_once 'includes/auth.php';

$_SESSION = [];

// Also delete the session cookie in the browser.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

redirect('login.php');
