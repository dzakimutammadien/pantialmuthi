<?php
// ======================================================
// FILE: admin/verifikasi_pendaftaran.php
// HALAMAN VERIFIKASI PENDAFTARAN DONATUR
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');
requirePermission('users.view');

$currentUser = getCurrentUser();

// ======================================================
// PROSES APPROVE / REJECT
// ======================================================
if (isset($_POST['verifikasi'])) {
    $id = (int)$_POST['id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $catatan_admin = mysqli_real_escape_string($conn, $_POST['catatan_admin'] ?? '');
    $approved_by = $currentUser['id'];
    $approved_at = date('Y-m-d H:i:s');
    
    // Ambil data pendaftaran
    $query = mysqli_query($conn, "SELECT * FROM pendaftaran WHERE id = $id");
    $pendaftar = mysqli_fetch_assoc($query);
    
    if ($status == 'approved') {
        // ======================================================
        // APPROVE: Pindahkan data ke tabel users
        // ======================================================
        $role_id = 3; // Role Donatur
        
        // Cek username tidak bentrok
        $check_user = mysqli_query($conn, "SELECT id FROM users WHERE username = '" . $pendaftar['username'] . "'");
        if (mysqli_num_rows($check_user) > 0) {
            $_SESSION['error'] = "Username '" . $pendaftar['username'] . "' sudah digunakan! Tidak bisa approve.";
            header("Location: verifikasi_pendaftaran.php");
            exit();
        }
        
        $sql = "INSERT INTO users (nama_lengkap, username, password, jenis_kelamin, email, no_whatsapp, alamat, role_id, is_active, foto_profil) 
                VALUES (
                    '" . $pendaftar['nama_lengkap'] . "',
                    '" . $pendaftar['username'] . "',
                    '" . $pendaftar['password'] . "',
                    '" . $pendaftar['jenis_kelamin'] . "',
                    '" . $pendaftar['email'] . "',
                    '" . $pendaftar['no_whatsapp'] . "',
                    '" . $pendaftar['alamat'] . "',
                    $role_id,
                    1,
                    '" . $pendaftar['foto'] . "'
                )";
        
        if (mysqli_query($conn, $sql)) {
            // Update status pendaftaran
            $update = "UPDATE pendaftaran SET 
                       status = 'approved', 
                       catatan_admin = '$catatan_admin',
                       approved_by = $approved_by, 
                       approved_at = '$approved_at' 
                       WHERE id = $id";
            mysqli_query($conn, $update);
            
            logActivity($currentUser['id'], "Menyetujui pendaftaran ID: $id - Username: " . $pendaftar['username']);
            $_SESSION['success'] = "Pendaftaran berhasil disetujui! User telah dibuat.";
        } else {
            $_SESSION['error'] = "Gagal approve: " . mysqli_error($conn);
        }
    } else {
        // ======================================================
        // REJECT: Update status saja
        // ======================================================
        $update = "UPDATE pendaftaran SET 
                   status = 'rejected', 
                   catatan_admin = '$catatan_admin',
                   approved_by = $approved_by, 
                   approved_at = '$approved_at' 
                   WHERE id = $id";
        
        if (mysqli_query($conn, $update)) {
            logActivity($currentUser['id'], "Menolak pendaftaran ID: $id - Username: " . $pendaftar['username']);
            $_SESSION['success'] = "Pendaftaran berhasil ditolak.";
        } else {
            $_SESSION['error'] = "Gagal menolak: " . mysqli_error($conn);
        }
    }
    
    header("Location: verifikasi_pendaftaran.php");
    exit();
}

// ======================================================
// HAPUS PENDAFTARAN
// ======================================================
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    // Ambil foto untuk dihapus
    $query = mysqli_query($conn, "SELECT foto FROM pendaftaran WHERE id = $id");
    $data = mysqli_fetch_assoc($query);
    
    $sql = "DELETE FROM pendaftaran WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        if ($data['foto'] && $data['foto'] != 'default-user.png' && file_exists('../assets/uploads/users/' . $data['foto'])) {
            unlink('../assets/uploads/users/' . $data['foto']);
        }
        logActivity($currentUser['id'], "Menghapus pendaftaran ID: $id");
        $_SESSION['success'] = "Pendaftaran berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus: " . mysqli_error($conn);
    }
    header("Location: verifikasi_pendaftaran.php");
    exit();
}

