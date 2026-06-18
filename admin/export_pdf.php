<?php
// ======================================================
// FILE: admin/export_pdf.php
// EXPORT LAPORAN KEUANGAN KE PDF
// DENGAN DONASI PROGRAM & PENYALURAN
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');

// Load Dompdf
require_once '../vendor/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

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
// RINCIAN TRANSAKSI
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

// ======================================================
// HTML untuk PDF
// ======================================================
$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Keuangan</title>
    <style>
        body { font-family: Arial, sans-serif; }
        h2 { text-align: center; color: #2e8b57; margin-bottom: 5px; }
        .periode { text-align: center; margin-bottom: 20px; font-size: 14px; }
        h3 { background: #f0f2f5; padding: 8px; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary-table { width: 70%; margin: 0 auto; }
        .label-pemasukan { color: #4caf50; font-weight: bold; }
        .label-pengeluaran { color: #f44336; font-weight: bold; }
        .label-program { color: #9c27b0; font-weight: bold; }
        .label-penyaluran { color: #ff9800; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 11px; color: #888; border-top: 1px solid #ddd; padding-top: 15px; }
        .saldo-positif { color: #2196f3; font-weight: bold; }
    </style>
</head>
<body>
    <h2>LAPORAN KEUANGAN</h2>
    <h2>PANTI ASUHAN AL-MUTHI</h2>
    <div class="periode">
        Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)) . '
    </div>
    
    <!-- RINGKASAN PEMASUKAN -->
    <h3>A. RINGKASAN PEMASUKAN</h3>
    <table class="summary-table">
        <tr><th style="width:60%;">Jenis Pemasukan</th><th style="width:40%;" class="text-right">Jumlah</th></tr>
        <tr><td>Donasi Biasa</td><td class="text-right">Rp ' . number_format($total_pemasukan_biasa, 0, ',', '.') . '</td></tr>
        <tr><td>Donasi Program</td><td class="text-right">Rp ' . number_format($total_pemasukan_program, 0, ',', '.') . '</td></tr>
        <tr style="font-weight:bold;"><td>TOTAL PEMASUKAN</td><td class="text-right">Rp ' . number_format($total_pemasukan, 0, ',', '.') . '</td></tr>
    </table>
    
    <!-- RINGKASAN PENGELUARAN -->
    <h3>B. RINGKASAN PENGELUARAN</h3>
    <table class="summary-table">
        <tr><th style="width:60%;">Jenis Pengeluaran</th><th style="width:40%;" class="text-right">Jumlah</th></tr>
        <tr><td>Pengeluaran Operasional</td><td class="text-right">Rp ' . number_format($total_pengeluaran, 0, ',', '.') . '</td></tr>
        <tr><td>Penyaluran Program</td><td class="text-right">Rp ' . number_format($total_penyaluran, 0, ',', '.') . '</td></tr>
        <tr style="font-weight:bold;"><td>TOTAL PENGELUARAN</td><td class="text-right">Rp ' . number_format($total_pengeluaran_all, 0, ',', '.') . '</td></tr>
    </table>
    
    <!-- SALDO -->
    <h3>C. SALDO PANTI</h3>
    <table class="summary-table">
        <tr><th style="width:60%;">Keterangan</th><th style="width:40%;" class="text-right">Jumlah</th></tr>
        <tr><td>Total Pemasukan</td><td class="text-right">Rp ' . number_format($total_pemasukan, 0, ',', '.') . '</td></tr>
        <tr><td>Total Pengeluaran</td><td class="text-right">Rp ' . number_format($total_pengeluaran_all, 0, ',', '.') . '</td></tr>
        <tr style="font-weight:bold; font-size:14px;"><td>SALDO PANTI</td><td class="text-right saldo-positif">Rp ' . number_format($saldo, 0, ',', '.') . '</td></tr>
    </table>
    
    <!-- RINCIAN TRANSAKSI -->
    <h3>D. RINCIAN TRANSAKSI</h3>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Tipe</th>
                <th>Nama</th>
                <th>Kategori</th>
                <th>Keterangan</th>
                <th class="text-right">Masuk</th>
                <th class="text-right">Keluar</th>
            </tr>
        </thead>
        <tbody>';

foreach ($rincian as $r) {
    $tipeLabel = '';
    $tipeClass = '';
    if ($r['jenis'] == 'Pemasukan') {
        if ($r['tipe'] == 'program') {
            $tipeLabel = 'Program';
            $tipeClass = 'label-program';
        } else {
            $tipeLabel = 'Biasa';
            $tipeClass = 'label-pemasukan';
        }
    } else {
        if ($r['tipe'] == 'penyaluran') {
            $tipeLabel = 'Penyaluran';
            $tipeClass = 'label-penyaluran';
        } else {
            $tipeLabel = 'Operasional';
            $tipeClass = 'label-pengeluaran';
        }
    }
    
    $html .= '
            <tr>
                <td>' . date('d/m/Y', strtotime($r['tanggal'])) . '</td>
                <td><span class="' . $tipeClass . '">' . $tipeLabel . '</span></td>
                <td>' . htmlspecialchars($r['nama']) . '</td>
                <td>' . htmlspecialchars($r['kategori']) . '</td>
                <td>' . (htmlspecialchars($r['keterangan']) ?: '-') . '</td>
                <td class="text-right">' . ($r['jenis'] == 'Pemasukan' ? 'Rp ' . number_format($r['jumlah'], 0, ',', '.') : '-') . '</td>
                <td class="text-right">' . ($r['jenis'] == 'Pengeluaran' ? 'Rp ' . number_format($r['keluar'], 0, ',', '.') : '-') . '</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>
    
    <div class="footer">
        Dicetak pada: ' . date('d/m/Y H:i:s') . '<br>
        Diterbitkan oleh: ' . ($_SESSION['nama_lengkap'] ?? 'Admin') . '
    </div>
</body>
</html>';

// Generate PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Laporan_Keuangan_" . date('Y-m-d') . ".pdf", array("Attachment" => true));
?>