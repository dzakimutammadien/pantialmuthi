<?php
// ======================================================
// FILE: admin/program_galeri.php
// HALAMAN KELOLA GALERI PROGRAM (DENGAN SIDEBAR)
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');

$currentUser = getCurrentUser();

$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;

// Ambil data program
$sql_program = "SELECT * FROM program_donasi WHERE id = $program_id";
$program = query($sql_program);
if (count($program) == 0) {
    header("Location: program.php");
    exit();
}
$program = $program[0];

// Fungsi upload file
function uploadFileGaleri($existing_file = null) {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            return ['success' => false, 'message' => 'Format file tidak diizinkan'];
        }
        
        $filename = 'galeri_program_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target = '../assets/uploads/galeri_program/' . $filename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            if ($existing_file && file_exists('../assets/uploads/galeri_program/' . $existing_file)) {
                unlink('../assets/uploads/galeri_program/' . $existing_file);
            }
            return ['success' => true, 'filename' => $filename];
        }
    }
    return ['success' => true, 'filename' => $existing_file];
}

// Proses CRUD
if (isset($_POST['tambah'])) {
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $tipe = mysqli_real_escape_string($conn, $_POST['tipe']);
    
    $upload = uploadFileGaleri();
    $file_path = $upload['success'] ? $upload['filename'] : null;
    
    if ($file_path) {
        $sql = "INSERT INTO galeri_program (program_id, judul, deskripsi, tipe, file_path) 
                VALUES ($program_id, '$judul', '$deskripsi', '$tipe', '$file_path')";
        
        if (mysqli_query($conn, $sql)) {
            logActivity($currentUser['id'], "Menambah galeri program: $judul");
            $_SESSION['success'] = "Galeri berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "Gagal upload file!";
    }
    header("Location: program_galeri.php?program_id=$program_id");
    exit();
}

if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $tipe = mysqli_real_escape_string($conn, $_POST['tipe']);
    
    $sql = "UPDATE galeri_program SET 
            judul = '$judul',
            deskripsi = '$deskripsi',
            tipe = '$tipe'
            WHERE id = $id AND program_id = $program_id";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Mengedit galeri program ID: $id");
        $_SESSION['success'] = "Galeri berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate: " . mysqli_error($conn);
    }
    header("Location: program_galeri.php?program_id=$program_id");
    exit();
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    $query = mysqli_query($conn, "SELECT file_path FROM galeri_program WHERE id = $id");
    $file = mysqli_fetch_assoc($query);
    
    $sql = "DELETE FROM galeri_program WHERE id = $id AND program_id = $program_id";
    if (mysqli_query($conn, $sql)) {
        if ($file['file_path'] && file_exists('../assets/uploads/galeri_program/' . $file['file_path'])) {
            unlink('../assets/uploads/galeri_program/' . $file['file_path']);
        }
        logActivity($currentUser['id'], "Menghapus galeri program ID: $id");
        $_SESSION['success'] = "Galeri berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus: " . mysqli_error($conn);
    }
    header("Location: program_galeri.php?program_id=$program_id");
    exit();
}

