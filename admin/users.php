<?php
// ======================================================
// FILE: admin/users.php
// HALAMAN MANAJEMEN USER DENGAN FOTO
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');
requirePermission('users.view');

$currentUser = getCurrentUser();

// ======================================================
// FUNGSI UPLOAD FOTO
// ======================================================
function uploadFoto($existing_foto = null) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            return ['success' => false, 'message' => 'Format file tidak diizinkan (JPG, PNG, GIF, WEBP)'];
        }
        
        $filename = 'user_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target = '../assets/uploads/users/' . $filename;
        
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
            // Hapus foto lama jika bukan default
            if ($existing_foto && $existing_foto != 'default-user.png' && file_exists('../assets/uploads/users/' . $existing_foto)) {
                unlink('../assets/uploads/users/' . $existing_foto);
            }
            return ['success' => true, 'filename' => $filename];
        }
    }
    return ['success' => true, 'filename' => $existing_foto ?: 'default-user.png'];
}

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
    
    // Upload foto
    $upload = uploadFoto();
    $foto = $upload['success'] ? $upload['filename'] : 'default-user.png';
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['error'] = "Username '$username' sudah digunakan!";
    } else {
        $sql = "INSERT INTO users (nama_lengkap, username, password, jenis_kelamin, email, no_whatsapp, alamat, role_id, is_active, foto_profil) 
                VALUES ('$nama_lengkap', '$username', '$password', '$jenis_kelamin', '$email', '$no_whatsapp', '$alamat', $role_id, $is_active, '$foto')";
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
    
    // Ambil foto lama
    $query_foto = mysqli_query($conn, "SELECT foto_profil FROM users WHERE id = $id");
    $old_foto = mysqli_fetch_assoc($query_foto)['foto_profil'];
    
    // Upload foto baru
    $upload = uploadFoto($old_foto);
    $foto = $upload['success'] ? $upload['filename'] : $old_foto;
    
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
            is_active = $is_active,
            foto_profil = '$foto'
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

// Hapus User (beserta fotonya)
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    if ($id == $currentUser['id']) {
        $_SESSION['error'] = "Anda tidak bisa menghapus akun sendiri!";
    } else {
        // Ambil foto untuk dihapus
        $query_foto = mysqli_query($conn, "SELECT foto_profil FROM users WHERE id = $id");
        $foto = mysqli_fetch_assoc($query_foto)['foto_profil'];
        
        $sql = "DELETE FROM users WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            // Hapus file foto jika bukan default
            if ($foto && $foto != 'default-user.png' && file_exists('../assets/uploads/users/' . $foto)) {
                unlink('../assets/uploads/users/' . $foto);
            }
            logActivity($currentUser['id'], "Menghapus user ID: $id");
            $_SESSION['success'] = "User berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus user: " . mysqli_error($conn);
        }
    }
    header("Location: users.php");
    exit();
}

// Toggle Status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    
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

// Filter & Pagination
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_role = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$where = "WHERE u.id != " . $currentUser['id'];

if ($search != '') {
    $where .= " AND (u.nama_lengkap LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%')";
}
if ($filter_role != '' && $filter_role != 'semua') {
    $where .= " AND r.nama_role = '$filter_role'";
}
if ($filter_status != '' && $filter_status != 'semua') {
    $where .= " AND u.is_active = " . ($filter_status == 'aktif' ? 1 : 0);
}

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

