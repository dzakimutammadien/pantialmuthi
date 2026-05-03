<?php
// ======================================================
// FILE: galeri.php (PUBLIK - untuk donatur & pengunjung)
// HALAMAN GALERI FOTO & VIDEO KEGIATAN PANTI
// ======================================================

require_once 'config/database.php';

// Ambil data galeri yang statusnya AKTIF
$sql = "SELECT * FROM galeri WHERE status = 'aktif' ORDER BY created_at DESC";
$galeri = query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeri Kegiatan - Panti Asuhan Al-Muthi</title>
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
        
        /* HEADER */
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: white;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: rgba(255,255,255,0.9);
            font-size: 14px;
        }
        
        .btn-back {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            transition: 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* GALERI GRID */
        .galeri-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .galeri-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }
        
        .galeri-card:hover {
            transform: translateY(-5px);
        }
        
        .galeri-media {
            position: relative;
            height: 200px;
            overflow: hidden;
            background: #f0f2f5;
            cursor: pointer;
        }
        
        .galeri-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .play-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 45px;
            color: white;
            text-shadow: 0 0 10px rgba(0,0,0,0.5);
            pointer-events: none;
        }
        
        .galeri-info {
            padding: 12px;
        }
        
        .galeri-info h3 {
            font-size: 14px;
            margin-bottom: 5px;
            color: #333;
        }
        
        .galeri-info .tanggal {
            font-size: 11px;
            color: #888;
        }
        
        /* FOOTER */
        .footer {
            text-align: center;
            margin-top: 40px;
            color: rgba(255,255,255,0.7);
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .galeri-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="login.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
            <h1><i class="fas fa-images"></i> Galeri Kegiatan</h1>
            <p>Dokumentasi kegiatan sosial Panti Asuhan Al-Muthi</p>
        </div>
        
        <div class="galeri-grid">
            <?php if (count($galeri) > 0): ?>
                <?php foreach ($galeri as $g): ?>
                    <div class="galeri-card">
                        <div class="galeri-media" onclick="<?php 
                            if ($g['tipe'] == 'foto') {
                                echo "window.open('assets/uploads/galeri/" . $g['file_path'] . "', '_blank')";
                            } elseif ($g['tipe'] == 'video' && !empty($g['youtube_id'])) {
                                $youtube_id = $g['youtube_id'];
                                if (strpos($youtube_id, 'youtube.com') !== false || strpos($youtube_id, 'youtu.be') !== false) {
                                    parse_str(parse_url($youtube_id, PHP_URL_QUERY), $params);
                                    $youtube_id = $params['v'] ?? substr($youtube_id, strrpos($youtube_id, '/') + 1);
                                }
                                echo "window.open('https://www.youtube.com/watch?v=" . $youtube_id . "', '_blank')";
                            }
                        ?>">
                            <?php if ($g['tipe'] == 'foto'): ?>
                                <img src="assets/uploads/galeri/<?php echo $g['file_path']; ?>" alt="<?php echo $g['judul']; ?>">
                            <?php elseif ($g['tipe'] == 'video' && !empty($g['youtube_id'])): ?>
                                <?php 
                                $youtube_id = $g['youtube_id'];
                                if (strpos($youtube_id, 'youtube.com') !== false || strpos($youtube_id, 'youtu.be') !== false) {
                                    parse_str(parse_url($youtube_id, PHP_URL_QUERY), $params);
                                    $youtube_id = $params['v'] ?? substr($youtube_id, strrpos($youtube_id, '/') + 1);
                                }
                                ?>
                                <img src="assets/uploads/galeri/<?php echo $g['file_path']; ?>" alt="<?php echo $g['judul']; ?>">
                                <div class="play-icon"><i class="fas fa-play-circle"></i></div>
                            <?php else: ?>
                                <video src="assets/uploads/galeri/<?php echo $g['file_path']; ?>" style="width:100%; height:100%; object-fit:cover;"></video>
                                <div class="play-icon"><i class="fas fa-play-circle"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="galeri-info">
                            <h3><?php echo htmlspecialchars($g['judul']); ?></h3>
                            <p class="tanggal"><i class="far fa-calendar-alt"></i> <?php echo date('d F Y', strtotime($g['created_at'])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 50px; background: white; border-radius: 15px;">
                    <i class="fas fa-images" style="font-size: 48px; color: #ccc;"></i>
                    <p style="margin-top: 15px; color: #888;">Belum ada galeri kegiatan</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Panti Asuhan Al-Muthi | Lembaga Amil Zakat Nasional</p>
        </div>
    </div>
</body>
</html>