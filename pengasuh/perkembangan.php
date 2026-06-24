<?php
// ======================================================
// FILE: pengasuh/perkembangan.php
// HALAMAN INPUT & LIHAT PERKEMBANGAN ANAK ASUH
// (Kesehatan + Pendidikan) + EDIT & HAPUS
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('pengasuh');

$currentUser = getCurrentUser();

// ======================================================
// PROSES TAMBAH DATA KESEHATAN
// ======================================================
if (isset($_POST['tambah_kesehatan'])) {
    $anak_asuh_id = (int)$_POST['anak_asuh_id'];
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $berat_badan = (float)$_POST['berat_badan'];
    $tinggi_badan = (float)$_POST['tinggi_badan'];
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    $sql = "INSERT INTO perkembangan_kesehatan (anak_asuh_id, tanggal, berat_badan, tinggi_badan, keterangan, created_by) 
            VALUES ($anak_asuh_id, '$tanggal', $berat_badan, $tinggi_badan, '$keterangan', " . $currentUser['id'] . ")";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menambah data kesehatan anak asuh ID: $anak_asuh_id");
        $_SESSION['success'] = "Data kesehatan berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan: " . mysqli_error($conn);
    }
    header("Location: perkembangan.php?anak_id=$anak_asuh_id");
    exit();
}

// ======================================================
// PROSES TAMBAH DATA PENDIDIKAN
// ======================================================
if (isset($_POST['tambah_pendidikan'])) {
    $anak_asuh_id = (int)$_POST['anak_asuh_id'];
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $tahun_ajaran = mysqli_real_escape_string($conn, $_POST['tahun_ajaran']);
    $rata_rata = (float)$_POST['rata_rata'];
    $predikat = mysqli_real_escape_string($conn, $_POST['predikat']);
    $prestasi = mysqli_real_escape_string($conn, $_POST['prestasi']);
    
    $sql = "INSERT INTO perkembangan_pendidikan (anak_asuh_id, semester, tahun_ajaran, rata_rata, predikat, prestasi, created_by) 
            VALUES ($anak_asuh_id, '$semester', '$tahun_ajaran', $rata_rata, '$predikat', '$prestasi', " . $currentUser['id'] . ")";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menambah data pendidikan anak asuh ID: $anak_asuh_id");
        $_SESSION['success'] = "Data pendidikan berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan: " . mysqli_error($conn);
    }
    header("Location: perkembangan.php?anak_id=$anak_asuh_id");
    exit();
}

// ======================================================
// PROSES EDIT DATA KESEHATAN
// ======================================================
if (isset($_POST['edit_kesehatan'])) {
    $id = (int)$_POST['id'];
    $anak_asuh_id = (int)$_POST['anak_id'];
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $berat_badan = (float)$_POST['berat_badan'];
    $tinggi_badan = (float)$_POST['tinggi_badan'];
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    $sql = "UPDATE perkembangan_kesehatan SET 
            tanggal = '$tanggal', 
            berat_badan = $berat_badan, 
            tinggi_badan = $tinggi_badan, 
            keterangan = '$keterangan' 
            WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Mengedit data kesehatan ID: $id");
        $_SESSION['success'] = "Data kesehatan berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate: " . mysqli_error($conn);
    }
    header("Location: perkembangan.php?anak_id=$anak_asuh_id");
    exit();
}

// ======================================================
// PROSES EDIT DATA PENDIDIKAN
// ======================================================
if (isset($_POST['edit_pendidikan'])) {
    $id = (int)$_POST['id'];
    $anak_asuh_id = (int)$_POST['anak_id'];
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $tahun_ajaran = mysqli_real_escape_string($conn, $_POST['tahun_ajaran']);
    $rata_rata = (float)$_POST['rata_rata'];
    $predikat = mysqli_real_escape_string($conn, $_POST['predikat']);
    $prestasi = mysqli_real_escape_string($conn, $_POST['prestasi']);
    
    $sql = "UPDATE perkembangan_pendidikan SET 
            semester = '$semester', 
            tahun_ajaran = '$tahun_ajaran', 
            rata_rata = $rata_rata, 
            predikat = '$predikat', 
            prestasi = '$prestasi' 
            WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Mengedit data pendidikan ID: $id");
        $_SESSION['success'] = "Data pendidikan berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate: " . mysqli_error($conn);
    }
    header("Location: perkembangan.php?anak_id=$anak_asuh_id");
    exit();
}

