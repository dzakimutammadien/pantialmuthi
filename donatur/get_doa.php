<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

$sql = "SELECT d.*, u.nama_lengkap as nama_lengkap, v.nama_lengkap as dibaca_oleh_nama 
        FROM doa d 
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN users v ON d.dibaca_oleh = v.id 
        WHERE d.id = $id AND d.user_id = $user_id";

$result = mysqli_query($conn, $sql);

if ($data = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false]);
}
?>