<?php
// ======================================================
// FILE: program_detail.php
// HALAMAN DETAIL PROGRAM + FORM DONASI
// DENGAN 2 TAMPILAN: PUBLIK (BG HIJAU) & DONATUR LOGIN (SIDEBAR)
// PERBAIKAN: No WhatsApp untuk donatur login READONLY
// ======================================================

require_once 'config/database.php';
require_once 'config/session.php';

// Cek apakah user login sebagai donatur
$is_donatur_login = false;
$currentUser = null;
if (isLoggedIn() && getUserRole() == 'donatur') {
    $is_donatur_login = true;
    $currentUser = getCurrentUser();
}

$program_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Ambil data program dengan jumlah donatur & terkumpul terbaru
$sql_program = "SELECT *, 
                (SELECT COUNT(*) FROM donasi_program WHERE program_id = $program_id AND status = 'success') as jumlah_donatur,
                (SELECT SUM(nominal) FROM donasi_program WHERE program_id = $program_id AND status = 'success') as total_terkumpul
                FROM program_donasi WHERE id = $program_id AND status = 'aktif'";
$program = query($sql_program);

if (count($program) == 0) {
    header("Location: " . ($is_donatur_login ? "donatur/dashboard.php" : "login.php"));
    exit();
}
$program = $program[0];

// Gunakan total_terkumpul dari query, jika null set 0
$terkumpul = $program['total_terkumpul'] ?? 0;
$persen = ($program['target_nominal'] > 0) ? round(($terkumpul / $program['target_nominal']) * 100) : 0;
$persen = min($persen, 100);

// TOTAL TERSALURKAN
$sql_tersalurkan = "SELECT SUM(jumlah) as total FROM penerima_manfaat WHERE program_id = $program_id";
$result_tersalurkan = mysqli_query($conn, $sql_tersalurkan);
$total_tersalurkan = mysqli_fetch_assoc($result_tersalurkan)['total'] ?? 0;

// Proses Donasi Program - PERBAIKAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donasi_program'])) {
    $nama_donatur = mysqli_real_escape_string($conn, $_POST['nama_donatur']);
    // SETELAH PERBAIKAN
$no_whatsapp = isset($_POST['no_whatsapp']) ? mysqli_real_escape_string($conn, $_POST['no_whatsapp']) : '';
    $is_anonim = isset($_POST['is_anonim']) ? (int)$_POST['is_anonim'] : 0;
    $nominal = (float)$_POST['nominal'];
    $pesan = mysqli_real_escape_string($conn, $_POST['pesan']);
    
    // ======================================================
    // VALIDASI: No WhatsApp WAJIB diisi (kecuali user login)
    // ======================================================
    if (empty($no_whatsapp) && !$is_donatur_login) {
        $error = "Nomor WhatsApp wajib diisi untuk konfirmasi donasi!";
    } else {
        // Upload bukti transfer
        $bukti_transfer = null;
        if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
            
            if (in_array(strtolower($ext), $allowed)) {
                $filename = 'donasi_program_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $target = 'assets/uploads/bukti_transfer/' . $filename;
                if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $target)) {
                    $bukti_transfer = $filename;
                }
            }
        }
        
        $nama_donatur_final = ($is_anonim == 1) ? 'Hamba Allah' : ($nama_donatur ?: 'Hamba Allah');
        
        // Jika user login
        if ($is_donatur_login) {
            $user_id = $_SESSION['user_id'];
            
            $sql = "INSERT INTO donasi_program (program_id, user_id, nama_donatur, is_anonim, nominal, pesan, bukti_transfer, status) 
                    VALUES ($program_id, $user_id, '$nama_donatur_final', $is_anonim, $nominal, '$pesan', '$bukti_transfer', 'pending')";
            
        } else {
            // ======================================================
            // Donatur tidak login - CEK BERDASARKAN NO WHATSAPP
            // ======================================================
            $user_check = mysqli_query($conn, "SELECT id FROM users WHERE no_whatsapp = '$no_whatsapp' AND role_id = 3");
            
            if (mysqli_num_rows($user_check) > 0) {
                // User ditemukan, pakai user yang sudah ada
                $user_data = mysqli_fetch_assoc($user_check);
                $user_id = $user_data['id'];
            } else {
                // ======================================================
                // BUAT USER BARU dengan no_whatsapp
                // ======================================================
                $username = strtolower(str_replace(' ', '_', $nama_donatur_final)) . '_' . rand(100, 999);
                $hash_password = password_hash('donasi123', PASSWORD_DEFAULT);
                $foto_default = 'default-user.png';
                
                mysqli_query($conn, "INSERT INTO users (username, password, nama_lengkap, role_id, is_active, foto_profil, no_whatsapp) 
                                     VALUES ('$username', '$hash_password', '$nama_donatur_final', 3, 1, '$foto_default', '$no_whatsapp')");
                $user_id = mysqli_insert_id($conn);
            }
            
            // ======================================================
            // SIMPAN DONASI PROGRAM
            // ======================================================
            $sql = "INSERT INTO donasi_program (program_id, user_id, nama_donatur, is_anonim, nominal, pesan, bukti_transfer, status) 
                    VALUES ($program_id, $user_id, '$nama_donatur_final', $is_anonim, $nominal, '$pesan', '$bukti_transfer', 'pending')";
        }
        
        if (mysqli_query($conn, $sql)) {
            $success = "Donasi berhasil dikirim! Menunggu verifikasi admin.";
        } else {
            $error = "Gagal mengirim donasi: " . mysqli_error($conn);
        }
    }
}

