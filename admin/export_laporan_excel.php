<?php
// ======================================================
// FILE: admin/export_laporan_excel.php
// EXPORT LAPORAN KEUANGAN KE EXCEL
// DENGAN DONASI PROGRAM & PENYALURAN
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');

// Ambil parameter filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// ======================================================
// PEMASUKAN: Donasi Biasa + Donasi Program
// ======================================================
$sql_pemasukan_biasa = "SELECT SUM(nominal) as total FROM donasi 
                        WHERE status = 'success' 
                        AND DATE(tanggal_donasi) BETWEEN '$start_date' AND '$end_date'";
$result_pemasukan_biasa = mysqli_query($conn, $sql_pemasukan_biasa);
$total_pemasukan_biasa = mysqli_fetch_assoc($result_pemasukan_biasa)['total'] ?? 0;

$sql_pemasukan_program = "SELECT SUM(nominal) as total FROM donasi_program 
                          WHERE status = 'success' 
                          AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
$result_pemasukan_program = mysqli_query($conn, $sql_pemasukan_program);
$total_pemasukan_program = mysqli_fetch_assoc($result_pemasukan_program)['total'] ?? 0;

$total_pemasukan = $total_pemasukan_biasa + $total_pemasukan_program;

// ======================================================
// PENGELUARAN: Operasional + Penyaluran
// ======================================================
$sql_pengeluaran = "SELECT SUM(nominal) as total FROM pengeluaran 
                    WHERE status = 'disetujui' 
                    AND DATE(tanggal_pengeluaran) BETWEEN '$start_date' AND '$end_date'";
$result_pengeluaran = mysqli_query($conn, $sql_pengeluaran);
$total_pengeluaran = mysqli_fetch_assoc($result_pengeluaran)['total'] ?? 0;

$sql_penyaluran = "SELECT SUM(jumlah) as total FROM penerima_manfaat 
                   WHERE DATE(tanggal_penyaluran) BETWEEN '$start_date' AND '$end_date'";
$result_penyaluran = mysqli_query($conn, $sql_penyaluran);
$total_penyaluran = mysqli_fetch_assoc($result_penyaluran)['total'] ?? 0;

$total_pengeluaran_all = $total_pengeluaran + $total_penyaluran;

// Saldo
$saldo = $total_pemasukan - $total_pengeluaran_all;

// ======================================================
// RINCIAN TRANSAKSI (Donasi Biasa + Program + Pengeluaran + Penyaluran)
// ======================================================
$sql_rincian = "
    (SELECT 
        tanggal_donasi as tanggal,
        'Pemasukan' as jenis,
        'biasa' as tipe,
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
        dp.created_at as tanggal,
        'Pemasukan' as jenis,
        'program' as tipe,
        u.nama_lengkap as nama,
        p.nama_program as kategori,
        dp.pesan as keterangan,
        dp.nominal as jumlah,
        0 as keluar
    FROM donasi_program dp
    JOIN users u ON dp.user_id = u.id
    JOIN program_donasi p ON dp.program_id = p.id
    WHERE dp.status = 'success' 
    AND DATE(dp.created_at) BETWEEN '$start_date' AND '$end_date')
    
    UNION ALL
    
    (SELECT 
        p.tanggal_pengeluaran as tanggal,
        'Pengeluaran' as jenis,
        'operasional' as tipe,
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
    
    UNION ALL
    
    (SELECT 
        pm.tanggal_penyaluran as tanggal,
        'Pengeluaran' as jenis,
        'penyaluran' as tipe,
        'Penyaluran Program' as nama,
        CONCAT('Penyaluran - ', pm.jenis_bantuan) as kategori,
        CONCAT('Penerima: ', pm.nama_penerima, IF(pm.keterangan != '', CONCAT(' | ', pm.keterangan), '')) as keterangan,
        0 as masuk,
        pm.jumlah as keluar
    FROM penerima_manfaat pm
    WHERE DATE(pm.tanggal_penyaluran) BETWEEN '$start_date' AND '$end_date')
    
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
echo "<tr><th>Pemasukan Biasa</th><th>Pemasukan Program</th><th>Total Pemasukan</th></tr>";
echo "<tr>";
echo "<td>Rp " . number_format($total_pemasukan_biasa, 0, ',', '.') . "</td>";
echo "<td>Rp " . number_format($total_pemasukan_program, 0, ',', '.') . "</td>";
echo "<td><strong>Rp " . number_format($total_pemasukan, 0, ',', '.') . "</strong></td>";
echo "</tr>";
echo "</table><br>";

echo "<table border='1'>";
echo "<tr><th>Pengeluaran Operasional</th><th>Penyaluran Program</th><th>Total Pengeluaran</th></tr>";
echo "<tr>";
echo "<td>Rp " . number_format($total_pengeluaran, 0, ',', '.') . "</td>";
echo "<td>Rp " . number_format($total_penyaluran, 0, ',', '.') . "</td>";
echo "<td><strong>Rp " . number_format($total_pengeluaran_all, 0, ',', '.') . "</strong></td>";
echo "</tr>";
echo "</table><br>";

echo "<table border='1'>";
echo "<tr><th>Total Pemasukan</th><th>Total Pengeluaran</th><th>Saldo Panti</th></tr>";
echo "<tr>";
echo "<td>Rp " . number_format($total_pemasukan, 0, ',', '.') . "</td>";
echo "<td>Rp " . number_format($total_pengeluaran_all, 0, ',', '.') . "</td>";
echo "<td><strong>Rp " . number_format($saldo, 0, ',', '.') . "</strong></td>";
echo "</tr>";
echo "</table><br>";

echo "<h3>RINCIAN TRANSAKSI</h3>";
echo "<table border='1'>";
echo "<tr><th>Tanggal</th><th>Tipe</th><th>Nama</th><th>Kategori</th><th>Keterangan</th><th>Masuk</th><th>Keluar</th></tr>";
foreach ($rincian as $r) {
    $tipeLabel = '';
    if ($r['jenis'] == 'Pemasukan') {
        $tipeLabel = ($r['tipe'] == 'program') ? 'Program' : 'Biasa';
    } else {
        $tipeLabel = ($r['tipe'] == 'penyaluran') ? 'Penyaluran' : 'Operasional';
    }
    
    echo "<tr>";
    echo "<td>" . date('d/m/Y', strtotime($r['tanggal'])) . "</td>";
    echo "<td>" . $tipeLabel . "</td>";
    echo "<td>" . $r['nama'] . "</td>";
    echo "<td>" . $r['kategori'] . "</td>";
    echo "<td>" . ($r['keterangan'] ?: '-') . "</td>";
    echo "<td>" . ($r['jenis'] == 'Pemasukan' ? 'Rp ' . number_format($r['jumlah'], 0, ',', '.') : '-') . "</td>";
    echo "<td>" . ($r['jenis'] == 'Pengeluaran' ? 'Rp ' . number_format($r['keluar'], 0, ',', '.') : '-') . "</td>";
    echo "</tr>";
}
echo "</table>";
?>