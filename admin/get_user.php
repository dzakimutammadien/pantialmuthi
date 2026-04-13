<?php
// ======================================================
// FILE: admin/get_user.php
// AMBIL DATA USER UNTUK AJAX (DETAIL & EDIT)
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';

// Pastikan user sudah login
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $sql = "SELECT u.*, r.nama_role 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.id = $id";
    $result = mysqli_query($conn, $sql);
    
    if ($user = mysqli_fetch_assoc($result)) {
        // Hapus password dari response
        unset($user['password']);
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
}
?>