// ======================================================
// PROSES HAPUS DATA KESEHATAN
// ======================================================
if (isset($_GET['hapus_kesehatan'])) {
    $id = (int)$_GET['hapus_kesehatan'];
    $anak_id = (int)$_GET['anak_id'];
    
    $sql = "DELETE FROM perkembangan_kesehatan WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menghapus data kesehatan ID: $id");
        $_SESSION['success'] = "Data kesehatan berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus: " . mysqli_error($conn);
    }
    header("Location: perkembangan.php?anak_id=$anak_id");
    exit();
}

// ======================================================
// PROSES HAPUS DATA PENDIDIKAN
// ======================================================
if (isset($_GET['hapus_pendidikan'])) {
    $id = (int)$_GET['hapus_pendidikan'];
    $anak_id = (int)$_GET['anak_id'];
    
    $sql = "DELETE FROM perkembangan_pendidikan WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Menghapus data pendidikan ID: $id");
        $_SESSION['success'] = "Data pendidikan berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus: " . mysqli_error($conn);
    }
    header("Location: perkembangan.php?anak_id=$anak_id");
    exit();
}

// ======================================================
// AMBIL DATA ANAK ASUH
// ======================================================
$sql_anak = "SELECT id, nama_lengkap, umur FROM anak_asuh ORDER BY nama_lengkap ASC";
$anak_list = query($sql_anak);

// ======================================================
// FILTER & AMBIL DATA PERKEMBANGAN
// ======================================================
$selected_anak = isset($_GET['anak_id']) ? (int)$_GET['anak_id'] : 0;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$kesehatan_data = [];
$pendidikan = [];
$labels_kesehatan = [];

