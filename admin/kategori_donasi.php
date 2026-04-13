<?php
// ======================================================
// FILE: admin/kategori_donasi.php
// HALAMAN KELOLA KATEGORI TRANSAKSI (DONASI & PENGELUARAN)
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');
requirePermission('kategori_donasi.view');

$currentUser = getCurrentUser();

// ======================================================
// PROSES CRUD
// ======================================================

// Tambah Kategori
if (isset($_POST['tambah'])) {
    $nama_kategori = mysqli_real_escape_string($conn, $_POST['nama_kategori']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $tipe = mysqli_real_escape_string($conn, $_POST['tipe']);
    
    $sql = "INSERT INTO kategori_donasi (nama_kategori, deskripsi, tipe) VALUES ('$nama_kategori', '$deskripsi', '$tipe')";
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menambah kategori transaksi: $nama_kategori");
        $_SESSION['success'] = "Kategori transaksi berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan: " . mysqli_error($conn);
    }
    header("Location: kategori_donasi.php");
    exit();
}

// Edit Kategori
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $nama_kategori = mysqli_real_escape_string($conn, $_POST['nama_kategori']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $tipe = mysqli_real_escape_string($conn, $_POST['tipe']);
    
    $sql = "UPDATE kategori_donasi SET nama_kategori = '$nama_kategori', deskripsi = '$deskripsi', tipe = '$tipe' WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Mengedit kategori transaksi ID: $id");
        $_SESSION['success'] = "Kategori transaksi berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate: " . mysqli_error($conn);
    }
    header("Location: kategori_donasi.php");
    exit();
}

// Hapus Kategori
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    // Cek apakah kategori sudah digunakan di donasi atau pengeluaran
    $checkDonasi = mysqli_query($conn, "SELECT COUNT(*) as total FROM donasi WHERE kategori_id = $id");
    $usedDonasi = mysqli_fetch_assoc($checkDonasi);
    
    $checkPengeluaran = mysqli_query($conn, "SELECT COUNT(*) as total FROM pengeluaran WHERE kategori_id = $id");
    $usedPengeluaran = mysqli_fetch_assoc($checkPengeluaran);
    
    if ($usedDonasi['total'] > 0 || $usedPengeluaran['total'] > 0) {
        $_SESSION['error'] = "Kategori tidak bisa dihapus karena sudah digunakan pada transaksi!";
    } else {
        $sql = "DELETE FROM kategori_donasi WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            logActivity($currentUser['id'], "Menghapus kategori transaksi ID: $id");
            $_SESSION['success'] = "Kategori transaksi berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus: " . mysqli_error($conn);
        }
    }
    header("Location: kategori_donasi.php");
    exit();
}

// Filter dan Pencarian
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_tipe = isset($_GET['tipe']) ? mysqli_real_escape_string($conn, $_GET['tipe']) : '';

$where = "";
if ($search != '') {
    $where .= "WHERE nama_kategori LIKE '%$search%' OR deskripsi LIKE '%$search%'";
}
if ($filter_tipe != '' && $filter_tipe != 'semua') {
    if ($where == "") {
        $where = "WHERE tipe = '$filter_tipe'";
    } else {
        $where .= " AND tipe = '$filter_tipe'";
    }
}

