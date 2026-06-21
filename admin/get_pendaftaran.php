<?php
// ======================================================
// FILE: admin/get_pendaftaran.php
// API GET DATA PENDAFTARAN
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $sql = "SELECT p.*, u.nama_lengkap as approved_by_nama
            FROM pendaftaran p
            LEFT JOIN users u ON p.approved_by = u.id
            WHERE p.id = $id";
    
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
}
?>