// Ambil donatur terbaru (hanya yang status success)
$sql_donatur = "SELECT * FROM donasi_program WHERE program_id = $program_id AND status = 'success' ORDER BY created_at DESC LIMIT 10";
$donatur_list = query($sql_donatur);

// Ambil penerima manfaat
$sql_penerima = "SELECT * FROM penerima_manfaat WHERE program_id = $program_id ORDER BY tanggal_penyaluran DESC LIMIT 5";
$penerima_list = query($sql_penerima);
$total_penerima = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM penerima_manfaat WHERE program_id = $program_id"))['total'];

// Ambil galeri program
$sql_galeri = "SELECT * FROM galeri_program WHERE program_id = $program_id ORDER BY created_at DESC LIMIT 5";
$galeri_list = query($sql_galeri);
$total_galeri = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM galeri_program WHERE program_id = $program_id"))['total'];
?>

<?php if ($is_donatur_login): ?>
    <!-- ====================================================== -->
    <!-- TAMPILAN UNTUK DONATUR YANG LOGIN (DENGAN SIDEBAR)     -->
    <!-- ====================================================== -->
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $program['nama_program']; ?> - Panti Asuhan Al-Muthi</title>
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
            
            /* MAIN CONTENT - geser ke kanan karena ada sidebar */
            .main-content-donatur { margin-left: 280px; padding: 20px; min-height: 100vh; }
            
            /* TOPBAR */
            .topbar { background: white; border-radius: 15px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
            .page-title h2 { font-size: 20px; color: #333; }
            .page-title p { font-size: 13px; color: #888; margin-top: 5px; }
            .profile-dropdown { position: relative; }
            .profile-icon { width: 45px; height: 45px; background: linear-gradient(135deg, #50c878, #2e8b57); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; color: white; }
            .dropdown-menu { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 1000; }
            .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; }
            .dropdown-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
            
            /* Container untuk konten (sama seperti desain asli) */
            .container-donatur { max-width: 1200px; margin: 0 auto; }
            
            /* PROGRAM HEADER - SAMA PERSIS DENGAN DESAIN ASLI */
            .program-header { background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .program-title { font-size: 24px; color: #1a3a2a; margin-bottom: 10px; }
            .program-desc { color: #666; margin-bottom: 20px; line-height: 1.6; }
            .program-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
            .stat-box { background: #f8f9fa; border-radius: 15px; padding: 15px; text-align: center; }
            .stat-box h3 { font-size: 24px; color: #50c878; }
            .stat-box p { font-size: 12px; color: #888; }
            .progress-bar { background: #e0e0e0; border-radius: 10px; height: 10px; overflow: hidden; }
            .progress-fill { background: #50c878; height: 100%; width: 0%; transition: width 0.5s ease; }
            
            /* MAIN CONTENT FLEX */
            .main-content { display: flex; gap: 30px; flex-wrap: wrap; }
            .donasi-form { flex: 1; min-width: 300px; }
            .donatur-list { flex: 1; min-width: 300px; }
            
            .card { background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .card h3 { margin-bottom: 20px; color: #1a3a2a; border-left: 4px solid #50c878; padding-left: 15px; }
            
            .form-group { margin-bottom: 15px; }
            .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 13px; }
            .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 10px; }
            .radio-group { display: flex; gap: 20px; margin: 10px 0; }
            .radio-group label { display: flex; align-items: center; gap: 5px; font-weight: normal; cursor: pointer; }
            .btn-donasi { background: #50c878; color: white; width: 100%; padding: 12px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; }
            .btn-donasi:hover { background: #2e8b57; }
            
            .donatur-item { padding: 12px; border-bottom: 1px solid #eee; }
            .donatur-nama { font-weight: 600; }
            .donatur-nominal { color: #50c878; font-weight: 600; float: right; }
            .donatur-pesan { font-size: 12px; color: #888; margin-top: 5px; }
            
            .penerima-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
            .penerima-card { background: #f8f9fa; border-radius: 15px; padding: 15px; text-align: center; }
            .penerima-card img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; }
            .penerima-card h4 { font-size: 14px; }
            .penerima-card p { font-size: 11px; color: #888; }
            
            .galeri-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
            .galeri-item { background: #f8f9fa; border-radius: 10px; overflow: hidden; cursor: pointer; }
            .galeri-item img, .galeri-item video { width: 100%; height: 150px; object-fit: cover; }
            
            .alert { padding: 12px; border-radius: 10px; margin-bottom: 15px; }
            .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
            .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
            
            .qris-info { text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
            .qris-info img { width: 120px; border-radius: 15px; }
            .card-header-flex {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                flex-wrap: wrap;
                gap: 10px;
            }
            .card-header-flex h3 {
                margin-bottom: 0;
                border-left: 4px solid #50c878;
                padding-left: 15px;
            }
            .btn-lihat-semua {
                background: transparent;
                color: #50c878;
                padding: 5px 15px;
                border-radius: 20px;
                text-decoration: none;
                font-size: 12px;
                border: 1px solid #50c878;
                transition: 0.3s;
            }
            .btn-lihat-semua:hover {
                background: #50c878;
                color: white;
            }
           .btn-back { background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
            
            @media (max-width: 768px) { 
                .sidebar { left: -280px; } 
                .main-content-donatur { margin-left: 0; } 
                .main-content { flex-direction: column; } 
                .program-stats { grid-template-columns: 1fr; } 
            }
        </style>
    </head>
    <body>
        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="assets/image/almuthi.png" alt="Logo" class="sidebar-logo" onerror="this.style.display='none'">
                <div><h3>Panti Asuhan</h3><p>Al-Muthi</p></div>
            </div>
            <div class="sidebar-menu">
                <div class="menu-item" onclick="location.href='donatur/dashboard.php'"><i class="fas fa-tachometer-alt"></i><span>Beranda</span></div>
                <div class="menu-item" onclick="location.href='donatur/donasi.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Sekarang</span></div>
                <div class="menu-item active" onclick="location.href='semua_program.php'"><i class="fas fa-chalkboard-user"></i><span>Program Donasi</span></div>
                <div class="menu-item" onclick="location.href='donatur/histori.php'"><i class="fas fa-history"></i><span>Riwayat Donasi</span></div>
                <div class="menu-item" onclick="location.href='donatur/laporan_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Laporan Pengeluaran</span></div>
                <div class="menu-item" onclick="location.href='donatur/doa_saya.php'"><i class="fas fa-pray"></i><span>Laporan Khusus Do'a</span></div>
                <div class="menu-item" onclick="location.href='donatur/perkembangan.php'"><i class="fas fa-seedling"></i><span>Perkembangan Anak</span></div>
                <div class="menu-item" onclick="location.href='donatur/laporan.php'"><i class="fas fa-chart-line"></i><span>Laporan</span></div>
            </div>
        </div>
        
        <!-- MAIN CONTENT DONATUR -->
        <div class="main-content-donatur">
            <div class="topbar">
                <div class="page-title">
                    <h2><?php echo $program['nama_program']; ?></h2>
                    <p>Detail program donasi</p>
                </div>
                <div class="profile-dropdown">
                    <div class="profile-icon"><i class="fas fa-user"></i></div>
                    <div class="dropdown-menu">
                        <a href="donatur/profil.php"><i class="fas fa-user-circle"></i> Profil</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
            
            <div class="container-donatur">
                <!-- PROGRAM HEADER -->
                <div class="program-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 10px;">
                        <h1 class="program-title" style="margin-bottom: 0;"><?php echo $program['nama_program']; ?></h1>
                        <a href="semua_program.php" class="btn-back" style="margin-bottom: 0; white-space: nowrap; background: #6c757d; color: white; padding: 8px 20px; border-radius: 25px; text-decoration: none; font-size: 14px;"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </div>
                    <p class="program-desc"><?php echo nl2br(htmlspecialchars($program['deskripsi'])); ?></p>
                    
                    <div class="program-stats">
                        <div class="stat-box"><h3>Rp <?php echo number_format($terkumpul, 0, ',', '.'); ?></h3><p>Terkumpul</p></div>
                        <div class="stat-box"><h3>Rp <?php echo number_format($program['target_nominal'], 0, ',', '.'); ?></h3><p>Target</p></div>
                        <div class="stat-box"><h3><?php echo $program['jumlah_donatur']; ?></h3><p>Donatur</p></div>
                        <div class="stat-box"><h3>Rp <?php echo number_format($total_tersalurkan, 0, ',', '.'); ?></h3><p>Tersalurkan</p></div>
                    </div>
                    
                    <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $persen; ?>%;"></div></div>
                    <div style="text-align: right; font-size: 12px; margin-top: 5px;"><?php echo $persen; ?>% tercapai</div>
                </div>
                
                <!-- MAIN CONTENT -->
                <div class="main-content">
                    
                    <!-- FORM DONASI -->
                    <div class="donasi-form">
                        <div class="card">
                            <h3><i class="fas fa-hand-holding-heart"></i> Donasi Sekarang</h3>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php endif; ?>
                            <?php if ($error): ?>
                                <div class="alert alert-error"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label>Nominal Donasi (Rp)</label>
                                    <input type="number" name="nominal" placeholder="Masukkan nominal" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Tampilkan Nama di Publik</label>
                                    <div class="radio-group">
                                        <label><input type="radio" name="is_anonim" value="0" checked> ✅ Ya, tampilkan nama asli</label>
                                        <label><input type="radio" name="is_anonim" value="1"> 🙈 Tidak, tampilkan sebagai "Anonim"</label>
                                    </div>
                                </div>
                                
                                <div class="form-group" id="nama_field">
                                    <label>Nama (jika ditampilkan)</label>
                                    <input type="text" name="nama_donatur" placeholder="Masukkan nama Anda" value="<?php echo htmlspecialchars($currentUser['nama_lengkap']); ?>">
                                </div>
                                
                                <!-- ====================================================== -->
                                <!-- UNTUK DONATUR LOGIN: NO WHATSAPP READONLY              -->
                                <!-- ====================================================== -->
                                <div class="form-group">
                                    <label>No. WhatsApp Terdaftar</label>
                                    <input type="text" value="<?php echo htmlspecialchars($currentUser['no_whatsapp'] ?? '-'); ?>" disabled style="background:#f5f5f5;">
                                    <small style="color:#888;">Nomor WhatsApp yang terdaftar di akun Anda</small>
                                </div>
                                <input type="hidden" name="no_whatsapp" value="<?php echo htmlspecialchars($currentUser['no_whatsapp'] ?? ''); ?>">
                                
                                <div class="form-group">
                                    <label>Pesan (Opsional)</label>
                                    <textarea name="pesan" rows="2" placeholder="Tulis pesan dukungan..."></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Upload Bukti Transfer</label>
                                    <input type="file" name="bukti_transfer" accept="image/*,application/pdf" required>
                                    <small style="color:#888;">Format: JPG, PNG, PDF (Max 2MB)</small>
                                </div>
                                
                                <div class="qris-info">
                                    <p style="font-size: 12px; color: #888; margin-bottom: 10px;">
                                        <i class="fas fa-qrcode"></i> Scan QRIS untuk donasi cepat
                                    </p>
                                    <img src="assets/image/qris.jpeg" alt="QRIS" onerror="this.src='assets/image/almuthi.png'">
                                    <p style="font-size: 11px; color: #888; margin-top: 10px;">
                                        Bank BRI: 0821-3191-3839-9383-92<br>
                                        a.n Yayasan Sosial Bina Umat Al-Muthi
                                    </p>
                                </div>
                                
                                <button type="submit" name="donasi_program" class="btn-donasi" style="margin-top: 15px;">
                                    <i class="fas fa-paper-plane"></i> Kirim Donasi
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- DONATUR TERBARU -->
                    <div class="donatur-list">
                        <div class="card">
                            <h3><i class="fas fa-users"></i> Donatur Terbaru</h3>
                            <?php if (count($donatur_list) > 0): ?>
                                <?php foreach ($donatur_list as $d): ?>
                                    <div class="donatur-item">
                                        <span class="donatur-nama">
                                            <?php if ($d['is_anonim']): ?>
                                                🙈 Anonim
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($d['nama_donatur']); ?>
                                            <?php endif; ?>
                                        </span>
                                        <span class="donatur-nominal">Rp <?php echo number_format($d['nominal'], 0, ',', '.'); ?></span>
                                        <?php if ($d['pesan']): ?>
                                            <div class="donatur-pesan">"<?php echo htmlspecialchars($d['pesan']); ?>"</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <div style="text-align: center; margin-top: 15px;">
                                    <a href="semua_donatur.php?program_id=<?php echo $program_id; ?>" class="btn-lihat-semua" style="display: inline-block; background: #50c878; color: white; padding: 8px 20px; border-radius: 25px; text-decoration: none; font-size: 13px;">
                                        <i class="fas fa-arrow-right"></i> Lihat Semua Donatur (<?php echo $program['jumlah_donatur']; ?>)
                                    </a>
                                </div>
                            <?php else: ?>
                                <p style="text-align: center; color: #888;">Belum ada donatur</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- PENERIMA MANFAAT -->
                <?php if (count($penerima_list) > 0): ?>
                <div class="card">
                    <div class="card-header-flex">
                        <h3><i class="fas fa-users"></i> Penerima Manfaat</h3>
                        <?php if ($total_penerima > 5): ?>
                            <a href="semua_penerima.php?program_id=<?php echo $program_id; ?>" class="btn-lihat-semua">Lihat Semua (<?php echo $total_penerima; ?>)</a>
                        <?php endif; ?>
                    </div>
                    <div class="penerima-grid">
                        <?php foreach ($penerima_list as $p): ?>
                            <div class="penerima-card">
                                <?php if ($p['foto']): ?>
                                    <img src="assets/uploads/penerima/<?php echo $p['foto']; ?>" alt="<?php echo $p['nama_penerima']; ?>">
                                <?php else: ?>
                                    <i class="fas fa-user-circle" style="font-size: 60px; color: #ccc;"></i>
                                <?php endif; ?>
                                <h4><?php echo htmlspecialchars($p['nama_penerima']); ?></h4>
                                <p><?php echo htmlspecialchars($p['jenis_bantuan']); ?></p>
                                <p><strong>Rp <?php echo number_format($p['jumlah'], 0, ',', '.'); ?></strong></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- GALERI PROGRAM -->
                <?php if (count($galeri_list) > 0): ?>
                <div class="card">
                    <div class="card-header-flex">
                        <h3><i class="fas fa-images"></i> Galeri Kegiatan</h3>
                        <?php if ($total_galeri > 5): ?>
                            <a href="semua_galeri_program.php?program_id=<?php echo $program_id; ?>" class="btn-lihat-semua">Lihat Semua (<?php echo $total_galeri; ?>)</a>
                        <?php endif; ?>
                    </div>
                    <div class="galeri-grid">
                        <?php foreach ($galeri_list as $g): ?>
                            <div class="galeri-item" onclick="window.open('assets/uploads/galeri_program/<?php echo $g['file_path']; ?>', '_blank')">
                                <?php if ($g['tipe'] == 'foto'): ?>
                                    <img src="assets/uploads/galeri_program/<?php echo $g['file_path']; ?>" alt="<?php echo $g['judul']; ?>">
                                <?php else: ?>
                                    <video src="assets/uploads/galeri_program/<?php echo $g['file_path']; ?>"></video>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
            document.querySelectorAll('input[name="is_anonim"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const namaField = document.getElementById('nama_field');
                    if (this.value == '1') {
                        namaField.style.display = 'none';
                    } else {
                        namaField.style.display = 'block';
                    }
                });
            });
        </script>
    </body>
    </html>

<?php else: ?>
    <!-- ====================================================== -->
    <!-- TAMPILAN PUBLIK (TANPA SIDEBAR, BACKGROUND HIJAU)      -->
    <!-- ====================================================== -->
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $program['nama_program']; ?> - Panti Asuhan Al-Muthi</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%); min-height: 100vh; padding: 40px 20px; }
            
            .container { max-width: 1200px; margin: 0 auto; }
            .btn-back { display: inline-block; background: #6c757d; color: white; padding: 10px 20px; border-radius: 25px; text-decoration: none; margin-bottom: 20px; font-size: 14px; }
            .btn-back:hover { background: #5a6268; }
            
            .program-header { background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .program-title { font-size: 24px; color: #1a3a2a; margin-bottom: 10px; }
            .program-desc { color: #666; margin-bottom: 20px; line-height: 1.6; }
            .program-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
            .stat-box { background: #f8f9fa; border-radius: 15px; padding: 15px; text-align: center; }
            .stat-box h3 { font-size: 24px; color: #50c878; }
            .stat-box p { font-size: 12px; color: #888; }
            .progress-bar { background: #e0e0e0; border-radius: 10px; height: 10px; overflow: hidden; }
            .progress-fill { background: #50c878; height: 100%; width: 0%; transition: width 0.5s ease; }
            
            .main-content { display: flex; gap: 30px; flex-wrap: wrap; }
            .donasi-form { flex: 1; min-width: 300px; }
            .donatur-list { flex: 1; min-width: 300px; }
            
            .card { background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .card h3 { margin-bottom: 20px; color: #1a3a2a; border-left: 4px solid #50c878; padding-left: 15px; }
            
            .form-group { margin-bottom: 15px; }
            .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 13px; }
            .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 10px; }
            .radio-group { display: flex; gap: 20px; margin: 10px 0; }
            .radio-group label { display: flex; align-items: center; gap: 5px; font-weight: normal; cursor: pointer; }
            .btn-donasi { background: #50c878; color: white; width: 100%; padding: 12px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; }
            .btn-donasi:hover { background: #2e8b57; }
            
            .donatur-item { padding: 12px; border-bottom: 1px solid #eee; }
            .donatur-nama { font-weight: 600; }
            .donatur-nominal { color: #50c878; font-weight: 600; float: right; }
            .donatur-pesan { font-size: 12px; color: #888; margin-top: 5px; }
            
            .penerima-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
            .penerima-card { background: #f8f9fa; border-radius: 15px; padding: 15px; text-align: center; }
            .penerima-card img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; }
            .penerima-card h4 { font-size: 14px; }
            .penerima-card p { font-size: 11px; color: #888; }
            
            .galeri-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
            .galeri-item { background: #f8f9fa; border-radius: 10px; overflow: hidden; cursor: pointer; }
            .galeri-item img, .galeri-item video { width: 100%; height: 150px; object-fit: cover; }
            
            .alert { padding: 12px; border-radius: 10px; margin-bottom: 15px; }
            .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
            .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
            
            .qris-info { text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
            .qris-info img { width: 120px; border-radius: 15px; }
            .card-header-flex {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                flex-wrap: wrap;
                gap: 10px;
            }
            .card-header-flex h3 {
                margin-bottom: 0;
                border-left: 4px solid #50c878;
                padding-left: 15px;
            }
            .btn-lihat-semua {
                background: transparent;
                color: #50c878;
                padding: 5px 15px;
                border-radius: 20px;
                text-decoration: none;
                font-size: 12px;
                border: 1px solid #50c878;
                transition: 0.3s;
            }
            .btn-lihat-semua:hover {
                background: #50c878;
                color: white;
            }
            @media (max-width: 768px) { .main-content { flex-direction: column; } .program-stats { grid-template-columns: 1fr; } }
        </style>
    </head>
    <body>
    <div class="container">
        <a href="semua_program.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
        
        <div class="program-header">
            <h1 class="program-title"><?php echo $program['nama_program']; ?></h1>
            <p class="program-desc"><?php echo nl2br(htmlspecialchars($program['deskripsi'])); ?></p>
            
            <div class="program-stats">
                <div class="stat-box"><h3>Rp <?php echo number_format($terkumpul, 0, ',', '.'); ?></h3><p>Terkumpul</p></div>
                <div class="stat-box"><h3>Rp <?php echo number_format($program['target_nominal'], 0, ',', '.'); ?></h3><p>Target</p></div>
                <div class="stat-box"><h3><?php echo $program['jumlah_donatur']; ?></h3><p>Donatur</p></div>
                <div class="stat-box"><h3>Rp <?php echo number_format($total_tersalurkan, 0, ',', '.'); ?></h3><p>Tersalurkan</p></div>
            </div>
            
            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $persen; ?>%;"></div></div>
            <div style="text-align: right; font-size: 12px; margin-top: 5px;"><?php echo $persen; ?>% tercapai</div>
        </div>
        
        <div class="main-content">
            
            <!-- FORM DONASI -->
            <div class="donasi-form">
                <div class="card">
                    <h3><i class="fas fa-hand-holding-heart"></i> Donasi Sekarang</h3>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Nominal Donasi (Rp)</label>
                            <input type="number" name="nominal" placeholder="Masukkan nominal" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Tampilkan Nama di Publik</label>
                            <div class="radio-group">
                                <label><input type="radio" name="is_anonim" value="0" checked> ✅ Ya, tampilkan nama asli</label>
                                <label><input type="radio" name="is_anonim" value="1"> 🙈 Tidak, tampilkan sebagai "Anonim"</label>
                            </div>
                        </div>
                        
                        <!-- ====================================================== -->
                        <!-- UNTUK PUBLIK: NO WHATSAPP WAJIB                       -->
                        <!-- ====================================================== -->
                        <div class="form-group">
                            <label>No. WhatsApp <span style="color:red;">*</span></label>
                            <input type="text" name="no_whatsapp" placeholder="Contoh: 08123456789" required>
                            <small style="color:#888;">Wajib diisi untuk konfirmasi donasi dan riwayat donasi Anda</small>
                        </div>
                        
                        <div class="form-group" id="nama_field">
                            <label>Nama (jika ditampilkan)</label>
                            <input type="text" name="nama_donatur" placeholder="Masukkan nama Anda">
                        </div>
                        
                        <div class="form-group">
                            <label>Pesan (Opsional)</label>
                            <textarea name="pesan" rows="2" placeholder="Tulis pesan dukungan..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Upload Bukti Transfer</label>
                            <input type="file" name="bukti_transfer" accept="image/*,application/pdf" required>
                            <small style="color:#888;">Format: JPG, PNG, PDF (Max 2MB)</small>
                        </div>
                        
                        <div class="qris-info">
                            <p style="font-size: 12px; color: #888; margin-bottom: 10px;">
                                <i class="fas fa-qrcode"></i> Scan QRIS untuk donasi cepat
                            </p>
                            <img src="assets/image/qris.jpeg" alt="QRIS" onerror="this.src='assets/image/almuthi.png'">
                            <p style="font-size: 11px; color: #888; margin-top: 10px;">
                                Bank BRI: 0821-3191-3839-9383-92<br>
                                a.n Yayasan Sosial Bina Umat Al-Muthi
                            </p>
                        </div>
                        
                        <button type="submit" name="donasi_program" class="btn-donasi" style="margin-top: 15px;">
                            <i class="fas fa-paper-plane"></i> Kirim Donasi
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- DONATUR TERBARU -->
            <div class="donatur-list">
                <div class="card">
                    <h3><i class="fas fa-users"></i> Donatur Terbaru</h3>
                    <?php if (count($donatur_list) > 0): ?>
                        <?php foreach ($donatur_list as $d): ?>
                            <div class="donatur-item">
                                <span class="donatur-nama">
                                    <?php if ($d['is_anonim']): ?>
                                        🙈 Anonim
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($d['nama_donatur']); ?>
                                    <?php endif; ?>
                                </span>
                                <span class="donatur-nominal">Rp <?php echo number_format($d['nominal'], 0, ',', '.'); ?></span>
                                <?php if ($d['pesan']): ?>
                                    <div class="donatur-pesan">"<?php echo htmlspecialchars($d['pesan']); ?>"</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div style="text-align: center; margin-top: 15px;">
                            <a href="semua_donatur.php?program_id=<?php echo $program_id; ?>" class="btn-lihat-semua" style="display: inline-block; background: #50c878; color: white; padding: 8px 20px; border-radius: 25px; text-decoration: none; font-size: 13px;">
                                <i class="fas fa-arrow-right"></i> Lihat Semua Donatur (<?php echo $program['jumlah_donatur']; ?>)
                            </a>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #888;">Belum ada donatur</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- PENERIMA MANFAAT -->
        <?php if (count($penerima_list) > 0): ?>
        <div class="card">
            <div class="card-header-flex">
                <h3><i class="fas fa-users"></i> Penerima Manfaat</h3>
                <?php if ($total_penerima > 5): ?>
                    <a href="semua_penerima.php?program_id=<?php echo $program_id; ?>" class="btn-lihat-semua">Lihat Semua (<?php echo $total_penerima; ?>)</a>
                <?php endif; ?>
            </div>
            <div class="penerima-grid">
                <?php foreach ($penerima_list as $p): ?>
                    <div class="penerima-card">
                        <?php if ($p['foto']): ?>
                            <img src="assets/uploads/penerima/<?php echo $p['foto']; ?>" alt="<?php echo $p['nama_penerima']; ?>">
                        <?php else: ?>
                            <i class="fas fa-user-circle" style="font-size: 60px; color: #ccc;"></i>
                        <?php endif; ?>
                        <h4><?php echo htmlspecialchars($p['nama_penerima']); ?></h4>
                        <p><?php echo htmlspecialchars($p['jenis_bantuan']); ?></p>
                        <p><strong>Rp <?php echo number_format($p['jumlah'], 0, ',', '.'); ?></strong></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- GALERI PROGRAM -->
        <?php if (count($galeri_list) > 0): ?>
        <div class="card">
            <div class="card-header-flex">
                <h3><i class="fas fa-images"></i> Galeri Kegiatan</h3>
                <?php if ($total_galeri > 5): ?>
                    <a href="semua_galeri_program.php?program_id=<?php echo $program_id; ?>" class="btn-lihat-semua">Lihat Semua (<?php echo $total_galeri; ?>)</a>
                <?php endif; ?>
            </div>
            <div class="galeri-grid">
                <?php foreach ($galeri_list as $g): ?>
                    <div class="galeri-item" onclick="window.open('assets/uploads/galeri_program/<?php echo $g['file_path']; ?>', '_blank')">
                        <?php if ($g['tipe'] == 'foto'): ?>
                            <img src="assets/uploads/galeri_program/<?php echo $g['file_path']; ?>" alt="<?php echo $g['judul']; ?>">
                        <?php else: ?>
                            <video src="assets/uploads/galeri_program/<?php echo $g['file_path']; ?>"></video>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.querySelectorAll('input[name="is_anonim"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const namaField = document.getElementById('nama_field');
                if (this.value == '1') {
                    namaField.style.display = 'none';
                } else {
                    namaField.style.display = 'block';
                }
            });
        });
    </script>
    </body>
    </html>
<?php endif; ?>