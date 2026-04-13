<?php
// ======================================================
// FILE: config/session.php
// MANAJEMEN SESSION & LOGIN CHECK
// ======================================================

session_start();

// Cek apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Cek role user
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

// Cek apakah user memiliki role tertentu
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Redirect jika tidak login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
}

// Redirect jika role tidak sesuai
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ../dashboard.php');
        exit();
    }
}

// Log aktivitas
function logActivity($user_id, $aktivitas) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $sql = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address) 
            VALUES ($user_id, '$aktivitas', '$ip')";
    mysqli_query($conn, $sql);
}

// Ambil data user yang sedang login
function getCurrentUser() {
    global $conn;
    if (!isLoggedIn()) return null;
    
    $user_id = $_SESSION['user_id'];
    $result = mysqli_query($conn, "SELECT u.*, r.nama_role FROM users u 
                                    JOIN roles r ON u.role_id = r.id 
                                    WHERE u.id = $user_id");
    return mysqli_fetch_assoc($result);
}
?>