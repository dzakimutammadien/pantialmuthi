<?php
// ======================================================
// FILE: pengasuh/pengeluaran.php
// HALAMAN PENGELUARAN PANTI UNTUK PENGASUH
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('pengasuh');

$currentUser = getCurrentUser();

// ======================================================
// FUNGSI UPLOAD GAMBAR
// ======================================================
function uploadGambar($existing_file = null) {
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            return ['success' => false, 'message' => 'Format file tidak diizinkan'];
        }
        
        $filename = 'pengeluaran_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target = '../assets/uploads/pengeluaran/' . $filename;
        
        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target)) {
            if ($existing_file && file_exists('../assets/uploads/pengeluaran/' . $existing_file)) {
                unlink('../assets/uploads/pengeluaran/' . $existing_file);
            }
            return ['success' => true, 'filename' => $filename];
        }
    }
    return ['success' => true, 'filename' => $existing_file];
}

// ======================================================
// PROSES CRUD
// ======================================================

// Tambah Pengeluaran
if (isset($_POST['tambah'])) {
    $tanggal_pengeluaran = mysqli_real_escape_string($conn, $_POST['tanggal_pengeluaran']);
    $kategori_id = (int)$_POST['kategori_id'];
    $nominal = (float)$_POST['nominal'];
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $created_by = $currentUser['id'];
    
    $upload = uploadGambar();
    $gambar = $upload['success'] ? $upload['filename'] : null;
    
    $sql = "INSERT INTO pengeluaran (tanggal_pengeluaran, kategori_id, nominal, deskripsi, bukti_foto, created_by, status) 
            VALUES ('$tanggal_pengeluaran', $kategori_id, $nominal, '$deskripsi', '$gambar', $created_by, 'pending')";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menambah pengeluaran: Rp " . number_format($nominal));
        $_SESSION['success'] = "Pengeluaran berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan: " . mysqli_error($conn);
    }
    header("Location: pengeluaran.php");
    exit();
}

// Edit Pengeluaran (hanya jika status pending & milik sendiri)
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    
    // Cek kepemilikan dan status
    $check = mysqli_query($conn, "SELECT created_by, status, bukti_foto FROM pengeluaran WHERE id = $id");
    $data = mysqli_fetch_assoc($check);
    
    if ($data['created_by'] != $currentUser['id']) {
        $_SESSION['error'] = "Anda tidak bisa mengedit pengeluaran milik orang lain!";
    } elseif ($data['status'] != 'pending') {
        $_SESSION['error'] = "Pengeluaran yang sudah diverifikasi tidak bisa diedit!";
    } else {
        $tanggal_pengeluaran = mysqli_real_escape_string($conn, $_POST['tanggal_pengeluaran']);
        $kategori_id = (int)$_POST['kategori_id'];
        $nominal = (float)$_POST['nominal'];
        $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
        
        $upload = uploadGambar($data['bukti_foto']);
        $gambar = $upload['success'] ? $upload['filename'] : $data['bukti_foto'];
        
        $sql = "UPDATE pengeluaran SET 
                tanggal_pengeluaran = '$tanggal_pengeluaran',
                kategori_id = $kategori_id,
                nominal = $nominal,
                deskripsi = '$deskripsi',
                bukti_foto = '$gambar'
                WHERE id = $id";
        
        if (mysqli_query($conn, $sql)) {
            logActivity($currentUser['id'], "Mengedit pengeluaran ID: $id");
            $_SESSION['success'] = "Pengeluaran berhasil diupdate!";
        } else {
            $_SESSION['error'] = "Gagal mengupdate: " . mysqli_error($conn);
        }
    }
    header("Location: pengeluaran.php");
    exit();
}

// Hapus Pengeluaran (hanya jika status pending & milik sendiri)
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    $check = mysqli_query($conn, "SELECT created_by, status, bukti_foto FROM pengeluaran WHERE id = $id");
    $data = mysqli_fetch_assoc($check);
    
    if ($data['created_by'] != $currentUser['id']) {
        $_SESSION['error'] = "Anda tidak bisa menghapus pengeluaran milik orang lain!";
    } elseif ($data['status'] != 'pending') {
        $_SESSION['error'] = "Pengeluaran yang sudah diverifikasi tidak bisa dihapus!";
    } else {
        if ($data['bukti_foto'] && file_exists('../assets/uploads/pengeluaran/' . $data['bukti_foto'])) {
            unlink('../assets/uploads/pengeluaran/' . $data['bukti_foto']);
        }
        
        $sql = "DELETE FROM pengeluaran WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            logActivity($currentUser['id'], "Menghapus pengeluaran ID: $id");
            $_SESSION['success'] = "Pengeluaran berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus: " . mysqli_error($conn);
        }
    }
    header("Location: pengeluaran.php");
    exit();
}

// ======================================================
// FILTER & PAGINATION
// ======================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_kategori = isset($_GET['kategori']) ? (int)$_GET['kategori'] : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$where = "";

