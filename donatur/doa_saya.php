<?php
// ======================================================
// FILE: donatur/doa_saya.php
// HALAMAN LAPORAN KHUSUSON DO'A UNTUK DONATUR
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('donatur');

$currentUser = getCurrentUser();

// ======================================================
// PROSES TAMBAH DOA MANUAL
// ======================================================
if (isset($_POST['tambah'])) {
    $catatan_doa = mysqli_real_escape_string($conn, $_POST['catatan_doa']);
    
    $sql = "INSERT INTO doa (user_id, donasi_id, catatan_doa, status_doa) 
            VALUES (" . $currentUser['id'] . ", NULL, '$catatan_doa', 'pending')";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menambah titip doa manual");
        $_SESSION['success'] = "Doa berhasil dititipkan!";
    } else {
        $_SESSION['error'] = "Gagal menitipkan doa: " . mysqli_error($conn);
    }
    header("Location: doa_saya.php");
    exit();
}

// ======================================================
// PROSES EDIT DOA (hanya jika status belum terlaksana)
// ======================================================
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $catatan_doa = mysqli_real_escape_string($conn, $_POST['catatan_doa']);
    
    $check = mysqli_query($conn, "SELECT status_doa FROM doa WHERE id = $id AND user_id = " . $currentUser['id']);
    $data = mysqli_fetch_assoc($check);
    
    if ($data && $data['status_doa'] != 'didoakan') {
        $sql = "UPDATE doa SET catatan_doa = '$catatan_doa' WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            logActivity($currentUser['id'], "Mengedit doa ID: $id");
            $_SESSION['success'] = "Doa berhasil diupdate!";
        } else {
            $_SESSION['error'] = "Gagal mengupdate doa!";
        }
    } else {
        $_SESSION['error'] = "Doa yang sudah Terlaksana tidak bisa diedit!";
    }
    header("Location: doa_saya.php");
    exit();
}

// ======================================================
// PROSES HAPUS DOA (hanya jika status belum terlaksana)
// ======================================================
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    $check = mysqli_query($conn, "SELECT status_doa FROM doa WHERE id = $id AND user_id = " . $currentUser['id']);
    $data = mysqli_fetch_assoc($check);
    
    if ($data && $data['status_doa'] != 'didoakan') {
        $sql = "DELETE FROM doa WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            logActivity($currentUser['id'], "Menghapus doa ID: $id");
            $_SESSION['success'] = "Doa berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus doa!";
        }
    } else {
        $_SESSION['error'] = "Doa yang sudah Terlaksana tidak bisa dihapus!";
    }
    header("Location: doa_saya.php");
    exit();
}

// ======================================================
// FILTER
// ======================================================
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_periode = isset($_GET['periode']) ? mysqli_real_escape_string($conn, $_GET['periode']) : '';

$where = "WHERE d.user_id = " . $currentUser['id'];

if ($filter_status != '' && $filter_status != 'semua') {
    $where .= " AND d.status_doa = '$filter_status'";
}
if ($filter_periode != '' && $filter_periode != 'semua') {
    switch ($filter_periode) {
        case 'minggu_ini':
            $where .= " AND YEARWEEK(d.created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'bulan_ini':
            $where .= " AND MONTH(d.created_at) = MONTH(CURDATE()) AND YEAR(d.created_at) = YEAR(CURDATE())";
            break;
    }
}

// Ambil data doa
$sql = "SELECT d.*, 
        u.nama_lengkap as pengasuh_nama
        FROM doa d 
        LEFT JOIN users u ON d.dibaca_oleh = u.id 
        WHERE d.user_id = " . $currentUser['id'] . "
        ORDER BY d.created_at DESC";
