<?php
// ======================================================
// FILE: admin/program_penerima.php
// HALAMAN KELOLA PENERIMA MANFAAT PER PROGRAM (DENGAN FOTO)
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

// ======================================================
// FUNGSI UPLOAD FOTO PENERIMA
// ======================================================
function uploadFotoPenerima($existing_foto = null) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            return ['success' => false, 'message' => 'Format file tidak diizinkan (JPG, PNG, GIF, WEBP)'];
        }
        
        $filename = 'penerima_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target = '../assets/uploads/penerima/' . $filename;
        
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
            if ($existing_foto && file_exists('../assets/uploads/penerima/' . $existing_foto)) {
                unlink('../assets/uploads/penerima/' . $existing_foto);
            }
            return ['success' => true, 'filename' => $filename];
        }
    }
    return ['success' => true, 'filename' => $existing_foto];
}

// ======================================================
// PROSES CRUD PENERIMA MANFAAT
// ======================================================

// TAMBAH PENERIMA
if (isset($_POST['tambah'])) {
    $nama_penerima = mysqli_real_escape_string($conn, $_POST['nama_penerima']);
    $jenis_bantuan = mysqli_real_escape_string($conn, $_POST['jenis_bantuan']);
    $jumlah = (float)$_POST['jumlah'];
    $tanggal_penyaluran = mysqli_real_escape_string($conn, $_POST['tanggal_penyaluran']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    // Upload foto
    $upload = uploadFotoPenerima();
    $foto = $upload['success'] ? $upload['filename'] : null;
    
    $sql = "INSERT INTO penerima_manfaat (program_id, nama_penerima, jenis_bantuan, jumlah, tanggal_penyaluran, keterangan, foto) 
            VALUES ($program_id, '$nama_penerima', '$jenis_bantuan', $jumlah, '$tanggal_penyaluran', '$keterangan', '$foto')";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menambah penerima manfaat untuk program: " . $program['nama_program']);
        $_SESSION['success'] = "Penerima manfaat berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan: " . mysqli_error($conn);
    }
    header("Location: program_penerima.php?program_id=$program_id");
    exit();
}

// EDIT PENERIMA
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $nama_penerima = mysqli_real_escape_string($conn, $_POST['nama_penerima']);
    $jenis_bantuan = mysqli_real_escape_string($conn, $_POST['jenis_bantuan']);
    $jumlah = (float)$_POST['jumlah'];
    $tanggal_penyaluran = mysqli_real_escape_string($conn, $_POST['tanggal_penyaluran']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    // Ambil foto lama
    $query = mysqli_query($conn, "SELECT foto FROM penerima_manfaat WHERE id = $id");
    $old = mysqli_fetch_assoc($query);
    
    // Upload foto baru
    $upload = uploadFotoPenerima($old['foto']);
    $foto = $upload['success'] ? $upload['filename'] : $old['foto'];
    
    $sql = "UPDATE penerima_manfaat SET 
            nama_penerima = '$nama_penerima',
            jenis_bantuan = '$jenis_bantuan',
            jumlah = $jumlah,
            tanggal_penyaluran = '$tanggal_penyaluran',
            keterangan = '$keterangan',
            foto = '$foto'
            WHERE id = $id AND program_id = $program_id";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Mengedit penerima manfaat ID: $id");
        $_SESSION['success'] = "Penerima manfaat berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate: " . mysqli_error($conn);
    }
    header("Location: program_penerima.php?program_id=$program_id");
    exit();
}

// HAPUS PENERIMA
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    // Ambil foto untuk dihapus
    $query = mysqli_query($conn, "SELECT foto FROM penerima_manfaat WHERE id = $id");
    $foto = mysqli_fetch_assoc($query)['foto'];
    
    $sql = "DELETE FROM penerima_manfaat WHERE id = $id AND program_id = $program_id";
    if (mysqli_query($conn, $sql)) {
        if ($foto && file_exists('../assets/uploads/penerima/' . $foto)) {
            unlink('../assets/uploads/penerima/' . $foto);
        }
        logActivity($currentUser['id'], "Menghapus penerima manfaat ID: $id");
        $_SESSION['success'] = "Penerima manfaat berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus: " . mysqli_error($conn);
    }
    header("Location: program_penerima.php?program_id=$program_id");
    exit();
}

