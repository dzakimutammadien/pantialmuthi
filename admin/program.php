<?php
// ======================================================
// FILE: admin/program.php
// HALAMAN KELOLA PROGRAM UTAMA (CRUD) - PERBAIKAN
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');

$currentUser = getCurrentUser();

// ======================================================
// FUNGSI UPLOAD GAMBAR PROGRAM
// ======================================================
function uploadGambarProgram($existing_file = null) {
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            return ['success' => false, 'message' => 'Format file tidak diizinkan (JPG, PNG, GIF, WEBP)'];
        }
        
        $filename = 'program_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target = '../assets/uploads/program/' . $filename;
        
        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target)) {
            if ($existing_file && file_exists('../assets/uploads/program/' . $existing_file)) {
                unlink('../assets/uploads/program/' . $existing_file);
            }
            return ['success' => true, 'filename' => $filename];
        }
    }
    return ['success' => true, 'filename' => $existing_file];
}

// ======================================================
// PROSES CRUD PROGRAM
// ======================================================

// TAMBAH PROGRAM
if (isset($_POST['tambah'])) {
    $nama_program = mysqli_real_escape_string($conn, $_POST['nama_program']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $target_nominal = (float)$_POST['target_nominal'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $upload = uploadGambarProgram();
    $gambar = $upload['success'] ? $upload['filename'] : null;
    
    $sql = "INSERT INTO program_donasi (nama_program, deskripsi, target_nominal, gambar, status) 
            VALUES ('$nama_program', '$deskripsi', $target_nominal, '$gambar', '$status')";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menambah program: $nama_program");
        $_SESSION['success'] = "Program berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan: " . mysqli_error($conn);
    }
    header("Location: program.php");
    exit();
}

// EDIT PROGRAM
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $nama_program = mysqli_real_escape_string($conn, $_POST['nama_program']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $target_nominal = (float)$_POST['target_nominal'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Ambil gambar lama
    $query = mysqli_query($conn, "SELECT gambar FROM program_donasi WHERE id = $id");
    $old = mysqli_fetch_assoc($query);
    
    $upload = uploadGambarProgram($old['gambar']);
    $gambar = $upload['success'] ? $upload['filename'] : $old['gambar'];
    
    $sql = "UPDATE program_donasi SET 
            nama_program = '$nama_program',
            deskripsi = '$deskripsi',
            target_nominal = $target_nominal,
            gambar = '$gambar',
            status = '$status'
            WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Mengedit program ID: $id");
        $_SESSION['success'] = "Program berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate: " . mysqli_error($conn);
    }
    header("Location: program.php");
    exit();
}

// HAPUS PROGRAM
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    // Ambil gambar untuk dihapus
    $query = mysqli_query($conn, "SELECT gambar FROM program_donasi WHERE id = $id");
    $gambar = mysqli_fetch_assoc($query)['gambar'];
    
    $sql = "DELETE FROM program_donasi WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        if ($gambar && file_exists('../assets/uploads/program/' . $gambar)) {
            unlink('../assets/uploads/program/' . $gambar);
        }
        logActivity($currentUser['id'], "Menghapus program ID: $id");
        $_SESSION['success'] = "Program berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus: " . mysqli_error($conn);
    }
    header("Location: program.php");
    exit();
}

// ======================================================
// PAGINATION
// ======================================================
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_sql = "SELECT COUNT(*) as total FROM program_donasi";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

// ======================================================
// QUERY PROGRAM DENGAN TOTAL TERKUMPUL
// ======================================================
$sql = "SELECT 
            p.*,
            COALESCE((SELECT SUM(nominal) FROM donasi_program WHERE program_id = p.id AND status = 'success'), 0) as terkumpul,
            COALESCE((SELECT COUNT(*) FROM donasi_program WHERE program_id = p.id AND status = 'success'), 0) as jumlah_donatur,
            COALESCE((SELECT SUM(jumlah) FROM penerima_manfaat WHERE program_id = p.id), 0) as tersalurkan
        FROM program_donasi p 
        ORDER BY p.created_at DESC 
        LIMIT $offset, $limit";
