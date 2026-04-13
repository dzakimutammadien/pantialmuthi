<?php
// ======================================================
// FILE: admin/log_aktivitas.php
// HALAMAN LOG AKTIVITAS UNTUK ADMIN
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');
requirePermission('log_aktivitas.view');

$currentUser = getCurrentUser();

// ======================================================
// FILTER & PAGINATION
// ======================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_user = isset($_GET['user']) ? (int)$_GET['user'] : '';
$filter_periode = isset($_GET['periode']) ? mysqli_real_escape_string($conn, $_GET['periode']) : '';
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? mysqli_real_escape_string($conn, $_GET['tanggal_mulai']) : '';
$tanggal_selesai = isset($_GET['tanggal_selesai']) ? mysqli_real_escape_string($conn, $_GET['tanggal_selesai']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

$where = "WHERE 1=1";

// Search
if ($search != '') {
    $where .= " AND (l.aktivitas LIKE '%$search%' OR u.nama_lengkap LIKE '%$search%' OR u.username LIKE '%$search%')";
}

// Filter user
if ($filter_user != '' && $filter_user > 0) {
    $where .= " AND l.user_id = $filter_user";
}

// Filter periode
if ($filter_periode != '' && $filter_periode != 'custom') {
    switch ($filter_periode) {
        case 'hari_ini':
            $where .= " AND DATE(l.created_at) = CURDATE()";
            break;
        case 'minggu_ini':
            $where .= " AND YEARWEEK(l.created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'bulan_ini':
            $where .= " AND MONTH(l.created_at) = MONTH(CURDATE()) AND YEAR(l.created_at) = YEAR(CURDATE())";
            break;
        case 'bulan_lalu':
            $where .= " AND MONTH(l.created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(l.created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
            break;
    }
} elseif ($filter_periode == 'custom' && $tanggal_mulai != '' && $tanggal_selesai != '') {
    $where .= " AND DATE(l.created_at) BETWEEN '$tanggal_mulai' AND '$tanggal_selesai'";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_sql = "SELECT COUNT(*) as total FROM log_aktivitas l 
              JOIN users u ON l.user_id = u.id 
              $where";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT l.*, u.nama_lengkap, u.username, r.nama_role
        FROM log_aktivitas l 
        JOIN users u ON l.user_id = u.id 
        JOIN roles r ON u.role_id = r.id
        $where 
        ORDER BY l.created_at DESC 
        LIMIT $offset, $limit";
$logs = query($sql);

// Ambil daftar user untuk filter
$users = query("SELECT id, nama_lengkap, username FROM users ORDER BY nama_lengkap ASC");

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas - Admin Panti Asuhan Al-Muthi</title>
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
-align-items: center;
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
        
        .submenu {
            padding-left: 56px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .submenu.open {
            max-height: 300px;
        }
        
        .submenu-item {
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.7);
            font-size: 13px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .submenu-item:hover {
            color: #50c878;
            padding-left: 25px;
        }
        
        .submenu-item i {
            width: 20px;
            font-size: 14px;
        }
        
        .menu-item.has-submenu {
            position: relative;
        }
        
        .menu-item.has-submenu .arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
        }
        
        .menu-item.has-submenu.open .arrow {
            transform: rotate(180deg);
        }
        
        
        /* MAIN CONTENT */
        .main-content { margin-left: 280px; padding: 20px; min-height: 100vh; }
        
        /* TOPBAR */
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .page-title h2 { font-size: 20px; color: #333; }
        .page-title p { font-size: 13px; color: #888; margin-top: 5px; }
        .profile-dropdown { position: relative; }
        .profile-icon { width: 45px; height: 45px; background: linear-gradient(135deg, #50c878, #2e8b57); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; color: white; }
        .dropdown-menu { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 1000; }
        .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        .dropdown-menu a:hover { background: #f5f5f5; color: #50c878; }
        
        /* CONTENT */
        .content-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .filter-section { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-section input, .filter-section select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; }
        .filter-section input { flex: 2; }
        .filter-section select { flex: 1; }
        .date-range { display: flex; gap: 10px; align-items: center; }
        .date-range input { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; }
        .btn-filter, .btn-reset, .btn-export { padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-filter { background: #50c878; color: white; }
        .btn-reset { background: #6c757d; color: white; text-decoration: none; display: inline-block; }
        .btn-export { background: #28a745; color: white; }
        .btn-refresh { background: #17a2b8; color: white; padding: 10px 15px; border: none; border-radius: 10px; cursor: pointer; }
        
        /* TABLE */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .badge-role { background: #e3f2fd; color: #2196f3; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        
        /* PAGINATION */
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; text-decoration: none; }
        .pagination a { background: #f0f2f5; color: #555; }
        .pagination .active { background: #50c878; color: white; }
        .per-page { display: flex; align-items: center; gap: 10px; margin-left: auto; }
        .per-page select { padding: 8px; border-radius: 8px; border: 1px solid #ddd; }
        
        @media (max-width: 768px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } .date-range { flex-wrap: wrap; } }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/image/almuthi.png" alt="Logo" class="sidebar-logo" onerror="this.style.display='none'">
            <div><h3>Panti Asuhan</h3><p>Al-Muthi</p></div>
        </div>
        <div class="sidebar-menu">
            <div class="menu-item" onclick="location.href='dashboard.php'"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></div>
            <div class="menu-item" onclick="location.href='users.php'"><i class="fas fa-users"></i><span>Manajemen User</span></div>
            <div class="menu-item has-submenu" onclick="toggleSubmenu(this)"><i class="fas fa-exchange-alt"></i><span>Transaksi</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu">
                <div class="submenu-item" onclick="location.href='donasi_donatur.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Donatur</span></div>
                <div class="submenu-item" onclick="location.href='verifikasi_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Pengeluaran Panti</span></div>
                <div class="submenu-item" onclick="location.href='laporan_keuangan.php'"><i class="fas fa-chart-line"></i><span>Laporan Keuangan</span></div>
            </div>
            <div class="menu-item has-submenu" onclick="toggleSubmenu(this)"><i class="fas fa-database"></i><span>Master Data</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu">
                <div class="submenu-item" onclick="location.href='kategori_donasi.php'"><i class="fas fa-tags"></i><span>Kategori Transaksi</span></div>
                <div class="submenu-item" onclick="location.href='kategori_role.php'"><i class="fas fa-user-tag"></i><span>Kategori Role</span></div>
                <div class="submenu-item" onclick="location.href='anak_asuh.php'"><i class="fas fa-child"></i><span>Data Anak Asuh</span></div>
                <div class="submenu-item active" onclick="location.href='log_aktivitas.php'"><i class="fas fa-history"></i><span>Log Aktivitas</span></div>
            </div>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Log Aktivitas</h2>
                <p>Riwayat aktivitas semua pengguna sistem</p>
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
        
        <div class="content-card">
            <!-- FILTER FORM -->
            <form method="GET" action="" class="filter-section" id="filterForm">
                <input type="text" name="search" placeholder="Cari aktivitas atau user..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="user">
                    <option value="">Semua User</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filter_user == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['nama_lengkap']); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select name="periode" id="periode" onchange="toggleDateRange()">
                    <option value="">Semua Periode</option>
                    <option value="hari_ini" <?php echo $filter_periode == 'hari_ini' ? 'selected' : ''; ?>>Hari Ini</option>
                    <option value="minggu_ini" <?php echo $filter_periode == 'minggu_ini' ? 'selected' : ''; ?>>Minggu Ini</option>
                    <option value="bulan_ini" <?php echo $filter_periode == 'bulan_ini' ? 'selected' : ''; ?>>Bulan Ini</option>
                    <option value="bulan_lalu" <?php echo $filter_periode == 'bulan_lalu' ? 'selected' : ''; ?>>Bulan Lalu</option>
                    <option value="custom" <?php echo $filter_periode == 'custom' ? 'selected' : ''; ?>>Custom</option>
                </select>
                
                <div id="dateRangeInput" class="date-range" style="display: <?php echo $filter_periode == 'custom' ? 'flex' : 'none'; ?>;">
                    <input type="date" name="tanggal_mulai" value="<?php echo $tanggal_mulai; ?>" placeholder="Mulai">
                    <span>-</span>
                    <input type="date" name="tanggal_selesai" value="<?php echo $tanggal_selesai; ?>" placeholder="Selesai">
                </div>
                
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="log_aktivitas.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
                <button type="button" class="btn-export" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Export Excel</button>
                
                <div class="per-page">
                    <span>Show:</span>
                    <select name="limit" onchange="this.form.submit()">
                        <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
            </form>
            
            <!-- TABLE -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Waktu</th>
                            <th>Tanggal</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Aktivitas</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): $no = $offset + 1; foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo date('H:i:s', strtotime($log['created_at'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['nama_lengkap']); ?> <br><small><?php echo $log['username']; ?></small></td>
                            <td><span class="badge-role"><?php echo ucfirst($log['nama_role']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['aktivitas']); ?></td>
                            <td><?php echo $log['ip_address'] ?: '-'; ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:40px;">Tidak ada data log aktivitas</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&user=<?php echo $filter_user; ?>&periode=<?php echo $filter_periode; ?>&tanggal_mulai=<?php echo $tanggal_mulai; ?>&tanggal_selesai=<?php echo $tanggal_selesai; ?>&limit=<?php echo $limit; ?>">« Sebelumnya</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&user=<?php echo $filter_user; ?>&periode=<?php echo $filter_periode; ?>&tanggal_mulai=<?php echo $tanggal_mulai; ?>&tanggal_selesai=<?php echo $tanggal_selesai; ?>&limit=<?php echo $limit; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&user=<?php echo $filter_user; ?>&periode=<?php echo $filter_periode; ?>&tanggal_mulai=<?php echo $tanggal_mulai; ?>&tanggal_selesai=<?php echo $tanggal_selesai; ?>&limit=<?php echo $limit; ?>">Selanjutnya »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 15px; text-align: center; font-size: 12px; color: #888;">
                Total data: <?php echo $total_rows; ?>
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
            var dateRange = document.getElementById('dateRangeInput');
            if (periode == 'custom') {
                dateRange.style.display = 'flex';
            } else {
                dateRange.style.display = 'none';
            }
        }
        
        function exportToExcel() {
            var form = document.getElementById('filterForm');
            var currentAction = form.action;
            form.action = 'export_log_excel.php';
            form.submit();
            form.action = currentAction;
        }
    </script>
</body>
</html>