<?php
// ======================================================
// FILE: admin/users.php
// HALAMAN MANAJEMEN USER (CRUD + STATUS AKTIF/NONAKTIF)
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');
requirePermission('users.view');

$currentUser = getCurrentUser();

// ======================================================
// PROSES CRUD
// ======================================================

// Tambah User
if (isset($_POST['tambah'])) {
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $no_whatsapp = mysqli_real_escape_string($conn, $_POST['no_whatsapp']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $role_id = (int)$_POST['role_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Cek username sudah ada belum
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['error'] = "Username '$username' sudah digunakan!";
    } else {
        $sql = "INSERT INTO users (nama_lengkap, username, password, jenis_kelamin, email, no_whatsapp, alamat, role_id, is_active) 
                VALUES ('$nama_lengkap', '$username', '$password', '$jenis_kelamin', '$email', '$no_whatsapp', '$alamat', $role_id, $is_active)";
        if (mysqli_query($conn, $sql)) {
            logActivity($currentUser['id'], "Menambah user: $username");
            $_SESSION['success'] = "User berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan user: " . mysqli_error($conn);
        }
    }
    header("Location: users.php");
    exit();
}

// Edit User
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $no_whatsapp = mysqli_real_escape_string($conn, $_POST['no_whatsapp']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $role_id = (int)$_POST['role_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Jika password diisi, update password
    $password_sql = "";
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $password_sql = ", password = '$password'";
    }
    
    $sql = "UPDATE users SET 
            nama_lengkap = '$nama_lengkap',
            jenis_kelamin = '$jenis_kelamin',
            email = '$email',
            no_whatsapp = '$no_whatsapp',
            alamat = '$alamat',
            role_id = $role_id,
            is_active = $is_active
            $password_sql
            WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Mengedit user ID: $id");
        $_SESSION['success'] = "User berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate user: " . mysqli_error($conn);
    }
    header("Location: users.php");
    exit();
}

// Hapus User
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    // Cek jangan sampai hapus diri sendiri
    if ($id == $currentUser['id']) {
        $_SESSION['error'] = "Anda tidak bisa menghapus akun sendiri!";
    } else {
        $sql = "DELETE FROM users WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            logActivity($currentUser['id'], "Menghapus user ID: $id");
            $_SESSION['success'] = "User berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus user: " . mysqli_error($conn);
        }
    }
    header("Location: users.php");
    exit();
}

// Toggle Status (Aktif/Nonaktif)
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    
    // Cek jangan toggle diri sendiri
    if ($id == $currentUser['id']) {
        $_SESSION['error'] = "Anda tidak bisa menonaktifkan akun sendiri!";
    } else {
        $user = mysqli_query($conn, "SELECT is_active FROM users WHERE id = $id");
        $data = mysqli_fetch_assoc($user);
        $new_status = $data['is_active'] ? 0 : 1;
        $status_text = $new_status ? "diaktifkan" : "dinonaktifkan";
        
        $sql = "UPDATE users SET is_active = $new_status WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            logActivity($currentUser['id'], "$status_text user ID: $id");
            $_SESSION['success'] = "User berhasil $status_text!";
        } else {
            $_SESSION['error'] = "Gagal mengubah status user!";
        }
    }
    header("Location: users.php");
    exit();
}

// ======================================================
// FILTER, SEARCH & PAGINATION
// ======================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_role = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$where = "WHERE u.id != " . $currentUser['id']; // exclude diri sendiri

