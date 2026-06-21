<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$id = (int)$_GET['id'];

$sql = "SELECT dp.*, p.nama_program, u.nama_lengkap as verified_by_nama
        FROM donasi_program dp 
        JOIN program_donasi p ON dp.program_id = p.id 
        LEFT JOIN users u ON dp.verified_by = u.id 
        WHERE dp.id = $id";
$result = mysqli_query($conn, $sql);

if ($data = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false]);
}
?>