$programs = query($sql);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Program - Admin Panti Asuhan Al-Muthi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; overflow-x: hidden; }
        
        .sidebar { position: fixed; left: 0; top: 0; width: 280px; height: 100%; background: linear-gradient(135deg, #1a3a2a 0%, #2d4a3a 100%); color: white; overflow-y: auto; }
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
        
        .main-content { margin-left: 280px; padding: 20px; min-height: 100vh; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-title h2 { font-size: 20px; color: #333; }
        .profile-dropdown { position: relative; }
        .profile-icon { width: 45px; height: 45px; background: linear-gradient(135deg, #50c878, #2e8b57); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; color: white; }
        .dropdown-menu { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        
        .content-card { background: white; border-radius: 20px; padding: 25px; margin-bottom: 20px; }
        .btn-tambah { background: #50c878; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin-bottom: 20px; }
        
        .program-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; }
        .program-card { background: #f8f9fa; border-radius: 15px; padding: 15px; border: 1px solid #e0e0e0; transition: transform 0.3s; }
        .program-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .program-card img { width: 100%; height: 160px; object-fit: cover; border-radius: 10px; margin-bottom: 10px; }
        .program-card h3 { font-size: 16px; margin-bottom: 5px; }
        .program-card .target { font-size: 12px; color: #666; margin-bottom: 8px; }
        .progress-bar { background: #e0e0e0; border-radius: 10px; height: 8px; margin: 10px 0; overflow: hidden; }
        .progress-fill { background: #50c878; height: 100%; border-radius: 10px; transition: width 0.5s ease; }
        .progress-fill.done { background: #2196f3; }
        .btn-action { padding: 5px 10px; border: none; border-radius: 5px; cursor: pointer; font-size: 11px; margin: 2px; }
        .btn-edit { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-penerima { background: #17a2b8; color: white; }
        .btn-galeri { background: #6c757d; color: white; }
        .btn-donasi { background: #9c27b0; color: white; }
        
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 12px; border-radius: 5px; text-decoration: none; background: #f0f2f5; color: #333; }
        .pagination .active { background: #50c878; color: white; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 550px; max-width: 90%; padding: 25px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: #50c878; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        
        .status-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .status-aktif { background: #e8f5e9; color: #4caf50; }
        .status-selesai { background: #e3f2fd; color: #2196f3; }
        .status-ditutup { background: #ffebee; color: #f44336; }
        
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
                <div class="submenu-item" onclick="location.href='verifikasi_program.php'"><i class="fas fa-heart"></i><span>Verifikasi Program</span></div>
                <div class="submenu-item" onclick="location.href='laporan_keuangan.php'"><i class="fas fa-chart-line"></i><span>Laporan Keuangan</span></div>
            </div>
            <div class="menu-item has-submenu open" onclick="toggleSubmenu(this)"><i class="fas fa-database"></i><span>Master Data</span><i class="fas fa-chevron-down arrow"></i></div>
            <div class="submenu open">
                <div class="submenu-item" onclick="location.href='kategori_donasi.php'"><i class="fas fa-tags"></i><span>Kategori Transaksi</span></div>
                <div class="submenu-item" onclick="location.href='kategori_role.php'"><i class="fas fa-user-tag"></i><span>Kategori Role</span></div>
                <div class="submenu-item" onclick="location.href='anak_asuh.php'"><i class="fas fa-child"></i><span>Data Anak Asuh</span></div>
                <div class="submenu-item active" onclick="location.href='program.php'"><i class="fas fa-chalkboard-user"></i><span>Program Utama</span></div>
                <div class="submenu-item" onclick="location.href='galeri.php'"><i class="fas fa-images"></i><span>Galeri</span></div>
                <div class="submenu-item" onclick="location.href='perkembangan.php'"><i class="fas fa-seedling"></i><span>Perkembangan Anak</span></div>
                <div class="submenu-item" onclick="location.href='doa_khusus.php'"><i class="fas fa-pray"></i><span>Data Doa Khusus</span></div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div class="page-title"><h2>Program Utama</h2><p>Kelola program donasi crowdfunding panti</p></div>
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
            
            <button class="btn-tambah" onclick="openTambahModal()"><i class="fas fa-plus"></i> Tambah Program</button>
            
            <div class="program-grid">
                <?php if (count($programs) > 0): ?>
                    <?php foreach ($programs as $p): 
                        $persen = ($p['target_nominal'] > 0) ? round(($p['terkumpul'] / $p['target_nominal']) * 100) : 0;
                        $persen = min($persen, 100);
                        $statusClass = 'status-' . $p['status'];
                    ?>
                        <div class="program-card">
                            <?php if ($p['gambar']): ?>
                                <img src="../assets/uploads/program/<?php echo $p['gambar']; ?>" alt="<?php echo $p['nama_program']; ?>">
                            <?php else: ?>
                                <div style="height:160px; background:#e0e0e0; display:flex; align-items:center; justify-content:center; border-radius:10px; margin-bottom:10px;">
                                    <i class="fas fa-chalkboard-user" style="font-size:48px; color:#aaa;"></i>
                                </div>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($p['nama_program']); ?></h3>
                            <div class="target">
                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($p['status']); ?></span>
                            </div>
                            <div class="target">Target: Rp <?php echo number_format($p['target_nominal'], 0, ',', '.'); ?></div>
                            <div class="target">Terkumpul: Rp <?php echo number_format($p['terkumpul'], 0, ',', '.'); ?> (<?php echo $p['jumlah_donatur']; ?> donatur)</div>
                            <div class="target">Tersalurkan: Rp <?php echo number_format($p['tersalurkan'], 0, ',', '.'); ?></div>
                            <div class="progress-bar">
                                <div class="progress-fill <?php echo $persen >= 100 ? 'done' : ''; ?>" style="width: <?php echo $persen; ?>%;"></div>
                            </div>
                            <div class="target" style="color: <?php echo $persen >= 100 ? '#2196f3' : '#888'; ?>;">
                                <?php echo $persen; ?>% tercapai
                            </div>
                            <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 4px;">
                                <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $p['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $p['id']; ?>)"><i class="fas fa-trash"></i> Hapus</button>
                                <button class="btn-action btn-penerima" onclick="location.href='program_penerima.php?program_id=<?php echo $p['id']; ?>'"><i class="fas fa-users"></i> Penerima</button>
                                <button class="btn-action btn-galeri" onclick="location.href='program_galeri.php?program_id=<?php echo $p['id']; ?>'"><i class="fas fa-images"></i> Galeri</button>
                                <button class="btn-action btn-donasi" onclick="location.href='verifikasi_program.php?program_id=<?php echo $p['id']; ?>'"><i class="fas fa-hand-holding-heart"></i> Donasi</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; grid-column: 1/-1; padding: 40px;">Belum ada program. Klik "Tambah Program" untuk memulai.</p>
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
    
    <!-- MODAL TAMBAH PROGRAM -->
    <div id="tambahModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Tambah Program</h3><span class="close-modal" onclick="closeModal('tambahModal')">&times;</span></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group"><label>Nama Program</label><input type="text" name="nama_program" required></div>
                <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi" rows="3"></textarea></div>
                <div class="form-group"><label>Target Nominal (Rp)</label><input type="number" name="target_nominal" required></div>
                <div class="form-group"><label>Gambar Program</label><input type="file" name="gambar" accept="image/*"></div>
                <div class="form-group"><label>Status</label><select name="status">
                    <option value="aktif">Aktif</option>
                    <option value="selesai">Selesai</option>
                    <option value="ditutup">Ditutup</option>
                </select></div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('tambahModal')">Batal</button><button type="submit" name="tambah" class="btn-save">Simpan</button></div>
            </form>
        </div>
    </div>
    
    <!-- MODAL EDIT PROGRAM -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit Program</h3><span class="close-modal" onclick="closeModal('editModal')">&times;</span></div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group"><label>Nama Program</label><input type="text" name="nama_program" id="edit_nama" required></div>
                <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi" id="edit_deskripsi" rows="3"></textarea></div>
                <div class="form-group"><label>Target Nominal (Rp)</label><input type="number" name="target_nominal" id="edit_target" required></div>
                <div class="form-group"><label>Ganti Gambar</label><input type="file" name="gambar" accept="image/*"></div>
                <div class="form-group"><label>Status</label><select name="status" id="edit_status">
                    <option value="aktif">Aktif</option>
                    <option value="selesai">Selesai</option>
                    <option value="ditutup">Ditutup</option>
                </select></div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('editModal')">Batal</button><button type="submit" name="edit" class="btn-save">Simpan</button></div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleSubmenu(e){e.classList.toggle('open');let s=e.nextElementSibling;if(s&&s.classList.contains('submenu'))s.classList.toggle('open');}
        
        function openTambahModal(){document.getElementById('tambahModal').classList.add('show');}
        function closeModal(id){document.getElementById(id).classList.remove('show');}
        
        function openEditModal(id){
            fetch('get_program.php?id='+id)
                .then(r=>r.json())
                .then(d=>{
                    if(d.success){
                        document.getElementById('edit_id').value = d.data.id;
                        document.getElementById('edit_nama').value = d.data.nama_program;
                        document.getElementById('edit_deskripsi').value = d.data.deskripsi || '';
                        document.getElementById('edit_target').value = d.data.target_nominal;
                        document.getElementById('edit_status').value = d.data.status;
                        document.getElementById('editModal').classList.add('show');
                    }
                });
        }
        
        function confirmDelete(id){
            if(confirm('Yakin ingin menghapus program ini?')){
                window.location.href = 'program.php?hapus='+id;
            }
        }
        
        window.onclick = function(event){
            if(event.target.classList.contains('modal')) event.target.classList.remove('show');
        }
    </script>
</body>
</html>