// ======================================================
// FILTER & PAGINATION
// ======================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$where = "WHERE 1=1";

if ($search != '') {
    $where .= " AND (nama_lengkap LIKE '%$search%' OR username LIKE '%$search%' OR no_whatsapp LIKE '%$search%')";
}
if ($filter_status != '' && $filter_status != 'semua') {
    $where .= " AND status = '$filter_status'";
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_sql = "SELECT COUNT(*) as total FROM pendaftaran $where";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT p.*, u.nama_lengkap as approved_by_nama
        FROM pendaftaran p
        LEFT JOIN users u ON p.approved_by = u.id
        $where
        ORDER BY p.created_at DESC
        LIMIT $offset, $limit";
$pendaftar_list = query($sql);

// Hitung pending untuk badge
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pendaftaran WHERE status = 'pending'"))['total'];

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pendaftaran - Admin Panti Asuhan Al-Muthi</title>
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
        .submenu-item .badge-pending { background: #f44336; color: white; padding: 1px 8px; border-radius: 20px; font-size: 10px; margin-left: auto; }
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
        
        .content-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .filter-section { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-section input, .filter-section select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; }
        .filter-section input { flex: 2; }
        .filter-section select { flex: 1; }
        .btn-filter, .btn-reset { padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-filter { background: #50c878; color: white; }
        .btn-reset { background: #6c757d; color: white; text-decoration: none; display: inline-block; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; vertical-align: middle; }
        .foto-thumb { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #f0f2f5; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .status-pending { background: #fff3e0; color: #ff9800; }
        .status-approved { background: #e8f5e9; color: #4caf50; }
        .status-rejected { background: #ffebee; color: #f44336; }
        .btn-action { padding: 5px 10px; border: none; border-radius: 8px; cursor: pointer; margin: 2px; font-size: 12px; }
        .btn-detail { background: #17a2b8; color: white; }
        .btn-approve { background: #50c878; color: white; }
        .btn-reject { background: #ff9800; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        
        .alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; text-decoration: none; }
        .pagination a { background: #f0f2f5; color: #555; }
        .pagination .active { background: #50c878; color: white; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 600px; max-width: 90%; padding: 25px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .detail-item { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
        .detail-label { font-weight: 600; font-size: 12px; color: #888; margin-bottom: 5px; }
        .detail-value { font-size: 14px; color: #333; }
        .detail-foto { text-align: center; margin-bottom: 20px; }
        .detail-foto img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #50c878; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; }
        .form-group textarea { width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; resize: vertical; min-height: 80px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: #50c878; color: white; padding: 10px 25px; border: none; border-radius: 10px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 10px 25px; border: none; border-radius: 10px; cursor: pointer; }
        .radio-group { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        .radio-group label { display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; }
        .text-center { text-align: center; }
        
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
            <div class="menu-item has-submenu" onclick="toggleSubmenu(this)"><i class="fas fa-exchange-alt"></i><span>Transaksi</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu">
                <div class="submenu-item" onclick="location.href='verifikasi_donasi.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Donatur</span></div>
                <div class="submenu-item" onclick="location.href='verifikasi_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Pengeluaran Panti</span></div>
                <div class="submenu-item" onclick="location.href='verifikasi_program.php'"><i class="fas fa-heart"></i><span>Verifikasi Program</span></div>
                <div class="submenu-item" onclick="location.href='laporan_keuangan.php'"><i class="fas fa-chart-line"></i><span>Laporan Keuangan</span></div>
            </div>
            <div class="menu-item has-submenu open" onclick="toggleSubmenu(this)"><i class="fas fa-database"></i><span>Master Data</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu">
                <div class="submenu-item" onclick="location.href='kategori_donasi.php'"><i class="fas fa-tags"></i><span>Kategori Transaksi</span></div>
                <div class="submenu-item" onclick="location.href='kategori_role.php'"><i class="fas fa-user-tag"></i><span>Kategori Role</span></div>
                <div class="submenu-item" onclick="location.href='anak_asuh.php'"><i class="fas fa-child"></i><span>Data Anak Asuh</span></div>
                <div class="submenu-item" onclick="location.href='program.php'"><i class="fas fa-chalkboard-user"></i><span>Program Utama</span></div>
                <div class="submenu-item" onclick="location.href='galeri.php'"><i class="fas fa-images"></i><span>Galeri</span></div>
                <div class="submenu-item" onclick="location.href='perkembangan.php'"><i class="fas fa-seedling"></i><span>Perkembangan Anak</span></div>
                <div class="submenu-item" onclick="location.href='doa_khusus.php'"><i class="fas fa-pray"></i><span>Data Doa Khusus</span></div>
                
            </div>
            <div class="menu-item active" onclick="location.href='verifikasi_pendaftaran.php'">
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
                <h2>Verifikasi Pendaftaran</h2>
                <p>Validasi pendaftaran donatur baru</p>
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
                <input type="text" name="search" placeholder="Cari nama, username, no WhatsApp..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="status">
                    <option value="semua">Semua Status</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Disetujui</option>
                    <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>Ditolak</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="verifikasi_pendaftaran.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
            </form>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Foto</th>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>WhatsApp</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pendaftar_list) > 0): $no = $offset + 1; foreach ($pendaftar_list as $p): ?>
                            <?php 
                                $statusClass = '';
                                $statusText = '';
                                if ($p['status'] == 'pending') {
                                    $statusClass = 'status-pending';
                                    $statusText = '⏳ Pending';
                                } elseif ($p['status'] == 'approved') {
                                    $statusClass = 'status-approved';
                                    $statusText = '✅ Disetujui';
                                } else {
                                    $statusClass = 'status-rejected';
                                    $statusText = '❌ Ditolak';
                                }
                            ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <img src="../assets/uploads/users/<?php echo !empty($p['foto']) ? $p['foto'] : 'default-user.png'; ?>" 
                                         class="foto-thumb" 
                                         onerror="this.src='../assets/uploads/users/default-user.png'">
                                </td>
                                <td><?php echo htmlspecialchars($p['nama_lengkap']); ?></td>
                                <td><?php echo htmlspecialchars($p['username']); ?></td>
                                <td><?php echo htmlspecialchars($p['no_whatsapp']); ?></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <td>
                                    <button class="btn-action btn-detail" onclick="openDetailModal(<?php echo $p['id']; ?>)"><i class="fas fa-info-circle"></i> Detail</button>
                                    
                                    <?php if ($p['status'] == 'pending'): ?>
                                        <button class="btn-action btn-approve" onclick="openVerifikasiModal(<?php echo $p['id']; ?>, 'approved')"><i class="fas fa-check"></i> Setujui</button>
                                        <button class="btn-action btn-reject" onclick="openVerifikasiModal(<?php echo $p['id']; ?>, 'rejected')"><i class="fas fa-times"></i> Tolak</button>
                                    <?php endif; ?>
                                    
                                    <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $p['id']; ?>)"><i class="fas fa-trash"></i> Hapus</button>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:40px;">Tidak ada data pendaftaran</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&status=<?php echo $filter_status; ?>">« Sebelumnya</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&status=<?php echo $filter_status; ?>">Selanjutnya »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MODAL DETAIL -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detail Pendaftaran</h3>
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
                <h3>Verifikasi Pendaftaran</h3>
                <span class="close-modal" onclick="closeModal('verifikasiModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="id" id="verifikasi_id">
                <input type="hidden" name="status" id="verifikasi_status">
                <div id="verifikasiData"></div>
                <div class="form-group">
                    <label>Catatan (Opsional)</label>
                    <textarea name="catatan_admin" id="catatan_admin" placeholder="Isi catatan jika ada..." rows="3"></textarea>
                    <small style="color:#888;">Catatan akan tersimpan dan terlihat di histori</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('verifikasiModal')">Batal</button>
                    <button type="submit" name="verifikasi" class="btn-save">Konfirmasi</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleSubmenu(e){e.classList.toggle('open');let s=e.nextElementSibling;if(s&&s.classList.contains('submenu'))s.classList.toggle('open');}
        function closeModal(id){document.getElementById(id).classList.remove('show');}
        
        function openDetailModal(id){
            fetch('get_pendaftaran.php?id='+id)
                .then(r=>r.json())
                .then(d=>{
                    if(d.success){
                        let p = d.data;
                        let statusText = p.status == 'pending' ? '⏳ Pending' : (p.status == 'approved' ? '✅ Disetujui' : '❌ Ditolak');
                        let statusClass = p.status == 'pending' ? 'status-pending' : (p.status == 'approved' ? 'status-approved' : 'status-rejected');
                        let fotoUrl = p.foto ? p.foto : 'default-user.png';
                        let catatanHtml = p.catatan_admin ? `<div class="detail-item"><div class="detail-label">Catatan Admin</div><div class="detail-value">${p.catatan_admin}</div></div>` : '';
                        
                        document.getElementById('detailContent').innerHTML = `
                            <div class="detail-foto"><img src="../assets/uploads/users/${fotoUrl}" onerror="this.src='../assets/uploads/users/default-user.png'"></div>
                            <div class="detail-item"><div class="detail-label">Nama Lengkap</div><div class="detail-value">${p.nama_lengkap}</div></div>
                            <div class="detail-item"><div class="detail-label">Username</div><div class="detail-value">${p.username}</div></div>
                            <div class="detail-item"><div class="detail-label">Jenis Kelamin</div><div class="detail-value">${p.jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan'}</div></div>
                            <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value">${p.email || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">No. WhatsApp</div><div class="detail-value">${p.no_whatsapp}</div></div>
                            <div class="detail-item"><div class="detail-label">Alamat</div><div class="detail-value">${p.alamat || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value"><span class="status-badge ${statusClass}">${statusText}</span></div></div>
                            ${catatanHtml}
                            ${p.approved_at ? `<div class="detail-item"><div class="detail-label">Diverifikasi Pada</div><div class="detail-value">${p.approved_at}</div></div>` : ''}
                            ${p.approved_by_nama ? `<div class="detail-item"><div class="detail-label">Diverifikasi Oleh</div><div class="detail-value">${p.approved_by_nama}</div></div>` : ''}
                            <div class="detail-item"><div class="detail-label">Tanggal Daftar</div><div class="detail-value">${p.created_at}</div></div>
                        `;
                        document.getElementById('detailModal').classList.add('show');
                    }
                });
        }
        
        function openVerifikasiModal(id, status){
            fetch('get_pendaftaran.php?id='+id)
                .then(r=>r.json())
                .then(d=>{
                    if(d.success){
                        let p = d.data;
                        let statusText = status == 'approved' ? '✅ SETUJUI' : '❌ TOLAK';
                        let statusColor = status == 'approved' ? '#50c878' : '#f44336';
                        
                        document.getElementById('verifikasi_id').value = p.id;
                        document.getElementById('verifikasi_status').value = status;
                        document.getElementById('verifikasiData').innerHTML = `
                            <div class="detail-item"><div class="detail-label">Nama</div><div class="detail-value">${p.nama_lengkap}</div></div>
                            <div class="detail-item"><div class="detail-label">Username</div><div class="detail-value">${p.username}</div></div>
                            <div class="detail-item"><div class="detail-label">No. WhatsApp</div><div class="detail-value">${p.no_whatsapp}</div></div>
                            <div class="detail-item"><div class="detail-label">Aksi</div><div class="detail-value" style="font-weight:600; color:${statusColor}; font-size:16px;">${statusText}</div></div>
                        `;
                        document.getElementById('catatan_admin').value = '';
                        document.getElementById('verifikasiModal').classList.add('show');
                    }
                });
        }
        
        function confirmDelete(id){
            if(confirm('Yakin ingin menghapus pendaftaran ini?')){
                window.location.href = 'verifikasi_pendaftaran.php?hapus=' + id;
            }
        }
        
        window.onclick = function(event){if(event.target.classList.contains('modal')) event.target.classList.remove('show');}
    </script>
</body>
</html>