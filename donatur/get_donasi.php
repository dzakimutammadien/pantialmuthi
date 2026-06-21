<?php
// ======================================================
// FILE: donatur/get_donasi.php
// API GET DATA DONASI UNTUK DONATUR
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('donatur');

$currentUser = getCurrentUser();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // Cek kepemilikan donasi
    $sql = "SELECT d.*, k.nama_kategori 
            FROM donasi d 
            JOIN kategori_donasi k ON d.kategori_id = k.id 
            WHERE d.id = $id AND d.user_id = " . $currentUser['id'];
    
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Donasi tidak ditemukan']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
}
?>