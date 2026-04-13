<?php
// ======================================================
// FILE: pengasuh/dashboard.php
// DASHBOARD PENGASUH - SESUAI DESAIN CANVA
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('pengasuh');

$currentUser = getCurrentUser();

// ======================================================
// AMBIL DATA STATISTIK UNTUK DASHBOARD
// ======================================================

// Total Pemasukan Panti (donasi success)
$queryPemasukan = "SELECT SUM(nominal) as total FROM donasi WHERE status = 'success'";
$resultPemasukan = mysqli_query($conn, $queryPemasukan);
$totalPemasukan = mysqli_fetch_assoc($resultPemasukan)['total'] ?? 0;

// Total Pengeluaran Panti (pengeluaran yang disetujui)
$queryPengeluaran = "SELECT SUM(nominal) as total FROM pengeluaran WHERE status = 'disetujui'";
$resultPengeluaran = mysqli_query($conn, $queryPengeluaran);
$totalPengeluaran = mysqli_fetch_assoc($resultPengeluaran)['total'] ?? 0;

// Saldo Panti
$saldoPanti = $totalPemasukan - $totalPengeluaran;

// Do'a Khususon belum Terlaksana (doa dengan status pending)
$queryDoaPending = "SELECT COUNT(*) as total FROM doa WHERE status_doa = 'pending'";
$resultDoaPending = mysqli_query($conn, $queryDoaPending);
$totalDoaPending = mysqli_fetch_assoc($resultDoaPending)['total'] ?? 0;

// Jumlah Anak Asuh
$queryAnakAsuh = "SELECT COUNT(*) as total FROM anak_asuh";
$resultAnakAsuh = mysqli_query($conn, $queryAnakAsuh);
$totalAnakAsuh = mysqli_fetch_assoc($resultAnakAsuh)['total'] ?? 0;

