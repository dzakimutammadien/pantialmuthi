<?php
// ======================================================
// FILE: admin/verifikasi_program.php
// HALAMAN VERIFIKASI DONASI PROGRAM (CROWDFUNDING)
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
    
    $query = mysqli_query($conn, "SELECT program_id, nominal FROM donasi_program WHERE id = $id");
    $donasi = mysqli_fetch_assoc($query);
    $program_id = $donasi['program_id'];
    
    $sql = "UPDATE donasi_program SET status = '$status', verified_by = $verified_by, verified_at = '$verified_at' WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        $update_program = "UPDATE program_donasi SET 
                   jumlah_donatur = (SELECT COUNT(*) FROM donasi_program WHERE program_id = $program_id AND status = 'success'),
                   terkumpul = (SELECT SUM(nominal) FROM donasi_program WHERE program_id = $program_id AND status = 'success')
                   WHERE id = $program_id";
        logActivity($currentUser['id'], "Verifikasi donasi program ID: $id => $status");
        $_SESSION['success'] = "Donasi program berhasil diverifikasi!";
    } else {
        $_SESSION['error'] = "Gagal verifikasi: " . mysqli_error($conn);
    }
    header("Location: verifikasi_program.php");
    exit();
}

// Update verifikasi (edit)
if (isset($_POST['update_verifikasi'])) {
    $id = (int)$_POST['id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $verified_by = $currentUser['id'];
    $verified_at = date('Y-m-d H:i:s');
    
    $query = mysqli_query($conn, "SELECT program_id FROM donasi_program WHERE id = $id");
    $donasi = mysqli_fetch_assoc($query);
    $program_id = $donasi['program_id'];
    
    $sql = "UPDATE donasi_program SET status = '$status', verified_by = $verified_by, verified_at = '$verified_at' WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        $update_program = "UPDATE program_donasi SET 
                           jumlah_donatur = (SELECT COUNT(DISTINCT user_id) FROM donasi_program WHERE program_id = $program_id AND status = 'success'),
                           terkumpul = (SELECT SUM(nominal) FROM donasi_program WHERE program_id = $program_id AND status = 'success')
                           WHERE id = $program_id";
        mysqli_query($conn, $update_program);
        
        logActivity($currentUser['id'], "Update verifikasi donasi program ID: $id => $status");
        $_SESSION['success'] = "Verifikasi donasi program berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate verifikasi: " . mysqli_error($conn);
    }
    header("Location: verifikasi_program.php");
    exit();
}
// HAPUS DONASI PROGRAM
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    $sql = "DELETE FROM donasi_program WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menghapus donasi program ID: $id");
        $_SESSION['success'] = "Donasi program berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus: " . mysqli_error($conn);
    }
    header("Location: verifikasi_program.php");
    exit();
}
// ======================================================
// FILTER & PAGINATION
// ======================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_program = isset($_GET['program']) ? (int)$_GET['program'] : '';

$where = "WHERE 1=1";

