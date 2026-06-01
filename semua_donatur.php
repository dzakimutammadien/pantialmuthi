<?php
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

// Ambil semua donatur (tanpa limit)
$sql_donatur = "SELECT * FROM donasi_program WHERE program_id = $program_id AND status = 'success' ORDER BY created_at DESC";
$donatur_list = query($sql_donatur);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Donatur - <?php echo $program['nama_program']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%); min-height: 100vh; padding: 40px 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #1a3a2a; margin-bottom: 10px; }
        .btn-back { display: inline-block; background: #6c757d; color: white; padding: 8px 20px; border-radius: 25px; text-decoration: none; margin-bottom: 20px; font-size: 13px; }
        .donatur-item { padding: 12px; border-bottom: 1px solid #eee; }
        .donatur-nama { font-weight: 600; }
        .donatur-nominal { color: #50c878; font-weight: 600; float: right; }
        .donatur-pesan { font-size: 12px; color: #888; margin-top: 5px; }
        .total { margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px; text-align: right; font-weight: bold; }
        @media (max-width: 768px) { .donatur-nominal { float: none; display: block; margin-top: 5px; } }
    </style>
</head>
<body>
<div class="container">
    <a href="program_detail.php?id=<?php echo $program_id; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Program</a>
    
    <div class="card">
        <h2><i class="fas fa-users"></i> Semua Donatur</h2>
        <p>Program: <strong><?php echo $program['nama_program']; ?></strong></p>
        <p>Total Donatur: <?php echo count($donatur_list); ?> orang</p>
        
        <div style="margin-top: 20px;">
            <?php foreach ($donatur_list as $d): ?>
                <div class="donatur-item">
                    <span class="donatur-nama">
                        <?php if ($d['is_anonim']): ?>
                            🙈 Anonim
                        <?php else: ?>
                            <?php echo htmlspecialchars($d['nama_donatur']); ?>
                        <?php endif; ?>
                    </span>
                    <span class="donatur-nominal">Rp <?php echo number_format($d['nominal'], 0, ',', '.'); ?></span>
                    <?php if ($d['pesan']): ?>
                        <div class="donatur-pesan">"<?php echo htmlspecialchars($d['pesan']); ?>"</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="total">
            Total Donasi Terkumpul: Rp <?php echo number_format($program['terkumpul'], 0, ',', '.'); ?>
        </div>
    </div>
</div>
</body>
</html>