// Laporan Pengeluaran ditolak (pengeluaran dengan status ditolak)
$queryPengeluaranDitolak = "SELECT COUNT(*) as total FROM pengeluaran WHERE status = 'ditolak' AND created_by = " . $currentUser['id'];
$resultPengeluaranDitolak = mysqli_query($conn, $queryPengeluaranDitolak);
$totalPengeluaranDitolak = mysqli_fetch_assoc($resultPengeluaranDitolak)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengasuh - Panti Asuhan Al-Muthi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f0f2f5;
            overflow-x: hidden;
        }
        
        /* SIDEBAR STYLES */
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
        
        .sidebar-logo {
            width: 45px;
            height: 45px;
            object-fit: contain;
        }
        
        .sidebar-header h3 {
            font-size: 16px;
            margin-bottom: 3px;
        }
        
        .sidebar-header p {
            font-size: 11px;
            opacity: 0.7;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
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
        
        .menu-item i {
            width: 24px;
            font-size: 18px;
        }
        
        .menu-item span {
            font-size: 14px;
        }
        
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
        
        .page-title h2 {
            font-size: 20px;
            color: #333;
        }
        
        .page-title p {
            font-size: 13px;
            color: #888;
            margin-top: 5px;
        }
        
        .profile-dropdown {
            position: relative;
        }
        
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
        
        .dropdown-menu a:last-child {
            border-bottom: none;
        }
        
        .dropdown-menu a:hover {
            background: #f5f5f5;
            color: #50c878;
        }
        
        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-info h4 {
            font-size: 13px;
            color: #888;
            margin-bottom: 8px;
        }
        
        .stat-info .value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }
        
        .stat-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #50c87820, #2e8b5720);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #50c878;
        }
        
        .stat-card.saldo .value {
            color: #50c878;
        }
        
        .stat-card.warning .stat-icon {
            color: #ff9800;
            background: linear-gradient(135deg, #ff980020, #ff980010);
        }
        
        .stat-card.danger .stat-icon {
            color: #f44336;
            background: linear-gradient(135deg, #f4433620, #f4433610);
        }
        
        /* QUICK ACTIONS */
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
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
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #50c878;
        }
        
        .action-btn i {
            font-size: 32px;
            color: #50c878;
            margin-bottom: 10px;
        }
        
        .action-btn h4 {
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .action-btn p {
            font-size: 11px;
            color: #888;
        }
        
        /* RECENT TABLE */
        .recent-table {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .recent-table h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            font-size: 13px;
            color: #666;
            font-weight: 600;
        }
        
        td {
            padding: 12px;
            font-size: 13px;
            color: #555;
            border-bottom: 1px solid #eee;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #ff9800;
        }
        
        .status-disetujui {
            background: #e8f5e9;
            color: #4caf50;
        }
        
        .status-ditolak {
            background: #ffebee;
            color: #f44336;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }
            .main-content {
                margin-left: 0;
            }
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
                <span>Beranda</span>
            </div>
            <div class="menu-item" onclick="location.href='pengeluaran.php'">
                <i class="fas fa-money-bill-wave"></i>
                <span>Pengeluaran Panti</span>
            </div>
            <div class="menu-item" onclick="location.href='doa.php'">
                <i class="fas fa-pray"></i>
                <span>Permohonan Khusus Do'a</span>
            </div>
            <div class="menu-item" onclick="location.href='anak_asuh.php'">
                <i class="fas fa-child"></i>
                <span>Data Anak Asuh</span>
            </div>
            
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Dashboard Pengasuh</h2>
                <p>Selamat datang, <?php echo htmlspecialchars($currentUser['nama_lengkap']); ?></p>
            </div>
            <div class="profile-dropdown">
                <div class="profile-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="profil.php">
                        <i class="fas fa-user-circle"></i>
                        <span>Profil</span>
                    </a>
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- ======================================================
             STATISTIK CARD BARIS 1: PEMASUKAN, PENGELUARAN, SALDO
        ====================================================== -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h4><i class="fas fa-arrow-down"></i> Total Pemasukan Panti</h4>
                    <div class="value">Rp <?php echo number_format($totalPemasukan, 0, ',', '.'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h4><i class="fas fa-arrow-up"></i> Total Pengeluaran Panti</h4>
                    <div class="value">Rp <?php echo number_format($totalPengeluaran, 0, ',', '.'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
            <div class="stat-card saldo">
                <div class="stat-info">
                    <h4><i class="fas fa-wallet"></i> Saldo Panti</h4>
                    <div class="value">Rp <?php echo number_format($saldoPanti, 0, ',', '.'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
        </div>
        
        <!-- ======================================================
             STATISTIK CARD BARIS 2: DOA PENDING, ANAK ASUH, PENGELUARAN DITOLAK
        ====================================================== -->
        <div class="stats-grid">
            <div class="stat-card warning">
                <div class="stat-info">
                    <h4><i class="fas fa-pray"></i> Do'a Khususon Belum Terlaksana</h4>
                    <div class="value"><?php echo $totalDoaPending; ?> Do'a</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h4><i class="fas fa-child"></i> Jumlah Anak Asuh</h4>
                    <div class="value"><?php echo $totalAnakAsuh; ?> Anak</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-baby-carriage"></i>
                </div>
            </div>
            <div class="stat-card danger">
                <div class="stat-info">
                    <h4><i class="fas fa-times-circle"></i> Laporan Pengeluaran Ditolak</h4>
                    <div class="value"><?php echo $totalPengeluaranDitolak; ?> Laporan</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
        </div>
        
        <!-- ======================================================
             QUICK ACTIONS (AKSI CEPAT)
        ====================================================== -->
        <div class="section-title">
            <i class="fas fa-bolt" style="color:#50c878;"></i>
            <span>Aksi Cepat</span>
        </div>
        <div class="quick-actions">
            <a href="anak_asuh.php" class="action-btn">
                <i class="fas fa-child"></i>
                <h4>Tambah Anak Asuh</h4>
                <p>Data anak asuh baru</p>
            </a>
            <a href="pengeluaran.php" class="action-btn">
                <i class="fas fa-money-bill-wave"></i>
                <h4>Input Pengeluaran</h4>
                <p>Catat pengeluaran panti</p>
            </a>
            <a href="doa.php" class="action-btn">
                <i class="fas fa-praying-hands"></i>
                <h4>Verifikasi Do'a</h4>
                <p>Do'a khusus dari donatur</p>
            </a>
        </div>
        
        <!-- ======================================================
             PENGELUARAN TERBARU (RECENT EXPENSES)
        ====================================================== -->
        <div class="recent-table">
            <h3><i class="fas fa-history"></i> Pengeluaran Terbaru</h3>
            <?php
            $queryRecent = "SELECT p.*, k.nama_kategori 
                            FROM pengeluaran p 
                            JOIN kategori_pengeluaran k ON p.kategori_id = k.id 
                            WHERE p.created_by = " . $currentUser['id'] . "
                            ORDER BY p.created_at DESC 
                            LIMIT 5";
            $recentPengeluaran = query($queryRecent);
            ?>
            <?php if (count($recentPengeluaran) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Kategori</th>
                            <th>Deskripsi</th>
                            <th>Nominal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPengeluaran as $pengeluaran): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($pengeluaran['tanggal_pengeluaran'])); ?></td>
                            <td><?php echo htmlspecialchars($pengeluaran['nama_kategori']); ?></td>
                            <td><?php echo htmlspecialchars($pengeluaran['deskripsi']) ?: '-'; ?></td>
                            <td>Rp <?php echo number_format($pengeluaran['nominal'], 0, ',', '.'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $pengeluaran['status']; ?>">
                                    <?php echo ucfirst($pengeluaran['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 20px;">Belum ada data pengeluaran</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>