// Ambil data penerima manfaat
$sql_penerima = "SELECT * FROM penerima_manfaat WHERE program_id = $program_id ORDER BY tanggal_penyaluran DESC";
$penerima_list = query($sql_penerima);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penerima Manfaat - <?php echo $program['nama_program']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; }
        
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        h2 { color: #333; }
        .btn-back { background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-tambah { background: #50c878; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; vertical-align: middle; }
        th { background: #f8f9fa; }
        .foto-thumb { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
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
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: #50c878; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        
        @media (max-width: 768px) { .container { margin: 10px; } table { font-size: 12px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2><i class="fas fa-users"></i> Penerima Manfaat</h2>
                    <p>Program: <strong><?php echo $program['nama_program']; ?></strong></p>
                </div>
                <div>
                    <a href="program.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
                    <button class="btn-tambah" onclick="openTambahModal()"><i class="fas fa-plus"></i> Tambah Penerima</button>
                </div>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Foto</th>
                        <th>Nama Penerima</th>
                        <th>Jenis Bantuan</th>
                        <th>Jumlah</th>
                        <th>Tanggal Penyaluran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($penerima_list) > 0): $no = 1; foreach ($penerima_list as $p): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td>
                                <?php if ($p['foto']): ?>
                                    <img src="../assets/uploads/penerima/<?php echo $p['foto']; ?>" class="foto-thumb" onerror="this.src='../assets/image/almuthi.png'">
                                <?php else: ?>
                                    <i class="fas fa-user-circle" style="font-size: 32px; color: #ccc;"></i>
                                <?php endif; ?>
                                </td>
                            <td><?php echo htmlspecialchars($p['nama_penerima']); ?></td>
                            <td><?php echo htmlspecialchars($p['jenis_bantuan']); ?></td>
                            <td>Rp <?php echo number_format($p['jumlah'], 0, ',', '.'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p['tanggal_penyaluran'])); ?></td>
                            <td>
                                <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $p['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $p['id']; ?>)"><i class="fas fa-trash"></i> Hapus</button>
                                </td>
                                </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7" style="text-align:center;">Belum ada penerima manfaat</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- MODAL TAMBAH -->
    <div id="tambahModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Tambah Penerima Manfaat</h3><span class="close-modal" onclick="closeModal('tambahModal')">&times;</span></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group"><label>Nama Penerima</label><input type="text" name="nama_penerima" required></div>
                <div class="form-group"><label>Jenis Bantuan</label><input type="text" name="jenis_bantuan" placeholder="Contoh: Beasiswa, Kesehatan, Sembako"></div>
                <div class="form-group"><label>Jumlah (Rp)</label><input type="number" name="jumlah" required></div>
                <div class="form-group"><label>Tanggal Penyaluran</label><input type="date" name="tanggal_penyaluran" required></div>
                <div class="form-group"><label>Keterangan</label><textarea name="keterangan" rows="2"></textarea></div>
                <div class="form-group"><label>Foto Penerima</label><input type="file" name="foto" accept="image/*">
                    <small style="color:#888;">Format: JPG, PNG (Max 2MB)</small>
                </div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('tambahModal')">Batal</button><button type="submit" name="tambah" class="btn-save">Simpan</button></div>
            </form>
        </div>
    </div>
    
    <!-- MODAL EDIT -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit Penerima Manfaat</h3><span class="close-modal" onclick="closeModal('editModal')">&times;</span></div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group"><label>Nama Penerima</label><input type="text" name="nama_penerima" id="edit_nama" required></div>
                <div class="form-group"><label>Jenis Bantuan</label><input type="text" name="jenis_bantuan" id="edit_jenis"></div>
                <div class="form-group"><label>Jumlah (Rp)</label><input type="number" name="jumlah" id="edit_jumlah" required></div>
                <div class="form-group"><label>Tanggal Penyaluran</label><input type="date" name="tanggal_penyaluran" id="edit_tanggal" required></div>
                <div class="form-group"><label>Keterangan</label><textarea name="keterangan" id="edit_keterangan" rows="2"></textarea></div>
                <div class="form-group"><label>Ganti Foto</label><input type="file" name="foto" accept="image/*">
                    <small style="color:#888;">Kosongkan jika tidak ingin mengganti foto</small>
                </div>
                <div id="edit_current_foto" class="form-group" style="text-align:center;"></div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('editModal')">Batal</button><button type="submit" name="edit" class="btn-save">Simpan</button></div>
            </form>
        </div>
    </div>
    
    <script>
        function closeModal(id){document.getElementById(id).classList.remove('show');}
        
        function openTambahModal(){document.getElementById('tambahModal').classList.add('show');}
        
        function openEditModal(id){
            fetch('get_penerima.php?id='+id)
                .then(r=>r.json())
                .then(d=>{
                    if(d.success){
                        document.getElementById('edit_id').value = d.data.id;
                        document.getElementById('edit_nama').value = d.data.nama_penerima;
                        document.getElementById('edit_jenis').value = d.data.jenis_bantuan || '';
                        document.getElementById('edit_jumlah').value = d.data.jumlah;
                        document.getElementById('edit_tanggal').value = d.data.tanggal_penyaluran;
                        document.getElementById('edit_keterangan').value = d.data.keterangan || '';
                        
                        if(d.data.foto){
                            document.getElementById('edit_current_foto').innerHTML = '<label>Foto Saat Ini</label><br><img src="../assets/uploads/penerima/'+d.data.foto+'" width="80" height="80" style="border-radius:50%; object-fit:cover;">';
                        } else {
                            document.getElementById('edit_current_foto').innerHTML = '';
                        }
                        document.getElementById('editModal').classList.add('show');
                    }
                });
        }
        
        function confirmDelete(id){
            if(confirm('Yakin ingin menghapus penerima manfaat ini?')){
                window.location.href = 'program_penerima.php?program_id=<?php echo $program_id; ?>&hapus='+id;
            }
        }
        
        window.onclick = function(event){
            if(event.target.classList.contains('modal')) event.target.classList.remove('show');
        }
    </script>
</body>
</html>