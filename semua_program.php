<?php
// ======================================================
// FILE: semua_program.php
// HALAMAN SEMUA PROGRAM (CROWDFUNDING)
// ======================================================

require_once 'config/database.php';

// Ambil semua program aktif
$sql = "SELECT * FROM program_donasi WHERE status = 'aktif' ORDER BY created_at DESC";
$program_list = query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Program - Panti Asuhan Al-Muthi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .btn-back {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            margin-bottom: 25px;
            transition: 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }
        
        .page-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .page-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .program-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }
        
        .program-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .program-card:hover {
            transform: translateY(-5px);
        }
        
        .program-image {
            height: 180px;
            overflow: hidden;
        }
        
        .program-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .program-info {
            padding: 20px;
        }
        
        .program-info h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #1a3a2a;
        }
        
        .program-info p {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .progress-fill {
            background: #50c878;
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        
        .progress-stats {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #666;
            margin-bottom: 12px;
        }
        
        .donatur-count {
            font-size: 11px;
            color: #888;
            margin-bottom: 15px;
        }
        
        .btn-donasi {
            display: inline-block;
            background: #50c878;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            text-align: center;
            width: 100%;
            transition: 0.3s;
        }
        
        .btn-donasi:hover {
            background: #2e8b57;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            color: rgba(255,255,255,0.7);
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .program-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <a href="login.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
    
    <div class="page-header">
        <h1><i class="fas fa-chalkboard-user"></i> Semua Program</h1>
        <p>Donasi untuk program-program panti asuhan Al-Muthi</p>
    </div>
    
    <div class="program-grid">
        <?php if (count($program_list) > 0): ?>
            <?php foreach ($program_list as $program):
                $terkumpul = $program['terkumpul'];
                $target = $program['target_nominal'];
                $persen = ($target > 0) ? round(($terkumpul / $target) * 100) : 0;
                $persen = min($persen, 100);
            ?>
                <div class="program-card">
                    <div class="program-image">
                        <?php if ($program['gambar'] && file_exists('assets/uploads/program/' . $program['gambar'])): ?>
                            <img src="assets/uploads/program/<?php echo $program['gambar']; ?>" alt="<?php echo $program['nama_program']; ?>">
                        <?php else: ?>
                            <div style="height: 100%; background: linear-gradient(135deg, #50c878, #2e8b57); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-hand-holding-heart" style="font-size: 48px; color: white;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="program-info">
                        <h3><?php echo htmlspecialchars($program['nama_program']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($program['deskripsi'], 0, 80)) . (strlen($program['deskripsi']) > 80 ? '...' : ''); ?></p>
                        
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $persen; ?>%;"></div>
                        </div>
                        <div class="progress-stats">
                            <span><?php echo $persen; ?>%</span>
                            <span>Rp <?php echo number_format($terkumpul, 0, ',', '.'); ?> / Rp <?php echo number_format($target, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="donatur-count">
                            <i class="fas fa-users"></i> <?php echo $program['jumlah_donatur']; ?> Donatur
                        </div>
                        
                        <a href="program_detail.php?id=<?php echo $program['id']; ?>" class="btn-donasi">
                            <i class="fas fa-hand-holding-heart"></i> Donasi Sekarang
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="program-card" style="text-align: center; padding: 40px;">
                <i class="fas fa-chalkboard-user" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                <p>Belum ada program aktif</p>
                <a href="login.php" class="btn-donasi" style="margin-top: 15px;">Kembali</a>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Panti Asuhan Al-Muthi | Lembaga Amil Zakat Nasional</p>
    </div>
</div>
</body>
</html>