<?php
// ======================================================
// FILE: donatur/dashboard.php
// DASHBOARD DONATUR - MENAMPILKAN KEUANGAN PANTI
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('donatur');

$currentUser = getCurrentUser();

// ======================================================
// BARIS 1: DONASI BIASA PANTI (TOTAL SEMUA DONATUR)
// ======================================================
$queryPemasukanBiasa = "SELECT SUM(nominal) as total FROM donasi WHERE status = 'success'";
$resultPemasukanBiasa = mysqli_query($conn, $queryPemasukanBiasa);
$totalPemasukanBiasa = mysqli_fetch_assoc($resultPemasukanBiasa)['total'] ?? 0;

$queryPengeluaranBiasa = "SELECT SUM(nominal) as total FROM pengeluaran WHERE status = 'disetujui'";
$resultPengeluaranBiasa = mysqli_query($conn, $queryPengeluaranBiasa);
$totalPengeluaranBiasa = mysqli_fetch_assoc($resultPengeluaranBiasa)['total'] ?? 0;

$saldoBiasa = $totalPemasukanBiasa - $totalPengeluaranBiasa;

// ======================================================
// BARIS 2: PROGRAM PANTI (TOTAL SEMUA DONATUR)
// ======================================================
$queryPemasukanProgram = "SELECT SUM(nominal) as total FROM donasi_program WHERE status = 'success'";
$resultPemasukanProgram = mysqli_query($conn, $queryPemasukanProgram);
$totalPemasukanProgram = mysqli_fetch_assoc($resultPemasukanProgram)['total'] ?? 0;

$queryPenyaluranProgram = "SELECT SUM(jumlah) as total FROM penerima_manfaat";
$resultPenyaluranProgram = mysqli_query($conn, $queryPenyaluranProgram);
$totalPenyaluranProgram = mysqli_fetch_assoc($resultPenyaluranProgram)['total'] ?? 0;

$saldoProgram = $totalPemasukanProgram - $totalPenyaluranProgram;

// ======================================================
// BARIS 3: TOTAL KESELURUHAN PANTI
// ======================================================
$totalPemasukan = $totalPemasukanBiasa + $totalPemasukanProgram;
$totalPengeluaran = $totalPengeluaranBiasa + $totalPenyaluranProgram;
$saldoPanti = $totalPemasukan - $totalPengeluaran;

// ======================================================
// BARIS 4: STATISTIK DONATUR INI
// ======================================================
// Total Donasi Saya (Biasa + Program)
$queryDonasiSayaBiasa = "SELECT SUM(nominal) as total FROM donasi 
                         WHERE user_id = " . $currentUser['id'] . " AND status = 'success'";
$resultDonasiSayaBiasa = mysqli_query($conn, $queryDonasiSayaBiasa);
$totalDonasiSayaBiasa = mysqli_fetch_assoc($resultDonasiSayaBiasa)['total'] ?? 0;

$queryDonasiSayaProgram = "SELECT SUM(nominal) as total FROM donasi_program 
                           WHERE user_id = " . $currentUser['id'] . " AND status = 'success'";
$resultDonasiSayaProgram = mysqli_query($conn, $queryDonasiSayaProgram);
$totalDonasiSayaProgram = mysqli_fetch_assoc($resultDonasiSayaProgram)['total'] ?? 0;

$totalDonasiSaya = $totalDonasiSayaBiasa + $totalDonasiSayaProgram;

// Jumlah Anak Asuh
$queryAnakAsuh = "SELECT COUNT(*) as total FROM anak_asuh";
$resultAnakAsuh = mysqli_query($conn, $queryAnakAsuh);
$totalAnakAsuh = mysqli_fetch_assoc($resultAnakAsuh)['total'] ?? 0;

// Donasi tidak Valid (failed)
$queryDonasiInvalid = "SELECT COUNT(*) as total FROM donasi 
                       WHERE user_id = " . $currentUser['id'] . " AND status = 'failed'";
$resultDonasiInvalid = mysqli_query($conn, $queryDonasiInvalid);
$totalDonasiInvalid = mysqli_fetch_assoc($resultDonasiInvalid)['total'] ?? 0;

