<?php
// ======================================================
// FILE: pengasuh/anak_asuh.php
// HALAMAN DATA ANAK ASUH UNTUK PENGASUH
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('pengasuh');

$currentUser = getCurrentUser();

// ======================================================
// FUNGSI UPLOAD FOTO
// ======================================================
function uploadFoto($existing_foto = null) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            return ['success' => false, 'message' => 'Format file tidak diizinkan'];
        }
        
        $filename = 'anak_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target = '../assets/uploads/anak_asuh/' . $filename;
        
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
            if ($existing_foto && file_exists('../assets/uploads/anak_asuh/' . $existing_foto)) {
                unlink('../assets/uploads/anak_asuh/' . $existing_foto);
            }
            return ['success' => true, 'filename' => $filename];
        }
    }
    return ['success' => true, 'filename' => $existing_foto ?: 'default-anak.png'];
}

// ======================================================
// PROSES CRUD
// ======================================================

// Tambah Anak Asuh
if (isset($_POST['tambah'])) {
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $tempat_lahir = mysqli_real_escape_string($conn, $_POST['tempat_lahir']);
    $tanggal_lahir = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
    $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
    $tanggal_masuk = mysqli_real_escape_string($conn, $_POST['tanggal_masuk']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $created_by = $currentUser['id'];
    
    // Hitung umur dari tanggal lahir
    $birthDate = new DateTime($tanggal_lahir);
    $today = new DateTime('today');
    $umur = $birthDate->diff($today)->y;
    
    $upload = uploadFoto();
    $foto = $upload['success'] ? $upload['filename'] : 'default-anak.png';
    
    $sql = "INSERT INTO anak_asuh (nama_lengkap, tempat_lahir, tanggal_lahir, umur, jenis_kelamin, tanggal_masuk, status, keterangan, foto, created_by) 
            VALUES ('$nama_lengkap', '$tempat_lahir', '$tanggal_lahir', $umur, '$jenis_kelamin', '$tanggal_masuk', '$status', '$keterangan', '$foto', $created_by)";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menambah anak asuh: $nama_lengkap");
        $_SESSION['success'] = "Anak asuh berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan: " . mysqli_error($conn);
    }
    header("Location: anak_asuh.php");
    exit();
}

// Edit Anak Asuh (hanya milik sendiri)
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    
    // Cek kepemilikan
    $check = mysqli_query($conn, "SELECT created_by, foto FROM anak_asuh WHERE id = $id");
    $data = mysqli_fetch_assoc($check);
    
    if ($data['created_by'] != $currentUser['id']) {
        $_SESSION['error'] = "Anda tidak bisa mengedit data anak asuh milik pengasuh lain!";
    } else {
        $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
        $tempat_lahir = mysqli_real_escape_string($conn, $_POST['tempat_lahir']);
        $tanggal_lahir = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
        $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
        $tanggal_masuk = mysqli_real_escape_string($conn, $_POST['tanggal_masuk']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
        
        // Hitung umur
        $birthDate = new DateTime($tanggal_lahir);
        $today = new DateTime('today');
        $umur = $birthDate->diff($today)->y;
        
        $upload = uploadFoto($data['foto']);
        $foto = $upload['success'] ? $upload['filename'] : $data['foto'];
        
        $sql = "UPDATE anak_asuh SET 
                nama_lengkap = '$nama_lengkap',
                tempat_lahir = '$tempat_lahir',
                tanggal_lahir = '$tanggal_lahir',
                umur = $umur,
                jenis_kelamin = '$jenis_kelamin',
                tanggal_masuk = '$tanggal_masuk',
                status = '$status',
                keterangan = '$keterangan',
                foto = '$foto'
                WHERE id = $id";
        
        if (mysqli_query($conn, $sql)) {
            logActivity($currentUser['id'], "Mengedit anak asuh ID: $id");
            $_SESSION['success'] = "Anak asuh berhasil diupdate!";
        } else {
            $_SESSION['error'] = "Gagal mengupdate: " . mysqli_error($conn);
        }
    }
    header("Location: anak_asuh.php");
    exit();
}

