<?php
// ======================================================
// FILE: donatur/edit_donasi_program.php
// HALAMAN EDIT DONASI PROGRAM UNTUK DONATUR
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('donatur');

$currentUser = getCurrentUser();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Ambil data donasi program
$sql = "SELECT dp.*, p.nama_program 
        FROM donasi_program dp 
        JOIN program_donasi p ON dp.program_id = p.id 
        WHERE dp.id = $id AND dp.user_id = " . $currentUser['id'];
$donasi = query($sql);

if (count($donasi) == 0) {
    header('Location: histori.php');
    exit();
}
$donasi = $donasi[0];

// Cek status (hanya pending/failed yang bisa diedit)
if (!in_array($donasi['status'], ['pending', 'failed'])) {
    $_SESSION['error'] = "Donasi program yang sudah sukses tidak bisa diedit!";
    header('Location: histori.php');
    exit();
}

// Ambil program untuk dropdown
$programs = query("SELECT id, nama_program FROM program_donasi WHERE status = 'aktif' ORDER BY nama_program ASC");

// ======================================================
// FUNGSI UPLOAD BUKTI
// ======================================================
function uploadBukti($existing_file = null) {
    if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            return ['success' => false, 'message' => 'Format file tidak diizinkan'];
        }
        
        $filename = 'donasi_program_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target = '../assets/uploads/bukti_transfer/' . $filename;
        
        if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $target)) {
            if ($existing_file && file_exists('../assets/uploads/bukti_transfer/' . $existing_file)) {
                unlink('../assets/uploads/bukti_transfer/' . $existing_file);
            }
            return ['success' => true, 'filename' => $filename];
        }
    }
    return ['success' => true, 'filename' => $existing_file];
}