if ($search != '') {
    $where .= " AND (u.nama_lengkap LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%')";
}
if ($filter_role != '' && $filter_role != 'semua') {
    $where .= " AND r.nama_role = '$filter_role'";
}
if ($filter_status != '' && $filter_status != 'semua') {
    $where .= " AND u.is_active = " . ($filter_status == 'aktif' ? 1 : 0);
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_sql = "SELECT COUNT(*) as total FROM users u JOIN roles r ON u.role_id = r.id $where";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT u.*, r.nama_role 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        $where 
        ORDER BY u.created_at DESC 
        LIMIT $offset, $limit";
$users = query($sql);

// Ambil roles untuk dropdown
$roles = query("SELECT * FROM roles ORDER BY nama_role ASC");

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
    <title>Manajemen User - Admin Panti Asuhan Al-Muthi</title>
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
        
        /* FILTER SECTION */
        .filter-section {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-section input, .filter-section select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .filter-section input {
            flex: 2;
        }
        
        .filter-section select {
            flex: 1;
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
        
        .btn-tambah {
            background: #50c878;
            color: white;
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
        
        .badge-aktif {
            background: #e8f5e9;
            color: #4caf50;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-nonaktif {
            background: #ffebee;
            color: #f44336;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-role {
            background: #e3f2fd;
            color: #2196f3;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }
        
        .btn-action {
            padding: 5px 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
            margin: 2px;
        }
        
        .btn-detail {
            background: #17a2b8;
            color: white;
        }
        
        .btn-edit {
            background: #50c878;
            color: white;
        }
        
        .btn-toggle {
            background: #ffc107;
            color: #333;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
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
        
        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        
        .pagination a {
            background: #f0f2f5;
            color: #555;
        }
        
        .pagination a:hover {
            background: #50c878;
            color: white;
        }
        
        .pagination .active {
            background: #50c878;
            color: white;
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
            overflow-y: auto;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            width: 600px;
            max-width: 90%;
            padding: 25px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            font-size: 18px;
        }
        
        .close-modal {
            font-size: 24px;
            cursor: pointer;
            color: #999;
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
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #50c878;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .btn-save {
            background: #50c878;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }
        
        .detail-item {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-label {
            font-weight: 600;
            font-size: 12px;
            color: #888;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 14px;
            color: #333;
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
                <span>Beranda</span>
            </div>
            <div class="menu-item active" onclick="location.href='users.php'">
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
                <div class="submenu-item" onclick="location.href='pengeluaran_panti.php'">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Pengeluaran Panti</span>
                </div>
                <div class="submenu-item" onclick="location.href='laporan_keuangan.php'">
                    <i class="fas fa-chart-line"></i>
                    <span>Laporan Keuangan</span>
                </div>
            </div>
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
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Manajemen User</h2>
                <p>Kelola data pengguna sistem</p>
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
            
            <!-- FILTER & SEARCH -->
            <form method="GET" action="" class="filter-section">
                <input type="text" name="search" placeholder="Cari nama, username, atau email..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="role">
                    <option value="semua" <?php echo $filter_role == 'semua' || $filter_role == '' ? 'selected' : ''; ?>>Semua Role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['nama_role']; ?>" <?php echo $filter_role == $role['nama_role'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst($role['nama_role']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="semua" <?php echo $filter_status == 'semua' || $filter_status == '' ? 'selected' : ''; ?>>Semua Status</option>
                    <option value="aktif" <?php echo $filter_status == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="nonaktif" <?php echo $filter_status == 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="users.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
                <button type="button" class="btn-tambah" onclick="openTambahModal()">
                    <i class="fas fa-plus"></i> Tambah
                </button>
            </form>
            
            <!-- TABLE USER -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php $no = $offset + 1; ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><span class="badge-role"><?php echo ucfirst($user['nama_role']); ?></span></td>
                                    <td>
                                        <span class="<?php echo $user['is_active'] ? 'badge-aktif' : 'badge-nonaktif'; ?>">
                                            <?php echo $user['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-action btn-detail" onclick="openDetailModal(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-info-circle"></i> Detail
                                        </button>
                                        <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-action btn-toggle" onclick="toggleStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active']; ?>)">
                                            <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check-circle'; ?>"></i>
                                            <?php echo $user['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                        </button>
                                        <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['nama_lengkap']); ?>')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-users-slash" style="font-size: 48px; color: #ccc;"></i>
                                    <p style="margin-top: 10px; color: #888;">Tidak ada data user</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&role=<?php echo $filter_role; ?>&status=<?php echo $filter_status; ?>">&laquo; Sebelumnya</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&role=<?php echo $filter_role; ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&role=<?php echo $filter_role; ?>&status=<?php echo $filter_status; ?>">Selanjutnya &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MODAL TAMBAH USER -->
    <div id="tambahModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Tambah User</h3>
                <span class="close-modal" onclick="closeModal('tambahModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin" required>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>
                    <div class="form-group">
                        <label>No. Whatsapp</label>
                        <input type="text" name="no_whatsapp">
                    </div>
                </div>
                <div class="form-group">
                    <label>Alamat</label>
                    <textarea name="alamat" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo ucfirst($role['nama_role']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="is_active">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('tambahModal')">Batal</button>
                    <button type="submit" name="tambah" class="btn-save">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- MODAL EDIT USER -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit User</h3>
                <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" id="edit_nama" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" id="edit_username" disabled style="background:#f5f5f5;">
                        <small style="color:#888;">Username tidak bisa diubah</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password (kosongkan jika tidak diubah)</label>
                        <input type="password" name="password" placeholder="******">
                    </div>
                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin" id="edit_jk" required>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email">
                    </div>
                    <div class="form-group">
                        <label>No. Whatsapp</label>
                        <input type="text" name="no_whatsapp" id="edit_wa">
                    </div>
                </div>
                <div class="form-group">
                    <label>Alamat</label>
                    <textarea name="alamat" id="edit_alamat" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role_id" id="edit_role" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo ucfirst($role['nama_role']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="is_active" id="edit_status">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Batal</button>
                    <button type="submit" name="edit" class="btn-save">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- MODAL DETAIL USER -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-circle"></i> Detail User</h3>
                <span class="close-modal" onclick="closeModal('detailModal')">&times;</span>
            </div>
            <div id="detailContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('detailModal')">Tutup</button>
            </div>
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
        
        // Modal Tambah
        function openTambahModal() {
            document.getElementById('tambahModal').classList.add('show');
        }
        
        // Modal Edit - Load data via AJAX
        function openEditModal(id) {
            fetch(`get_user.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_id').value = data.user.id;
                        document.getElementById('edit_nama').value = data.user.nama_lengkap;
                        document.getElementById('edit_username').value = data.user.username;
                        document.getElementById('edit_jk').value = data.user.jenis_kelamin;
                        document.getElementById('edit_email').value = data.user.email;
                        document.getElementById('edit_wa').value = data.user.no_whatsapp;
                        document.getElementById('edit_alamat').value = data.user.alamat;
                        document.getElementById('edit_role').value = data.user.role_id;
                        document.getElementById('edit_status').value = data.user.is_active;
                        document.getElementById('editModal').classList.add('show');
                    }
                });
        }
        
        // Modal Detail
        function openDetailModal(id) {
            fetch(`get_user.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const u = data.user;
                        document.getElementById('detailContent').innerHTML = `
                            <div class="detail-item">
                                <div class="detail-label">Nama Lengkap</div>
                                <div class="detail-value">${u.nama_lengkap}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Username</div>
                                <div class="detail-value">${u.username}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Jenis Kelamin</div>
                                <div class="detail-value">${u.jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Email</div>
                                <div class="detail-value">${u.email || '-'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">No. Whatsapp</div>
                                <div class="detail-value">${u.no_whatsapp || '-'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Alamat</div>
                                <div class="detail-value">${u.alamat || '-'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Role</div>
                                <div class="detail-value">${u.nama_role}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">${u.is_active ? 'Aktif' : 'Nonaktif'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Dibuat Pada</div>
                                <div class="detail-value">${u.created_at}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Terakhir Update</div>
                                <div class="detail-value">${u.updated_at}</div>
                            </div>
                        `;
                        document.getElementById('detailModal').classList.add('show');
                    }
                });
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        // Toggle Status
        function toggleStatus(id, currentStatus) {
            const action = currentStatus ? 'nonaktifkan' : 'aktifkan';
            if (confirm(`Apakah Anda yakin ingin ${action} user ini?`)) {
                window.location.href = `users.php?toggle=${id}`;
            }
        }
        
        // Konfirmasi Hapus
        function confirmDelete(id, nama) {
            if (confirm(`Apakah Anda yakin ingin menghapus user "${nama}"? Data yang dihapus tidak dapat dikembalikan.`)) {
                window.location.href = `users.php?hapus=${id}`;
            }
        }
        
        // Tutup modal klik di luar
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>