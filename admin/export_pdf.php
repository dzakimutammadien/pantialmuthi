<?php
// ======================================================
// FILE: admin/export_pdf.php
// EXPORT LAPORAN KEUANGAN KE PDF
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

// HTML untuk PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Keuangan</title>
    <style>
        body { font-family: Arial, sans-serif; }
        h2 { text-align: center; color: #2e8b57; }
        .periode { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        .summary-table { width: 50%; margin: 20px auto; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; }
        .badge-masuk { color: #4caf50; font-weight: bold; }
        .badge-keluar { color: #f44336; font-weight: bold; }
    </style>
</head>
<body>
    <h2>LAPORAN KEUANGAN</h2>
    <h2>PANTI ASUHAN AL-MUTHI</h2>
    <div class="periode">
        Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)) . '
    </div>
    
    <h3>RINGKASAN</h3>
    <table class="summary-table">
        <tr><th>Pemasukan</th><td>Rp ' . number_format($total_pemasukan, 0, ',', '.') . '</td></tr>
        <tr><th>Pengeluaran</th><td>Rp ' . number_format($total_pengeluaran, 0, ',', '.') . '</td></tr>
        <tr><th>Saldo</th><td>Rp ' . number_format($saldo, 0, ',', '.') . '</td></tr>
    </table>
    
    <h3>RINCIAN TRANSAKSI</h3>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Nama</th>
                <th>Kategori</th>
                <th>Keterangan</th>
                <th class="text-right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>';

foreach ($rincian as $r) {
    $html .= '
            <tr>
                <td>' . date('d/m/Y', strtotime($r['tanggal'])) . '</td>
                <td>' . $r['jenis'] . '</td>
                <td>' . htmlspecialchars($r['nama']) . '</td>
                <td>' . htmlspecialchars($r['kategori']) . '</td>
                <td>' . (htmlspecialchars($r['keterangan']) ?: '-') . '</td>
                <td class="text-right">Rp ' . number_format(($r['jenis'] == 'Pemasukan' ? $r['jumlah'] : $r['keluar']), 0, ',', '.') . '</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>
    
    <div class="footer">
        Dicetak pada: ' . date('d/m/Y H:i:s') . '<br>
        Diterbitkan oleh: ' . $_SESSION['nama_lengkap'] . '
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