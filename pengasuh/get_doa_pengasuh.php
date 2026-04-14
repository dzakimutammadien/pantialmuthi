<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$id = (int)$_GET['id'];

$sql = "SELECT d.*, 
        donatur.nama_lengkap as donatur_nama, 
        donatur.username as donatur_username,
        CASE 
            WHEN d.donasi_id IS NULL THEN 'Titip Doa Manual'
            ELSE CONCAT('Donasi ID: ', d.donasi_id)
        END as sumber
        FROM doa d 
        JOIN users donatur ON d.user_id = donatur.id 
        WHERE d.id = $id";

$result = mysqli_query($conn, $sql);

if ($data = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false]);
}
?>