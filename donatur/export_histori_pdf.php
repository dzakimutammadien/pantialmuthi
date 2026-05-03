<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('donatur');

require_once '../vendor/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$user_id = $_SESSION['user_id'];

$sql = "SELECT d.*, k.nama_kategori 
        FROM donasi d 
        JOIN kategori_donasi k ON d.kategori_id = k.id 
        WHERE d.user_id = $user_id 
        ORDER BY d.tanggal_donasi DESC";
$donasiList = query($sql);

$total_donasi = 0;
foreach ($donasiList as $d) {
    if ($d['status'] == 'success') {
        $total_donasi += $d['nominal'];
    }
}

$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Riwayat Donasi Saya</title>
    <style>
        body { font-family: Arial, sans-serif; }
        h2 { text-align: center; color: #2e8b57; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        .total { margin-top: 20px; text-align: right; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <h2>RIWAYAT DONASI</h2>
    <h2>' . $_SESSION['nama_lengkap'] . '</h2>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Nominal</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';

foreach ($donasiList as $d) {
    $status = $d['status'] == 'success' ? 'Sukses' : ($d['status'] == 'pending' ? 'Menunggu' : 'Tidak Valid');
    $html .= '
            <tr>
                <td>' . date('d/m/Y', strtotime($d['tanggal_donasi'])) . '</td>
                <td>' . $d['nama_kategori'] . '</td>
                <td class="text-right">Rp ' . number_format($d['nominal'], 0, ',', '.') . '</td>
                <td>' . $status . '</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>
    <div class="total">
        Total Donasi Sukses: Rp ' . number_format($total_donasi, 0, ',', '.') . '
    </div>
    <div class="footer">
        Dicetak pada: ' . date('d/m/Y H:i:s') . '
    </div>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Riwayat_Donasi_" . date('Y-m-d') . ".pdf", array("Attachment" => true));
?>