// ======================================================
// DONASI TERBARU (gabungan biasa + program)
// ======================================================
$queryRecent = "
    (SELECT 
        'biasa' as tipe,
        d.id,
        d.tanggal_donasi as tanggal,
        k.nama_kategori as kategori,
        d.nominal,
        d.status,
        d.keterangan
    FROM donasi d 
    JOIN kategori_donasi k ON d.kategori_id = k.id 
    WHERE d.user_id = " . $currentUser['id'] . "
    ORDER BY d.tanggal_donasi DESC 
    LIMIT 5)
    
    UNION ALL
    
    (SELECT 
        'program' as tipe,
        dp.id,
        dp.created_at as tanggal,
        p.nama_program as kategori,
        dp.nominal,
        dp.status,
        dp.pesan as keterangan
    FROM donasi_program dp
    JOIN program_donasi p ON dp.program_id = p.id
    WHERE dp.user_id = " . $currentUser['id'] . "
    ORDER BY dp.created_at DESC 
    LIMIT 5)
    
    ORDER BY tanggal DESC 
    LIMIT 5
";
$recentDonasi = query($queryRecent);

// ======================================================
// PENYALURAN TERBARU
// ======================================================
$queryPenyaluranRecent = "SELECT * FROM penerima_manfaat ORDER BY tanggal_penyaluran DESC LIMIT 5";
$recentPenyaluran = query($queryPenyaluranRecent);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Donatur - Panti Asuhan Al-Muthi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; overflow-x: hidden; }
        
        /* SIDEBAR */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100%;
            background: linear-gradient(135deg, #1a3a2a 0%, #2d4a3a 100%);
            color: white;
            transition: all 0.3s ease;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: center;
        }
        .sidebar-logo { width: 45px; height: 45px; object-fit: contain; }
        .sidebar-header h3 { font-size: 16px; margin-bottom: 3px; }
        .sidebar-header p { font-size: 11px; opacity: 0.7; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.8);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .menu-item:hover, .menu-item.active {
            background: rgba(80,200,120,0.3);
            color: white;
            border-left: 4px solid #50c878;
        }
        .menu-item i { width: 24px; font-size: 18px; }
        .menu-item span { font-size: 14px; }
        
        /* MAIN CONTENT */
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
        }
        
        /* TOPBAR */
        .topbar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .page-title h2 { font-size: 20px; color: #333; }
        .page-title p { font-size: 13px; color: #888; margin-top: 5px; }
        .profile-dropdown { position: relative; }
        .profile-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #50c878, #2e8b57);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            cursor: pointer;
        }
        .dropdown-menu {
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            width: 200px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        .profile-dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
        }
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f0;
        }
        .dropdown-menu a:last-child { border-bottom: none; }
        .dropdown-menu a:hover { background: #f5f5f5; color: #50c878; }
        
        /* STATS CARDS - 3 KOLOM */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-3px); }
        
        .stat-info h4 { font-size: 12px; color: #888; margin-bottom: 5px; font-weight: 500; }
        .stat-info .value { font-size: 22px; font-weight: 700; color: #333; }
        .stat-info small { font-size: 11px; color: #999; display: block; margin-top: 2px; }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        
        /* Warna Card */
        .card-pemasukan .stat-icon { background: #e8f5e9; color: #4caf50; }
        .card-pengeluaran .stat-icon { background: #ffebee; color: #f44336; }
        .card-saldo .stat-icon { background: #e3f2fd; color: #2196f3; }
        .card-saldo .value { color: #2196f3; }
        
        .card-program .stat-icon { background: #f3e5f5; color: #9c27b0; }
        .card-program .value { color: #9c27b0; }
        .card-penyaluran .stat-icon { background: #fff3e0; color: #ff9800; }
        
        .card-total-pemasukan .stat-icon { background: #e8f5e9; color: #2e7d32; }
        .card-total-pengeluaran .stat-icon { background: #ffebee; color: #c62828; }
        .card-total-saldo .stat-icon { background: #e3f2fd; color: #0d47a1; }
        .card-total-saldo .value { color: #0d47a1; }
        
        .card-donasi-saya .stat-icon { background: #e8f5e9; color: #4caf50; }
        .card-anak .stat-icon { background: #fce4ec; color: #e91e63; }
        .card-invalid .stat-icon { background: #ffebee; color: #f44336; }
        
        /* BORDER LEFT */
        .baris-1 .stat-card { border-left: 4px solid #4caf50; }
        .baris-1 .stat-card:nth-child(2) { border-left-color: #f44336; }
        .baris-1 .stat-card:nth-child(3) { border-left-color: #2196f3; }
        
        .baris-2 .stat-card { border-left: 4px solid #9c27b0; }
        .baris-2 .stat-card:nth-child(2) { border-left-color: #ff9800; }
        .baris-2 .stat-card:nth-child(3) { border-left-color: #9c27b0; }
        
        .baris-3 .stat-card { border-left: 4px solid #2e7d32; }
        .baris-3 .stat-card:nth-child(2) { border-left-color: #c62828; }
        .baris-3 .stat-card:nth-child(3) { border-left-color: #0d47a1; }
        
        .baris-4 .stat-card { border-left: 4px solid #4caf50; }
        .baris-4 .stat-card:nth-child(2) { border-left-color: #e91e63; }
        .baris-4 .stat-card:nth-child(3) { border-left-color: #f44336; }
        
        /* SECTION TITLE */
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 25px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title .badge-section {
            font-size: 11px;
            font-weight: 400;
            color: #888;
            background: #f0f2f5;
            padding: 2px 12px;
            border-radius: 20px;
        }
        
        /* QUICK ACTIONS */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .action-btn {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
            cursor: pointer;
        }
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #50c878;
        }
        .action-btn i { font-size: 30px; color: #50c878; margin-bottom: 10px; }
        .action-btn h4 { font-size: 14px; color: #333; margin-bottom: 5px; }
        .action-btn p { font-size: 11px; color: #888; }
        
        /* RECENT TABLE */
        .recent-table {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .recent-table h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #333;
        }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: 10px 12px;
            background: #f8f9fa;
            font-size: 12px;
            color: #666;
            font-weight: 600;
        }
        td {
            padding: 10px 12px;
            font-size: 13px;
            color: #555;
            border-bottom: 1px solid #eee;
        }
        .status-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }
        .status-pending { background: #fff3e0; color: #ff9800; }
        .status-success { background: #e8f5e9; color: #4caf50; }
        .status-failed { background: #ffebee; color: #f44336; }
        
        .badge-tipe-program { background: #f3e5f5; color: #9c27b0; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .badge-tipe-biasa { background: #e3f2fd; color: #2196f3; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        
        .two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        @media (max-width: 992px) { .two-column { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .sidebar { left: -280px; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/image/almuthi.png" alt="Logo Al-Muthi" class="sidebar-logo" onerror="this.style.display='none'">
            <div>
                <h3>Panti Asuhan</h3>
                <p>Al-Muthi</p>
            </div>
        </div>
        <div class="sidebar-menu">
            <div class="menu-item active" onclick="location.href='dashboard.php'">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </div>
            <div class="menu-item" onclick="location.href='donasi.php'">
                <i class="fas fa-hand-holding-heart"></i>
                <span>Donasi Sekarang</span>
            </div>
            <div class="menu-item" onclick="location.href='../semua_program.php'">
                <i class="fas fa-chalkboard-user"></i>
                <span>Program Utama</span>
            </div>
            <div class="menu-item" onclick="location.href='histori.php'">
                <i class="fas fa-history"></i>
                <span>Riwayat Donasi</span>
            </div>
            <div class="menu-item" onclick="location.href='laporan_pengeluaran.php'">
                <i class="fas fa-money-bill-wave"></i>
                <span>Pengeluaran Panti</span>
            </div>
            <div class="menu-item" onclick="location.href='doa_saya.php'">
                <i class="fas fa-pray"></i>
                <span>Laporan Khususon Do'a</span>
            </div>
            <div class="menu-item" onclick="location.href='perkembangan.php'">
                <i class="fas fa-seedling"></i>
                <span>Perkembangan Anak</span>
            </div>
            <div class="menu-item" onclick="location.href='laporan.php'">
                <i class="fas fa-chart-line"></i>
                <span>Laporan Keuangan</span>
            </div>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Dashboard Donatur</h2>
                <p>Selamat datang, <?php echo htmlspecialchars($currentUser['nama_lengkap']); ?></p>
            </div>
            <div class="profile-dropdown">
                <div class="profile-icon"><i class="fas fa-cog"></i></div>
                <div class="dropdown-menu">
                    <a href="profil.php"><i class="fas fa-user-circle"></i><span>Profil</span></a>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- BARIS 1: KEUANGAN DONASI BIASA PANTI                          -->
        <!-- ============================================================ -->
        <div class="section-title">
            <i class="fas fa-hand-holding-heart" style="color:#4caf50;"></i>
            <span>Keuangan Donasi Biasa Panti</span>
            <span class="badge-section">Pemasukan - Pengeluaran</span>
        </div>
        <div class="stats-grid baris-1">
            <div class="stat-card card-pemasukan">
                <div class="stat-info">
                    <h4><i class="fas fa-arrow-down"></i> Pemasukan Biasa</h4>
                    <div class="value">Rp <?php echo number_format($totalPemasukanBiasa, 0, ',', '.'); ?></div>
                    <small>Total donasi biasa success</small>
                </div>
                <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
            </div>
            <div class="stat-card card-pengeluaran">
                <div class="stat-info">
                    <h4><i class="fas fa-arrow-up"></i> Pengeluaran Biasa</h4>
                    <div class="value">Rp <?php echo number_format($totalPengeluaranBiasa, 0, ',', '.'); ?></div>
                    <small>Pengeluaran operasional disetujui</small>
                </div>
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
            <div class="stat-card card-saldo">
                <div class="stat-info">
                    <h4><i class="fas fa-wallet"></i> Saldo Biasa</h4>
                    <div class="value">Rp <?php echo number_format($saldoBiasa, 0, ',', '.'); ?></div>
                    <small>Pemasukan - Pengeluaran</small>
                </div>
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- BARIS 2: KEUANGAN PROGRAM PANTI                               -->
        <!-- ============================================================ -->
        <div class="section-title">
            <i class="fas fa-chalkboard-user" style="color:#9c27b0;"></i>
            <span>Keuangan Program Panti</span>
            <span class="badge-section">Pemasukan - Penyaluran</span>
        </div>
        <div class="stats-grid baris-2">
            <div class="stat-card card-program">
                <div class="stat-info">
                    <h4><i class="fas fa-arrow-down"></i> Pemasukan Program</h4>
                    <div class="value">Rp <?php echo number_format($totalPemasukanProgram, 0, ',', '.'); ?></div>
                    <small>Total donasi program success</small>
                </div>
                <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
            </div>
            <div class="stat-card card-penyaluran">
                <div class="stat-info">
                    <h4><i class="fas fa-arrow-up"></i> Penyaluran Program</h4>
                    <div class="value">Rp <?php echo number_format($totalPenyaluranProgram, 0, ',', '.'); ?></div>
                    <small>Ke penerima manfaat</small>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-card card-saldo">
                <div class="stat-info">
                    <h4><i class="fas fa-wallet"></i> Saldo Program</h4>
                    <div class="value">Rp <?php echo number_format($saldoProgram, 0, ',', '.'); ?></div>
                    <small>Pemasukan - Penyaluran</small>
                </div>
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- BARIS 3: TOTAL KESELURUHAN PANTI                              -->
        <!-- ============================================================ -->
        <div class="section-title">
            <i class="fas fa-chart-pie" style="color:#0d47a1;"></i>
            <span>Total Keuangan Panti</span>
            <span class="badge-section">Keseluruhan</span>
        </div>
        <div class="stats-grid baris-3">
            <div class="stat-card card-total-pemasukan">
                <div class="stat-info">
                    <h4><i class="fas fa-arrow-down"></i> Total Pemasukan</h4>
                    <div class="value">Rp <?php echo number_format($totalPemasukan, 0, ',', '.'); ?></div>
                    <small>Biasa + Program</small>
                </div>
                <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
            </div>
            <div class="stat-card card-total-pengeluaran">
                <div class="stat-info">
                    <h4><i class="fas fa-arrow-up"></i> Total Pengeluaran</h4>
                    <div class="value">Rp <?php echo number_format($totalPengeluaran, 0, ',', '.'); ?></div>
                    <small>Operasional + Penyaluran</small>
                </div>
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
            <div class="stat-card card-total-saldo">
                <div class="stat-info">
                    <h4><i class="fas fa-wallet"></i> Saldo Panti</h4>
                    <div class="value">Rp <?php echo number_format($saldoPanti, 0, ',', '.'); ?></div>
                    <small>Total Pemasukan - Total Pengeluaran</small>
                </div>
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- BARIS 4: STATISTIK DONATUR INI                                -->
        <!-- ============================================================ -->
        <div class="section-title">
            <i class="fas fa-user" style="color:#4caf50;"></i>
            <span>Statistik Donasi Saya</span>
        </div>
        <div class="stats-grid baris-4">
            <div class="stat-card card-donasi-saya">
                <div class="stat-info">
                    <h4><i class="fas fa-donate"></i> Total Donasi Saya</h4>
                    <div class="value">Rp <?php echo number_format($totalDonasiSaya, 0, ',', '.'); ?></div>
                    <small>Biasa + Program (success)</small>
                </div>
                <div class="stat-icon"><i class="fas fa-gift"></i></div>
            </div>
            <div class="stat-card card-anak">
                <div class="stat-info">
                    <h4><i class="fas fa-child"></i> Jumlah Anak Asuh</h4>
                    <div class="value"><?php echo $totalAnakAsuh; ?></div>
                    <small>Anak</small>
                </div>
                <div class="stat-icon"><i class="fas fa-baby-carriage"></i></div>
            </div>
            <div class="stat-card card-invalid">
                <div class="stat-info">
                    <h4><i class="fas fa-times-circle"></i> Donasi Tidak Valid</h4>
                    <div class="value"><?php echo $totalDonasiInvalid; ?></div>
                    <small>Transaksi failed</small>
                </div>
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- QUICK ACTIONS                                                -->
        <!-- ============================================================ -->
        <div class="section-title">
            <i class="fas fa-bolt" style="color:#50c878;"></i>
            <span>Aksi Cepat</span>
        </div>
        <div class="quick-actions">
            <div class="action-btn" onclick="location.href='donasi.php'">
                <i class="fas fa-hand-holding-heart"></i>
                <h4>Donasi Baru</h4>
                <p>Salurkan donasi Anda</p>
            </div>
            <div class="action-btn" onclick="location.href='histori.php'">
                <i class="fas fa-history"></i>
                <h4>Riwayat Donasi</h4>
                <p>Lihat histori donasi Anda</p>
            </div>
            <div class="action-btn" onclick="location.href='../semua_program.php'">
                <i class="fas fa-chalkboard-user"></i>
                <h4>Program Donasi</h4>
                <p>Lihat program crowdfunding</p>
            </div>
            <div class="action-btn" onclick="location.href='doa_saya.php'">
                <i class="fas fa-pray"></i>
                <h4>Doa Saya</h4>
                <p>Lihat status doa Anda</p>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- TWO COLUMN: Donasi Terbaru & Penyaluran Terbaru              -->
        <!-- ============================================================ -->
        <div class="two-column">
            <!-- DONASI TERBARU -->
            <div class="recent-table">
                <h3><i class="fas fa-history"></i> Donasi Terbaru Anda</h3>
                <?php if (count($recentDonasi) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Tipe</th>
                                <th>Kategori</th>
                                <th>Nominal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentDonasi as $donasi): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($donasi['tanggal'])); ?></td>
                                    <td>
                                        <span class="<?php echo $donasi['tipe'] == 'program' ? 'badge-tipe-program' : 'badge-tipe-biasa'; ?>">
                                            <?php echo $donasi['tipe'] == 'program' ? '📦 Program' : '💝 Biasa'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($donasi['kategori']); ?></td>
                                    <td>Rp <?php echo number_format($donasi['nominal'], 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $donasi['status']; ?>">
                                            <?php echo ucfirst($donasi['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align:center;padding:20px;">Belum ada data donasi</p>
                <?php endif; ?>
            </div>
            
            <!-- PENYALURAN TERBARU -->
            <div class="recent-table">
                <h3><i class="fas fa-hands-helping"></i> Penyaluran Terbaru</h3>
                <?php if (count($recentPenyaluran) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Nama Penerima</th>
                                <th>Bantuan</th>
                                <th>Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPenyaluran as $p): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($p['tanggal_penyaluran'])); ?></td>
                                    <td><?php echo htmlspecialchars($p['nama_penerima']); ?></td>
                                    <td><?php echo htmlspecialchars($p['jenis_bantuan']); ?></td>
                                    <td>Rp <?php echo number_format($p['jumlah'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align:center;padding:20px;">Belum ada penyaluran</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>