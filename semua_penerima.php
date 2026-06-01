<?php
// ======================================================
// FILE: semua_penerima.php
// HALAMAN SEMUA PENERIMA MANFAAT PER PROGRAM
// ======================================================

require_once 'config/database.php';
require_once 'config/session.php';

$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;

// Ambil data program
$sql_program = "SELECT * FROM program_donasi WHERE id = $program_id";
$program = query($sql_program);
if (count($program) == 0) {
    header("Location: login.php");
    exit();
}
$program = $program[0];

// Ambil semua penerima manfaat
$sql_penerima = "SELECT * FROM penerima_manfaat WHERE program_id = $program_id ORDER BY tanggal_penyaluran DESC";
$penerima_list = query($sql_penerima);

// Hitung total tersalurkan
$total_tersalurkan = 0;
foreach ($penerima_list as $p) {
    $total_tersalurkan += $p['jumlah'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Penerima Manfaat - <?php echo $program['nama_program']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%); min-height: 100vh; padding: 40px 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .btn-back { display: inline-block; background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 25px; text-decoration: none; margin-bottom: 20px; transition: 0.3s; }
        .btn-back:hover { background: rgba(255,255,255,0.3); }
        .page-header { text-align: center; margin-bottom: 30px; color: white; }
        .page-header h1 { font-size: 28px; margin-bottom: 10px; }
        .page-header p { font-size: 14px; opacity: 0.9; }
        .card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .total-info { background: #f8f9fa; border-radius: 15px; padding: 15px; margin-bottom: 20px; text-align: right; font-weight: bold; }
        .penerima-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .penerima-card { background: #f8f9fa; border-radius: 15px; padding: 15px; text-align: center; transition: transform 0.3s; }
        .penerima-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .penerima-card img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; }
        .penerima-card i { font-size: 80px; color: #ccc; margin-bottom: 10px; }
        .penerima-card h4 { font-size: 16px; margin-bottom: 5px; }
        .penerima-card p { font-size: 13px; color: #666; margin-bottom: 5px; }
        .jumlah { font-weight: bold; color: #50c878; font-size: 18px; margin-top: 10px; }
        .footer { text-align: center; margin-top: 40px; color: rgba(255,255,255,0.7); font-size: 12px; }
        @media (max-width: 768px) { .penerima-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <a href="program_detail.php?id=<?php echo $program_id; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Program</a>
    
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Semua Penerima Manfaat</h1>
        <p>Program: <?php echo $program['nama_program']; ?></p>
    </div>
    
    <div class="card">
        <div class="total-info">
            Total Dana Tersalurkan: <strong>Rp <?php echo number_format($total_tersalurkan, 0, ',', '.'); ?></strong>
        </div>
        
        <div class="penerima-grid">
            <?php if (count($penerima_list) > 0): ?>
                <?php foreach ($penerima_list as $p): ?>
                    <div class="penerima-card">
                        <?php if ($p['foto'] && file_exists('assets/uploads/penerima/' . $p['foto'])): ?>
                            <img src="assets/uploads/penerima/<?php echo $p['foto']; ?>" alt="<?php echo $p['nama_penerima']; ?>">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                        <h4><?php echo htmlspecialchars($p['nama_penerima']); ?></h4>
                        <p><?php echo htmlspecialchars($p['jenis_bantuan']); ?></p>
                        <p><?php echo date('d/m/Y', strtotime($p['tanggal_penyaluran'])); ?></p>
                        <div class="jumlah">Rp <?php echo number_format($p['jumlah'], 0, ',', '.'); ?></div>
                        <?php if ($p['keterangan']): ?>
                            <p style="font-size: 11px; color: #888; margin-top: 5px;"><?php echo htmlspecialchars($p['keterangan']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; grid-column: 1/-1;">Belum ada penerima manfaat</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Panti Asuhan Al-Muthi | Lembaga Amil Zakat Nasional</p>
    </div>
</div>
</body>
</html>