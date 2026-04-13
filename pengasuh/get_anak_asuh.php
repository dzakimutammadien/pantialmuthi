<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$id = (int)$_GET['id'];

$sql = "SELECT a.*, u.nama_lengkap as pengasuh_nama
        FROM anak_asuh a 
        JOIN users u ON a.created_by = u.id 
        WHERE a.id = $id";

$result = mysqli_query($conn, $sql);

if ($data = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false]);
}
?>