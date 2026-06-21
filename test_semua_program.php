<?php
// ======================================================
// FILE: test_semua_program.php
// TEST UNTUK DEBUG
// ======================================================

require_once 'config/database.php';
require_once 'config/session.php';

echo "<h1>TEST HALAMAN PROGRAM</h1>";

// Cek koneksi
if ($conn) {
    echo "<p>✅ Koneksi database OK</p>";
} else {
    echo "<p>❌ Koneksi database GAGAL</p>";
    die();
}

// Cek login
$is_donatur_login = false;
if (isLoggedIn() && getUserRole() == 'donatur') {
    $is_donatur_login = true;
    echo "<p>✅ User login sebagai donatur</p>";
} else {
    echo "<p>ℹ️ User tidak login atau bukan donatur</p>";
}

// Query program
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM donasi_program WHERE program_id = p.id AND status = 'success') as jumlah_donatur,
        (SELECT SUM(nominal) FROM donasi_program WHERE program_id = p.id AND status = 'success') as total_terkumpul
        FROM program_donasi p 
        WHERE p.status = 'aktif' 
        ORDER BY p.created_at DESC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo "<p>❌ Query error: " . mysqli_error($conn) . "</p>";
    die();
}

$program_list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $program_list[] = $row;
}

echo "<p>✅ Jumlah program: " . count($program_list) . "</p>";

// Tampilkan program
echo "<h2>Daftar Program:</h2>";
echo "<ul>";
foreach ($program_list as $p) {
    echo "<li>" . $p['nama_program'] . " - Terkumpul: " . number_format($p['total_terkumpul'] ?? 0, 0, ',', '.') . "</li>";
}
echo "</ul>";

echo "<p>✅ Halaman test selesai!</p>";
?>