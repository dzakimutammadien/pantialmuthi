<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    echo json_encode(['success' => false]);
    exit();
}

$id = (int)$_GET['id'];
$type = $_GET['type'];

if ($type == 'kesehatan') {
    $sql = "SELECT id, tanggal, berat_badan, tinggi_badan, keterangan FROM perkembangan_kesehatan WHERE id = $id";
} else {
    $sql = "SELECT id, semester, tahun_ajaran, rata_rata, predikat, prestasi FROM perkembangan_pendidikan WHERE id = $id";
}

$result = mysqli_query($conn, $sql);

if ($data = mysqli_fetch_assoc($result)) {
    echo json_encode($data);
} else {
    echo json_encode(['success' => false]);
}
?>