<?php
// ======================================================
// FILE: admin/kategori_role.php
// HALAMAN KELOLA KATEGORI ROLE (MASTER DATA)
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');
requirePermission('kategori_role.view');

$currentUser = getCurrentUser();

// ======================================================
// PROSES CRUD
// ======================================================

// Tambah Role
if (isset($_POST['tambah'])) {
    $nama_role = mysqli_real_escape_string($conn, $_POST['nama_role']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    
    $sql = "INSERT INTO roles (nama_role, deskripsi) VALUES ('$nama_role', '$deskripsi')";
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menambah role: $nama_role");
        $_SESSION['success'] = "Role berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan role: " . mysqli_error($conn);
    }
    header("Location: kategori_role.php");
    exit();
}

// Edit Role
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $nama_role = mysqli_real_escape_string($conn, $_POST['nama_role']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    
    // Cek apakah role system (tidak boleh diedit namanya)
    $check = mysqli_query($conn, "SELECT nama_role FROM roles WHERE id = $id");
    $old = mysqli_fetch_assoc($check);
    
    if (in_array($old['nama_role'], ['admin', 'pengasuh', 'donatur'])) {
        // Role system hanya boleh diedit deskripsinya
        $sql = "UPDATE roles SET deskripsi = '$deskripsi' WHERE id = $id";
    } else {
        $sql = "UPDATE roles SET nama_role = '$nama_role', deskripsi = '$deskripsi' WHERE id = $id";
    }
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Mengedit role ID: $id");
        $_SESSION['success'] = "Role berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate role: " . mysqli_error($conn);
    }
    header("Location: kategori_role.php");
    exit();
}

// Hapus Role
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    // Cek apakah role digunakan oleh user
    $check = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role_id = $id");
    $used = mysqli_fetch_assoc($check);
    
    if ($used['total'] > 0) {
        $_SESSION['error'] = "Role tidak bisa dihapus karena masih digunakan oleh {$used['total']} user!";
    } else {
        // Cek apakah role system (tidak boleh dihapus)
        $role = mysqli_query($conn, "SELECT nama_role FROM roles WHERE id = $id");
        $nama = mysqli_fetch_assoc($role);
        
        if (in_array($nama['nama_role'], ['admin', 'pengasuh', 'donatur'])) {
            $_SESSION['error'] = "Role system tidak boleh dihapus!";
        } else {
            $sql = "DELETE FROM roles WHERE id = $id";
            if (mysqli_query($conn, $sql)) {
                logActivity($currentUser['id'], "Menghapus role ID: $id");
                $_SESSION['success'] = "Role berhasil dihapus!";
            } else {
                $_SESSION['error'] = "Gagal menghapus role: " . mysqli_error($conn);
            }
        }
    }
    header("Location: kategori_role.php");
    exit();
}

// Filter dan Pencarian
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where = "";
if ($search != '') {
    $where = "WHERE nama_role LIKE '%$search%' OR deskripsi LIKE '%$search%'";
}

// Ambil data roles
$sql = "SELECT * FROM roles $where ORDER BY 
        CASE 
            WHEN nama_role = 'admin' THEN 1
            WHEN nama_role = 'pengasuh' THEN 2
            WHEN nama_role = 'donatur' THEN 3
            ELSE 4
        END, nama_role ASC";
$roles = query($sql);

