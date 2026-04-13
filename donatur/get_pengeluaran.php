<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$id = (int)$_GET['id'];

$sql = "SELECT p.*, u.nama_lengkap as pengasuh_nama, k.nama_kategori
        FROM pengeluaran p 
        JOIN users u ON p.created_by = u.id 
        JOIN kategori_donasi k ON p.kategori_id = k.id 
        WHERE p.id = $id AND p.status = 'disetujui'";

$result = mysqli_query($conn, $sql);

if ($data = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false]);
}
?>