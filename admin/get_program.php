<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$id = (int)$_GET['id'];

$sql = "SELECT * FROM program_donasi WHERE id = $id";
$result = mysqli_query($conn, $sql);

if ($data = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false]);
}
?>