// Ambil pesan session
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Role - Admin Panti Asuhan Al-Muthi</title>
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
        
        /* CONTENT CARD */
        .content-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .card-header h3 {
            font-size: 18px;
            color: #333;
        }
        
        /* FORM TAMBAH */
        .form-tambah {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 13px;
            color: #555;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #50c878;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        /* FILTER SECTION */
        .filter-section {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-section input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .filter-section input:focus {
            border-color: #50c878;
            outline: none;
        }
        
        .btn-filter, .btn-reset, .btn-tambah {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-filter {
            background: #50c878;
            color: white;
        }
        
        .btn-filter:hover {
            background: #2e8b57;
        }
        
        .btn-reset {
            background: #6c757d;
            color: white;
        }
        
        .btn-reset:hover {
            background: #5a6268;
        }
        
        .btn-tambah {
            background: #50c878;
            color: white;
        }
        
        .btn-tambah:hover {
            background: #2e8b57;
        }
        
        /* TABLE */
        .table-wrapper {
            overflow-x: auto;
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
        
        .badge-system {
            background: #e8f5e9;
            color: #4caf50;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
        }
        
        .btn-edit, .btn-delete {
            padding: 6px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background: #50c878;
            color: white;
            margin-right: 5px;
        }
        
        .btn-edit:hover {
            background: #2e8b57;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        
        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            width: 500px;
            max-width: 90%;
            padding: 25px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            font-size: 18px;
        }
        
        .close-modal {
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-save {
            background: #50c878;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }
            .main-content {
                margin-left: 0;
            }
            .form-row {
                grid-template-columns: 1fr;
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
            <div class="menu-item" onclick="location.href='dashboard.php'">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </div>
            <div class="menu-item" onclick="location.href='users.php'">
                <i class="fas fa-users"></i>
                <span>Manajemen User</span>
            </div>
           <div class="menu-item has-submenu" onclick="toggleSubmenu(this)">
    <i class="fas fa-exchange-alt"></i>
    <span>Transaksi</span>
    <i class="fas fa-chevron-down arrow"></i>
</div>
<div class="submenu">
    <div class="submenu-item" onclick="location.href='donasi_donatur.php'">
        <i class="fas fa-hand-holding-heart"></i>
        <span>Donasi Donatur</span>
    </div>
    <div class="submenu-item" onclick="location.href='verifikasi_pengeluaran.php'">
        <i class="fas fa-money-bill-wave"></i>
        <span>Pengeluaran Panti</span>
    </div>
    <div class="submenu-item" onclick="location.href='laporan_keuangan.php'">
        <i class="fas fa-chart-line"></i>
        <span>Laporan Keuangan</span>
    </div>
</div>

<!-- Master Data: ada class "open" → selalu terbuka saat load -->
<div class="menu-item has-submenu open" onclick="toggleSubmenu(this)">
    <i class="fas fa-database"></i>
    <span>Master Data</span>
    <i class="fas fa-chevron-down arrow"></i>
</div>
<div class="submenu open">
    <div class="submenu-item" onclick="location.href='kategori_donasi.php'">
        <i class="fas fa-tags"></i>
        <span>Kategori Transaksi</span>
    </div>
    <div class="submenu-item active" onclick="location.href='kategori_role.php'">
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
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Kategori Role</h2>
                <p>Kelola role pengguna sistem</p>
            </div>
            <div class="profile-dropdown">
                <div class="profile-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="profil.php"><i class="fas fa-user-circle"></i> Profil</a>
                    <a href="log_aktivitas.php"><i class="fas fa-history"></i> Log Aktivitas</a>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
        
        <div class="content-card">
            <!-- ALERT -->
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- FORM TAMBAH ROLE -->
            <!-- FORM TAMBAH ROLE - SATU BARIS -->
<div class="form-tambah">
    <h4 style="margin-bottom: 15px;"><i class="fas fa-plus-circle"></i> Tambah Role Baru</h4>
    <form method="POST" action="" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 2;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: #555;">Nama Kategori Role</label>
            <input type="text" name="nama_role" placeholder="Contoh: Donatur VIP" required style="width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px;">
        </div>
        <div style="flex: 3;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: #555;">Deskripsi</label>
            <input type="text" name="deskripsi" placeholder="Deskripsi role..." style="width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px;">
        </div>
        <div>
            <button type="submit" name="tambah" class="btn-tambah" style="padding: 10px 25px;">
                <i class="fas fa-save"></i> Simpan
            </button>
        </div>
    </form>
</div>
            
            <!-- FILTER & SEARCH -->
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Daftar Role</h3>
            </div>
            
            <form method="GET" action="" class="filter-section">
                <input type="text" name="search" placeholder="Cari nama role atau deskripsi..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="kategori_role.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
            </form>
            
            <!-- TABLE ROLE -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Role</th>
                            <th>Deskripsi</th>
                            <th>Info</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($roles) > 0): ?>
                            <?php $no = 1; ?>
                            <?php foreach ($roles as $role): ?>
                                <?php 
                                    $isSystem = in_array($role['nama_role'], ['admin', 'pengasuh', 'donatur']);
                                ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($role['nama_role']); ?>
                                        <?php if ($isSystem): ?>
                                            <span class="badge-system">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($role['deskripsi']); ?></td>
                                    <td>
                                        <?php if ($isSystem): ?>
                                            <small style="color:#888;">(tidak bisa dihapus)</small>
                                        <?php else: ?>
                                            <small style="color:#50c878;">custom</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn-edit" onclick="openEditModal(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['nama_role']); ?>', '<?php echo htmlspecialchars(addslashes($role['deskripsi'])); ?>', <?php echo $isSystem ? 'true' : 'false'; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <?php if (!$isSystem): ?>
                                            <button class="btn-delete" onclick="confirmDelete(<?php echo $role['id']; ?>)">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-folder-open" style="font-size: 48px; color: #ccc;"></i>
                                    <p style="margin-top: 10px; color: #888;">Belum ada data role</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- MODAL EDIT -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Role</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Nama Kategori Role</label>
                    <input type="text" name="nama_role" id="edit_nama_role" required>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" id="edit_deskripsi" rows="3"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" name="edit" class="btn-save">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Toggle Submenu
        function toggleSubmenu(element) {
            element.classList.toggle('open');
            let submenu = element.nextElementSibling;
            if (submenu && submenu.classList.contains('submenu')) {
                submenu.classList.toggle('open');
            }
        }
        
        // Modal Edit
        function openEditModal(id, nama_role, deskripsi, isSystem) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama_role').value = nama_role;
            document.getElementById('edit_deskripsi').value = deskripsi;
            
            // Jika role system, nama role tidak bisa diedit
            if (isSystem) {
                document.getElementById('edit_nama_role').disabled = true;
            } else {
                document.getElementById('edit_nama_role').disabled = false;
            }
            
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('editModal').classList.remove('show');
        }
        
        // Konfirmasi Hapus
        function confirmDelete(id) {
            if (confirm('Apakah Anda yakin ingin menghapus role ini? Role yang sudah dihapus tidak dapat dikembalikan.')) {
                window.location.href = 'kategori_role.php?hapus=' + id;
            }
        }
        
        // Tutup modal klik di luar
        window.onclick = function(event) {
            let modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>