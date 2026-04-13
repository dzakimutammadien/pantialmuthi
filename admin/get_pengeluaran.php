<?php
// ======================================================
// FILE: admin/get_pengeluaran.php
// AMBIL DATA PENGELUARAN UNTUK AJAX
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
    exit();
}

$id = (int)$_GET['id'];

$sql = "SELECT p.*, u.nama_lengkap as pengasuh_nama, k.nama_kategori,
        v.nama_lengkap as verified_by_nama
        FROM pengeluaran p 
        JOIN users u ON p.created_by = u.id 
        JOIN kategori_donasi k ON p.kategori_id = k.id 
        LEFT JOIN users v ON p.verified_by = v.id 
        WHERE p.id = $id";

$result = mysqli_query($conn, $sql);

if ($data = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
}
?>