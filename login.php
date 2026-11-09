<?php
// Shared login page for students and staff/admin.
// The role comes from the database, so users never pick it themselves.

require_once 'includes/auth.php';
require_once 'includes/icons.php';

// Already logged in? Go straight to the right dashboard.
if (is_logged_in()) {
    redirect(dashboard_for($_SESSION['role']));
}

$error = '';
$login_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // IDs are stored in lower case, so "MC240930202" also works.
    $login_id = strtolower(trim($_POST['login_id'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($login_id === '' || $password === '') {
        $error = 'Please enter your ID and password.';
    } else {
        require_once 'config/db.php';

        $stmt = $conn->prepare('SELECT user_id, username, password, role FROM users WHERE login_id = ?');
        $stmt->bind_param('s', $login_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        // password_verify compares the typed password with the stored hash.
        if ($user && password_verify($password, $user['password'])) {
            // New session ID after login, so an old session ID cannot be reused.
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            redirect(dashboard_for($user['role']));
        }

        // Same message for a wrong ID or a wrong password, so we do not reveal which IDs exist.
        $error = 'Incorrect ID or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in - RoomiePatch</title>
    <?php include 'includes/theme_head.php'; ?>
    <link rel="icon" type="image/png" href="<?= asset('assets/img/logo.png') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body>
    <main class="login-page">
        <button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="Switch light or dark theme">
            <span class="icon-moon"><?= icon('moon') ?></span>
            <span class="icon-sun"><?= icon('sun') ?></span>
        </button>

        <div class="login-card">
            <span class="brand">
                <img class="brand-logo" src="<?= asset('assets/img/logo.png') ?>" alt="" width="48" height="48">
                <span>RoomiePatch</span>
            </span>

            <h1>Log in</h1>
            <p class="subtitle">Use your Matric ID or Staff ID.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" novalidate>
                <div class="form-group">
                    <label for="login_id">Matric ID / Staff ID</label>
                    <input class="input" type="text" id="login_id" name="login_id"
                           value="<?= e($login_id) ?>" autocomplete="username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input class="input" type="password" id="password" name="password"
                           autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Log in</button>
            </form>

            <p class="login-hint">Accounts are provided by the university. There is no sign-up.</p>
        </div>
    </main>

    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