if ($selected_anak > 0) {
    $sql_kesehatan = "SELECT * FROM perkembangan_kesehatan 
                      WHERE anak_asuh_id = $selected_anak 
                      ORDER BY tanggal DESC LIMIT 6";
    $kesehatan = query($sql_kesehatan);
    $kesehatan_data = array_reverse($kesehatan);
    
    foreach ($kesehatan_data as $k) {
        $labels_kesehatan[] = date('M Y', strtotime($k['tanggal']));
    }
    
    $sql_pendidikan = "SELECT * FROM perkembangan_pendidikan 
                       WHERE anak_asuh_id = $selected_anak 
                       ORDER BY created_at ASC";
    $pendidikan = query($sql_pendidikan);
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perkembangan Anak Asuh - Pengasuh Panti Asuhan Al-Muthi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .content-card { background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-section input { flex: 2; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; }
        .filter-section button { padding: 10px 20px; background: #50c878; color: white; border: none; border-radius: 10px; cursor: pointer; }
        .btn-reset { padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 10px; cursor: pointer; text-decoration: none; display: inline-block; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; }
        
        .btn-save { background: #50c878; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin-top: 10px; }
        
        .chart-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-box { background: #f8f9fa; padding: 15px; border-radius: 15px; }
        .chart-box h4 { margin-bottom: 15px; text-align: center; }
        canvas { max-height: 250px; }
        
        .riwayat-table { overflow-x: auto; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        .badge { padding: 4px 8px; border-radius: 20px; font-size: 11px; }
        .badge-success { background: #e8f5e9; color: #4caf50; }
        
        .btn-action { padding: 5px 10px; border: none; border-radius: 5px; cursor: pointer; margin: 2px; font-size: 11px; }
        .btn-edit { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        
        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 500px; max-width: 90%; padding: 25px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-cancel { background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; }
        
        @media (max-width: 768px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } .form-grid { grid-template-columns: 1fr; } .chart-container { grid-template-columns: 1fr; } }
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
            <div class="menu-item" onclick="location.href='pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Pengeluaran Panti</span></div>
            
            <div class="menu-item" onclick="location.href='doa.php'"><i class="fas fa-pray"></i><span>Permohonan Khusus Doa</span></div>
            <div class="menu-item" onclick="location.href='anak_asuh.php'"><i class="fas fa-child"></i><span>Data Anak Asuh</span></div> 
            <div class="menu-item" onclick="location.href='perkembangan.php'">
                <i class="fas fa-seedling"></i>
                <span>Perkembangan Anak</span>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div class="page-title"><h2>Perkembangan Anak Asuh</h2><p>Input dan pantau perkembangan kesehatan & pendidikan</p></div>
            <div class="profile-dropdown">
                <div class="profile-icon"><i class="fas fa-cog"></i></div>
                <div class="dropdown-menu">
                    <a href="profil.php"><i class="fas fa-user-circle"></i> Profil</a>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- PILIH ANAK ASUH -->
        <div class="content-card">
            <h3><i class="fas fa-child"></i> Pilih Anak Asuh</h3>
            <form method="GET" action="" class="filter-section">
                <input type="text" name="search" id="search_anak" placeholder="Cari nama anak..." autocomplete="off" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> Cari</button>
                <a href="perkembangan.php" class="btn-reset">Reset</a>
            </form>
            
            <select name="anak_id" id="anak_id" style="width: 100%; padding: 10px; margin-top: 10px;" onchange="location.href='perkembangan.php?anak_id='+this.value">
                <option value="">-- Pilih Anak Asuh --</option>
                <?php foreach ($anak_list as $a): ?>
                    <?php if (empty($search) || stripos($a['nama_lengkap'], $search) !== false): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $selected_anak == $a['id'] ? 'selected' : ''; ?>>
                            <?php echo $a['nama_lengkap']; ?> (<?php echo $a['umur']; ?> tahun)
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if ($selected_anak > 0): 
            $nama_anak = '';
            foreach ($anak_list as $a) {
                if ($a['id'] == $selected_anak) {
                    $nama_anak = $a['nama_lengkap'];
                    break;
                }
            }
        ?>
        
        <!-- FORM INPUT DATA -->
        <div class="content-card">
            <h3><i class="fas fa-plus-circle"></i> Tambah Data Perkembangan untuk <?php echo $nama_anak; ?></h3>
            <div class="form-grid">
                <div>
                    <h4><i class="fas fa-heartbeat"></i> Data Kesehatan</h4>
                    <form method="POST">
                        <input type="hidden" name="anak_asuh_id" value="<?php echo $selected_anak; ?>">
                        <div class="form-group">
                            <label>Tanggal Pengukuran</label>
                            <input type="date" name="tanggal" required>
                        </div>
                        <div class="form-group">
                            <label>Berat Badan (kg)</label>
                            <input type="number" step="0.1" name="berat_badan" placeholder="Contoh: 25.5" required>
                        </div>
                        <div class="form-group">
                            <label>Tinggi Badan (cm)</label>
                            <input type="number" step="0.1" name="tinggi_badan" placeholder="Contoh: 120.5" required>
                        </div>
                        <div class="form-group">
                            <label>Keterangan</label>
                            <textarea name="keterangan" rows="2" placeholder="Catatan kesehatan..."></textarea>
                        </div>
                        <button type="submit" name="tambah_kesehatan" class="btn-save"><i class="fas fa-save"></i> Simpan Kesehatan</button>
                    </form>
                </div>
                
                <div>
                    <h4><i class="fas fa-graduation-cap"></i> Data Pendidikan</h4>
                    <form method="POST">
                        <input type="hidden" name="anak_asuh_id" value="<?php echo $selected_anak; ?>">
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester" required>
                                <option value="">Pilih Semester</option>
                                <option value="Ganjil">Ganjil</option>
                                <option value="Genap">Genap</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tahun Ajaran</label>
                            <input type="text" name="tahun_ajaran" placeholder="Contoh: 2024/2025" required>
                        </div>
                        <div class="form-group">
                            <label>Rata-rata Nilai</label>
                            <input type="number" step="0.1" name="rata_rata" placeholder="Contoh: 85.5" required>
                        </div>
                        <div class="form-group">
                            <label>Predikat</label>
                            <select name="predikat">
                                <option value="A">A (Sangat Baik)</option>
                                <option value="B">B (Baik)</option>
                                <option value="C">C (Cukup)</option>
                                <option value="D">D (Kurang)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Prestasi</label>
                            <textarea name="prestasi" rows="2" placeholder="Juara kelas, Olimpiade, dll..."></textarea>
                        </div>
                        <button type="submit" name="tambah_pendidikan" class="btn-save"><i class="fas fa-save"></i> Simpan Pendidikan</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- GRAFIK PERKEMBANGAN -->
        <div class="content-card">
            <h3><i class="fas fa-chart-line"></i> Grafik Perkembangan <?php echo $nama_anak; ?></h3>
            <div class="chart-container">
                <div class="chart-box">
                    <h4>📊 Perkembangan Berat Badan</h4>
                    <canvas id="beratChart"></canvas>
                </div>
                <div class="chart-box">
                    <h4>📊 Perkembangan Tinggi Badan</h4>
                    <canvas id="tinggiChart"></canvas>
                </div>
            </div>
            
            <?php if (count($pendidikan) > 0): ?>
            <div class="chart-box" style="margin-top: 20px;">
                <h4>📚 Perkembangan Pendidikan (Rata-rata Nilai)</h4>
                <canvas id="pendidikanChart" style="max-height: 250px;"></canvas>
                <?php 
                $pendidikan_labels = [];
                $pendidikan_nilai = [];
                $pendidikan_prestasi = [];
                foreach ($pendidikan as $p) {
                    $pendidikan_labels[] = $p['semester'] . ' ' . $p['tahun_ajaran'];
                    $pendidikan_nilai[] = $p['rata_rata'];
                    $pendidikan_prestasi[] = $p['prestasi'];
                }
                ?>
                <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 10px;">
                    <strong>🏆 Prestasi Terbaru:</strong> <?php echo end($pendidikan_prestasi) ?: '-'; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- RIWAYAT DATA KESEHATAN -->
            <div class="riwayat-table">
                <h4><i class="fas fa-history"></i> Riwayat Kesehatan</h4>
            <table>
                    <thead>
                        <tr><th>Tanggal</th><th>Berat (kg)</th><th>Tinggi (cm)</th><th>Keterangan</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($kesehatan_data) > 0): ?>
                            <?php foreach ($kesehatan_data as $k): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($k['tanggal'])); ?></td>
                                    <td><?php echo $k['berat_badan']; ?> kg</td>
                                    <td><?php echo $k['tinggi_badan']; ?> cm</td>
                                    <td><?php echo $k['keterangan'] ?: '-'; ?></td>
                                    <td>
                                        <button class="btn-action btn-edit" onclick="editKesehatan(<?php echo $k['id']; ?>, <?php echo $selected_anak; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                        <button class="btn-action btn-delete" onclick="if(confirm('Yakin hapus data ini?')) window.location.href='perkembangan.php?hapus_kesehatan=<?php echo $k['id']; ?>&anak_id=<?php echo $selected_anak; ?>'"><i class="fas fa-trash"></i> Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;">Belum ada data kesehatan</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- RIWAYAT DATA PENDIDIKAN -->
            <div class="riwayat-table" style="margin-top: 20px;">
                <h4><i class="fas fa-history"></i> Riwayat Pendidikan</h4>
                <table>
                    <thead>
                        <tr><th>Semester</th><th>Tahun Ajaran</th><th>Rata-rata</th><th>Predikat</th><th>Prestasi</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($pendidikan) > 0): ?>
                            <?php foreach ($pendidikan as $p): ?>
                                <tr>
                                    <td><?php echo $p['semester']; ?></td>
                                    <td><?php echo $p['tahun_ajaran']; ?></td>
                                    <td><?php echo $p['rata_rata']; ?></td>
                                    <td><span class="badge badge-success"><?php echo $p['predikat']; ?></span></td>
                                    <td><?php echo $p['prestasi'] ?: '-'; ?></td>
                                    <td>
                                        <button class="btn-action btn-edit" onclick="editPendidikan(<?php echo $p['id']; ?>, <?php echo $selected_anak; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                        <button class="btn-action btn-delete" onclick="if(confirm('Yakin hapus data ini?')) window.location.href='perkembangan.php?hapus_pendidikan=<?php echo $p['id']; ?>&anak_id=<?php echo $selected_anak; ?>'"><i class="fas fa-trash"></i> Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center;">Belum ada data pendidikan</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
            // Grafik Berat Badan
            const beratLabels = <?php echo json_encode($labels_kesehatan); ?>;
            const beratData = <?php echo json_encode(array_column($kesehatan_data, 'berat_badan')); ?>;
            
            if (beratLabels.length > 0) {
                new Chart(document.getElementById('beratChart'), {
                    type: 'line',
                    data: { labels: beratLabels, datasets: [{ label: 'Berat Badan (kg)', data: beratData, borderColor: '#4caf50', backgroundColor: 'rgba(76,175,80,0.1)', fill: true, tension: 0.3 }] },
                    options: { responsive: true, maintainAspectRatio: true }
                });
                
                new Chart(document.getElementById('tinggiChart'), {
                    type: 'line',
                    data: { labels: beratLabels, datasets: [{ label: 'Tinggi Badan (cm)', data: <?php echo json_encode(array_column($kesehatan_data, 'tinggi_badan')); ?>, borderColor: '#2196f3', backgroundColor: 'rgba(33,150,243,0.1)', fill: true, tension: 0.3 }] },
                    options: { responsive: true, maintainAspectRatio: true }
                });
            }
            
            <?php if (count($pendidikan) > 0): ?>
            new Chart(document.getElementById('pendidikanChart'), {
                type: 'line',
                data: { labels: <?php echo json_encode($pendidikan_labels); ?>, datasets: [{ label: 'Rata-rata Nilai', data: <?php echo json_encode($pendidikan_nilai); ?>, borderColor: '#ff9800', backgroundColor: 'rgba(255,152,0,0.1)', fill: true, tension: 0.3 }] },
                options: { responsive: true, maintainAspectRatio: true, scales: { y: { min: 0, max: 100 } } }
            });
            <?php endif; ?>
        </script>
        
        <?php else: ?>
            <div class="content-card" style="text-align: center; padding: 40px;">
                <i class="fas fa-child" style="font-size: 48px; color: #ccc;"></i>
                <p style="margin-top: 15px;">Silakan pilih anak asuh terlebih dahulu</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- MODAL EDIT KESEHATAN -->
    <div id="editKesehatanModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit Data Kesehatan</h3><span class="close-modal" onclick="closeModal('editKesehatanModal')">&times;</span></div>
            <form method="POST">
                <input type="hidden" name="id" id="edit_kesehatan_id">
                <input type="hidden" name="anak_id" id="edit_kesehatan_anak_id">
                <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal" id="edit_tanggal" required></div>
                <div class="form-group"><label>Berat Badan (kg)</label><input type="number" step="0.1" name="berat_badan" id="edit_berat" required></div>
                <div class="form-group"><label>Tinggi Badan (cm)</label><input type="number" step="0.1" name="tinggi_badan" id="edit_tinggi" required></div>
                <div class="form-group"><label>Keterangan</label><textarea name="keterangan" id="edit_keterangan" rows="2"></textarea></div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('editKesehatanModal')">Batal</button><button type="submit" name="edit_kesehatan" class="btn-save">Simpan</button></div>
            </form>
        </div>
    </div>
    
    <!-- MODAL EDIT PENDIDIKAN -->
    <div id="editPendidikanModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit Data Pendidikan</h3><span class="close-modal" onclick="closeModal('editPendidikanModal')">&times;</span></div>
            <form method="POST">
                <input type="hidden" name="id" id="edit_pendidikan_id">
                <input type="hidden" name="anak_id" id="edit_pendidikan_anak_id">
                <div class="form-group"><label>Semester</label><select name="semester" id="edit_semester"><option value="Ganjil">Ganjil</option><option value="Genap">Genap</option></select></div>
                <div class="form-group"><label>Tahun Ajaran</label><input type="text" name="tahun_ajaran" id="edit_tahun_ajaran" required></div>
                <div class="form-group"><label>Rata-rata Nilai</label><input type="number" step="0.1" name="rata_rata" id="edit_rata_rata" required></div>
                <div class="form-group"><label>Predikat</label><select name="predikat" id="edit_predikat"><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select></div>
                <div class="form-group"><label>Prestasi</label><textarea name="prestasi" id="edit_prestasi" rows="2"></textarea></div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('editPendidikanModal')">Batal</button><button type="submit" name="edit_pendidikan" class="btn-save">Simpan</button></div>
            </form>
        </div>
    </div>
    
    <script>
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function editKesehatan(id, anakId) {
            fetch('get_perkembangan.php?id=' + id + '&type=kesehatan')
                .then(r => r.json())
                .then(d => {
                    document.getElementById('edit_kesehatan_id').value = d.id;
                    document.getElementById('edit_kesehatan_anak_id').value = anakId;
                    document.getElementById('edit_tanggal').value = d.tanggal;
                    document.getElementById('edit_berat').value = d.berat_badan;
                    document.getElementById('edit_tinggi').value = d.tinggi_badan;
                    document.getElementById('edit_keterangan').value = d.keterangan || '';
                    document.getElementById('editKesehatanModal').classList.add('show');
                });
        }
        
        function editPendidikan(id, anakId) {
            fetch('get_perkembangan.php?id=' + id + '&type=pendidikan')
                .then(r => r.json())
                .then(d => {
                    document.getElementById('edit_pendidikan_id').value = d.id;
                    document.getElementById('edit_pendidikan_anak_id').value = anakId;
                    document.getElementById('edit_semester').value = d.semester;
                    document.getElementById('edit_tahun_ajaran').value = d.tahun_ajaran;
                    document.getElementById('edit_rata_rata').value = d.rata_rata;
                    document.getElementById('edit_predikat').value = d.predikat;
                    document.getElementById('edit_prestasi').value = d.prestasi || '';
                    document.getElementById('editPendidikanModal').classList.add('show');
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