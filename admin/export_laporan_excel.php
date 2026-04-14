<?php
// ======================================================
// FILE: admin/export_laporan_excel.php
// EXPORT LAPORAN KEUANGAN KE EXCEL
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');

// Ambil parameter filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Data pemasukan
$sql_pemasukan = "SELECT SUM(nominal) as total FROM donasi 
                  WHERE status = 'success' 
                  AND DATE(tanggal_donasi) BETWEEN '$start_date' AND '$end_date'";
$result_pemasukan = mysqli_query($conn, $sql_pemasukan);
$total_pemasukan = mysqli_fetch_assoc($result_pemasukan)['total'] ?? 0;

// Data pengeluaran
$sql_pengeluaran = "SELECT SUM(nominal) as total FROM pengeluaran 
                    WHERE status = 'disetujui' 
                    AND DATE(tanggal_pengeluaran) BETWEEN '$start_date' AND '$end_date'";
$result_pengeluaran = mysqli_query($conn, $sql_pengeluaran);
$total_pengeluaran = mysqli_fetch_assoc($result_pengeluaran)['total'] ?? 0;

$saldo = $total_pemasukan - $total_pengeluaran;

// Rincian transaksi
$sql_rincian = "
    (SELECT 
        tanggal_donasi as tanggal,
        'Pemasukan' as jenis,
        u.nama_lengkap as nama,
        k.nama_kategori as kategori,
        d.keterangan as keterangan,
        d.nominal as jumlah,
        0 as keluar
    FROM donasi d
    JOIN users u ON d.user_id = u.id
    JOIN kategori_donasi k ON d.kategori_id = k.id
    WHERE d.status = 'success' 
    AND DATE(d.tanggal_donasi) BETWEEN '$start_date' AND '$end_date')
    
    UNION ALL
    
    (SELECT 
        p.tanggal_pengeluaran as tanggal,
        'Pengeluaran' as jenis,
        u.nama_lengkap as nama,
        k.nama_kategori as kategori,
        p.deskripsi as keterangan,
        0 as masuk,
        p.nominal as keluar
    FROM pengeluaran p
    JOIN users u ON p.created_by = u.id
    JOIN kategori_donasi k ON p.kategori_id = k.id
    WHERE p.status = 'disetujui' 
    AND DATE(p.tanggal_pengeluaran) BETWEEN '$start_date' AND '$end_date')
    
    ORDER BY tanggal DESC";
$rincian = query($sql_rincian);

// Header Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_Keuangan_' . date('Y-m-d') . '.xls"');

echo "<h2>LAPORAN KEUANGAN PANTI ASUHAN AL-MUTHI</h2>";
echo "<p>Periode: " . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . "</p>";
echo "<hr>";

echo "<h3>RINGKASAN</h3>";
echo "<table border='1'>";
echo "<tr><th>Pemasukan</th><th>Pengeluaran</th><th>Saldo</th></tr>";
echo "<tr>";
echo "<td>Rp " . number_format($total_pemasukan, 0, ',', '.') . "</td>";
echo "<td>Rp " . number_format($total_pengeluaran, 0, ',', '.') . "</td>";
echo "<td>Rp " . number_format($saldo, 0, ',', '.') . "</td>";
echo "</tr>";
echo "</table><br>";

echo "<h3>RINCIAN TRANSAKSI</h3>";
echo "<table border='1'>";
echo "<tr><th>Tanggal</th><th>Nama</th><th>Kategori</th><th>Keterangan</th><th>Masuk</th><th>Keluar</th></tr>";
foreach ($rincian as $r) {
    echo "<tr>";
    echo "<td>" . date('d/m/Y', strtotime($r['tanggal'])) . "</td>";
    echo "<td>" . $r['nama'] . "</td>";
    echo "<td>" . $r['kategori'] . "</td>";
    echo "<td>" . ($r['keterangan'] ?: '-') . "</td>";
    echo "<td>" . ($r['jenis'] == 'Pemasukan' ? 'Rp ' . number_format($r['jumlah'], 0, ',', '.') : '-') . "</td>";
    echo "<td>" . ($r['jenis'] == 'Pengeluaran' ? 'Rp ' . number_format($r['keluar'], 0, ',', '.') : '-') . "</td>";
    echo "</tr>";
}
echo "</table>";
?>