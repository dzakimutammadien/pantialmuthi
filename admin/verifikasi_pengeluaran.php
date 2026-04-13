<?php
// ======================================================
// FILE: admin/verifikasi_pengeluaran.php
// HALAMAN VERIFIKASI PENGELUARAN PANTI UNTUK ADMIN
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');
requirePermission('verifikasi_pengeluaran.view');

$currentUser = getCurrentUser();

// ======================================================
// PROSES VERIFIKASI
// ======================================================

if (isset($_POST['verifikasi'])) {
    $id = (int)$_POST['id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $catatan = mysqli_real_escape_string($conn, $_POST['catatan']);
    $verified_by = $currentUser['id'];
    $verified_at = date('Y-m-d H:i:s');
    
    $sql = "UPDATE pengeluaran SET 
            status = '$status', 
            catatan_verifikasi = '$catatan',
            verified_by = $verified_by,
            verified_at = '$verified_at'
            WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Verifikasi pengeluaran ID: $id => $status");
        $_SESSION['success'] = "Pengeluaran berhasil diverifikasi!";
    } else {
        $_SESSION['error'] = "Gagal verifikasi: " . mysqli_error($conn);
    }
    header("Location: verifikasi_pengeluaran.php");
    exit();
}

// ======================================================
// FILTER & PAGINATION
// ======================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_kategori = isset($_GET['kategori']) ? (int)$_GET['kategori'] : '';

$where = "WHERE 1=1";

