<?php
// ======================================================
// FILE: logout.php
// PROSES LOGOUT
// ======================================================

require_once 'config/database.php';
require_once 'config/session.php';

if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'Logout dari sistem');
}

// Hapus semua session
$_SESSION = array();
session_destroy();

// Redirect ke login
header('Location: login.php');
exit();
?>