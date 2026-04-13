<?php
// ======================================================
// FILE: config/database.php
// KONEKSI DATABASE
// ======================================================

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'pantialmuthi';

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Set charset ke UTF-8
mysqli_set_charset($conn, "utf8mb4");

// Fungsi helper untuk query
function query($sql) {
    global $conn;
    $result = mysqli_query($conn, $sql);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function insert($sql) {
    global $conn;
    mysqli_query($conn, $sql);
    return mysqli_insert_id($conn);
}

function update($sql) {
    global $conn;
    mysqli_query($conn, $sql);
    return mysqli_affected_rows($conn);
}

function delete($sql) {
    global $conn;
    mysqli_query($conn, $sql);
    return mysqli_affected_rows($conn);
}
?>