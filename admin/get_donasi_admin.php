<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$id = (int)$_GET['id'];

$sql = "SELECT d.*, u.nama_lengkap, u.username, k.nama_kategori, v.nama_lengkap as verified_by_nama
        FROM donasi d 
        JOIN users u ON d.user_id = u.id 
        JOIN kategori_donasi k ON d.kategori_id = k.id 
        LEFT JOIN users v ON d.verified_by = v.id 
        WHERE d.id = $id";

$result = mysqli_query($conn, $sql);

if ($data = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false]);
}
?>