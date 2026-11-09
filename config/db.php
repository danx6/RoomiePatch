<?php
// Database connection settings.
// These are the XAMPP defaults: user "root" with no password.
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'roomiepatch';

// Make mysqli throw errors instead of failing silently.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Use Malaysia time (UTC+8) in both PHP and MySQL so timestamps match.
date_default_timezone_set('Asia/Kuala_Lumpur');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+08:00'");
