<?php
// ======================================================
// FILE: admin/laporan_keuangan.php
// HALAMAN LAPORAN KEUANGAN UNTUK ADMIN
// DENGAN PENYALURAN PROGRAM & PAGINATION
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');
requirePermission('laporan.view');

$currentUser = getCurrentUser();

// ======================================================
// FILTER PERIODE
// ======================================================
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');
$filter_periode = isset($_GET['periode']) ? $_GET['periode'] : 'bulan_ini';

if ($filter_periode == 'bulan_ini') {
    $bulan = date('m');
    $tahun = date('Y');
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
} elseif ($filter_periode == 'tahun_ini') {
    $start_date = date('Y-01-01');
    $end_date = date('Y-12-31');
} elseif ($filter_periode == 'custom') {
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
} else {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

// ======================================================
// PEMASUKAN: Donasi Biasa + Donasi Program
// ======================================================
$sql_pemasukan_biasa = "SELECT SUM(nominal) as total FROM donasi 
                        WHERE status = 'success' 
                        AND DATE(tanggal_donasi) BETWEEN '$start_date' AND '$end_date'";
$result_pemasukan_biasa = mysqli_query($conn, $sql_pemasukan_biasa);
$total_pemasukan_biasa = mysqli_fetch_assoc($result_pemasukan_biasa)['total'] ?? 0;

$sql_pemasukan_program = "SELECT SUM(nominal) as total FROM donasi_program 
                          WHERE status = 'success' 
                          AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
$result_pemasukan_program = mysqli_query($conn, $sql_pemasukan_program);
$total_pemasukan_program = mysqli_fetch_assoc($result_pemasukan_program)['total'] ?? 0;

$total_pemasukan = $total_pemasukan_biasa + $total_pemasukan_program;

// ======================================================
// PENGELUARAN OPERASIONAL (disetujui)
// ======================================================
$sql_pengeluaran = "SELECT SUM(nominal) as total FROM pengeluaran 
                    WHERE status = 'disetujui' 
                    AND DATE(tanggal_pengeluaran) BETWEEN '$start_date' AND '$end_date'";
$result_pengeluaran = mysqli_query($conn, $sql_pengeluaran);
$total_pengeluaran = mysqli_fetch_assoc($result_pengeluaran)['total'] ?? 0;

// ======================================================
// PENYALURAN PROGRAM (penerima_manfaat)
// ======================================================
$sql_penyaluran = "SELECT SUM(jumlah) as total FROM penerima_manfaat 
                   WHERE DATE(tanggal_penyaluran) BETWEEN '$start_date' AND '$end_date'";
$result_penyaluran = mysqli_query($conn, $sql_penyaluran);
$total_penyaluran = mysqli_fetch_assoc($result_penyaluran)['total'] ?? 0;

// TOTAL PENGELUARAN = Operasional + Penyaluran
$total_pengeluaran_all = $total_pengeluaran + $total_penyaluran;

// Saldo
$saldo = $total_pemasukan - $total_pengeluaran_all;

// ======================================================
// PAGINATION UNTUK RINCIAN TRANSAKSI
// ======================================================
$limit_rincian = 20;
$page_rincian = isset($_GET['page_rincian']) ? (int)$_GET['page_rincian'] : 1;
$offset_rincian = ($page_rincian - 1) * $limit_rincian;

// ======================================================
// RINCIAN TRANSAKSI (dengan pagination)
// ======================================================
$sql_rincian_base = "
    (SELECT 
        tanggal_donasi as tanggal,
        'Pemasukan' as jenis,
        'biasa' as tipe,
        u.nama_lengkap as nama,
        k.nama_kategori as kategori,
        d.keterangan as keterangan,
        d.nominal as jumlah,
        0 as keluar,
        NULL as penerima
    FROM donasi d
    JOIN users u ON d.user_id = u.id
    JOIN kategori_donasi k ON d.kategori_id = k.id
    WHERE d.status = 'success' 
    AND DATE(d.tanggal_donasi) BETWEEN '$start_date' AND '$end_date')
    
    UNION ALL
    
    (SELECT 
        dp.created_at as tanggal,
        'Pemasukan' as jenis,
        'program' as tipe,
        u.nama_lengkap as nama,
        p.nama_program as kategori,
        dp.pesan as keterangan,
        dp.nominal as jumlah,
        0 as keluar,
        NULL as penerima
    FROM donasi_program dp
    JOIN users u ON dp.user_id = u.id
    JOIN program_donasi p ON dp.program_id = p.id
    WHERE dp.status = 'success' 
    AND DATE(dp.created_at) BETWEEN '$start_date' AND '$end_date')
    
    UNION ALL
    
    (SELECT 
        p.tanggal_pengeluaran as tanggal,
        'Pengeluaran' as jenis,
        'operasional' as tipe,
        u.nama_lengkap as nama,
        k.nama_kategori as kategori,
        p.deskripsi as keterangan,
        0 as masuk,
        p.nominal as keluar,
        NULL as penerima
    FROM pengeluaran p
    JOIN users u ON p.created_by = u.id
    JOIN kategori_donasi k ON p.kategori_id = k.id
    WHERE p.status = 'disetujui' 
    AND DATE(p.tanggal_pengeluaran) BETWEEN '$start_date' AND '$end_date')
    
    UNION ALL
    
    (SELECT 
        pm.tanggal_penyaluran as tanggal,
        'Pengeluaran' as jenis,
        'penyaluran' as tipe,
        'Penyaluran Program' as nama,
        CONCAT('Penyaluran - ', pm.jenis_bantuan) as kategori,
        CONCAT('Penerima: ', pm.nama_penerima, IF(pm.keterangan != '', CONCAT(' | ', pm.keterangan), '')) as keterangan,
        0 as masuk,
        pm.jumlah as keluar,
        pm.nama_penerima as penerima
    FROM penerima_manfaat pm
    WHERE DATE(pm.tanggal_penyaluran) BETWEEN '$start_date' AND '$end_date')
    
    ORDER BY tanggal DESC
";

// Hitung total data untuk pagination
$count_sql = "SELECT COUNT(*) as total FROM ($sql_rincian_base) as total_data";
$count_result = mysqli_query($conn, $count_sql);
$total_rincian = mysqli_fetch_assoc($count_result)['total'];
$total_pages_rincian = ceil($total_rincian / $limit_rincian);

// Ambil data dengan LIMIT
$sql_rincian = $sql_rincian_base . " LIMIT $offset_rincian, $limit_rincian";
$rincian = query($sql_rincian);

// ======================================================
// RINCIAN PER KATEGORI
// ======================================================
$sql_kategori = "
    SELECT 
        k.id,
        k.nama_kategori,
        COALESCE((SELECT SUM(d.nominal) FROM donasi d WHERE d.kategori_id = k.id AND d.status = 'success' AND DATE(d.tanggal_donasi) BETWEEN '$start_date' AND '$end_date'), 0) as pemasukan_biasa,
        COALESCE((SELECT SUM(p.nominal) FROM pengeluaran p WHERE p.kategori_id = k.id AND p.status = 'disetujui' AND DATE(p.tanggal_pengeluaran) BETWEEN '$start_date' AND '$end_date'), 0) as pengeluaran
    FROM kategori_donasi k
    WHERE k.tipe IN ('donasi', 'both') OR k.tipe IN ('pengeluaran', 'both')
    GROUP BY k.id
    ORDER BY k.nama_kategori ASC";
$kategori_laporan = query($sql_kategori);

// Kategori Program
$sql_program_kategori = "
    SELECT 
        p.id,
        p.nama_program as nama_kategori,
        COALESCE((SELECT SUM(dp.nominal) FROM donasi_program dp WHERE dp.program_id = p.id AND dp.status = 'success' AND DATE(dp.created_at) BETWEEN '$start_date' AND '$end_date'), 0) as pemasukan_program,
        0 as pengeluaran
    FROM program_donasi p
    HAVING pemasukan_program > 0
    ORDER BY p.nama_program ASC";
$program_kategori = query($sql_program_kategori);

// Kategori Penyaluran
$sql_penyaluran_kategori = "
    SELECT 
        'Penyaluran Program' as nama_kategori,
        0 as pemasukan_biasa,
        COALESCE((SELECT SUM(jumlah) FROM penerima_manfaat WHERE DATE(tanggal_penyaluran) BETWEEN '$start_date' AND '$end_date'), 0) as pengeluaran
";
$penyaluran = mysqli_query($conn, $sql_penyaluran_kategori);
$penyaluran_kategori = mysqli_fetch_assoc($penyaluran);
$penyaluran_kategori = $penyaluran_kategori && $penyaluran_kategori['pengeluaran'] > 0 ? [$penyaluran_kategori] : [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - Admin Panti Asuhan Al-Muthi</title>
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
        .submenu { padding-left: 56px; max-height: 0; overflow: hidden; transition: max-height 0.3s; }
        .submenu.open { max-height: 300px; }
        .submenu-item { padding: 10px 20px; display: flex; align-items: center; gap: 12px; cursor: pointer; color: rgba(255,255,255,0.7); font-size: 13px; }
        .submenu-item:hover { color: #50c878; padding-left: 25px; }
        .menu-item.has-submenu .arrow { margin-left: auto; transition: transform 0.3s; font-size: 12px; }
        .menu-item.has-submenu.open .arrow { transform: rotate(180deg); }
        .badge-pending {
    background: #f44336;
    color: white;
    padding: 1px 8px;
    border-radius: 20px;
    font-size: 10px;
    margin-left: auto;
        }

        .main-content { margin-left: 280px; padding: 20px; min-height: 100vh; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .page-title h2 { font-size: 20px; color: #333; }
        .page-title p { font-size: 13px; color: #888; margin-top: 5px; }
        .profile-dropdown { position: relative; }
        .profile-icon { width: 45px; height: 45px; background: linear-gradient(135deg, #50c878, #2e8b57); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; color: white; }
        .dropdown-menu { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 1000; }
        .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        .dropdown-menu a:hover { background: #f5f5f5; color: #50c878; }
        
        .content-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: flex-end;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
        }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 12px; color: #666; font-weight: 500; }
        .filter-group select, .filter-group input { padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
        .btn-filter { background: #50c878; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .btn-reset { background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-export-excel { background: #28a745; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; text-decoration: none; display: inline-block; text-align: center; font-family: inherit; }
        .btn-export-pdf { background: #dc3545; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; text-decoration: none; display: inline-block; text-align: center; font-family: inherit; }
        
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .summary-card { background: white; border-radius: 20px; padding: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .summary-info h4 { font-size: 12px; color: #888; margin-bottom: 8px; }
        .summary-info .value { font-size: 22px; font-weight: 700; color: #333; }
        .summary-info small { font-size: 11px; color: #999; display: block; margin-top: 3px; }
        .summary-icon { width: 50px; height: 50px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .summary-card.pemasukan .summary-icon { background: #e8f5e9; color: #4caf50; }
        .summary-card.pengeluaran .summary-icon { background: #ffebee; color: #f44336; }
        .summary-card.penyaluran .summary-icon { background: #fff3e0; color: #ff9800; }
        .summary-card.saldo .summary-icon { background: #e3f2fd; color: #2196f3; }
        .summary-card.saldo .value { color: #2196f3; }
        
        .table-wrapper { overflow-x: auto; width: 100%; }
        table { width: 100%; min-width: 750px; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-size: 12px; font-weight: 600; color: #666; }
        td { font-size: 13px; color: #555; }
        .text-right { text-align: right; }
        .badge-masuk { background: #e8f5e9; color: #4caf50; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .badge-keluar { background: #ffebee; color: #f44336; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .badge-tipe-program { background: #f3e5f5; color: #9c27b0; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .badge-tipe-biasa { background: #e3f2fd; color: #2196f3; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .badge-tipe-penyaluran { background: #fff3e0; color: #e65100; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .badge-tipe-operasional { background: #fce4ec; color: #c62828; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        
        .section-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        /* PAGINATION STYLES */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s;
        }
        .pagination a {
            background: #f0f2f5;
            color: #555;
            border: 1px solid #e0e0e0;
        }
        .pagination a:hover {
            background: #50c878;
            color: white;
            border-color: #50c878;
        }
        .pagination .active {
            background: #50c878;
            color: white;
            border: 1px solid #50c878;
        }
        .pagination .info {
            font-size: 12px;
            color: #888;
            padding: 6px 12px;
            display: flex;
            align-items: center;
        }
        
        @media (max-width: 768px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/image/almuthi.png" alt="Logo Al-Muthi" class="sidebar-logo" onerror="this.style.display='none'">
            <div><h3>Panti Asuhan</h3><p>Al-Muthi</p></div>
        </div>
        <div class="sidebar-menu">
            <div class="menu-item" onclick="location.href='dashboard.php'"><i class="fas fa-tachometer-alt"></i><span>Beranda</span></div>
            <div class="menu-item" onclick="location.href='users.php'"><i class="fas fa-users"></i><span>Manajemen User</span></div>
            <div class="menu-item has-submenu open" onclick="toggleSubmenu(this)"><i class="fas fa-exchange-alt"></i><span>Transaksi</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu open">
                <div class="submenu-item" onclick="location.href='verifikasi_donasi.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Donatur</span></div>
                <div class="submenu-item" onclick="location.href='verifikasi_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Pengeluaran Panti</span></div>
                <div class="submenu-item" onclick="location.href='verifikasi_program.php'"><i class="fas fa-heart"></i><span>Verifikasi Program</span></div>
                <div class="submenu-item active" onclick="location.href='laporan_keuangan.php'"><i class="fas fa-chart-line"></i><span>Laporan Keuangan</span></div>
            </div>
            <div class="menu-item has-submenu" onclick="toggleSubmenu(this)"><i class="fas fa-database"></i><span>Master Data</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu">
                <div class="submenu-item" onclick="location.href='kategori_donasi.php'"><i class="fas fa-tags"></i><span>Kategori Transaksi</span></div>
                <div class="submenu-item" onclick="location.href='kategori_role.php'"><i class="fas fa-user-tag"></i><span>Kategori Role</span></div>
                <div class="submenu-item" onclick="location.href='anak_asuh.php'"><i class="fas fa-child"></i><span>Data Anak Asuh</span></div>
                <div class="submenu-item" onclick="location.href='program.php'"><i class="fas fa-chalkboard-user"></i><span>Program Utama</span></div>
                <div class="submenu-item" onclick="location.href='galeri.php'"><i class="fas fa-images"></i><span>Galeri</span></div>
                <div class="submenu-item" onclick="location.href='perkembangan.php'"><i class="fas fa-seedling"></i><span>Perkembangan Anak</span></div>
                <div class="submenu-item" onclick="location.href='doa_khusus.php'"><i class="fas fa-pray"></i><span>Data Doa Khusus</span></div>
            </div>
             <div class="menu-item" onclick="location.href='verifikasi_pendaftaran.php'">
    <i class="fas fa-user-check"></i>
    <span>Verifikasi Akun</span>
    <?php 
    $pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pendaftaran WHERE status = 'pending'"))['total'];
    if ($pending_count > 0): 
    ?>
        <span class="badge-pending"><?php echo $pending_count; ?></span>
    <?php endif; ?>
</div>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Laporan Keuangan</h2>
                <p>Rekap pemasukan, pengeluaran operasional, dan penyaluran program</p>
            </div>
            <div class="profile-dropdown">
                <div class="profile-icon"><i class="fas fa-cog"></i></div>
                <div class="dropdown-menu">
                    <a href="profil.php"><i class="fas fa-user-circle"></i> Profil</a>
                    <a href="log_aktivitas.php"><i class="fas fa-history"></i> Log Aktivitas</a>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
        
        <!-- FILTER -->
        <div class="filter-section">
            <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div class="filter-group">
                    <label>Periode</label>
                    <select name="periode" id="periode" onchange="toggleDateRange()">
                        <option value="bulan_ini" <?php echo $filter_periode == 'bulan_ini' ? 'selected' : ''; ?>>Bulan Ini</option>
                        <option value="tahun_ini" <?php echo $filter_periode == 'tahun_ini' ? 'selected' : ''; ?>>Tahun Ini</option>
                        <option value="custom" <?php echo $filter_periode == 'custom' ? 'selected' : ''; ?>>Custom</option>
                    </select>
                </div>
                <div id="dateRange" style="display: <?php echo $filter_periode == 'custom' ? 'flex' : 'none'; ?>; gap: 10px;">
                    <div class="filter-group">
                        <label>Dari Tanggal</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                </div>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Tampilkan</button>
                <a href="laporan_keuangan.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
                <button type="button" class="btn-export-excel" onclick="exportExcel()"><i class="fas fa-file-excel"></i> Export Excel</button>
                <a href="export_pdf.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn-export-pdf" target="_blank"><i class="fas fa-file-pdf"></i> Export PDF</a>
            </form>
        </div>
        
        <!-- SUMMARY CARDS -->
        <div class="summary-grid">
            <div class="summary-card pemasukan">
                <div class="summary-info">
                    <h4><i class="fas fa-arrow-down"></i> Total Pemasukan</h4>
                    <div class="value">Rp <?php echo number_format($total_pemasukan, 0, ',', '.'); ?></div>
                    <small>Biasa: Rp <?php echo number_format($total_pemasukan_biasa, 0, ',', '.'); ?> + Program: Rp <?php echo number_format($total_pemasukan_program, 0, ',', '.'); ?></small>
                </div>
                <div class="summary-icon"><i class="fas fa-hand-holding-usd"></i></div>
            </div>
            
            <div class="summary-card pengeluaran">
                <div class="summary-info">
                    <h4><i class="fas fa-arrow-up"></i> Total Pengeluaran</h4>
                    <div class="value">Rp <?php echo number_format($total_pengeluaran_all, 0, ',', '.'); ?></div>
                    <small>Operasional: Rp <?php echo number_format($total_pengeluaran, 0, ',', '.'); ?> + Penyaluran: Rp <?php echo number_format($total_penyaluran, 0, ',', '.'); ?></small>
                </div>
                <div class="summary-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
            
            <div class="summary-card penyaluran">
                <div class="summary-info">
                    <h4><i class="fas fa-hands-helping"></i> Total Penyaluran</h4>
                    <div class="value">Rp <?php echo number_format($total_penyaluran, 0, ',', '.'); ?></div>
                    <small>Ke penerima manfaat</small>
                </div>
                <div class="summary-icon"><i class="fas fa-users"></i></div>
            </div>
            
            <div class="summary-card saldo">
                <div class="summary-info">
                    <h4><i class="fas fa-wallet"></i> Saldo Panti</h4>
                    <div class="value">Rp <?php echo number_format($saldo, 0, ',', '.'); ?></div>
                    <small>Pemasukan - Pengeluaran</small>
                </div>
                <div class="summary-icon"><i class="fas fa-coins"></i></div>
            </div>
        </div>
        
        <!-- RINCIAN TRANSAKSI -->
        <div class="content-card">
            <div class="section-title">
                <i class="fas fa-list-ul" style="color:#50c878;"></i>
                <span>Rincian Transaksi</span>
                <span style="font-size:12px;color:#888;font-weight:400;margin-left:10px;">
                    <span class="badge-tipe-biasa">💝 Biasa</span>
                    <span class="badge-tipe-program">📦 Program</span>
                    <span class="badge-tipe-operasional">💸 Operasional</span>
                    <span class="badge-tipe-penyaluran">🤝 Penyaluran</span>
                </span>
                <span style="font-size:12px;color:#888;font-weight:400;margin-left:auto;">
                    Total: <?php echo $total_rincian; ?> data
                </span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Tipe</th>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Keterangan</th>
                            <th class="text-right">Masuk (Rp)</th>
                            <th class="text-right">Keluar (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rincian) > 0): ?>
                            <?php foreach ($rincian as $r): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($r['tanggal'])); ?></td>
                                    <td>
                                        <?php if ($r['jenis'] == 'Pemasukan'): ?>
                                            <?php if ($r['tipe'] == 'program'): ?>
                                                <span class="badge-tipe-program">📦 Program</span>
                                            <?php else: ?>
                                                <span class="badge-tipe-biasa">💝 Biasa</span>
                                            <?php endif; ?>
                                        <?php elseif ($r['tipe'] == 'penyaluran'): ?>
                                            <span class="badge-tipe-penyaluran">🤝 Penyaluran</span>
                                        <?php else: ?>
                                            <span class="badge-tipe-operasional">💸 Operasional</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($r['kategori']); ?></td>
                                    <td><?php echo htmlspecialchars($r['keterangan']) ?: '-'; ?></td>
                                    <td class="text-right">
                                        <?php if ($r['jenis'] == 'Pemasukan'): ?>
                                            <span class="badge-masuk">Rp <?php echo number_format($r['jumlah'], 0, ',', '.'); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <?php if ($r['jenis'] == 'Pengeluaran'): ?>
                                            <span class="badge-keluar">Rp <?php echo number_format($r['keluar'], 0, ',', '.'); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:40px;">Tidak ada transaksi pada periode ini</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINATION RINCIAN TRANSAKSI -->
            <?php if ($total_pages_rincian > 1): ?>
            <div class="pagination">
                <?php if ($page_rincian > 1): ?>
                    <a href="?page_rincian=<?php echo $page_rincian-1; ?>&periode=<?php echo $filter_periode; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">« Sebelumnya</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages_rincian; $i++): ?>
                    <?php if ($i == $page_rincian): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page_rincian=<?php echo $i; ?>&periode=<?php echo $filter_periode; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page_rincian < $total_pages_rincian): ?>
                    <a href="?page_rincian=<?php echo $page_rincian+1; ?>&periode=<?php echo $filter_periode; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">Selanjutnya »</a>
                <?php endif; ?>
                
                <span class="info">Halaman <?php echo $page_rincian; ?> dari <?php echo $total_pages_rincian; ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- RINCIAN PER KATEGORI -->
        <div class="content-card">
            <div class="section-title">
                <i class="fas fa-chart-pie" style="color:#50c878;"></i>
                <span>Rincian Per Kategori</span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th class="text-right">Pemasukan (Rp)</th>
                            <th class="text-right">Pengeluaran (Rp)</th>
                            <th class="text-right">Selisih (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hasData = false;
                        foreach ($kategori_laporan as $k) {
                            if ($k['pemasukan_biasa'] > 0 || $k['pengeluaran'] > 0) {
                                $hasData = true;
                                $selisih = $k['pemasukan_biasa'] - $k['pengeluaran'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($k['nama_kategori']); ?></td>
                                    <td class="text-right"><?php echo $k['pemasukan_biasa'] > 0 ? 'Rp ' . number_format($k['pemasukan_biasa'], 0, ',', '.') : '-'; ?></td>
                                    <td class="text-right"><?php echo $k['pengeluaran'] > 0 ? 'Rp ' . number_format($k['pengeluaran'], 0, ',', '.') : '-'; ?></td>
                                    <td class="text-right">
                                        <span style="color: <?php echo $selisih >= 0 ? '#4caf50' : '#f44336'; ?>">
                                            <?php echo $selisih >= 0 ? '+' : '-'; ?>Rp <?php echo number_format(abs($selisih), 0, ',', '.'); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        
                        foreach ($program_kategori as $p) {
                            $hasData = true;
                            ?>
                            <tr>
                                <td>📦 <?php echo htmlspecialchars($p['nama_kategori']); ?> (Program)</td>
                                <td class="text-right">Rp <?php echo number_format($p['pemasukan_program'], 0, ',', '.'); ?></td>
                                <td class="text-right">-</td>
                                <td class="text-right">
                                    <span style="color: #4caf50;">
                                        +Rp <?php echo number_format($p['pemasukan_program'], 0, ',', '.'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php
                        }
                        
                        foreach ($penyaluran_kategori as $pen) {
                            if ($pen['pengeluaran'] > 0) {
                                $hasData = true;
                                ?>
                                <tr>
                                    <td>🤝 <?php echo htmlspecialchars($pen['nama_kategori']); ?></td>
                                    <td class="text-right">-</td>
                                    <td class="text-right">Rp <?php echo number_format($pen['pengeluaran'], 0, ',', '.'); ?></td>
                                    <td class="text-right">
                                        <span style="color: #f44336;">
                                            -Rp <?php echo number_format($pen['pengeluaran'], 0, ',', '.'); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        
                        if (!$hasData):
                        ?>
                            <tr><td colspan="4" style="text-align:center; padding:40px;">Tidak ada data kategori</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function toggleSubmenu(e) {
            e.classList.toggle('open');
            let s = e.nextElementSibling;
            if(s && s.classList.contains('submenu')) {
                s.classList.toggle('open');
            }
        }
        
        function toggleDateRange() {
            var periode = document.getElementById('periode').value;
            var dateRange = document.getElementById('dateRange');
            if (periode == 'custom') {
                dateRange.style.display = 'flex';
            } else {
                dateRange.style.display = 'none';
            }
        }
        
        function exportExcel() {
            let params = new URLSearchParams(window.location.search);
            window.location.href = 'export_laporan_excel.php?' + params.toString();
        }
    </script>
</body>
</html>