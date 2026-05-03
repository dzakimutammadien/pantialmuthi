<?php
// ======================================================
// FILE: admin/galeri.php
// HALAMAN KELOLA GALERI FOTO & VIDEO (ADMIN)
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');

$currentUser = getCurrentUser();

// ======================================================
// FUNGSI UPLOAD FOTO
// ======================================================
function uploadFile($existing_file = null) {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            return ['success' => false, 'message' => 'Format file tidak diizinkan'];
        }
        
        $filename = 'galeri_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target = '../assets/uploads/galeri/' . $filename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            if ($existing_file && file_exists('../assets/uploads/galeri/' . $existing_file)) {
                unlink('../assets/uploads/galeri/' . $existing_file);
            }
            return ['success' => true, 'filename' => $filename];
        }
    }
    return ['success' => true, 'filename' => $existing_file];
}

// ======================================================
// PROSES CRUD
// ======================================================

// Tambah Galeri
if (isset($_POST['tambah'])) {
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $tipe = mysqli_real_escape_string($conn, $_POST['tipe']);
    $youtube_id = mysqli_real_escape_string($conn, $_POST['youtube_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $upload = uploadFile();
    $file_path = $upload['success'] ? $upload['filename'] : null;
    
    $sql = "INSERT INTO galeri (judul, deskripsi, tipe, file_path, youtube_id, status) 
            VALUES ('$judul', '$deskripsi', '$tipe', '$file_path', '$youtube_id', '$status')";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menambah galeri: $judul");
        $_SESSION['success'] = "Galeri berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan: " . mysqli_error($conn);
    }
    header("Location: galeri.php");
    exit();
}

// Edit Galeri
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $tipe = mysqli_real_escape_string($conn, $_POST['tipe']);
    $youtube_id = mysqli_real_escape_string($conn, $_POST['youtube_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Ambil file lama
    $query = mysqli_query($conn, "SELECT file_path FROM galeri WHERE id = $id");
    $old = mysqli_fetch_assoc($query);
    
    // Upload file baru jika ada
    $upload = uploadFile($old['file_path']);
    $file_path = $upload['success'] ? $upload['filename'] : $old['file_path'];
    
    $sql = "UPDATE galeri SET 
            judul = '$judul',
            deskripsi = '$deskripsi',
            tipe = '$tipe',
            file_path = '$file_path',
            youtube_id = '$youtube_id',
            status = '$status'
            WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Mengedit galeri ID: $id");
        $_SESSION['success'] = "Galeri berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate: " . mysqli_error($conn);
    }
    header("Location: galeri.php");
    exit();
}

// Hapus Galeri
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    $query = mysqli_query($conn, "SELECT file_path FROM galeri WHERE id = $id");
    $data = mysqli_fetch_assoc($query);
    
    if ($data['file_path'] && file_exists('../assets/uploads/galeri/' . $data['file_path'])) {
        unlink('../assets/uploads/galeri/' . $data['file_path']);
    }
    
    $sql = "DELETE FROM galeri WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menghapus galeri ID: $id");
        $_SESSION['success'] = "Galeri berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus: " . mysqli_error($conn);
    }
    header("Location: galeri.php");
    exit();
}