$galeri_list = query("SELECT * FROM galeri_program WHERE program_id = $program_id ORDER BY created_at DESC");

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeri Program - <?php echo $program['nama_program']; ?></title>
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
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .btn-back { background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-tambah { background: #50c878; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; }
        
        .galeri-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
        .galeri-item { background: #f8f9fa; border-radius: 15px; overflow: hidden; transition: transform 0.3s; }
        .galeri-item:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .galeri-item img, .galeri-item video { width: 100%; height: 180px; object-fit: cover; }
        .galeri-info { padding: 12px; }
        .galeri-info h4 { font-size: 14px; margin-bottom: 5px; }
        .galeri-info p { font-size: 11px; color: #888; }
        .btn-action { padding: 5px 10px; border: none; border-radius: 5px; cursor: pointer; font-size: 11px; margin: 2px; }
        .btn-edit { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 550px; max-width: 90%; padding: 25px; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: #50c878; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        
        @media (max-width: 768px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } .galeri-grid { grid-template-columns: 1fr; } }
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
                <div class="submenu-item" onclick="location.href='verifikasi_program.php'"><i class="fas fa-chalkboard-user"></i><span>Verifikasi Program</span></div>
                <div class="submenu-item" onclick="location.href='laporan_keuangan.php'"><i class="fas fa-chart-line"></i><span>Laporan Keuangan</span></div>
            </div>
            <div class="menu-item has-submenu open" onclick="toggleSubmenu(this)"><i class="fas fa-database"></i><span>Master Data</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu open">
                <div class="submenu-item" onclick="location.href='kategori_donasi.php'"><i class="fas fa-tags"></i><span>Kategori Transaksi</span></div>
                <div class="submenu-item" onclick="location.href='kategori_role.php'"><i class="fas fa-user-tag"></i><span>Kategori Role</span></div>
                <div class="submenu-item" onclick="location.href='anak_asuh.php'"><i class="fas fa-child"></i><span>Data Anak Asuh</span></div>
                <div class="submenu-item active" onclick="location.href='program_galeri.php?program_id=<?php echo $program_id; ?>'"><i class="fas fa-images"></i><span>Galeri Program</span></div>
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
                <h2>Galeri Program</h2>
                <p>Program: <?php echo $program['nama_program']; ?></p>
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
        
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2><i class="fas fa-images"></i> Galeri Kegiatan</h2>
                    </div>
                    <div>
                        <a href="program.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
                        <button class="btn-tambah" onclick="openTambahModal()"><i class="fas fa-plus"></i> Tambah Galeri</button>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="galeri-grid">
                    <?php if (count($galeri_list) > 0): ?>
                        <?php foreach ($galeri_list as $g): ?>
                            <div class="galeri-item">
                                <?php if ($g['tipe'] == 'foto'): ?>
                                    <img src="../assets/uploads/galeri_program/<?php echo $g['file_path']; ?>" alt="<?php echo $g['judul']; ?>">
                                <?php else: ?>
                                    <video src="../assets/uploads/galeri_program/<?php echo $g['file_path']; ?>"></video>
                                <?php endif; ?>
                                <div class="galeri-info">
                                    <h4><?php echo htmlspecialchars($g['judul']); ?></h4>
                                    <p><?php echo date('d/m/Y', strtotime($g['created_at'])); ?></p>
                                    <?php if ($g['deskripsi']): ?>
                                        <p><?php echo htmlspecialchars(substr($g['deskripsi'], 0, 60)) . (strlen($g['deskripsi']) > 60 ? '...' : ''); ?></p>
                                    <?php endif; ?>
                                    <div>
                                        <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $g['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                        <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $g['id']; ?>)"><i class="fas fa-trash"></i> Hapus</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; grid-column: 1/-1; padding: 40px;">Belum ada galeri untuk program ini</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- MODAL TAMBAH -->
    <div id="tambahModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Tambah Galeri Program</h3><span class="close-modal" onclick="closeModal('tambahModal')">&times;</span></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group"><label>Judul</label><input type="text" name="judul" required></div>
                <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi" rows="2"></textarea></div>
                <div class="form-group">
                    <label>Tipe</label>
                    <select name="tipe">
                        <option value="foto">Foto</option>
                        <option value="video">Video</option>
                    </select>
                </div>
                <div class="form-group"><label>Upload File</label><input type="file" name="file" accept="image/*,video/*" required>
                    <small style="color:#888;">Format: JPG, PNG, MP4 (Max 5MB)</small>
                </div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('tambahModal')">Batal</button><button type="submit" name="tambah" class="btn-save">Simpan</button></div>
            </form>
        </div>
    </div>
    
    <!-- MODAL EDIT -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit Galeri Program</h3><span class="close-modal" onclick="closeModal('editModal')">&times;</span></div>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group"><label>Judul</label><input type="text" name="judul" id="edit_judul" required></div>
                <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi" id="edit_deskripsi" rows="2"></textarea></div>
                <div class="form-group">
                    <label>Tipe</label>
                    <select name="tipe" id="edit_tipe">
                        <option value="foto">Foto</option>
                        <option value="video">Video</option>
                    </select>
                </div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('editModal')">Batal</button><button type="submit" name="edit" class="btn-save">Simpan</button></div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleSubmenu(e){e.classList.toggle('open');let s=e.nextElementSibling;if(s&&s.classList.contains('submenu'))s.classList.toggle('open');}
        function closeModal(id){document.getElementById(id).classList.remove('show');}
        function openTambahModal(){document.getElementById('tambahModal').classList.add('show');}
        
        function openEditModal(id){
            fetch('get_galeri_program.php?id='+id)
                .then(r=>r.json())
                .then(d=>{
                    if(d.success){
                        document.getElementById('edit_id').value = d.data.id;
                        document.getElementById('edit_judul').value = d.data.judul;
                        document.getElementById('edit_deskripsi').value = d.data.deskripsi || '';
                        document.getElementById('edit_tipe').value = d.data.tipe;
                        document.getElementById('editModal').classList.add('show');
                    }
                });
        }
        
        function confirmDelete(id){
            if(confirm('Yakin ingin menghapus galeri ini?')){
                window.location.href = 'program_galeri.php?program_id=<?php echo $program_id; ?>&hapus='+id;
            }
        }
        
        window.onclick = function(event){
            if(event.target.classList.contains('modal')) event.target.classList.remove('show');
        }
    </script>
</body>
</html>