if ($search != '') {
    $where .= " AND (dp.nama_donatur LIKE '%$search%')";
}
if ($filter_status != '' && $filter_status != 'semua') {
    $where .= " AND dp.status = '$filter_status'";
}
if ($filter_program != '' && $filter_program > 0) {
    $where .= " AND dp.program_id = $filter_program";
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_sql = "SELECT COUNT(*) as total FROM donasi_program dp $where";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT dp.*, p.nama_program, u.nama_lengkap as verified_by_nama
        FROM donasi_program dp 
        JOIN program_donasi p ON dp.program_id = p.id 
        LEFT JOIN users u ON dp.verified_by = u.id 
        $where 
        ORDER BY dp.created_at ASC
        LIMIT $offset, $limit";
$donasiList = query($sql);

$programs = query("SELECT id, nama_program FROM program_donasi ORDER BY nama_program ASC");

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Donasi Program - Admin Panti Asuhan Al-Muthi</title>
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
        .btn-delete { background: #dc3545; color: white; }
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
        .btn-edit-verifikasi { background: #2196f3; color: white; }
        
        .alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; text-decoration: none; }
        .pagination a { background: #f0f2f5; color: #555; }
        .pagination .active { background: #50c878; color: white; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 550px; max-width: 90%; padding: 25px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .detail-item { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
        .detail-label { font-weight: 600; font-size: 12px; color: #888; margin-bottom: 5px; }
        .detail-value { font-size: 14px; color: #333; }
        .radio-group { display: flex; gap: 20px; align-items: center; margin: 10px 0; }
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
            <img src="../assets/image/almuthi.png" alt="Logo" class="sidebar-logo" onerror="this.style.display='none'">
            <div><h3>Panti Asuhan</h3><p>Al-Muthi</p></div>
        </div>
        <div class="sidebar-menu">
            <div class="menu-item" onclick="location.href='dashboard.php'"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></div>
            <div class="menu-item" onclick="location.href='users.php'"><i class="fas fa-users"></i><span>Manajemen User</span></div>
            <div class="menu-item has-submenu open" onclick="toggleSubmenu(this)"><i class="fas fa-exchange-alt"></i><span>Transaksi</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu open">
                <div class="submenu-item" onclick="location.href='verifikasi_donasi.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Donatur</span></div>
                <div class="submenu-item" onclick="location.href='verifikasi_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Pengeluaran Panti</span></div>
                    <div class="submenu-item" onclick="location.href='verifikasi_program.php'">
    <i class="fas fa-heart"></i>
    <span>Verifikasi Program</span>
</div>
                <div class="submenu-item" onclick="location.href='laporan_keuangan.php'"><i class="fas fa-chart-line"></i><span>Laporan Keuangan</span></div>
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
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Verifikasi Donasi Program</h2>
                <p>Validasi donasi crowdfunding dari donatur publik</p>
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
                <input type="text" name="search" placeholder="Cari donatur..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="program">
                    <option value="">Semua Program</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $filter_program == $p['id'] ? 'selected' : ''; ?>><?php echo $p['nama_program']; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="semua">Semua Status</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="success" <?php echo $filter_status == 'success' ? 'selected' : ''; ?>>Sukses</option>
                    <option value="failed" <?php echo $filter_status == 'failed' ? 'selected' : ''; ?>>Gagal</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="verifikasi_program.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
            </form>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Program</th>
                            <th>Donatur</th>
                            <th>Nominal</th>
                            <th>Pesan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($donasiList) > 0): $no = $offset + 1; foreach ($donasiList as $d): 
                            $statusClass = '';
                            $statusText = '';
                            if ($d['status'] == 'pending') {
                                $statusClass = 'status-pending';
                                $statusText = 'Pending';
                            } elseif ($d['status'] == 'success') {
                                $statusClass = 'status-success';
                                $statusText = 'Sukses';
                            } else {
                                $statusClass = 'status-failed';
                                $statusText = 'Gagal';
                            }
                        ?>
                        <tr>
                            <td><?php echo $d['id']; ?></td>
                            <td><?php echo htmlspecialchars($d['nama_program']); ?></td>
                            <td>
                                <?php if ($d['is_anonim']): ?>
                                    🙈 Anonim
                                <?php else: ?>
                                    <?php echo htmlspecialchars($d['nama_donatur']); ?>
                                <?php endif; ?>
                                </td>
                            <td>Rp <?php echo number_format($d['nominal'], 0, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars(substr($d['pesan'], 0, 30)) . (strlen($d['pesan']) > 30 ? '...' : ''); ?></td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                            <td>
                                <button class="btn-action btn-detail" onclick="openDetailModal(<?php echo $d['id']; ?>)"><i class="fas fa-info-circle"></i> Detail</button>
                                <?php if ($d['status'] == 'pending'): ?>
                                    <button class="btn-action btn-verifikasi" onclick="openVerifikasiModal(<?php echo $d['id']; ?>)"><i class="fas fa-check-double"></i> Verifikasi</button>
                                <?php else: ?>
                                    <button class="btn-action btn-edit-verifikasi" onclick="openEditVerifikasiModal(<?php echo $d['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                <?php endif; ?>
                                <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $d['id']; ?>)"><i class="fas fa-trash"></i> Hapus</button>
    <!-- SAMPAI SINI -->
</td>
                                </td>
                                </td>
                        <?php endforeach; else: ?>
                        <td><td colspan="7" style="text-align:center; padding:40px;">Tidak ada donasi program</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>">«</a><?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?><span class="active"><?php echo $i; ?></span><?php else: ?><a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page+1; ?>">»</a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MODAL DETAIL -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Detail Donasi Program</h3><span class="close-modal" onclick="closeModal('detailModal')">&times;</span></div>
            <div id="detailContent"></div>
            <div class="modal-footer"><button class="btn-cancel" onclick="closeModal('detailModal')">Tutup</button></div>
        </div>
    </div>
    
    <!-- MODAL VERIFIKASI -->
    <div id="verifikasiModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Verifikasi Donasi Program</h3><span class="close-modal" onclick="closeModal('verifikasiModal')">&times;</span></div>
            <form method="POST">
                <input type="hidden" name="id" id="verifikasi_id">
                <div id="verifikasiData"></div>
                <div class="form-group">
                    <label>Status</label>
                    <div class="radio-group">
                        <label><input type="radio" name="status" value="success"> ✅ Sukses</label>
                        <label><input type="radio" name="status" value="failed"> ❌ Gagal</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('verifikasiModal')">Batal</button>
                    <button type="submit" name="verifikasi" class="btn-save">Kirim</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- MODAL EDIT VERIFIKASI -->
    <div id="editVerifikasiModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit Verifikasi Donasi Program</h3><span class="close-modal" onclick="closeModal('editVerifikasiModal')">&times;</span></div>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div id="editVerifikasiData"></div>
                <div class="form-group">
                    <label>Ubah Status</label>
                    <div class="radio-group">
                        <label><input type="radio" name="status" value="success"> ✅ Sukses</label>
                        <label><input type="radio" name="status" value="failed"> ❌ Gagal</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('editVerifikasiModal')">Batal</button>
                    <button type="submit" name="update_verifikasi" class="btn-save">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleSubmenu(e){e.classList.toggle('open');let s=e.nextElementSibling;if(s&&s.classList.contains('submenu'))s.classList.toggle('open');}
        function closeModal(id){document.getElementById(id).classList.remove('show');}
        
        function openDetailModal(id){
            fetch('get_donasi_program.php?id='+id).then(r=>r.json()).then(d=>{
                if(d.success){
                    let statusText = d.data.status == 'pending' ? 'Pending' : (d.data.status == 'success' ? 'Sukses' : 'Gagal');
                    document.getElementById('detailContent').innerHTML = `
                        <div class="detail-item"><div class="detail-label">ID</div><div class="detail-value">${d.data.id}</div></div>
                        <div class="detail-item"><div class="detail-label">Program</div><div class="detail-value">${d.data.nama_program}</div></div>
                        <div class="detail-item"><div class="detail-label">Donatur</div><div class="detail-value">${d.data.is_anonim ? '🙈 Anonim' : d.data.nama_donatur}</div></div>
                        <div class="detail-item"><div class="detail-label">Nominal</div><div class="detail-value">Rp ${new Intl.NumberFormat('id-ID').format(d.data.nominal)}</div></div>
                        <div class="detail-item"><div class="detail-label">Pesan</div><div class="detail-value">${d.data.pesan || '-'}</div></div>
                        <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">${statusText}</div></div>
                        ${d.data.bukti_transfer ? `<div class="detail-item"><div class="detail-label">Bukti Transfer</div><div class="detail-value"><a href="../assets/uploads/bukti_transfer/${d.data.bukti_transfer}" target="_blank">Lihat Bukti</a></div></div>` : ''}
                    `;
                    document.getElementById('detailModal').classList.add('show');
                }
            });
        }
        
        function openVerifikasiModal(id){
            fetch('get_donasi_program.php?id='+id).then(r=>r.json()).then(d=>{
                if(d.success){
                    document.getElementById('verifikasi_id').value = d.data.id;
                    document.getElementById('verifikasiData').innerHTML = `<div class="detail-item"><div class="detail-label">Donatur</div><div class="detail-value">${d.data.is_anonim ? 'Anonim' : d.data.nama_donatur}</div></div><div class="detail-item"><div class="detail-label">Program</div><div class="detail-value">${d.data.nama_program}</div></div><div class="detail-item"><div class="detail-label">Nominal</div><div class="detail-value">Rp ${new Intl.NumberFormat('id-ID').format(d.data.nominal)}</div></div>`;
                    document.getElementById('verifikasiModal').classList.add('show');
                }
            });
        }
        function confirmDelete(id) {
    if (confirm('Yakin ingin menghapus donasi program ini? Data yang dihapus tidak dapat dikembalikan.')) {
        window.location.href = 'verifikasi_program.php?hapus=' + id;
    }
}
        function openEditVerifikasiModal(id){
            fetch('get_donasi_program.php?id='+id).then(r=>r.json()).then(d=>{
                if(d.success){
                    document.getElementById('edit_id').value = d.data.id;
                    document.getElementById('editVerifikasiData').innerHTML = `<div class="detail-item"><div class="detail-label">Donatur</div><div class="detail-value">${d.data.is_anonim ? 'Anonim' : d.data.nama_donatur}</div></div><div class="detail-item"><div class="detail-label">Program</div><div class="detail-value">${d.data.nama_program}</div></div><div class="detail-item"><div class="detail-label">Nominal</div><div class="detail-value">Rp ${new Intl.NumberFormat('id-ID').format(d.data.nominal)}</div></div><div class="detail-item"><div class="detail-label">Status Saat Ini</div><div class="detail-value">${d.data.status == 'pending' ? 'Pending' : (d.data.status == 'success' ? 'Sukses' : 'Gagal')}</div></div>`;
                    document.getElementById('editVerifikasiModal').classList.add('show');
                }
            });
        }
        
        window.onclick = function(event){if(event.target.classList.contains('modal')) event.target.classList.remove('show');}
    </script>
</body>
</html>