// ======================================================
// PAGINATION
// ======================================================
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_sql = "SELECT COUNT(*) as total FROM galeri";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT * FROM galeri ORDER BY created_at DESC LIMIT $offset, $limit";
$galeri = query($sql);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeri - Admin Panti Asuhan Al-Muthi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; }
        
        .sidebar { position: fixed; left: 0; top: 0; width: 280px; height: 100%; background: linear-gradient(135deg, #1a3a2a 0%, #2d4a3a 100%); color: white; overflow-y: auto; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 12px; justify-content: center; }
        .sidebar-logo { width: 45px; height: 45px; object-fit: contain; }
        .sidebar-header h3 { font-size: 16px; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 12px 20px; display: flex; align-items: center; gap: 12px; cursor: pointer; color: rgba(255,255,255,0.8); transition: all 0.3s; }
        .menu-item:hover, .menu-item.active { background: rgba(80,200,120,0.3); border-left: 4px solid #50c878; }
        .menu-item i { width: 24px; }
        .submenu { padding-left: 56px; max-height: 0; overflow: hidden; transition: max-height 0.3s; }
        .submenu.open { max-height: 300px; }
        .submenu-item { padding: 10px 20px; display: flex; align-items: center; gap: 12px; cursor: pointer; color: rgba(255,255,255,0.7); font-size: 13px; }
        
        .main-content { margin-left: 280px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-title h2 { font-size: 20px; color: #333; }
        .profile-dropdown { position: relative; }
        .profile-icon { width: 45px; height: 45px; background: linear-gradient(135deg, #50c878, #2e8b57); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .dropdown-menu { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; }
        
        .content-card { background: white; border-radius: 20px; padding: 25px; }
        .btn-tambah { background: #50c878; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin-bottom: 20px; }
        .galeri-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .galeri-item { background: #f8f9fa; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .galeri-item img, .galeri-item video { width: 100%; height: 180px; object-fit: cover; }
        .galeri-info { padding: 10px; }
        .galeri-info h4 { font-size: 14px; margin-bottom: 5px; }
        .galeri-info p { font-size: 11px; color: #888; }
        .btn-action { padding: 5px 8px; border: none; border-radius: 5px; cursor: pointer; font-size: 11px; margin: 2px; }
        .btn-edit { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 500px; max-width: 90%; padding: 25px; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; }
        .btn-save { background: #50c878; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 12px; border-radius: 5px; text-decoration: none; background: #f0f2f5; color: #333; }
        .pagination .active { background: #50c878; color: white; }
        
        @media (max-width: 768px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
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
                <div class="submenu-item" onclick="location.href='laporan_keuangan.php'"><i class="fas fa-chart-line"></i><span>Laporan Keuangan</span></div>
            </div>
            <div class="menu-item has-submenu" onclick="toggleSubmenu(this)"><i class="fas fa-database"></i><span>Master Data</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu">
                <div class="submenu-item" onclick="location.href='kategori_donasi.php'"><i class="fas fa-tags"></i><span>Kategori Transaksi</span></div>
                <div class="submenu-item" onclick="location.href='kategori_role.php'"><i class="fas fa-user-tag"></i><span>Kategori Role</span></div>
                <div class="submenu-item" onclick="location.href='anak_asuh.php'"><i class="fas fa-child"></i><span>Data Anak Asuh</span></div>
                <div class="submenu-item active" onclick="location.href='galeri.php'"><i class="fas fa-images"></i><span>Galeri</span></div>
                <div class="submenu-item" onclick="location.href='doa_khusus.php'"><i class="fas fa-pray"></i><span>Data Doa Khusus</span></div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div class="page-title"><h2>Galeri Foto & Video</h2><p>Kelola galeri kegiatan panti</p></div>
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
            
            <button class="btn-tambah" onclick="openTambahModal()"><i class="fas fa-plus"></i> Tambah Galeri</button>
            
            <div class="galeri-grid">
                <?php if (count($galeri) > 0): ?>
                    <?php foreach ($galeri as $g): ?>
                        <div class="galeri-item">
                            
                            <?php if ($g['tipe'] == 'foto'): ?>
                                <!-- FOTO -->
                                <img src="../assets/uploads/galeri/<?php echo $g['file_path']; ?>" 
                                     alt="<?php echo $g['judul']; ?>" 
                                     onclick="window.open('../assets/uploads/galeri/<?php echo $g['file_path']; ?>', '_blank')" 
                                     style="cursor: pointer;">
                                     
                            <?php elseif ($g['tipe'] == 'video' && !empty($g['youtube_id'])): ?>
                                <!-- VIDEO YOUTUBE (Thumbnail + Link ke YouTube) -->
                                <?php 
                                // Extract YouTube ID dari URL jika perlu
                                $youtube_id_safe = $g['youtube_id'];
                                if (strpos($youtube_id_safe, 'youtube.com') !== false || strpos($youtube_id_safe, 'youtu.be') !== false) {
                                    parse_str(parse_url($youtube_id_safe, PHP_URL_QUERY), $params);
                                    $youtube_id_safe = $params['v'] ?? substr($youtube_id_safe, strrpos($youtube_id_safe, '/') + 1);
                                }
                                ?>
                                <a href="https://www.youtube.com/watch?v=<?php echo $youtube_id_safe; ?>" target="_blank" rel="noopener noreferrer" style="display: block; position: relative;">
                                    <img src="../assets/uploads/galeri/<?php echo $g['file_path']; ?>" 
                                         alt="<?php echo $g['judul']; ?>" 
                                         style="width:100%; height:180px; object-fit:cover;">
                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                                        <i class="fas fa-play-circle" style="font-size: 40px; color: white; text-shadow: 0 0 10px rgba(0,0,0,0.5);"></i>
                                    </div>
                                </a>
                                
                            <?php elseif ($g['tipe'] == 'video'): ?>
                                <!-- VIDEO UPLOAD (File MP4) -->
                                <video src="../assets/uploads/galeri/<?php echo $g['file_path']; ?>" 
                                       style="width:100%; height:180px; object-fit:cover;" 
                                       controls></video>
                            <?php endif; ?>
                            
                            <div class="galeri-info">
                                <h4><?php echo htmlspecialchars($g['judul']); ?></h4>
                                <p><?php echo date('d/m/Y', strtotime($g['created_at'])); ?></p>
                                <p style="font-size: 10px; color: <?php echo $g['status'] == 'aktif' ? '#4caf50' : '#f44336'; ?>">
                                    <?php echo $g['status'] == 'aktif' ? 'Aktif' : 'Nonaktif'; ?>
                                </p>
                                <div>
                                    <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $g['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                    <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $g['id']; ?>)"><i class="fas fa-trash"></i> Hapus</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; grid-column: 1/-1;">Belum ada data galeri</p>
                <?php endif; ?>
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
    
    <!-- MODAL TAMBAH -->
    <div id="tambahModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Tambah Galeri</h3><span class="close-modal" onclick="closeModal('tambahModal')">&times;</span></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group"><label>Judul</label><input type="text" name="judul" required></div>
                <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi" rows="2"></textarea></div>
                <div class="form-group">
                    <label>Tipe</label>
                    <select name="tipe" id="tipe_select" onchange="toggleYoutube()">
                        <option value="foto">Foto</option>
                        <option value="video">Video</option>
                    </select>
                </div>
                <div class="form-group" id="file_group"><label>Upload File</label><input type="file" name="file" accept="image/*,video/*"></div>
                <div class="form-group" id="youtube_group" style="display:none;"><label>YouTube ID atau Link</label><input type="text" name="youtube_id" placeholder="Contoh: dQw4w9WgXcQ atau https://youtu.be/..."></div>
                <div class="form-group"><label>Status</label><select name="status"><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></select></div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('tambahModal')">Batal</button><button type="submit" name="tambah" class="btn-save">Simpan</button></div>
            </form>
        </div>
    </div>
    
    <!-- MODAL EDIT -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit Galeri</h3><span class="close-modal" onclick="closeModal('editModal')">&times;</span></div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group"><label>Judul</label><input type="text" name="judul" id="edit_judul" required></div>
                <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi" id="edit_deskripsi" rows="2"></textarea></div>
                <div class="form-group"><label>Tipe</label><select name="tipe" id="edit_tipe" onchange="toggleEditYoutube()">
                    <option value="foto">Foto</option>
                    <option value="video">Video</option>
                </select></div>
                
                <div class="form-group" id="edit_file_group">
                    <label>Ganti File (Opsional)</label>
                    <input type="file" name="file" accept="image/*,video/*">
                    <small style="color:#888;">Kosongkan jika tidak ingin mengganti file</small>
                </div>
                
                <div class="form-group" id="edit_youtube_group" style="display:none;">
                    <label>YouTube ID atau Link</label>
                    <input type="text" name="youtube_id" id="edit_youtube" placeholder="Contoh: dQw4w9WgXcQ atau https://youtu.be/...">
                </div>
                
                <div class="form-group"><label>Status</label><select name="status" id="edit_status">
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </select></div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Batal</button>
                    <button type="submit" name="edit" class="btn-save">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleSubmenu(e){e.classList.toggle('open');let s=e.nextElementSibling;if(s&&s.classList.contains('submenu'))s.classList.toggle('open');}
        function closeModal(id){document.getElementById(id).classList.remove('show');}
        function openTambahModal(){document.getElementById('tambahModal').classList.add('show');}
        
        function toggleYoutube(){
            var tipe = document.getElementById('tipe_select').value;
            if(tipe == 'video'){
                document.getElementById('file_group').style.display = 'block';
                document.getElementById('youtube_group').style.display = 'block';
            } else {
                document.getElementById('file_group').style.display = 'block';
                document.getElementById('youtube_group').style.display = 'none';
            }
        }
        
        function toggleEditYoutube(){
            var tipe = document.getElementById('edit_tipe').value;
            if(tipe == 'video'){
                document.getElementById('edit_file_group').style.display = 'block';
                document.getElementById('edit_youtube_group').style.display = 'block';
            } else {
                document.getElementById('edit_file_group').style.display = 'block';
                document.getElementById('edit_youtube_group').style.display = 'none';
            }
        }
        
        function openEditModal(id){
            fetch('get_galeri.php?id='+id)
                .then(r=>r.json())
                .then(d=>{
                    if(d.success){
                        document.getElementById('edit_id').value = d.data.id;
                        document.getElementById('edit_judul').value = d.data.judul;
                        document.getElementById('edit_deskripsi').value = d.data.deskripsi || '';
                        document.getElementById('edit_tipe').value = d.data.tipe;
                        document.getElementById('edit_youtube').value = d.data.youtube_id || '';
                        document.getElementById('edit_status').value = d.data.status;
                        
                        if(d.data.tipe == 'video'){
                            document.getElementById('edit_file_group').style.display = 'block';
                            document.getElementById('edit_youtube_group').style.display = 'block';
                        } else {
                            document.getElementById('edit_file_group').style.display = 'block';
                            document.getElementById('edit_youtube_group').style.display = 'none';
                        }
                        document.getElementById('editModal').classList.add('show');
                    }
                });
        }
        
        function confirmDelete(id){
            if(confirm('Yakin ingin menghapus galeri ini?')){
                window.location.href = 'galeri.php?hapus='+id;
            }
        }
        
        window.onclick = function(event){if(event.target.classList.contains('modal'))event.target.classList.remove('show');}
    </script>
</body>
  
</html>