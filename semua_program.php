<?php
// ======================================================
// FILE: semua_program.php
// HALAMAN SEMUA PROGRAM (PUBLIK + DONATUR LOGIN)
// ======================================================

require_once 'config/database.php';
require_once 'config/session.php';

// Cek apakah user login sebagai donatur
$is_donatur_login = false;
$currentUser = null;
if (isLoggedIn() && getUserRole() == 'donatur') {
    $is_donatur_login = true;
    $currentUser = getCurrentUser();
}

// Ambil semua program aktif
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM donasi_program WHERE program_id = p.id AND status = 'success') as jumlah_donatur,
        (SELECT SUM(nominal) FROM donasi_program WHERE program_id = p.id AND status = 'success') as total_terkumpul
        FROM program_donasi p 
        WHERE p.status = 'aktif' 
        ORDER BY p.created_at DESC";
$program_list = query($sql);
?>

<?php if ($is_donatur_login): ?>
    <!-- ====================================================== -->
    <!-- TAMPILAN UNTUK DONATUR YANG LOGIN (DENGAN SIDEBAR)     -->
    <!-- ====================================================== -->
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
            body { font-family: 'Poppins', sans-serif; background: #f0f2f5; overflow-x: hidden; }
            
            .sidebar { position: fixed; left: 0; top: 0; width: 280px; height: 100%; background: linear-gradient(135deg, #1a3a2a 0%, #2d4a3a 100%); color: white; overflow-y: auto; z-index: 100; }
            .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 12px; justify-content: center; }
            .sidebar-logo { width: 45px; height: 45px; object-fit: contain; }
            .sidebar-header h3 { font-size: 16px; margin-bottom: 3px; }
            .sidebar-header p { font-size: 11px; opacity: 0.7; }
            .sidebar-menu { padding: 20px 0; }
            .menu-item { padding: 12px 20px; display: flex; align-items: center; gap: 12px; cursor: pointer; color: rgba(255,255,255,0.8); transition: all 0.3s; }
            .menu-item:hover, .menu-item.active { background: rgba(80,200,120,0.3); border-left: 4px solid #50c878; }
            .menu-item i { width: 24px; font-size: 18px; }
            .menu-item span { font-size: 14px; }
            
            .main-content { margin-left: 280px; padding: 20px; min-height: 100vh; }
            .topbar { background: white; border-radius: 15px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
            .page-title h2 { font-size: 20px; color: #333; }
            .page-title p { font-size: 13px; color: #888; margin-top: 5px; }
            .profile-dropdown { position: relative; }
            .profile-icon { width: 45px; height: 45px; background: linear-gradient(135deg, #50c878, #2e8b57); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; color: white; }
            .dropdown-menu { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 1000; }
            .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; }
            .dropdown-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
            
            .content-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
            .program-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
            .program-card { background: #f8f9fa; border-radius: 15px; overflow: hidden; transition: transform 0.3s; border: 1px solid #e0e0e0; }
            .program-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-color: #50c878; }
            .program-image { height: 180px; overflow: hidden; }
            .program-image img { width: 100%; height: 100%; object-fit: cover; }
            .program-image .no-image { height: 100%; background: linear-gradient(135deg, #50c878, #2e8b57); display: flex; align-items: center; justify-content: center; }
            .program-image .no-image i { font-size: 48px; color: white; }
            .program-info { padding: 15px; }
            .program-info h3 { font-size: 16px; margin-bottom: 8px; }
            .program-info p { font-size: 12px; color: #666; margin-bottom: 10px; }
            .progress-bar { background: #e0e0e0; border-radius: 10px; height: 8px; margin: 10px 0; }
            .progress-fill { background: #50c878; height: 100%; border-radius: 10px; }
            .progress-stats { display: flex; justify-content: space-between; font-size: 11px; color: #666; margin-bottom: 10px; }
            .donatur-count { font-size: 11px; color: #888; margin-bottom: 15px; }
            .btn-donasi { display: block; background: #50c878; color: white; text-align: center; padding: 8px; border-radius: 20px; text-decoration: none; font-size: 13px; transition: 0.3s; }
            .btn-donasi:hover { background: #2e8b57; }
            
            @media (max-width: 768px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } .program-grid { grid-template-columns: 1fr; } }
        </style>
    </head>
    <body>
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="assets/image/almuthi.png" alt="Logo" class="sidebar-logo" onerror="this.style.display='none'">
                <div><h3>Panti Asuhan</h3><p>Al-Muthi</p></div>
            </div>
            <div class="sidebar-menu">
                <div class="menu-item" onclick="location.href='donatur/dashboard.php'"><i class="fas fa-tachometer-alt"></i><span>Beranda</span></div>
                <div class="menu-item" onclick="location.href='donatur/donasi.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Sekarang</span></div>
                <div class="menu-item active" onclick="location.href='semua_program.php'"><i class="fas fa-chalkboard-user"></i><span>Program Donasi</span></div>
                <div class="menu-item" onclick="location.href='donatur/histori.php'"><i class="fas fa-history"></i><span>Riwayat Donasi</span></div>
                <div class="menu-item" onclick="location.href='donatur/laporan_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Laporan Pengeluaran</span></div>
                <div class="menu-item" onclick="location.href='donatur/doa_saya.php'"><i class="fas fa-pray"></i><span>Laporan Khusus Do'a</span></div>
                <div class="menu-item" onclick="location.href='donatur/perkembangan.php'"><i class="fas fa-seedling"></i><span>Perkembangan Anak</span></div>
                <div class="menu-item" onclick="location.href='donatur/laporan.php'"><i class="fas fa-chart-line"></i><span>Laporan</span></div>
            </div>
        </div>
        
        <div class="main-content">
            <div class="topbar">
                <div class="page-title"><h2>Semua Program</h2><p>Donasi untuk program-program panti asuhan Al-Muthi</p></div>
                <div class="profile-dropdown">
                    <div class="profile-icon"><i class="fas fa-cog"></i></div>
                    <div class="dropdown-menu">
                        <a href="donatur/profil.php"><i class="fas fa-user-circle"></i> Profil</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
            
            <div class="content-card">
                <div class="program-grid">
                    <?php foreach ($program_list as $program):
                        $terkumpul = $program['total_terkumpul'] ?? 0;
                        $target = $program['target_nominal'];
                        $persen = ($target > 0) ? round(($terkumpul / $target) * 100) : 0;
                        $persen = min($persen, 100);
                    ?>
                        <div class="program-card">
                            <div class="program-image">
                                <?php if ($program['gambar'] && file_exists('assets/uploads/program/' . $program['gambar'])): ?>
                                    <img src="assets/uploads/program/<?php echo $program['gambar']; ?>" alt="<?php echo $program['nama_program']; ?>">
                                <?php else: ?>
                                    <div class="no-image"><i class="fas fa-hand-holding-heart"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="program-info">
                                <h3><?php echo htmlspecialchars($program['nama_program']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($program['deskripsi'], 0, 80)) . (strlen($program['deskripsi']) > 80 ? '...' : ''); ?></p>
                                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $persen; ?>%;"></div></div>
                                <div class="progress-stats">
                                    <span><?php echo $persen; ?>%</span>
                                    <span>Rp <?php echo number_format($terkumpul, 0, ',', '.'); ?> / Rp <?php echo number_format($target, 0, ',', '.'); ?></span>
                                </div>
                                <div class="donatur-count"><i class="fas fa-users"></i> <?php echo $program['jumlah_donatur']; ?> Donatur</div>
                                <a href="program_detail.php?id=<?php echo $program['id']; ?>" class="btn-donasi">Donasi Sekarang</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </body>
    </html>

<?php else: ?>
    <!-- ====================================================== -->
    <!-- TAMPILAN PUBLIK (TANPA SIDEBAR, BACKGROUND HIJAU)      -->
    <!-- ====================================================== -->
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
            body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%); min-height: 100vh; padding: 40px 20px; }
            .container { max-width: 1200px; margin: 0 auto; }
            .btn-back { display: inline-block; background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 25px; text-decoration: none; margin-bottom: 20px; transition: 0.3s; }
            .btn-back:hover { background: rgba(255,255,255,0.3); }
            .page-header { text-align: center; margin-bottom: 30px; color: white; }
            .page-header h1 { font-size: 28px; margin-bottom: 10px; }
            .page-header p { font-size: 14px; opacity: 0.9; }
            .program-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
            .program-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); transition: transform 0.3s; }
            .program-card:hover { transform: translateY(-5px); }
            .program-image { height: 180px; overflow: hidden; }
            .program-image img { width: 100%; height: 100%; object-fit: cover; }
            .program-image .no-image { height: 100%; background: linear-gradient(135deg, #50c878, #2e8b57); display: flex; align-items: center; justify-content: center; }
            .program-image .no-image i { font-size: 48px; color: white; }
            .program-info { padding: 20px; }
            .program-info h3 { font-size: 18px; margin-bottom: 8px; color: #1a3a2a; }
            .program-info p { font-size: 13px; color: #666; margin-bottom: 15px; }
            .progress-bar { background: #e0e0e0; border-radius: 10px; height: 8px; margin: 10px 0; }
            .progress-fill { background: #50c878; height: 100%; border-radius: 10px; }
            .progress-stats { display: flex; justify-content: space-between; font-size: 12px; color: #666; margin-bottom: 10px; }
            .donatur-count { font-size: 12px; color: #888; margin-bottom: 15px; }
            .btn-donasi { display: inline-block; background: #50c878; color: white; text-align: center; padding: 10px 20px; border-radius: 25px; text-decoration: none; font-size: 14px; font-weight: 500; width: 100%; transition: 0.3s; }
            .btn-donasi:hover { background: #2e8b57; }
            .footer { text-align: center; margin-top: 40px; color: rgba(255,255,255,0.7); font-size: 12px; }
            @media (max-width: 768px) { .program-grid { grid-template-columns: 1fr; } }
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
                <?php foreach ($program_list as $program):
                    $terkumpul = $program['total_terkumpul'] ?? 0;
                    $target = $program['target_nominal'];
                    $persen = ($target > 0) ? round(($terkumpul / $target) * 100) : 0;
                    $persen = min($persen, 100);
                ?>
                    <div class="program-card">
                        <div class="program-image">
                            <?php if ($program['gambar'] && file_exists('assets/uploads/program/' . $program['gambar'])): ?>
                                <img src="assets/uploads/program/<?php echo $program['gambar']; ?>" alt="<?php echo $program['nama_program']; ?>">
                            <?php else: ?>
                                <div class="no-image"><i class="fas fa-hand-holding-heart"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="program-info">
                            <h3><?php echo htmlspecialchars($program['nama_program']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($program['deskripsi'], 0, 80)) . (strlen($program['deskripsi']) > 80 ? '...' : ''); ?></p>
                            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $persen; ?>%;"></div></div>
                            <div class="progress-stats">
                                <span><?php echo $persen; ?>%</span>
                                <span>Rp <?php echo number_format($terkumpul, 0, ',', '.'); ?> / Rp <?php echo number_format($target, 0, ',', '.'); ?></span>
                            </div>
                            <div class="donatur-count"><i class="fas fa-users"></i> <?php echo $program['jumlah_donatur']; ?> Donatur</div>
                            <a href="program_detail.php?id=<?php echo $program['id']; ?>" class="btn-donasi">Donasi Sekarang</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="footer"><p>&copy; <?php echo date('Y'); ?> Panti Asuhan Al-Muthi</p></div>
        </div>
    </body>
    </html>
<?php endif; ?>