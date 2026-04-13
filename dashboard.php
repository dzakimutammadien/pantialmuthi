<?php
// ======================================================
// FILE: dashboard.php
// REDIRECT KE DASHBOARD SESUAI ROLE
// ======================================================

require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$role = getUserRole();

// Redirect berdasarkan role
switch ($role) {
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    case 'pengasuh':
        header('Location: pengasuh/dashboard.php');
        break;
    case 'donatur':
        header('Location: donatur/dashboard.php');
        break;
    default:
        header('Location: login.php');
        break;
}
exit();
?>