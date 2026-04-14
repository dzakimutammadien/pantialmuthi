<?php
// ======================================================
// FILE: admin/verifikasi_donasi.php
// HALAMAN VERIFIKASI DONASI UNTUK ADMIN
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');
requirePermission('verifikasi_donasi.view');

$currentUser = getCurrentUser();

// ======================================================
// PROSES VERIFIKASI
// ======================================================
if (isset($_POST['verifikasi'])) {
    $id = (int)$_POST['id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $verified_by = $currentUser['id'];
    $verified_at = date('Y-m-d H:i:s');
    
    // Ambil data donasi sebelum update
    $query = mysqli_query($conn, "SELECT user_id, catatan_doa FROM donasi WHERE id = $id");
    $donasi = mysqli_fetch_assoc($query);
    
    $sql = "UPDATE donasi SET status = '$status', verified_by = $verified_by, verified_at = '$verified_at' WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        // Jika status menjadi success dan ada catatan doa, trigger akan otomatis insert ke tabel doa
        logActivity($currentUser['id'], "Verifikasi donasi ID: $id => $status");
        $_SESSION['success'] = "Donasi berhasil diverifikasi!";
    } else {
        $_SESSION['error'] = "Gagal verifikasi: " . mysqli_error($conn);
    }
    header("Location: verifikasi_donasi.php");
    exit();
}

// ======================================================
// FILTER & PAGINATION
// ======================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_kategori = isset($_GET['kategori']) ? (int)$_GET['kategori'] : '';
$filter_periode = isset($_GET['periode']) ? mysqli_real_escape_string($conn, $_GET['periode']) : '';

$where = "WHERE 1=1";