if ($search != '') {
    $where .= " AND (u.nama_lengkap LIKE '%$search%' OR p.deskripsi LIKE '%$search%')";
}
if ($filter_status != '' && $filter_status != 'semua') {
    $where .= " AND p.status = '$filter_status'";
}
if ($filter_kategori != '' && $filter_kategori > 0) {
    $where .= " AND p.kategori_id = $filter_kategori";
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_sql = "SELECT COUNT(*) as total FROM pengeluaran p 
              JOIN users u ON p.created_by = u.id 
              $where";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT p.*, u.nama_lengkap as pengasuh_nama, k.nama_kategori,
        v.nama_lengkap as verified_by_nama
        FROM pengeluaran p 
        JOIN users u ON p.created_by = u.id 
        JOIN kategori_donasi k ON p.kategori_id = k.id 
        LEFT JOIN users v ON p.verified_by = v.id 
        $where 
        ORDER BY p.created_at DESC 
        LIMIT $offset, $limit";
$pengeluarans = query($sql);

// Ambil kategori untuk filter
$kategoris = query("SELECT * FROM kategori_donasi WHERE tipe IN ('pengeluaran', 'both') ORDER BY nama_kategori ASC");

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pengeluaran - Admin Panti Asuhan Al-Muthi</title>
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
        .main-content { margin-left: 280px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-title h2 { font-size: 20px; color: #333; }
        .profile-dropdown { position: relative; }
        .profile-icon { width: 45px; height: 45px; background: linear-gradient(135deg, #50c878, #2e8b57); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; color: white; }
        .dropdown-menu { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        
        /* CONTENT */
        .content-card { background: white; border-radius: 20px; padding: 25px; }
        .filter-section { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-section input, .filter-section select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; }
        .filter-section input { flex: 2; }
        .filter-section select { flex: 1; }
        .btn-filter, .btn-reset { padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-filter { background: #50c878; color: white; }
        .btn-reset { background: #6c757d; color: white; text-decoration: none; display: inline-block; }
        
        /* TABLE */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .status-pending { background: #fff3e0; color: #ff9800; }
        .status-disetujui { background: #e8f5e9; color: #4caf50; }
        .status-ditolak { background: #ffebee; color: #f44336; }
        .btn-action { padding: 5px 10px; border: none; border-radius: 8px; cursor: pointer; margin: 2px; font-size: 12px; }
        .btn-detail { background: #17a2b8; color: white; }
        .btn-verifikasi { background: #ffc107; color: #333; }
        
        /* ALERT */
        .alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        
        /* PAGINATION */
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; text-decoration: none; }
        .pagination a { background: #f0f2f5; color: #555; }
        .pagination .active { background: #50c878; color: white; }
        
        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 600px; max-width: 90%; padding: 25px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: #50c878; color: white; padding: 10px 25px; border: none; border-radius: 10px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 10px 25px; border: none; border-radius: 10px; cursor: pointer; }
        .detail-item { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
        .detail-label { font-weight: 600; font-size: 12px; color: #888; margin-bottom: 5px; }
        .detail-value { font-size: 14px; color: #333; }
        .detail-image { text-align: center; margin: 15px 0; }
        .detail-image img { max-width: 100%; max-height: 300px; border-radius: 10px; cursor: pointer; }
        .radio-group { display: flex; gap: 20px; align-items: center; }
        .radio-group label { display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; }
        
        @media (max-width: 768px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } .form-row { grid-template-columns: 1fr; } }
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
            <!-- Beranda -->
            <div class="menu-item" onclick="location.href='dashboard.php'">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </div>
            
            <!-- Manajemen User -->
            <div class="menu-item" onclick="location.href='users.php'">
                <i class="fas fa-users"></i>
                <span>Manajemen User</span>
            </div>
            
            <!-- Transaksi (dengan submenu) -->
            <div class="menu-item has-submenu open" onclick="toggleSubmenu(this)">
                <i class="fas fa-exchange-alt"></i>
                <span>Transaksi</span>
                <i class="fas fa-chevron-down arrow"></i>
            </div>
            <div class="submenu open">
                <div class="submenu-item" onclick="location.href='donasi_donatur.php'">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span>Donasi Donatur</span>
                </div>
                <div class="submenu-item active" onclick="location.href='verifikasi_pengeluaran.php'">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Pengeluaran Panti</span>
                </div>
                <div class="submenu-item" onclick="location.href='laporan.php'">
                    <i class="fas fa-chart-line"></i>
                    <span>Laporan</span>
                </div>
            </div>
            
            <!-- Master Data (dengan submenu) -->
            <div class="menu-item has-submenu" onclick="toggleSubmenu(this)">
                <i class="fas fa-database"></i>
                <span>Master Data</span>
                <i class="fas fa-chevron-down arrow"></i>
            </div>
            <div class="submenu">
                <div class="submenu-item" onclick="location.href='kategori_donasi.php'">
                    <i class="fas fa-tags"></i>
                    <span>Kategori Transaksi</span>
                </div>
                <div class="submenu-item" onclick="location.href='kategori_role.php'">
                    <i class="fas fa-user-tag"></i>
                    <span>Kategori Role</span>
                </div>
                <div class="submenu-item" onclick="location.href='anak_asuh.php'">
                    <i class="fas fa-child"></i>
                    <span>Data Anak Asuh</span>
                </div>
                <div class="submenu-item" onclick="location.href='doa_khusus.php'">
                    <i class="fas fa-pray"></i>
                    <span>Data Doa Khusus</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div class="page-title"><h2>Verifikasi Pengeluaran Panti</h2><p>Validasi pengeluaran yang diajukan pengasuh</p></div>
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
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            
            <form method="GET" action="" class="filter-section">
                <input type="text" name="search" placeholder="Cari nama pengasuh atau keterangan..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="kategori">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($kategoris as $k): ?>
                        <option value="<?php echo $k['id']; ?>" <?php echo $filter_kategori == $k['id'] ? 'selected' : ''; ?>><?php echo $k['nama_kategori']; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="semua">Semua Status</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="disetujui" <?php echo $filter_status == 'disetujui' ? 'selected' : ''; ?>>Disetujui</option>
                    <option value="ditolak" <?php echo $filter_status == 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="verifikasi_pengeluaran.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
            </form>
            
            <div class="table-wrapper">
                 <table>
                    <thead>
                        <tr><th>ID</th><th>Nama Pengasuh</th><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th>Jumlah</th><th>Status</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($pengeluarans) > 0): $no = $offset + 1; foreach ($pengeluarans as $p): ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td><?php echo htmlspecialchars($p['pengasuh_nama']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p['tanggal_pengeluaran'])); ?></td>
                            <td><?php echo htmlspecialchars($p['nama_kategori']); ?></td>
                            <td><?php echo htmlspecialchars($p['deskripsi']) ?: '-'; ?></td>
                            <td>Rp <?php echo number_format($p['nominal'], 0, ',', '.'); ?></td>
                            <td><span class="status-badge status-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                            <td>
                                <button class="btn-action btn-detail" onclick="openDetailModal(<?php echo $p['id']; ?>)"><i class="fas fa-info-circle"></i> Detail</button>
                                <?php if ($p['status'] == 'pending'): ?>
                                    <button class="btn-action btn-verifikasi" onclick="openVerifikasiModal(<?php echo $p['id']; ?>)"><i class="fas fa-check-double"></i> Verifikasi</button>
                                <?php endif; ?>
                             </td>
                         </tr>
                        <?php endforeach; else: ?>
                         <tr><td colspan="8" style="text-align:center; padding:40px;">Tidak ada data pengeluaran</td></tr>
                        <?php endif; ?>
                    </tbody>
                 </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&kategori=<?php echo $filter_kategori; ?>&status=<?php echo $filter_status; ?>">« Sebelumnya</a><?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?><span class="active"><?php echo $i; ?></span><?php else: ?><a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&kategori=<?php echo $filter_kategori; ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&kategori=<?php echo $filter_kategori; ?>&status=<?php echo $filter_status; ?>">Selanjutnya »</a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MODAL DETAIL -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Detail Pengeluaran Panti</h3><span class="close-modal" onclick="closeModal('detailModal')">&times;</span></div>
            <div id="detailContent"></div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('detailModal')">Tutup</button></div>
        </div>
    </div>
    
    <!-- MODAL VERIFIKASI -->
    <div id="verifikasiModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Verifikasi Pengeluaran Panti</h3><span class="close-modal" onclick="closeModal('verifikasiModal')">&times;</span></div>
            <form method="POST" action="" id="verifikasiForm">
                <input type="hidden" name="id" id="verifikasi_id">
                <div id="verifikasiData"></div>
                <div class="form-group">
                    <label>Status</label>
                    <div class="radio-group">
                        <label><input type="radio" name="status" value="disetujui" required> ✅ Disetujui</label>
                        <label><input type="radio" name="status" value="ditolak"> ❌ Ditolak</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Catatan Verifikasi</label>
                    <textarea name="catatan" rows="3" placeholder="Masukkan catatan verifikasi..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('verifikasiModal')">Batal</button>
                    <button type="submit" name="verifikasi" class="btn-save">Kirim</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleSubmenu(e){e.classList.toggle('open');let s=e.nextElementSibling;if(s&&s.classList.contains('submenu'))s.classList.toggle('open');}
        
        function closeModal(id){
            document.getElementById(id).classList.remove('show');
        }
        
        function openDetailModal(id){
            fetch(`get_pengeluaran.php?id=${id}`)
                .then(response => response.json())
                .then(d => {
                    if(d.success){
                        let p = d.data;
                        let statusClass = p.status == 'pending' ? 'status-pending' : (p.status == 'disetujui' ? 'status-disetujui' : 'status-ditolak');
                        let imageHtml = p.bukti_foto ? `<div class="detail-image"><img src="../assets/uploads/pengeluaran/${p.bukti_foto}" onclick="window.open(this.src)"></div>` : '<div class="detail-image"><p>Tidak ada gambar</p></div>';
                        document.getElementById('detailContent').innerHTML = `
                            <div class="detail-item"><div class="detail-label">ID</div><div class="detail-value">${p.id}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal</div><div class="detail-value">${p.tanggal_pengeluaran}</div></div>
                            <div class="detail-item"><div class="detail-label">Pengasuh</div><div class="detail-value">${p.pengasuh_nama}</div></div>
                            <div class="detail-item"><div class="detail-label">Kategori</div><div class="detail-value">${p.nama_kategori}</div></div>
                            <div class="detail-item"><div class="detail-label">Jumlah</div><div class="detail-value">Rp ${new Intl.NumberFormat('id-ID').format(p.nominal)}</div></div>
                            <div class="detail-item"><div class="detail-label">Keterangan</div><div class="detail-value">${p.deskripsi || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value"><span class="status-badge ${statusClass}">${p.status}</span></div></div>
                            <div class="detail-item"><div class="detail-label">Catatan</div><div class="detail-value">${p.catatan_verifikasi || '-'}</div></div>
                            ${imageHtml}
                            <div class="detail-item"><div class="detail-label">Diverifikasi Oleh</div><div class="detail-value">${p.verified_by_nama || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal Verifikasi</div><div class="detail-value">${p.verified_at || '-'}</div></div>
                        `;
                        document.getElementById('detailModal').classList.add('show');
                    } else {
                        alert('Gagal mengambil data pengeluaran');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat mengambil data');
                });
        }
        
        function openVerifikasiModal(id){
            fetch(`get_pengeluaran.php?id=${id}`)
                .then(response => response.json())
                .then(d => {
                    if(d.success){
                        let p = d.data;
                        document.getElementById('verifikasi_id').value = p.id;
                        document.getElementById('verifikasiData').innerHTML = `
                            <div class="detail-item"><div class="detail-label">ID</div><div class="detail-value">${p.id}</div></div>
                            <div class="detail-item"><div class="detail-label">Pengasuh</div><div class="detail-value">${p.pengasuh_nama}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal</div><div class="detail-value">${p.tanggal_pengeluaran}</div></div>
                            <div class="detail-item"><div class="detail-label">Kategori</div><div class="detail-value">${p.nama_kategori}</div></div>
                            <div class="detail-item"><div class="detail-label">Jumlah</div><div class="detail-value">Rp ${new Intl.NumberFormat('id-ID').format(p.nominal)}</div></div>
                            <div class="detail-item"><div class="detail-label">Keterangan</div><div class="detail-value">${p.deskripsi || '-'}</div></div>
                            ${p.bukti_foto ? `<div class="detail-image"><img src="../assets/uploads/pengeluaran/${p.bukti_foto}" onclick="window.open(this.src)" style="max-width:100%; max-height:200px;"></div>` : '<div class="detail-image"><p>Tidak ada gambar</p></div>'}
                        `;
                        document.getElementById('verifikasiModal').classList.add('show');
                    } else {
                        alert('Gagal mengambil data pengeluaran');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat mengambil data');
                });
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) event.target.classList.remove('show');
        }
    </script>
</body>
</html>