// Hapus Anak Asuh (hanya milik sendiri)
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    $check = mysqli_query($conn, "SELECT created_by, foto FROM anak_asuh WHERE id = $id");
    $data = mysqli_fetch_assoc($check);
    
    if ($data['created_by'] != $currentUser['id']) {
        $_SESSION['error'] = "Anda tidak bisa menghapus data anak asuh milik pengasuh lain!";
    } else {
        if ($data['foto'] && $data['foto'] != 'default-anak.png' && file_exists('../assets/uploads/anak_asuh/' . $data['foto'])) {
            unlink('../assets/uploads/anak_asuh/' . $data['foto']);
        }
        
        $sql = "DELETE FROM anak_asuh WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            logActivity($currentUser['id'], "Menghapus anak asuh ID: $id");
            $_SESSION['success'] = "Anak asuh berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus: " . mysqli_error($conn);
        }
    }
    header("Location: anak_asuh.php");
    exit();
}

// ======================================================
// FILTER & PAGINATION
// ======================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$where = "WHERE 1=1";

if ($search != '') {
    $where .= " AND (a.nama_lengkap LIKE '%$search%' OR a.tempat_lahir LIKE '%$search%')";
}
if ($filter_status != '' && $filter_status != 'semua') {
    $where .= " AND a.status = '$filter_status'";
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_sql = "SELECT COUNT(*) as total FROM anak_asuh a $where";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT a.*, u.nama_lengkap as pengasuh_nama,
        CASE 
            WHEN a.created_by = " . $currentUser['id'] . " THEN 'can_edit'
            ELSE 'readonly'
        END as akses
        FROM anak_asuh a 
        JOIN users u ON a.created_by = u.id 
        $where 
        ORDER BY a.created_at DESC 
        LIMIT $offset, $limit";
$anakAsuh = query($sql);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Anak Asuh - Pengasuh Panti Asuhan Al-Muthi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; overflow-x: hidden; }
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
        .main-content { margin-left: 280px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-title h2 { font-size: 20px; color: #333; }
        .profile-dropdown { position: relative; }
        .profile-icon { width: 45px; height: 45px; background: linear-gradient(135deg, #50c878, #2e8b57); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; color: white; }
        .dropdown-menu { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        .content-card { background: white; border-radius: 20px; padding: 25px; }
        .filter-section { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-section input, .filter-section select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; }
        .filter-section input { flex: 2; }
        .filter-section select { flex: 1; }
        .btn-filter, .btn-reset, .btn-tambah { padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-filter { background: #50c878; color: white; }
        .btn-reset { background: #6c757d; color: white; text-decoration: none; display: inline-block; }
        .btn-tambah { background: #50c878; color: white; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; vertical-align: middle; }
        .foto-thumb { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .status-aktif { background: #e8f5e9; color: #4caf50; padding: 4px 12px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-keluar { background: #ffebee; color: #f44336; padding: 4px 12px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .btn-action { padding: 5px 10px; border: none; border-radius: 8px; cursor: pointer; margin: 2px; font-size: 12px; }
        .btn-detail { background: #17a2b8; color: white; }
        .btn-edit { background: #50c878; color: white; }
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
        .detail-image img { max-width: 100%; max-height: 200px; border-radius: 10px; }
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
            <div class="menu-item" onclick="location.href='dashboard.php'"><i class="fas fa-tachometer-alt"></i><span>Beranda</span></div>
            <div class="menu-item" onclick="location.href='pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Pengeluaran Panti</span></div>
            <div class="menu-item" onclick="location.href='doa.php'"><i class="fas fa-pray"></i><span>Permohonan Khusus Do'a</span></div>
            <div class="menu-item active" onclick="location.href='anak_asuh.php'"><i class="fas fa-child"></i><span>Data Anak Asuh</span></div>
            <div class="menu-item" onclick="location.href='laporan.php'"><i class="fas fa-chart-line"></i><span>Laporan</span></div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div class="page-title"><h2>Data Anak Asuh</h2><p>Kelola data anak asuh panti</p></div>
            <div class="profile-dropdown">
                <div class="profile-icon"><i class="fas fa-cog"></i></div>
                <div class="dropdown-menu">
                    <a href="profil.php"><i class="fas fa-user-circle"></i> Profil</a>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
        
        <div class="content-card">
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            
            <form method="GET" action="" class="filter-section">
                <input type="text" name="search" placeholder="Cari nama atau tempat lahir..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="status">
                    <option value="semua">Semua Status</option>
                    <option value="Aktif" <?php echo $filter_status == 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="Keluar" <?php echo $filter_status == 'Keluar' ? 'selected' : ''; ?>>Keluar</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="anak_asuh.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
                <button type="button" class="btn-tambah" onclick="openTambahModal()"><i class="fas fa-plus"></i> Tambah</button>
            </form>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>No</th><th>Foto</th><th>Nama</th><th>Umur</th><th>Jenis Kelamin</th><th>Tanggal Masuk</th><th>Status</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($anakAsuh) > 0): $no = $offset + 1; foreach ($anakAsuh as $a): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><img src="../assets/uploads/anak_asuh/<?php echo $a['foto'] ?: 'default-anak.png'; ?>" class="foto-thumb" onerror="this.src='../assets/uploads/anak_asuh/default-anak.png'"></td>
                            <td><?php echo htmlspecialchars($a['nama_lengkap']); ?></td>
                            <td><?php echo $a['umur']; ?> tahun</td>
                            <td><?php echo $a['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($a['tanggal_masuk'])); ?></td>
                            <td><span class="status-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span></td>
                            <td>
                                <button class="btn-action btn-detail" onclick="openDetailModal(<?php echo $a['id']; ?>)"><i class="fas fa-info-circle"></i> Detail</button>
                                <?php if ($a['akses'] == 'can_edit'): ?>
                                    <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $a['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                    <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $a['id']; ?>, '<?php echo htmlspecialchars($a['nama_lengkap']); ?>')"><i class="fas fa-trash"></i> Hapus</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:40px;">Tidak ada data anak asuh</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&status=<?php echo $filter_status; ?>">« Sebelumnya</a><?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?><span class="active"><?php echo $i; ?></span><?php else: ?><a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&status=<?php echo $filter_status; ?>">Selanjutnya »</a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MODAL TAMBAH -->
    <div id="tambahModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Tambah Anak Asuh</h3><span class="close-modal" onclick="closeModal('tambahModal')">&times;</span></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" required></div>
                    <div class="form-group"><label>Tempat Lahir</label><input type="text" name="tempat_lahir"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Tanggal Lahir</label><input type="date" name="tanggal_lahir" required onchange="hitungUmur(this)"></div>
                    <div class="form-group"><label>Umur</label><input type="text" id="umur" readonly style="background:#f5f5f5;"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Jenis Kelamin</label><select name="jenis_kelamin"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
                    <div class="form-group"><label>Tanggal Masuk</label><input type="date" name="tanggal_masuk" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Status</label><select name="status"><option value="Aktif">Aktif</option><option value="Keluar">Keluar</option></select></div>
                    <div class="form-group"><label>Foto</label><input type="file" name="foto" accept="image/*"></div>
                </div>
                <div class="form-group"><label>Keterangan</label><textarea name="keterangan" rows="2"></textarea></div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('tambahModal')">Batal</button><button type="submit" name="tambah" class="btn-save">Tambah</button></div>
            </form>
        </div>
    </div>
    
    <!-- MODAL EDIT -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit Anak Asuh</h3><span class="close-modal" onclick="closeModal('editModal')">&times;</span></div>
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-row">
                    <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" id="edit_nama" required></div>
                    <div class="form-group"><label>Tempat Lahir</label><input type="text" name="tempat_lahir" id="edit_tempat"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Tanggal Lahir</label><input type="date" name="tanggal_lahir" id="edit_tgl_lahir" onchange="hitungUmurEdit()"></div>
                    <div class="form-group"><label>Umur</label><input type="text" id="edit_umur" readonly style="background:#f5f5f5;"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Jenis Kelamin</label><select name="jenis_kelamin" id="edit_jk"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
                    <div class="form-group"><label>Tanggal Masuk</label><input type="date" name="tanggal_masuk" id="edit_tgl_masuk"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Status</label><select name="status" id="edit_status"><option value="Aktif">Aktif</option><option value="Keluar">Keluar</option></select></div>
                    <div class="form-group"><label>Ganti Foto</label><input type="file" name="foto" accept="image/*"></div>
                </div>
                <div class="form-group"><label>Keterangan</label><textarea name="keterangan" id="edit_keterangan" rows="2"></textarea></div>
                <div id="edit_current_image" class="detail-image"></div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('editModal')">Batal</button><button type="submit" name="edit" class="btn-save">Simpan</button></div>
            </form>
        </div>
    </div>
    
    <!-- MODAL DETAIL -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Detail Anak Asuh</h3><span class="close-modal" onclick="closeModal('detailModal')">&times;</span></div>
            <div id="detailContent"></div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('detailModal')">Tutup</button></div>
        </div>
    </div>
    
    <script>
        function hitungUmur(input) {
            if(input.value){
                let birthDate = new Date(input.value);
                let today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                let m = today.getMonth() - birthDate.getMonth();
                if(m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
                document.getElementById('umur').value = age + ' tahun';
            }
        }
        
        function hitungUmurEdit() {
            let tgl = document.getElementById('edit_tgl_lahir').value;
            if(tgl){
                let birthDate = new Date(tgl);
                let today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                let m = today.getMonth() - birthDate.getMonth();
                if(m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
                document.getElementById('edit_umur').value = age + ' tahun';
            }
        }
        
        function openTambahModal(){document.getElementById('tambahModal').classList.add('show');}
        function closeModal(id){document.getElementById(id).classList.remove('show');}
        
        function openDetailModal(id){
            fetch(`get_anak_asuh.php?id=${id}`)
                .then(r=>r.json())
                .then(d=>{
                    if(d.success){
                        let a = d.data;
                        document.getElementById('detailContent').innerHTML = `
                            <div class="detail-image"><img src="../assets/uploads/anak_asuh/${a.foto || 'default-anak.png'}" onerror="this.src='../assets/uploads/anak_asuh/default-anak.png'"></div>
                            <div class="detail-item"><div class="detail-label">Nama</div><div class="detail-value">${a.nama_lengkap}</div></div>
                            <div class="detail-item"><div class="detail-label">Tempat Lahir</div><div class="detail-value">${a.tempat_lahir || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal Lahir</div><div class="detail-value">${a.tanggal_lahir}</div></div>
                            <div class="detail-item"><div class="detail-label">Umur</div><div class="detail-value">${a.umur} tahun</div></div>
                            <div class="detail-item"><div class="detail-label">Jenis Kelamin</div><div class="detail-value">${a.jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan'}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal Masuk</div><div class="detail-value">${a.tanggal_masuk}</div></div>
                            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">${a.status}</div></div>
                            <div class="detail-item"><div class="detail-label">Keterangan</div><div class="detail-value">${a.keterangan || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Dibuat Oleh</div><div class="detail-value">${a.pengasuh_nama}</div></div>
                        `;
                        document.getElementById('detailModal').classList.add('show');
                    }
                });
        }
        
        function openEditModal(id){
            fetch(`get_anak_asuh.php?id=${id}`)
                .then(r=>r.json())
                .then(d=>{
                    if(d.success){
                        let a = d.data;
                        document.getElementById('edit_id').value = a.id;
                        document.getElementById('edit_nama').value = a.nama_lengkap;
                        document.getElementById('edit_tempat').value = a.tempat_lahir || '';
                        document.getElementById('edit_tgl_lahir').value = a.tanggal_lahir;
                        document.getElementById('edit_jk').value = a.jenis_kelamin;
                        document.getElementById('edit_tgl_masuk').value = a.tanggal_masuk;
                        document.getElementById('edit_status').value = a.status;
                        document.getElementById('edit_keterangan').value = a.keterangan || '';
                        document.getElementById('edit_umur').value = a.umur + ' tahun';
                        let imageHtml = a.foto ? `<label>Foto Saat Ini</label><br><img src="../assets/uploads/anak_asuh/${a.foto}" style="max-width:100%; max-height:150px; border-radius:10px;">` : '';
                        document.getElementById('edit_current_image').innerHTML = imageHtml;
                        document.getElementById('editModal').classList.add('show');
                    }
                });
        }
        
        function confirmDelete(id, nama){
            if(confirm(`Yakin ingin menghapus anak asuh "${nama}"?`)){
                window.location.href = `anak_asuh.php?hapus=${id}`;
            }
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) event.target.classList.remove('show');
        }
    </script>
</body>
</html>