// ======================================================
// PROSES UPDATE - PERBAIKAN
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $program_id = (int)$_POST['program_id'];
    $nominal = (float)$_POST['nominal'];
    $pesan = mysqli_real_escape_string($conn, $_POST['pesan']);
    $is_anonim = isset($_POST['is_anonim']) ? (int)$_POST['is_anonim'] : 0;
    
    // Upload bukti baru
    $upload = uploadBukti($donasi['bukti_transfer']);
    $bukti_transfer = $upload['success'] ? $upload['filename'] : $donasi['bukti_transfer'];
    
    // ======================================================
    // UPDATE + RESET STATUS KE PENDING (TANPA KOMENTAR)
    // ======================================================
    $sql_update = "UPDATE donasi_program SET 
                   program_id = $program_id,
                   nominal = $nominal,
                   pesan = '$pesan',
                   is_anonim = $is_anonim,
                   bukti_transfer = '$bukti_transfer',
                   status = 'pending',
                   verified_by = NULL,
                   verified_at = NULL,
                   catatan_verifikasi = NULL
                   WHERE id = $id AND user_id = " . $currentUser['id'];
    
    if (mysqli_query($conn, $sql_update)) {
        logActivity($currentUser['id'], "Mengedit donasi program ID: $id (status direset ke pending)");
        $_SESSION['success'] = "Donasi program berhasil diupdate! Menunggu verifikasi ulang admin.";
        header('Location: histori.php');
        exit();
    } else {
        $error = "Gagal mengupdate: " . mysqli_error($conn);
    }
}

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Donasi Program - Panti Asuhan Al-Muthi</title>
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
            overflow-y: auto;
            z-index: 100;
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
        .sidebar-logo { width: 45px; height: 45px; object-fit: contain; }
        .sidebar-header h3 { font-size: 16px; margin-bottom: 3px; }
        .sidebar-header p { font-size: 11px; opacity: 0.7; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            color: rgba(255,255,255,0.8);
            transition: all 0.3s;
        }
        .menu-item:hover, .menu-item.active {
            background: rgba(80,200,120,0.3);
            border-left: 4px solid #50c878;
        }
        .menu-item i { width: 24px; font-size: 18px; }
        .menu-item span { font-size: 14px; }
        
        .main-content { margin-left: 280px; padding: 20px; min-height: 100vh; }
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
        .page-title h2 { font-size: 20px; color: #333; }
        .page-title p { font-size: 13px; color: #888; margin-top: 5px; }
        .profile-dropdown { position: relative; }
        .profile-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #50c878, #2e8b57);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 20px;
            color: white;
        }
        .dropdown-menu {
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            border-radius: 12px;
            width: 200px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
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
            border-bottom: 1px solid #f0f0f0;
        }
        
        .content-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 700px;
            margin: 0 auto;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #50c878;
        }
        .card-header h2 { font-size: 20px; color: #333; }
        
        .info-donasi {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .info-donasi .label { font-size: 12px; color: #888; }
        .info-donasi .value { font-size: 14px; font-weight: 600; color: #333; }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        .status-pending { background: #fff3e0; color: #ff9800; }
        .status-failed { background: #ffebee; color: #f44336; }
        .status-success { background: #e8f5e9; color: #4caf50; }
        
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 13px;
            color: #555;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #50c878;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .current-image {
            text-align: center;
            margin: 10px 0;
        }
        .current-image img {
            max-width: 150px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-save {
            background: #50c878;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            flex: 1;
        }
        .btn-save:hover { background: #2e8b57; }
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            flex: 1;
        }
        .btn-cancel:hover { background: #5a6268; }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        small { color: #888; font-size: 11px; display: block; margin-top: 5px; }
        .radio-group { display: flex; gap: 20px; margin: 10px 0; }
        .radio-group label { display: flex; align-items: center; gap: 5px; font-weight: normal; cursor: pointer; }
        
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
            <div class="menu-item" onclick="location.href='donasi.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Sekarang</span></div>
            <div class="menu-item active" onclick="location.href='histori.php'"><i class="fas fa-history"></i><span>Riwayat Donasi</span></div>
            <div class="menu-item" onclick="location.href='laporan_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Laporan Pengeluaran</span></div>
            <div class="menu-item" onclick="location.href='doa_saya.php'"><i class="fas fa-pray"></i><span>Laporan Khusus Do'a</span></div>
            <div class="menu-item" onclick="location.href='perkembangan.php'"><i class="fas fa-seedling"></i><span>Perkembangan Anak</span></div>
            <div class="menu-item" onclick="location.href='laporan.php'"><i class="fas fa-chart-line"></i><span>Laporan</span></div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div class="page-title"><h2>Edit Donasi Program</h2><p>Perbarui data donasi program Anda</p></div>
            <div class="profile-dropdown">
                <div class="profile-icon"><i class="fas fa-cog"></i></div>
                <div class="dropdown-menu">
                    <a href="profil.php"><i class="fas fa-user-circle"></i> Profil</a>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fas fa-edit"></i> Edit Donasi Program #<?php echo $donasi['id']; ?></h2>
                <span style="font-size:12px; color:#888;">ID: <?php echo $donasi['id']; ?></span>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="info-donasi">
                <div>
                    <div class="label">Tanggal</div>
                    <div class="value"><?php echo date('d/m/Y H:i', strtotime($donasi['created_at'])); ?></div>
                </div>
                <div>
                    <div class="label">Status</div>
                    <div class="value">
                        <span class="status-badge status-<?php echo $donasi['status']; ?>">
                            <?php echo ucfirst($donasi['status']); ?>
                        </span>
                    </div>
                </div>
                <div>
                    <div class="label">Program</div>
                    <div class="value"><?php echo $donasi['nama_program']; ?></div>
                </div>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label>Program Donasi</label>
                        <select name="program_id" required>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $donasi['program_id'] ? 'selected' : ''; ?>><?php echo $p['nama_program']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nominal (Rp)</label>
                        <input type="number" name="nominal" value="<?php echo $donasi['nominal']; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Tampilkan Nama di Publik</label>
                    <div class="radio-group">
                        <label><input type="radio" name="is_anonim" value="0" <?php echo $donasi['is_anonim'] == 0 ? 'checked' : ''; ?>> ✅ Ya, tampilkan nama asli</label>
                        <label><input type="radio" name="is_anonim" value="1" <?php echo $donasi['is_anonim'] == 1 ? 'checked' : ''; ?>> 🙈 Tidak, tampilkan sebagai "Anonim"</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Pesan (Opsional)</label>
                    <textarea name="pesan" rows="2"><?php echo $donasi['pesan']; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Ganti Bukti Transfer</label>
                    <input type="file" name="bukti_transfer" accept="image/*,application/pdf">
                    <small>Format: JPG, PNG, PDF (Kosongkan jika tidak ingin mengganti)</small>
                </div>
                
                <?php if ($donasi['bukti_transfer']): ?>
                <div class="current-image">
                    <label style="font-size:12px; color:#888;">Bukti Transfer Saat Ini</label><br>
                    <img src="../assets/uploads/bukti_transfer/<?php echo $donasi['bukti_transfer']; ?>" onclick="window.open(this.src)">
                </div>
                <?php endif; ?>
                
                <div class="btn-group">
                    <a href="histori.php" class="btn-cancel"><i class="fas fa-times"></i> Batal</a>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>