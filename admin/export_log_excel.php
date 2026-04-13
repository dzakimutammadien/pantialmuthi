<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');

// Ambil parameter filter sama seperti di log_aktivitas.php
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_user = isset($_GET['user']) ? (int)$_GET['user'] : '';
$filter_periode = isset($_GET['periode']) ? mysqli_real_escape_string($conn, $_GET['periode']) : '';
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? mysqli_real_escape_string($conn, $_GET['tanggal_mulai']) : '';
$tanggal_selesai = isset($_GET['tanggal_selesai']) ? mysqli_real_escape_string($conn, $_GET['tanggal_selesai']) : '';

$where = "WHERE 1=1";

if ($search != '') {
    $where .= " AND (l.aktivitas LIKE '%$search%' OR u.nama_lengkap LIKE '%$search%' OR u.username LIKE '%$search%')";
}
if ($filter_user != '' && $filter_user > 0) {
    $where .= " AND l.user_id = $filter_user";
}
if ($filter_periode != '' && $filter_periode != 'custom') {
    switch ($filter_periode) {
        case 'hari_ini':
            $where .= " AND DATE(l.created_at) = CURDATE()";
            break;
        case 'minggu_ini':
            $where .= " AND YEARWEEK(l.created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'bulan_ini':
            $where .= " AND MONTH(l.created_at) = MONTH(CURDATE()) AND YEAR(l.created_at) = YEAR(CURDATE())";
            break;
        case 'bulan_lalu':
            $where .= " AND MONTH(l.created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(l.created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
            break;
    }
} elseif ($filter_periode == 'custom' && $tanggal_mulai != '' && $tanggal_selesai != '') {
    $where .= " AND DATE(l.created_at) BETWEEN '$tanggal_mulai' AND '$tanggal_selesai'";
}

$sql = "SELECT 
            DATE_FORMAT(l.created_at, '%d/%m/%Y') as tanggal,
            DATE_FORMAT(l.created_at, '%H:%i:%s') as waktu,
            u.nama_lengkap as user,
            r.nama_role as role,
            l.aktivitas,
            l.ip_address
        FROM log_aktivitas l 
        JOIN users u ON l.user_id = u.id 
        JOIN roles r ON u.role_id = r.id
        $where 
        ORDER BY l.created_at DESC";

$result = mysqli_query($conn, $sql);

// Set header untuk Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="log_aktivitas_' . date('Y-m-d') . '.xls"');

echo "<table border='1'>";
echo "<tr>
        <th>No</th>
        <th>Tanggal</th>
        <th>Waktu</th>
        <th>User</th>
        <th>Role</th>
        <th>Aktivitas</th>
        <th>IP Address</th>
      </tr>";

$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $no++ . "</td>";
    echo "<td>" . $row['tanggal'] . "</td>";
    echo "<td>" . $row['waktu'] . "</td>";
    echo "<td>" . $row['user'] . "</td>";
    echo "<td>" . $row['role'] . "</td>";
    echo "<td>" . $row['aktivitas'] . "</td>";
    echo "<td>" . ($row['ip_address'] ?: '-') . "</td>";
    echo "</tr>";
}
echo "</table>";
?>