// Ambil data kategori transaksi
$sql = "SELECT * FROM kategori_donasi $where ORDER BY id ASC";
$kategoris = query($sql);

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
    <title>Kategori Transaksi - Admin Panti Asuhan Al-Muthi</title>
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
        
        /* FORM TAMBAH - SATU BARIS */
        .form-tambah {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .form-tambah h4 {
            margin-bottom: 15px;
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
        
        .filter-section input:focus, .filter-section select:focus {
            border-color: #50c878;
            outline: none;
        }
        
        .btn-filter, .btn-reset {
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
        
        .btn-simpan {
            background: #50c878;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-simpan:hover {
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
        
        /* TIPE BADGE */
        .badge-donasi {
            background: #e8f5e9;
            color: #4caf50;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-pengeluaran {
            background: #fff3e0;
            color: #ff9800;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-both {
            background: #e3f2fd;
            color: #2196f3;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
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
                flex-direction: column;
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
                <h2>Kategori Transaksi</h2>
                <p>Kelola kategori untuk donasi dan pengeluaran panti</p>
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
            
            <!-- FORM TAMBAH KATEGORI - SATU BARIS -->
            <div class="form-tambah">
                <h4><i class="fas fa-plus-circle"></i> Tambah Kategori Transaksi</h4>
                <form method="POST" action="" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div style="flex: 2;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: #555;">Nama Kategori</label>
                        <input type="text" name="nama_kategori" placeholder="Contoh: Santunan, Pembangunan Panti..." required style="width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px;">
                    </div>
                    <div style="flex: 2;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: #555;">Deskripsi</label>
                        <input type="text" name="deskripsi" placeholder="Deskripsi kategori..." style="width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: #555;">Tipe</label>
                        <select name="tipe" required style="width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; background: white;">
                            <option value="donasi">Donasi</option>
                            <option value="pengeluaran">Pengeluaran</option>
                            <option value="both">Both (Donasi & Pengeluaran)</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" name="tambah" class="btn-simpan">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- FILTER & SEARCH -->
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Daftar Kategori Transaksi</h3>
            </div>
            
            <form method="GET" action="" class="filter-section">
                <input type="text" name="search" placeholder="Cari nama kategori atau deskripsi..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="tipe">
                    <option value="semua" <?php echo $filter_tipe == 'semua' || $filter_tipe == '' ? 'selected' : ''; ?>>Semua Tipe</option>
                    <option value="donasi" <?php echo $filter_tipe == 'donasi' ? 'selected' : ''; ?>>Donasi</option>
                    <option value="pengeluaran" <?php echo $filter_tipe == 'pengeluaran' ? 'selected' : ''; ?>>Pengeluaran</option>
                    <option value="both" <?php echo $filter_tipe == 'both' ? 'selected' : ''; ?>>Both</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="kategori_donasi.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
            </form>
            
            <!-- TABLE KATEGORI -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Kategori</th>
                            <th>Deskripsi</th>
                            <th>Tipe</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($kategoris) > 0): ?>
                            <?php $no = 1; ?>
                            <?php foreach ($kategoris as $kategori): ?>
                                <?php 
                                    $badgeClass = '';
                                    $badgeText = '';
                                    if ($kategori['tipe'] == 'donasi') {
                                        $badgeClass = 'badge-donasi';
                                        $badgeText = 'Donasi';
                                    } elseif ($kategori['tipe'] == 'pengeluaran') {
                                        $badgeClass = 'badge-pengeluaran';
                                        $badgeText = 'Pengeluaran';
                                    } else {
                                        $badgeClass = 'badge-both';
                                        $badgeText = 'Both';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($kategori['nama_kategori']); ?></td>
                                    <td><?php echo htmlspecialchars($kategori['deskripsi']) ?: '-'; ?></td>
                                    <td><span class="<?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span></td>
                                    <td>
                                        <button class="btn-edit" onclick="openEditModal(<?php echo $kategori['id']; ?>, '<?php echo htmlspecialchars(addslashes($kategori['nama_kategori'])); ?>', '<?php echo htmlspecialchars(addslashes($kategori['deskripsi'])); ?>', '<?php echo $kategori['tipe']; ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-delete" onclick="confirmDelete(<?php echo $kategori['id']; ?>)">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-folder-open" style="font-size: 48px; color: #ccc;"></i>
                                    <p style="margin-top: 10px; color: #888;">Belum ada data kategori transaksi</p>
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
                <h3><i class="fas fa-edit"></i> Edit Kategori Transaksi</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px;">Nama Kategori</label>
                    <input type="text" name="nama_kategori" id="edit_nama" required style="width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px;">Deskripsi</label>
                    <input type="text" name="deskripsi" id="edit_deskripsi" style="width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px;">Tipe</label>
                    <select name="tipe" id="edit_tipe" required style="width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px;">
                        <option value="donasi">Donasi</option>
                        <option value="pengeluaran">Pengeluaran</option>
                        <option value="both">Both (Donasi & Pengeluaran)</option>
                    </select>
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
        function openEditModal(id, nama, deskripsi, tipe) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_deskripsi').value = deskripsi;
            document.getElementById('edit_tipe').value = tipe;
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('editModal').classList.remove('show');
        }
        
        // Konfirmasi Hapus
        function confirmDelete(id) {
            if (confirm('Apakah Anda yakin ingin menghapus kategori ini? Kategori yang sudah dihapus tidak dapat dikembalikan.')) {
                window.location.href = 'kategori_donasi.php?hapus=' + id;
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