$doaList = query($sql);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Khususon Do'a - Panti Asuhan Al-Muthi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; overflow-x: hidden; }
        
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
        
        .main-content { margin-left: 280px; padding: 20px; min-height: 100vh; }
        
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .page-title h2 { font-size: 20px; color: #333; }
        .page-title p { font-size: 13px; color: #888; margin-top: 5px; }
        .profile-dropdown { position: relative; }
        .profile-icon { width: 45px; height: 45px; background: linear-gradient(135deg, #50c878, #2e8b57); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; color: white; }
        .dropdown-menu { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 1000; }
        .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        .dropdown-menu a:hover { background: #f5f5f5; color: #50c878; }
        
        .content-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        .form-tambah { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: #555; }
        .form-group textarea { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; resize: vertical; }
        .form-group textarea:focus { outline: none; border-color: #50c878; }
        .btn-simpan { background: #50c878; color: white; padding: 10px 25px; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        
        .filter-section { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-section select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; background: white; }
        .btn-filter, .btn-reset { padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-filter { background: #50c878; color: white; }
        .btn-reset { background: #6c757d; color: white; text-decoration: none; display: inline-block; }
        
        .table-wrapper { overflow-x: auto; width: 100%; }
        table { width: 100%; min-width: 700px; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; vertical-align: top; }
        th { background: #f8f9fa; font-size: 13px; font-weight: 600; color: #666; }
        td { border-bottom: 1px solid #eee; font-size: 13px; color: #555; }
        
        th:nth-child(1) { width: 50px; }
        th:nth-child(2) { width: 130px; }
        th:nth-child(3) { width: 100px; }
        th:nth-child(4) { width: auto; }
        th:nth-child(5) { width: 110px; }
        th:nth-child(6) { width: 140px; }
        
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; white-space: nowrap; }
        .status-pending { background: #fff3e0; color: #ff9800; }
        .status-dibaca { background: #e3f2fd; color: #2196f3; }
        .status-didoakan { background: #e8f5e9; color: #4caf50; }
        
        td:last-child {
    white-space: nowrap;
}

.btn-action { 
    padding: 5px 10px; 
    border: none; 
    border-radius: 8px; 
    cursor: pointer; 
    margin: 2px; 
    font-size: 12px; 
    display: inline-block;
    white-space: nowrap;
}
        .btn-detail { background: #17a2b8; color: white; }
        .btn-edit { background: #50c878; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        
        .alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 550px; max-width: 90%; padding: 25px; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .detail-item { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
        .detail-label { font-weight: 600; font-size: 12px; color: #888; margin-bottom: 5px; }
        .detail-value { font-size: 14px; color: #333; }
        .detail-image { text-align: center; margin: 10px 0; }
        .detail-image img { max-width: 100%; max-height: 200px; border-radius: 10px; }
        .modal-footer { display: flex; justify-content: flex-end; margin-top: 20px; }
        .btn-cancel { background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; }
        
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
            <div class="menu-item" onclick="location.href='dashboard.php'"><i class="fas fa-tachometer-alt"></i><span>Beranda</span></div>
            <div class="menu-item" onclick="location.href='donasi.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Sekarang</span></div>
            <div class="menu-item" onclick="location.href='histori.php'"><i class="fas fa-history"></i><span>Riwayat Donasi</span></div>
            <div class="menu-item" onclick="location.href='laporan_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Laporan Pengeluaran</span></div>
            <div class="menu-item active" onclick="location.href='doa_saya.php'"><i class="fas fa-pray"></i><span>Laporan Khususon Do'a</span></div>
            <div class="menu-item" onclick="location.href='laporan.php'"><i class="fas fa-chart-line"></i><span>Laporan</span></div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Laporan Khususon Do'a</h2>
                <p>Doa dan permohonan yang Anda titipkan</p>
            </div>
            <div class="profile-dropdown">
                <div class="profile-icon"><i class="fas fa-cog"></i></div>
                <div class="dropdown-menu">
                    <a href="profil.php"><i class="fas fa-user-circle"></i> Profil</a>
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
            
            <!-- FORM TITIP DOA MANUAL -->
            <div class="form-tambah">
                <h4 style="margin-bottom: 15px;"><i class="fas fa-praying-hands"></i> Titip Doa Manual</h4>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Doa / Permohonan</label>
                        <textarea name="catatan_doa" rows="3" placeholder="Tulis doa atau permohonan Anda..." required></textarea>
                        <small style="color:#888;">Doa akan disampaikan ke pengasuh panti</small>
                    </div>
                    <button type="submit" name="tambah" class="btn-simpan"><i class="fas fa-paper-plane"></i> Titip Doa</button>
                </form>
            </div>
            
            <!-- FILTER -->
            <form method="GET" action="" class="filter-section">
                <select name="periode">
                    <option value="semua">Semua Periode</option>
                    <option value="minggu_ini" <?php echo $filter_periode == 'minggu_ini' ? 'selected' : ''; ?>>Minggu Ini</option>
                    <option value="bulan_ini" <?php echo $filter_periode == 'bulan_ini' ? 'selected' : ''; ?>>Bulan Ini</option>
                </select>
                <select name="status">
                    <option value="semua">Semua Status</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                    <option value="dibaca" <?php echo $filter_status == 'dibaca' ? 'selected' : ''; ?>>Telah Dibaca</option>
                    <option value="didoakan" <?php echo $filter_status == 'didoakan' ? 'selected' : ''; ?>>Terlaksana</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="doa_saya.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
            </form>
            
            <!-- TABLE -->
            <div class="table-wrapper">
                <h4 style="margin-bottom: 15px;"><i class="fas fa-list"></i> Daftar Doa</h4>
                <table>
                    <thead>
                        <tr><th>No</th><th>Nama Pengasuh</th><th>ID Donasi</th><th>Isi Doa</th><th>Status</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($doaList) > 0): $no = 1; foreach ($doaList as $doa): ?>
                            <?php 
                                $statusClass = '';
                                $statusText = '';
                                if ($doa['status_doa'] == 'pending') {
                                    $statusClass = 'status-pending';
                                    $statusText = 'Menunggu';
                                } elseif ($doa['status_doa'] == 'dibaca') {
                                    $statusClass = 'status-dibaca';
                                    $statusText = 'Telah Dibaca';
                                } else {
                                    $statusClass = 'status-didoakan';
                                    $statusText = 'Terlaksana';
                                }
                            ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($doa['pengasuh_nama'] ?: '-'); ?></td>
                                <td><?php echo $doa['donasi_id'] ?: '-'; ?></td>
                                <td><?php echo nl2br(htmlspecialchars(substr($doa['catatan_doa'], 0, 50))) . (strlen($doa['catatan_doa']) > 50 ? '...' : ''); ?></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <td>
                                    <button class="btn-action btn-detail" onclick="openDetailModal(<?php echo $doa['id']; ?>)"><i class="fas fa-info-circle"></i> Detail</button>
                                    <?php if ($doa['status_doa'] != 'didoakan'): ?>
                                        <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $doa['id']; ?>, '<?php echo htmlspecialchars(addslashes($doa['catatan_doa'])); ?>')"><i class="fas fa-edit"></i> Edit</button>
                                        <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $doa['id']; ?>)"><i class="fas fa-trash"></i> Hapus</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px;">Belum ada data doa</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- MODAL DETAIL -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Detail Khususon Do'a</h3><span class="close-modal" onclick="closeModal('detailModal')">&times;</span></div>
            <div id="detailContent"></div>
            <div class="modal-footer"><button class="btn-cancel" onclick="closeModal('detailModal')">Tutup</button></div>
        </div>
    </div>
    
    <!-- MODAL EDIT -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit Doa</h3><span class="close-modal" onclick="closeModal('editModal')">&times;</span></div>
            <form method="POST" action="">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Doa / Permohonan</label>
                    <textarea name="catatan_doa" id="edit_catatan" rows="5" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Batal</button>
                    <button type="submit" name="edit" class="btn-simpan">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function openEditModal(id, catatan) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_catatan').value = catatan;
            document.getElementById('editModal').classList.add('show');
        }
        
        function confirmDelete(id) {
            if (confirm('Apakah Anda yakin ingin menghapus doa ini?')) {
                window.location.href = 'doa_saya.php?hapus=' + id;
            }
        }
        
        function openDetailModal(id) {
            fetch('get_doa.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let d = data.data;
                        let statusText = d.status_doa == 'pending' ? 'Menunggu' : (d.status_doa == 'dibaca' ? 'Telah Dibaca' : 'Terlaksana');
                        let statusClass = d.status_doa == 'pending' ? 'status-pending' : (d.status_doa == 'dibaca' ? 'status-dibaca' : 'status-didoakan');
                        let imageHtml = '';
                        if (d.bukti_foto) {
                            imageHtml = `<div class="detail-image"><img src="../assets/uploads/doa/${d.bukti_foto}" onclick="window.open(this.src)"></div>`;
                        }
                        
                        document.getElementById('detailContent').innerHTML = `
                            <div class="detail-item"><div class="detail-label">Nama</div><div class="detail-value">${d.nama_lengkap || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">ID Donasi</div><div class="detail-value">${d.donasi_id || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Isi Doa</div><div class="detail-value">${d.catatan_doa.replace(/\n/g, '<br>')}</div></div>
                            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value"><span class="status-badge ${statusClass}">${statusText}</span></div></div>
                            ${imageHtml}
                            <div class="detail-item"><div class="detail-label">Tanggal dilaksanakan</div><div class="detail-value">${d.dibaca_at || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Dilaksanakan Oleh</div><div class="detail-value">${d.dibaca_oleh_nama || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Keterangan</div><div class="detail-value">${d.keterangan || '-'}</div></div>
                        `;
                        document.getElementById('detailModal').classList.add('show');
                    }
                });
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>