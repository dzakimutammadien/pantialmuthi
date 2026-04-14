<?php
// ======================================================
// FILE: admin/doa_khusus.php
// HALAMAN DATA DOA KHUSUS UNTUK ADMIN (READ ONLY)
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');
requirePermission('doa.view');

$currentUser = getCurrentUser();

// ======================================================
// FILTER & PAGINATION
// ======================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_periode = isset($_GET['periode']) ? mysqli_real_escape_string($conn, $_GET['periode']) : '';

$where = "WHERE 1=1";

if ($search != '') {
    $where .= " AND (d.catatan_doa LIKE '%$search%' OR donatur.nama_lengkap LIKE '%$search%' OR donatur.username LIKE '%$search%' OR pengasuh.nama_lengkap LIKE '%$search%')";
}
if ($filter_status != '' && $filter_status != 'semua') {
    $where .= " AND d.status_doa = '$filter_status'";
}
if ($filter_periode != '' && $filter_periode != 'semua') {
    switch ($filter_periode) {
        case 'minggu_ini':
            $where .= " AND YEARWEEK(d.created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'bulan_ini':
            $where .= " AND MONTH(d.created_at) = MONTH(CURDATE()) AND YEAR(d.created_at) = YEAR(CURDATE())";
            break;
    }
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_sql = "SELECT COUNT(*) as total FROM doa d 
              JOIN users donatur ON d.user_id = donatur.id 
              LEFT JOIN users pengasuh ON d.dibaca_oleh = pengasuh.id 
              $where";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT d.*, 
        donatur.nama_lengkap as donatur_nama, 
        donatur.username as donatur_username,
        pengasuh.nama_lengkap as pengasuh_nama,
        CASE 
            WHEN d.donasi_id IS NULL THEN 'Titip Doa Manual'
            ELSE CONCAT('Donasi ID: ', d.donasi_id)
        END as sumber
        FROM doa d 
        JOIN users donatur ON d.user_id = donatur.id 
        LEFT JOIN users pengasuh ON d.dibaca_oleh = pengasuh.id 
        $where 
        ORDER BY d.created_at DESC 
        LIMIT $offset, $limit";
$doaList = query($sql);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Doa Khusus - Admin Panti Asuhan Al-Muthi</title>
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
        
        .content-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .filter-section { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-section input, .filter-section select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; }
        .filter-section input { flex: 2; }
        .filter-section select { flex: 1; }
        .btn-filter, .btn-reset { padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-filter { background: #50c878; color: white; }
        .btn-reset { background: #6c757d; color: white; text-decoration: none; display: inline-block; }
        
        .table-wrapper { overflow-x: auto; width: 100%; }
        table { width: 100%; min-width: 800px; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; vertical-align: middle; }
        th { background: #f8f9fa; font-size: 13px; font-weight: 600; color: #666; }
        td { border-bottom: 1px solid #eee; font-size: 13px; color: #555; }
        td:last-child { white-space: nowrap; }
        
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .status-pending { background: #fff3e0; color: #ff9800; }
        .status-dibaca { background: #e3f2fd; color: #2196f3; }
        .status-didoakan { background: #e8f5e9; color: #4caf50; }
        
        .btn-action { padding: 5px 10px; border: none; border-radius: 8px; cursor: pointer; margin: 2px; font-size: 12px; display: inline-block; white-space: nowrap; }
        .btn-detail { background: #17a2b8; color: white; }
        
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; text-decoration: none; }
        .pagination a { background: #f0f2f5; color: #555; }
        .pagination .active { background: #50c878; color: white; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 550px; max-width: 90%; padding: 25px; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .detail-item { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
        .detail-label { font-weight: 600; font-size: 12px; color: #888; margin-bottom: 5px; }
        .detail-value { font-size: 14px; color: #333; }
        .detail-image { text-align: center; margin: 15px 0; }
        .detail-image img { max-width: 100%; max-height: 200px; border-radius: 10px; }
        .modal-footer { display: flex; justify-content: flex-end; margin-top: 20px; }
        .btn-cancel { background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; }
        
        @media (max-width: 768px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/image/almuthi.png" alt="Logo Al-Muthi" class="sidebar-logo" onerror="this.style.display='none'">
            <div><h3>Panti Asuhan</h3><p>Al-Muthi</p></div>
        </div>
        <div class="sidebar-menu">
            <div class="menu-item" onclick="location.href='dashboard.php'"><i class="fas fa-tachometer-alt"></i><span>Beranda</span></div>
            <div class="menu-item" onclick="location.href='users.php'"><i class="fas fa-users"></i><span>Manajemen User</span></div>
            <div class="menu-item has-submenu" onclick="toggleSubmenu(this)"><i class="fas fa-exchange-alt"></i><span>Transaksi</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu">
                <div class="submenu-item" onclick="location.href='verifikasi_donasi.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Donatur</span></div>
                <div class="submenu-item" onclick="location.href='verifikasi_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Pengeluaran Panti</span></div>
                <div class="submenu-item" onclick="location.href='laporan_keuangan.php'"><i class="fas fa-chart-line"></i><span>Laporan Keuangan</span></div>
            </div>
            <div class="menu-item has-submenu open" onclick="toggleSubmenu(this)"><i class="fas fa-database"></i><span>Master Data</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu open">
                <div class="submenu-item" onclick="location.href='kategori_donasi.php'"><i class="fas fa-tags"></i><span>Kategori Transaksi</span></div>
                <div class="submenu-item" onclick="location.href='kategori_role.php'"><i class="fas fa-user-tag"></i><span>Kategori Role</span></div>
                <div class="submenu-item" onclick="location.href='anak_asuh.php'"><i class="fas fa-child"></i><span>Data Anak Asuh</span></div>
                <div class="submenu-item active" onclick="location.href='doa_khusus.php'"><i class="fas fa-pray"></i><span>Data Doa Khusus</span></div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Data Doa Khusus</h2>
                <p>Semua doa dan permohonan dari donatur</p>
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
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="GET" action="" class="filter-section">
                <input type="text" name="search" placeholder="Cari doa, donatur, atau pengasuh..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="periode">
                    <option value="semua">Semua Periode</option>
                    <option value="minggu_ini" <?php echo $filter_periode == 'minggu_ini' ? 'selected' : ''; ?>>Minggu Ini</option>
                    <option value="bulan_ini" <?php echo $filter_periode == 'bulan_ini' ? 'selected' : ''; ?>>Bulan Ini</option>
                </select>
                <select name="status">
                    <option value="semua">Semua Status</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                    <option value="dibaca" <?php echo $filter_status == 'dibaca' ? 'selected' : ''; ?>>Telah Dibaca</option>
                    <option value="didoakan" <?php echo $filter_status == 'didoakan' ? 'selected' : ''; ?>>Terlaksana</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="doa_khusus.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
            </form>
            
            <div class="table-wrapper">
                <h4 style="margin-bottom: 15px;"><i class="fas fa-list"></i> Daftar Doa Khusus</h4>
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Donatur</th>
                            <th>Sumber</th>
                            <th>Isi Doa</th>
                            <th>Status</th>
                            <th>Pengasuh</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($doaList) > 0): $no = $offset + 1; foreach ($doaList as $doa): ?>
                            <?php 
                                $statusClass = '';
                                $statusText = '';
                                if ($doa['status_doa'] == 'pending') {
                                    $statusClass = 'status-pending';
                                    $statusText = 'Menunggu';
                                } elseif ($doa['status_doa'] == 'dibaca') {
                                    $statusClass = 'status-dibaca';
                                    $statusText = 'Telah Dibaca';
                                } else {
                                    $statusClass = 'status-didoakan';
                                    $statusText = 'Terlaksana';
                                }
                            ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($doa['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($doa['donatur_nama']); ?><br><small><?php echo $doa['donatur_username']; ?></small></td>
                                <td><?php echo $doa['sumber']; ?></td>
                                <td><?php echo nl2br(htmlspecialchars(substr($doa['catatan_doa'], 0, 50))) . (strlen($doa['catatan_doa']) > 50 ? '...' : ''); ?></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <td><?php echo htmlspecialchars($doa['pengasuh_nama'] ?: '-'); ?></td>
                                <td>
                                    <button class="btn-action btn-detail" onclick="openDetailModal(<?php echo $doa['id']; ?>)"><i class="fas fa-info-circle"></i> Detail</button>
                        </td>
                            </tr
                        <?php endforeach; else: ?>
                            <tr><td colspan="8" style="text-align:center; padding:40px;">Belum ada data doa</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&periode=<?php echo $filter_periode; ?>&status=<?php echo $filter_status; ?>">« Sebelumnya</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&periode=<?php echo $filter_periode; ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&periode=<?php echo $filter_periode; ?>&status=<?php echo $filter_status; ?>">Selanjutnya »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detail Doa Khusus</h3>
                <span class="close-modal" onclick="closeModal('detailModal')">&times;</span>
            </div>
            <div id="detailContent"></div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal('detailModal')">Tutup</button>
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
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function openDetailModal(id) {
            fetch('get_doa_admin.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let d = data.data;
                        let statusText = d.status_doa == 'pending' ? 'Menunggu' : (d.status_doa == 'dibaca' ? 'Telah Dibaca' : 'Terlaksana');
                        let statusClass = d.status_doa == 'pending' ? 'status-pending' : (d.status_doa == 'dibaca' ? 'status-dibaca' : 'status-didoakan');
                        let imageHtml = '';
                        if (d.bukti_foto) {
                            imageHtml = `<div class="detail-image"><img src="../assets/uploads/doa/${d.bukti_foto}" onclick="window.open(this.src)"></div>`;
                        }
                        
                        document.getElementById('detailContent').innerHTML = `
                            <div class="detail-item"><div class="detail-label">Donatur</div><div class="detail-value">${d.donatur_nama} (${d.donatur_username})</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal Titip</div><div class="detail-value">${d.created_at}</div></div>
                            <div class="detail-item"><div class="detail-label">ID Donasi</div><div class="detail-value">${d.donasi_id || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Sumber</div><div class="detail-value">${d.sumber}</div></div>
                            <div class="detail-item"><div class="detail-label">Isi Doa</div><div class="detail-value">${d.catatan_doa.replace(/\n/g, '<br>')}</div></div>
                            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value"><span class="status-badge ${statusClass}">${statusText}</span></div></div>
                            ${imageHtml}
                            <div class="detail-item"><div class="detail-label">Dilaksanakan Oleh</div><div class="detail-value">${d.pengasuh_nama || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal Dilaksanakan</div><div class="detail-value">${d.dibaca_at || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Keterangan</div><div class="detail-value">${d.keterangan || '-'}</div></div>
                        `;
                        document.getElementById('detailModal').classList.add('show');
                    }
                });
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>