if ($search != '') {
    $where .= " AND (u.nama_lengkap LIKE '%$search%' OR u.username LIKE '%$search%' OR d.keterangan LIKE '%$search%')";
}
if ($filter_status != '' && $filter_status != 'semua') {
    $where .= " AND d.status = '$filter_status'";
} 
if ($filter_kategori != '' && $filter_kategori > 0) {
    $where .= " AND d.kategori_id = $filter_kategori";
}
if ($filter_periode != '' && $filter_periode != 'semua') {
    switch ($filter_periode) {
        case 'hari_ini':
            $where .= " AND DATE(d.tanggal_donasi) = CURDATE()";
            break;
        case 'minggu_ini':
            $where .= " AND YEARWEEK(d.tanggal_donasi, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'bulan_ini':
            $where .= " AND MONTH(d.tanggal_donasi) = MONTH(CURDATE()) AND YEAR(d.tanggal_donasi) = YEAR(CURDATE())";
            break;
    }
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_sql = "SELECT COUNT(*) as total FROM donasi d 
              JOIN users u ON d.user_id = u.id 
              JOIN kategori_donasi k ON d.kategori_id = k.id 
              $where";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT d.*, u.nama_lengkap, u.username, k.nama_kategori,
        v.nama_lengkap as verified_by_nama
        FROM donasi d 
        JOIN users u ON d.user_id = u.id 
        JOIN kategori_donasi k ON d.kategori_id = k.id 
        LEFT JOIN users v ON d.verified_by = v.id 
        $where 
        ORDER BY d.tanggal_donasi ASC 
        LIMIT $offset, $limit";
$donasiList = query($sql);

// Ambil kategori untuk filter
$kategoris = query("SELECT * FROM kategori_donasi WHERE tipe IN ('donasi', 'both') ORDER BY nama_kategori ASC");

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Donasi - Admin Panti Asuhan Al-Muthi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; overflow-x: hidden; }
        
        /* SIDEBAR */
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
        .btn-filter, .btn-reset { padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-filter { background: #50c878; color: white; }
        .btn-reset { background: #6c757d; color: white; text-decoration: none; display: inline-block; }
        
        /* TABLE */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; vertical-align: middle; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .status-pending { background: #fff3e0; color: #ff9800; }
        .status-success { background: #e8f5e9; color: #4caf50; }
        .status-failed { background: #ffebee; color: #f44336; }
        .btn-action { padding: 5px 10px; border: none; border-radius: 8px; cursor: pointer; margin: 2px; font-size: 12px; }
        .btn-detail { background: #17a2b8; color: white; }
        .btn-verifikasi { background: #ffc107; color: #333; }
        
        /* PAGINATION */
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; text-decoration: none; }
        .pagination a { background: #f0f2f5; color: #555; }
        .pagination .active { background: #50c878; color: white; }
        
        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 550px; max-width: 90%; padding: 25px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .detail-item { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
        .detail-label { font-weight: 600; font-size: 12px; color: #888; margin-bottom: 5px; }
        .detail-value { font-size: 14px; color: #333; }
        .detail-image { text-align: center; margin: 15px 0; }
        .detail-image img { max-width: 100%; max-height: 300px; border-radius: 10px; cursor: pointer; }
        .radio-group { display: flex; gap: 20px; align-items: center; }
        .radio-group label { display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: #50c878; color: white; padding: 10px 25px; border: none; border-radius: 10px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 10px 25px; border: none; border-radius: 10px; cursor: pointer; }
        
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
                <div class="submenu-item active" onclick="location.href='verifikasi_donasi.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Donatur</span></div>
                <div class="submenu-item" onclick="location.href='verifikasi_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Pengeluaran Panti</span></div>
                <div class="submenu-item" onclick="location.href='laporan_keuangan.php'"><i class="fas fa-chart-line"></i><span>Laporan Keuangan</span></div>
            </div>
            <div class="menu-item has-submenu" onclick="toggleSubmenu(this)"><i class="fas fa-database"></i><span>Master Data</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu">
                <div class="submenu-item" onclick="location.href='kategori_donasi.php'"><i class="fas fa-tags"></i><span>Kategori Transaksi</span></div>
                <div class="submenu-item" onclick="location.href='kategori_role.php'"><i class="fas fa-user-tag"></i><span>Kategori Role</span></div>
                <div class="submenu-item" onclick="location.href='anak_asuh.php'"><i class="fas fa-child"></i><span>Data Anak Asuh</span></div>
                <div class="submenu-item" onclick="location.href='doa_khusus.php'"><i class="fas fa-pray"></i><span>Data Doa Khusus</span></div>
            </div>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Verifikasi Donasi</h2>
                <p>Validasi bukti transfer donasi dari donatur</p>
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
            
            <!-- FILTER -->
            <form method="GET" action="" class="filter-section">
                <input type="text" name="search" placeholder="Cari donatur atau keterangan..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="periode">
                    <option value="semua">Semua Periode</option>
                    <option value="hari_ini" <?php echo $filter_periode == 'hari_ini' ? 'selected' : ''; ?>>Hari Ini</option>
                    <option value="minggu_ini" <?php echo $filter_periode == 'minggu_ini' ? 'selected' : ''; ?>>Minggu Ini</option>
                    <option value="bulan_ini" <?php echo $filter_periode == 'bulan_ini' ? 'selected' : ''; ?>>Bulan Ini</option>
                </select>
                <select name="kategori">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($kategoris as $k): ?>
                        <option value="<?php echo $k['id']; ?>" <?php echo $filter_kategori == $k['id'] ? 'selected' : ''; ?>><?php echo $k['nama_kategori']; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="pending">Menunggu</option>
                    <option value="success" <?php echo $filter_status == 'success' ? 'selected' : ''; ?>>Sukses</option>
                    <option value="failed" <?php echo $filter_status == 'failed' ? 'selected' : ''; ?>>Tidak Valid</option>
                    <option value="semua" <?php echo $filter_status == 'semua' ? 'selected' : ''; ?>>Semua Status</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="verifikasi_donasi.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
            </form>
            
            <!-- TABLE -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Donatur</th>
                            <th>Kategori</th>
                            <th>Nominal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($donasiList) > 0): $no = $offset + 1; foreach ($donasiList as $d): ?>
                            <?php 
                                $statusClass = '';
                                $statusText = '';
                                if ($d['status'] == 'pending') {
                                    $statusClass = 'status-pending';
                                    $statusText = 'Menunggu';
                                } elseif ($d['status'] == 'success') {
                                    $statusClass = 'status-success';
                                    $statusText = 'Sukses';
                                } else {
                                    $statusClass = 'status-failed';
                                    $statusText = 'Tidak Valid';
                                }
                            ?>
                            <tr>
                                <td><?php echo $d['id']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($d['tanggal_donasi'])); ?></td>
                                <td><?php echo htmlspecialchars($d['nama_lengkap']); ?><br><small><?php echo $d['username']; ?></small></td>
                                <td><?php echo htmlspecialchars($d['nama_kategori']); ?></td>
                                <td>Rp <?php echo number_format($d['nominal'], 0, ',', '.'); ?></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <td>
                                    <button class="btn-action btn-detail" onclick="openDetailModal(<?php echo $d['id']; ?>)"><i class="fas fa-info-circle"></i> Detail</button>
                                    <?php if ($d['status'] == 'pending'): ?>
                                        <button class="btn-action btn-verifikasi" onclick="openVerifikasiModal(<?php echo $d['id']; ?>)"><i class="fas fa-check-double"></i> Verifikasi</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:40px;">Tidak ada data donasi</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&periode=<?php echo $filter_periode; ?>&kategori=<?php echo $filter_kategori; ?>&status=<?php echo $filter_status; ?>">« Sebelumnya</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&periode=<?php echo $filter_periode; ?>&kategori=<?php echo $filter_kategori; ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&periode=<?php echo $filter_periode; ?>&kategori=<?php echo $filter_kategori; ?>&status=<?php echo $filter_status; ?>">Selanjutnya »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MODAL DETAIL -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detail Donasi</h3>
                <span class="close-modal" onclick="closeModal('detailModal')">&times;</span>
            </div>
            <div id="detailContent"></div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal('detailModal')">Tutup</button>
            </div>
        </div>
    </div>
    
    <!-- MODAL VERIFIKASI -->
    <div id="verifikasiModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Verifikasi Donasi</h3>
                <span class="close-modal" onclick="closeModal('verifikasiModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="id" id="verifikasi_id">
                <div id="verifikasiData"></div>
                <div class="form-group">
                    <label>Status Verifikasi</label>
                    <div class="radio-group">
                        <label><input type="radio" name="status" value="success" required> ✅ Disetujui (Sukses)</label>
                        <label><input type="radio" name="status" value="failed"> ❌ Ditolak (Tidak Valid)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('verifikasiModal')">Batal</button>
                    <button type="submit" name="verifikasi" class="btn-save">Kirim</button>
                </div>
            </form>
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
            fetch('get_donasi_admin.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let d = data.data;
                        let statusText = d.status == 'pending' ? 'Menunggu' : (d.status == 'success' ? 'Sukses' : 'Tidak Valid');
                        let statusClass = d.status == 'pending' ? 'status-pending' : (d.status == 'success' ? 'status-success' : 'status-failed');
                        let imageHtml = d.bukti_transfer ? `<div class="detail-image"><img src="../assets/uploads/bukti_transfer/${d.bukti_transfer}" onclick="window.open(this.src)"></div>` : '<div class="detail-image"><p>Tidak ada bukti transfer</p></div>';
                        
                        document.getElementById('detailContent').innerHTML = `
                            <div class="detail-item"><div class="detail-label">ID Donasi</div><div class="detail-value">${d.id}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal Donasi</div><div class="detail-value">${d.tanggal_donasi}</div></div>
                            <div class="detail-item"><div class="detail-label">Donatur</div><div class="detail-value">${d.nama_lengkap} (${d.username})</div></div>
                            <div class="detail-item"><div class="detail-label">Kategori</div><div class="detail-value">${d.nama_kategori}</div></div>
                            <div class="detail-item"><div class="detail-label">Nominal</div><div class="detail-value">Rp ${new Intl.NumberFormat('id-ID').format(d.nominal)}</div></div>
                            <div class="detail-item"><div class="detail-label">Keterangan</div><div class="detail-value">${d.keterangan || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Catatan Doa</div><div class="detail-value">${d.catatan_doa || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value"><span class="status-badge ${statusClass}">${statusText}</span></div></div>
                            ${imageHtml}
                            ${d.verified_at ? `<div class="detail-item"><div class="detail-label">Diverifikasi Pada</div><div class="detail-value">${d.verified_at}</div></div>` : ''}
                            ${d.verified_by_nama ? `<div class="detail-item"><div class="detail-label">Diverifikasi Oleh</div><div class="detail-value">${d.verified_by_nama}</div></div>` : ''}
                        `;
                        document.getElementById('detailModal').classList.add('show');
                    }
                });
        }
        
        function openVerifikasiModal(id) {
            fetch('get_donasi_admin.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let d = data.data;
                        document.getElementById('verifikasi_id').value = d.id;
                        document.getElementById('verifikasiData').innerHTML = `
                            <div class="detail-item"><div class="detail-label">Donatur</div><div class="detail-value">${d.nama_lengkap}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal</div><div class="detail-value">${d.tanggal_donasi}</div></div>
                            <div class="detail-item"><div class="detail-label">Kategori</div><div class="detail-value">${d.nama_kategori}</div></div>
                            <div class="detail-item"><div class="detail-label">Nominal</div><div class="detail-value">Rp ${new Intl.NumberFormat('id-ID').format(d.nominal)}</div></div>
                            <div class="detail-item"><div class="detail-label">Keterangan</div><div class="detail-value">${d.keterangan || '-'}</div></div>
                            ${d.bukti_transfer ? `<div class="detail-image"><img src="../assets/uploads/bukti_transfer/${d.bukti_transfer}" style="max-width:100%; max-height:200px;"></div>` : '<div class="detail-image"><p>Tidak ada bukti transfer</p></div>'}
                        `;
                        document.getElementById('verifikasiModal').classList.add('show');
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