$roles = query("SELECT * FROM roles ORDER BY nama_role ASC");

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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; overflow-x: hidden; }
        .sidebar { position: fixed; left: 0; top: 0; width: 280px; height: 100%; background: linear-gradient(135deg, #1a3a2a 0%, #2d4a3a 100%); color: white; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 12px; justify-content: center; }
        .sidebar-logo { width: 45px; height: 45px; object-fit: contain; }
        .sidebar-header h3 { font-size: 16px; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 12px 20px; display: flex; align-items: center; gap: 12px; cursor: pointer; color: rgba(255,255,255,0.8); }
        .menu-item:hover, .menu-item.active { background: rgba(80,200,120,0.3); border-left: 4px solid #50c878; }
        .submenu { padding-left: 56px; max-height: 0; overflow: hidden; transition: max-height 0.3s; }
        .submenu.open { max-height: 300px; }
        .submenu-item { padding: 10px 20px; display: flex; align-items: center; gap: 12px; cursor: pointer; color: rgba(255,255,255,0.7); font-size: 13px; }
        .menu-item.has-submenu .arrow { margin-left: auto; transition: transform 0.3s; }
        .menu-item.has-submenu.open .arrow { transform: rotate(180deg); }
        .main-content { margin-left: 280px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-title h2 { font-size: 20px; color: #333; }
        .profile-dropdown { position: relative; }
        .profile-icon { width: 45px; height: 45px; background: linear-gradient(135deg, #50c878, #2e8b57); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; color: white; }
        .dropdown-menu { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        .content-card { background: white; border-radius: 20px; padding: 25px; }
        .filter-section { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-section input, .filter-section select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; }
        .btn-filter { background: #50c878; color: white; padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; }
        .btn-reset { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-tambah { background: #50c878; color: white; padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .foto-thumb { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #f0f2f5; }
        .badge-aktif { background: #e8f5e9; color: #4caf50; padding: 4px 12px; border-radius: 20px; font-size: 11px; }
        .badge-nonaktif { background: #ffebee; color: #f44336; padding: 4px 12px; border-radius: 20px; font-size: 11px; }
        .badge-role { background: #e3f2fd; color: #2196f3; padding: 4px 12px; border-radius: 20px; font-size: 11px; }
        .btn-action { padding: 5px 10px; border: none; border-radius: 8px; cursor: pointer; margin: 2px; font-size: 12px; }
        .btn-detail { background: #17a2b8; color: white; }
        .btn-edit { background: #50c878; color: white; }
        .btn-toggle { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        .alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; text-decoration: none; }
        .pagination a { background: #f0f2f5; color: #555; }
        .pagination .active { background: #50c878; color: white; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 650px; max-width: 90%; padding: 25px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: #50c878; color: white; padding: 10px 25px; border: none; border-radius: 10px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 10px 25px; border: none; border-radius: 10px; cursor: pointer; }
        .current-photo { text-align: center; margin-bottom: 15px; }
        .current-photo img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #50c878; }
        .detail-foto { text-align: center; margin-bottom: 20px; }
        .detail-foto img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #50c878; }
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
        @media (max-width: 768px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } .form-row { grid-template-columns: 1fr; } }

    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/image/almuthi.png" alt="Logo" class="sidebar-logo" onerror="this.style.display='none'">
            <div><h3>Panti Asuhan</h3><p>Al-Muthi</p></div>
        </div>
        <div class="sidebar-menu">
            <div class="menu-item" onclick="location.href='dashboard.php'"><i class="fas fa-tachometer-alt"></i><span>Beranda</span></div>
            <div class="menu-item active" onclick="location.href='users.php'"><i class="fas fa-users"></i><span>Manajemen User</span></div>
            <div class="menu-item has-submenu" onclick="toggleSubmenu(this)"><i class="fas fa-exchange-alt"></i><span>Transaksi</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu"><div class="submenu-item" onclick="location.href='donasi_donatur.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Donatur</span></div><div class="submenu-item" onclick="location.href='verifikasi_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Pengeluaran Panti</span></div><div class="submenu-item" onclick="location.href='laporan_keuangan.php'"><i class="fas fa-chart-line"></i><span>Laporan Keuangan</span></div></div>
            <div class="menu-item has-submenu" onclick="toggleSubmenu(this)"><i class="fas fa-database"></i><span>Master Data</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu"><div class="submenu-item" onclick="location.href='kategori_donasi.php'"><i class="fas fa-tags"></i><span>Kategori Transaksi</span></div><div class="submenu-item" onclick="location.href='kategori_role.php'"><i class="fas fa-user-tag"></i><span>Kategori Role</span></div><div class="submenu-item" onclick="location.href='anak_asuh.php'"><i class="fas fa-child"></i><span>Data Anak Asuh</span></div><div class="submenu-item" onclick="location.href='doa_khusus.php'"><i class="fas fa-pray"></i><span>Data Doa Khusus</span></div></div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div class="page-title"><h2>Manajemen User</h2><p>Kelola data pengguna sistem</p></div>
            <div class="profile-dropdown"><div class="profile-icon"><i class="fas fa-cog"></i></div><div class="dropdown-menu"><a href="profil.php"><i class="fas fa-user-circle"></i> Profil</a><a href="log_aktivitas.php"><i class="fas fa-history"></i> Log Aktivitas</a><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div></div>
        </div>
        
        <div class="content-card">
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            
            <form method="GET" action="" class="filter-section">
                <input type="text" name="search" placeholder="Cari nama, username..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="role"><option value="semua">Semua Role</option><?php foreach ($roles as $role): ?><option value="<?php echo $role['nama_role']; ?>" <?php echo $filter_role == $role['nama_role'] ? 'selected' : ''; ?>><?php echo ucfirst($role['nama_role']); ?></option><?php endforeach; ?></select>
                <select name="status"><option value="semua">Semua Status</option><option value="aktif" <?php echo $filter_status == 'aktif' ? 'selected' : ''; ?>>Aktif</option><option value="nonaktif" <?php echo $filter_status == 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option></select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="users.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
                <button type="button" class="btn-tambah" onclick="openTambahModal()"><i class="fas fa-plus"></i> Tambah</button>
            </form>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>No</th><th>Foto</th><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): $no = $offset + 1; foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><img src="../assets/uploads/users/<?php echo $user['foto_profil'] ?: 'default-user.png'; ?>" class="foto-thumb" onerror="this.src='../assets/uploads/users/default-user.png'"></td>
                            <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><span class="badge-role"><?php echo ucfirst($user['nama_role']); ?></span></td>
                            <td><span class="<?php echo $user['is_active'] ? 'badge-aktif' : 'badge-nonaktif'; ?>"><?php echo $user['is_active'] ? 'Aktif' : 'Nonaktif'; ?></span></td>
                            <td>
                                <button class="btn-action btn-detail" onclick="openDetailModal(<?php echo $user['id']; ?>)"><i class="fas fa-info-circle"></i> Detail</button>
                                <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $user['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                <button class="btn-action btn-toggle" onclick="toggleStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active']; ?>)"><i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check-circle'; ?>"></i> <?php echo $user['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?></button>
                                <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['nama_lengkap']); ?>')"><i class="fas fa-trash"></i> Hapus</button>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:40px;">Tidak ada data user</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&role=<?php echo $filter_role; ?>&status=<?php echo $filter_status; ?>">« Sebelumnya</a><?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?><span class="active"><?php echo $i; ?></span><?php else: ?><a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&role=<?php echo $filter_role; ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&role=<?php echo $filter_role; ?>&status=<?php echo $filter_status; ?>">Selanjutnya »</a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MODAL TAMBAH -->
    <div id="tambahModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Tambah User</h3><span class="close-modal" onclick="closeModal('tambahModal')">&times;</span></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" required></div>
                    <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                    <div class="form-group"><label>Jenis Kelamin</label><select name="jenis_kelamin"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Email</label><input type="email" name="email"></div>
                    <div class="form-group"><label>No. Whatsapp</label><input type="text" name="no_whatsapp"></div>
                </div>
                <div class="form-group"><label>Alamat</label><textarea name="alamat" rows="2"></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>Role</label><select name="role_id"><?php foreach ($roles as $role): ?><option value="<?php echo $role['id']; ?>"><?php echo ucfirst($role['nama_role']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Status</label><select name="is_active"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                </div>
                <div class="form-group"><label>Foto Profil</label><input type="file" name="foto" accept="image/*"></div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('tambahModal')">Batal</button><button type="submit" name="tambah" class="btn-save">Simpan</button></div>
            </form>
        </div>
    </div>
    
    <!-- MODAL EDIT -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit User</h3><span class="close-modal" onclick="closeModal('editModal')">&times;</span></div>
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <div id="edit_current_photo" class="current-photo"></div>
                <div class="form-row">
                    <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" id="edit_nama" required></div>
                    <div class="form-group"><label>Username</label><input type="text" id="edit_username" disabled style="background:#f5f5f5;"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Password (kosongkan jika tidak diubah)</label><input type="password" name="password"></div>
                    <div class="form-group"><label>Jenis Kelamin</label><select name="jenis_kelamin" id="edit_jk"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email"></div>
                    <div class="form-group"><label>No. Whatsapp</label><input type="text" name="no_whatsapp" id="edit_wa"></div>
                </div>
                <div class="form-group"><label>Alamat</label><textarea name="alamat" id="edit_alamat" rows="2"></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>Role</label><select name="role_id" id="edit_role"><?php foreach ($roles as $role): ?><option value="<?php echo $role['id']; ?>"><?php echo ucfirst($role['nama_role']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Status</label><select name="is_active" id="edit_status"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                </div>
                <div class="form-group"><label>Ganti Foto</label><input type="file" name="foto" accept="image/*"></div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('editModal')">Batal</button><button type="submit" name="edit" class="btn-save">Simpan</button></div>
            </form>
        </div>
    </div>
    
    <!-- MODAL DETAIL -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Detail User</h3><span class="close-modal" onclick="closeModal('detailModal')">&times;</span></div>
            <div id="detailContent"></div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('detailModal')">Tutup</button></div>
        </div>
    </div>
    
    <script>
        function toggleSubmenu(e){e.classList.toggle('open');let s=e.nextElementSibling;if(s&&s.classList.contains('submenu'))s.classList.toggle('open');}
        
        function openTambahModal(){document.getElementById('tambahModal').classList.add('show');}
        
        function openEditModal(id){
            fetch(`get_user.php?id=${id}`)
                .then(r=>r.json())
                .then(d=>{
                    if(d.success){
                        document.getElementById('edit_id').value=d.user.id;
                        document.getElementById('edit_nama').value=d.user.nama_lengkap;
                        document.getElementById('edit_username').value=d.user.username;
                        document.getElementById('edit_jk').value=d.user.jenis_kelamin||'L';
                        document.getElementById('edit_email').value=d.user.email||'';
                        document.getElementById('edit_wa').value=d.user.no_whatsapp||'';
                        document.getElementById('edit_alamat').value=d.user.alamat||'';
                        document.getElementById('edit_role').value=d.user.role_id;
                        document.getElementById('edit_status').value=d.user.is_active;
                        
                        let fotoUrl = `../assets/uploads/users/${d.user.foto_profil || 'default-user.png'}`;
                        document.getElementById('edit_current_photo').innerHTML = `<label>Foto Saat Ini</label><br><img src="${fotoUrl}" onerror="this.src='../assets/uploads/users/default-user.png'">`;
                        document.getElementById('editModal').classList.add('show');
                    }
                });
        }
        
        function openDetailModal(id){
    fetch(`get_user.php?id=${id}&_=${Date.now()}`)  // Tambah timestamp biar tidak cache
        .then(r=>r.json())
        .then(d=>{
            if(d.success){
                let u = d.user;
                let statusText = u.is_active == 1 ? 'Aktif' : 'Nonaktif';
                let fotoUrl = `../assets/uploads/users/${u.foto_profil || 'default-user.png'}?_=${Date.now()}`;
                
                document.getElementById('detailContent').innerHTML = `
                    <div class="detail-foto"><img src="${fotoUrl}" onerror="this.src='../assets/uploads/users/default-user.png'"></div>
                    <div class="detail-item"><div class="detail-label">Nama Lengkap</div><div class="detail-value">${u.nama_lengkap}</div></div>
                    <div class="detail-item"><div class="detail-label">Username</div><div class="detail-value">${u.username}</div></div>
                    <div class="detail-item"><div class="detail-label">Jenis Kelamin</div><div class="detail-value">${u.jenis_kelamin=='L'?'Laki-laki':'Perempuan'}</div></div>
                    <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value">${u.email||'-'}</div></div>
                    <div class="detail-item"><div class="detail-label">No. Whatsapp</div><div class="detail-value">${u.no_whatsapp||'-'}</div></div>
                    <div class="detail-item"><div class="detail-label">Alamat</div><div class="detail-value">${u.alamat||'-'}</div></div>
                    <div class="detail-item"><div class="detail-label">Role</div><div class="detail-value">${u.nama_role}</div></div>
                    <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">${statusText}</div></div>
                    <div class="detail-item"><div class="detail-label">Dibuat Pada</div><div class="detail-value">${u.created_at}</div></div>
                `;
                document.getElementById('detailModal').classList.add('show');
            }
        });
}
        
        function closeModal(id){document.getElementById(id).classList.remove('show');}
        
     function toggleStatus(id, currentStatus){
    var action = (currentStatus == 1) ? 'nonaktifkan' : 'aktifkan';
    if(confirm('Yakin ingin ' + action + ' user ini?')) {
        window.location.href = 'users.php?toggle=' + id;
    }
}
        
        function confirmDelete(id,nama){
            if(confirm(`Yakin ingin menghapus user "${nama}"?`)) window.location.href=`users.php?hapus=${id}`;
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) event.target.classList.remove('show');
        }
    </script>
</body>
</html>