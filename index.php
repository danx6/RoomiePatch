<?php
// Entry point: logged-in users go to their dashboard, everyone else to the login page.

require_once 'includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_for($_SESSION['role']));
}

redirect('login.php');
