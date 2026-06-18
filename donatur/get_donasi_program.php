<?php
// ======================================================
// FILE: donatur/get_donasi_program.php
// AMBIL DATA DONASI PROGRAM UNTUK DETAIL
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false]);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

$sql = "SELECT dp.*, p.nama_program 
        FROM donasi_program dp 
        JOIN program_donasi p ON dp.program_id = p.id 
        WHERE dp.id = $id AND dp.user_id = $user_id";

$result = mysqli_query($conn, $sql);

if ($data = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false]);
}
?>