if ($search != '') {
    $where .= " AND (u.nama_lengkap LIKE '%$search%' OR p.deskripsi LIKE '%$search%')";
}
if ($filter_kategori != '' && $filter_kategori > 0) {
    $where .= " AND p.kategori_id = $filter_kategori";
}
if ($filter_status != '' && $filter_status != 'semua') {
    $where .= " AND p.status = '$filter_status'";
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_sql = "SELECT COUNT(*) as total FROM pengeluaran p 
              JOIN users u ON p.created_by = u.id 
              WHERE 1=1 $where";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT p.*, u.nama_lengkap as pengasuh_nama, k.nama_kategori,
        CASE 
            WHEN p.created_by = " . $currentUser['id'] . " AND p.status = 'pending' THEN 'can_edit'
            ELSE 'readonly'
        END as akses
        FROM pengeluaran p 
        JOIN users u ON p.created_by = u.id 
        JOIN kategori_pengeluaran k ON p.kategori_id = k.id 
        WHERE 1=1 $where 
        ORDER BY p.created_at DESC 
        LIMIT $offset, $limit";
$pengeluarans = query($sql);

// Ambil kategori untuk dropdown
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
    <title>Pengeluaran Panti - Pengasuh Panti Asuhan Al-Muthi</title>
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
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .status-pending { background: #fff3e0; color: #ff9800; }
        .status-disetujui { background: #e8f5e9; color: #4caf50; }
        .status-ditolak { background: #ffebee; color: #f44336; }
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
        .detail-image img { max-width: 100%; max-height: 300px; border-radius: 10px; }
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
            <div class="menu-item active" onclick="location.href='pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Pengeluaran Panti</span></div>
            <div class="menu-item" onclick="location.href='doa.php'"><i class="fas fa-pray"></i><span>Permohonan Khusus Do'a</span></div>
            <div class="menu-item" onclick="location.href='anak_asuh.php'"><i class="fas fa-child"></i><span>Data Anak Asuh</span></div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div class="page-title"><h2>Pengeluaran Panti</h2><p>Kelola data pengeluaran panti</p></div>
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
                <a href="pengeluaran.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
                <button type="button" class="btn-tambah" onclick="openTambahModal()"><i class="fas fa-plus"></i> Tambah</button>
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
                                
                                <?php if ($p['akses'] == 'can_edit'): ?>
                                    <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $p['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                    <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $p['id']; ?>)"><i class="fas fa-trash"></i> Hapus</button>
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
    
    <!-- MODAL TAMBAH -->
    <div id="tambahModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Tambah Pengeluaran Panti</h3><span class="close-modal" onclick="closeModal('tambahModal')">&times;</span></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal_pengeluaran" required></div>
                    <div class="form-group"><label>Kategori</label><select name="kategori_id" required>
    <?php foreach ($kategoris as $k): ?>
        <option value="<?php echo $k['id']; ?>"><?php echo $k['nama_kategori']; ?></option>
    <?php endforeach; ?>
</select></div>
                </div>
                <div class="form-group"><label>Jumlah (Rp)</label><input type="number" name="nominal" placeholder="0" required></div>
                <div class="form-group"><label>Keterangan</label><textarea name="deskripsi" rows="3" placeholder="Deskripsi pengeluaran..."></textarea></div>
                <div class="form-group"><label>Upload Gambar Bukti</label><input type="file" name="gambar" accept="image/*"></div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('tambahModal')">Batal</button><button type="submit" name="tambah" class="btn-save">Tambah</button></div>
            </form>
        </div>
    </div>
    
    <!-- MODAL EDIT -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit Pengeluaran Panti</h3><span class="close-modal" onclick="closeModal('editModal')">&times;</span></div>
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-row">
                    <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal_pengeluaran" id="edit_tanggal" required></div>
                    <div class="form-group"><label>Kategori</label><select name="kategori_id" id="edit_kategori" required><?php foreach ($kategoris as $k): ?><option value="<?php echo $k['id']; ?>"><?php echo $k['nama_kategori']; ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-group"><label>Jumlah (Rp)</label><input type="number" name="nominal" id="edit_nominal" required></div>
                <div class="form-group"><label>Keterangan</label><textarea name="deskripsi" id="edit_deskripsi" rows="3"></textarea></div>
                <div class="form-group"><label>Ganti Gambar Bukti</label><input type="file" name="gambar" accept="image/*"></div>
                <div id="edit_current_image" class="detail-image"></div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('editModal')">Batal</button><button type="submit" name="edit" class="btn-save">Simpan</button></div>
            </form>
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
    
    <script>
        function openTambahModal(){document.getElementById('tambahModal').classList.add('show');}
        function closeModal(id){document.getElementById(id).classList.remove('show');}
        
        function openDetailModal(id){
            fetch(`get_pengeluaran.php?id=${id}`)
                .then(r=>r.json())
                .then(d=>{
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
                            <div class="detail-item"><div class="detail-label">Catatan Verifikasi</div><div class="detail-value">${p.catatan_verifikasi || '-'}</div></div>
                            ${imageHtml}
                            <div class="detail-item"><div class="detail-label">Diverifikasi Oleh</div><div class="detail-value">${p.verified_by_nama || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal Verifikasi</div><div class="detail-value">${p.verified_at || '-'}</div></div>
                        `;
                        document.getElementById('detailModal').classList.add('show');
                    }
                });
        }
        
        function openEditModal(id){
            fetch(`get_pengeluaran.php?id=${id}`)
                .then(r=>r.json())
                .then(d=>{
                    if(d.success){
                        let p = d.data;
                        document.getElementById('edit_id').value = p.id;
                        document.getElementById('edit_tanggal').value = p.tanggal_pengeluaran;
                        document.getElementById('edit_kategori').value = p.kategori_id;
                        document.getElementById('edit_nominal').value = p.nominal;
                        document.getElementById('edit_deskripsi').value = p.deskripsi || '';
                        let imageHtml = p.bukti_foto ? `<label>Gambar Saat Ini</label><br><img src="../assets/uploads/pengeluaran/${p.bukti_foto}" style="max-width:100%; max-height:150px; border-radius:10px;">` : '';
                        document.getElementById('edit_current_image').innerHTML = imageHtml;
                        document.getElementById('editModal').classList.add('show');
                    }
                });
        }
        
        function confirmDelete(id){
            if(confirm('Yakin ingin menghapus pengeluaran ini?')){
                window.location.href = `pengeluaran.php?hapus=${id}`;
            }
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) event.target.classList.remove('show');
        }
    </script>
</body>
</html>