<?php
// ======================================================
// FILE: semua_galeri_program.php
// HALAMAN SEMUA GALERI PROGRAM
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

// Ambil semua galeri
$sql_galeri = "SELECT * FROM galeri_program WHERE program_id = $program_id ORDER BY created_at DESC";
$galeri_list = query($sql_galeri);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Galeri - <?php echo $program['nama_program']; ?></title>
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
        .galeri-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .galeri-item { background: #f8f9fa; border-radius: 15px; overflow: hidden; cursor: pointer; transition: transform 0.3s; }
        .galeri-item:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .galeri-item img, .galeri-item video { width: 100%; height: 200px; object-fit: cover; }
        .galeri-info { padding: 12px; }
        .galeri-info h4 { font-size: 14px; margin-bottom: 5px; }
        .galeri-info p { font-size: 11px; color: #888; }
        .footer { text-align: center; margin-top: 40px; color: rgba(255,255,255,0.7); font-size: 12px; }
        @media (max-width: 768px) { .galeri-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <a href="program_detail.php?id=<?php echo $program_id; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Program</a>
    
    <div class="page-header">
        <h1><i class="fas fa-images"></i> Semua Galeri Kegiatan</h1>
        <p>Program: <?php echo $program['nama_program']; ?></p>
    </div>
    
    <div class="card">
        <div class="galeri-grid">
            <?php if (count($galeri_list) > 0): ?>
                <?php foreach ($galeri_list as $g): ?>
                    <div class="galeri-item" onclick="window.open('assets/uploads/galeri_program/<?php echo $g['file_path']; ?>', '_blank')">
                        <?php if ($g['tipe'] == 'foto'): ?>
                            <img src="assets/uploads/galeri_program/<?php echo $g['file_path']; ?>" alt="<?php echo $g['judul']; ?>">
                        <?php else: ?>
                            <video src="assets/uploads/galeri_program/<?php echo $g['file_path']; ?>"></video>
                        <?php endif; ?>
                        <div class="galeri-info">
                            <h4><?php echo htmlspecialchars($g['judul']); ?></h4>
                            <p><?php echo date('d/m/Y', strtotime($g['created_at'])); ?></p>
                            <?php if ($g['deskripsi']): ?>
                                <p><?php echo htmlspecialchars(substr($g['deskripsi'], 0, 80)) . (strlen($g['deskripsi']) > 80 ? '...' : ''); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; grid-column: 1/-1;">Belum ada galeri untuk program ini</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Panti Asuhan Al-Muthi | Lembaga Amil Zakat Nasional</p>
    </div>
</div>
</body>
</html>