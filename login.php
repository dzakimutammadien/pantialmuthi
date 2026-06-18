<?php
// ======================================================
// FILE: login.php
// HALAMAN LOGIN + DONASI + PROGRAM KAMI
// PERBAIKAN: No WhatsApp WAJIB, Cek User Unik
// ======================================================

require_once 'config/database.php';
require_once 'config/session.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$donasi_success = '';
$donasi_error = '';

// ======================================================
// PROSES DONASI (Tanpa Login) - PERBAIKAN
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donasi'])) {
    $nama_donatur = mysqli_real_escape_string($conn, $_POST['nama_donatur']);
    $no_whatsapp = mysqli_real_escape_string($conn, $_POST['no_whatsapp']);
    $kategori_id = (int)$_POST['kategori_id'];
    $nominal = (float)$_POST['nominal'];
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $catatan_doa = mysqli_real_escape_string($conn, $_POST['catatan_doa']);
    
    // ======================================================
    // VALIDASI: No WhatsApp WAJIB diisi
    // ======================================================
    if (empty($no_whatsapp)) {
        $donasi_error = "Nomor WhatsApp wajib diisi untuk konfirmasi donasi!";
    } else {
        // Upload bukti transfer
        $bukti_transfer = null;
        if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
            
            if (in_array(strtolower($ext), $allowed)) {
                $filename = 'donasi_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $target = 'assets/uploads/bukti_transfer/' . $filename;
                
                if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $target)) {
                    $bukti_transfer = $filename;
                }
            }
        }
        
        // Tentukan nama_user (jika kosong, pakai "Hamba Allah")
        $nama_user = !empty($nama_donatur) ? $nama_donatur : 'Hamba Allah';
        
        // ======================================================
        // CEK USER BERDASARKAN NO WHATSAPP (UNIK)
        // ======================================================
        $user_check = mysqli_query($conn, "SELECT id, foto_profil FROM users WHERE no_whatsapp = '$no_whatsapp' AND role_id = 3");
        
        if (mysqli_num_rows($user_check) > 0) {
            // User ditemukan, pakai user yang sudah ada
            $user_data = mysqli_fetch_assoc($user_check);
            $user_id = $user_data['id'];
        } else {
            // ======================================================
            // BUAT USER BARU dengan no_whatsapp
            // ======================================================
            $username = strtolower(str_replace(' ', '_', $nama_user)) . '_' . rand(100, 999);
            $hash_password = password_hash('donasi123', PASSWORD_DEFAULT);
            $foto_default = 'default-user.png';
            
            mysqli_query($conn, "INSERT INTO users (username, password, nama_lengkap, role_id, is_active, foto_profil, no_whatsapp) 
                                 VALUES ('$username', '$hash_password', '$nama_user', 3, 1, '$foto_default', '$no_whatsapp')");
            $user_id = mysqli_insert_id($conn);
        }
        
        // ======================================================
        // SIMPAN DONASI
        // ======================================================
        $sql = "INSERT INTO donasi (user_id, kategori_id, nominal, bukti_transfer, catatan_doa, keterangan, status) 
                VALUES ($user_id, $kategori_id, $nominal, '$bukti_transfer', '$catatan_doa', '$keterangan', 'pending')";
        
        if (mysqli_query($conn, $sql)) {
            $donasi_success = "Donasi berhasil dikirim! Menunggu verifikasi admin.";
        } else {
            $donasi_error = "Gagal mengirim donasi: " . mysqli_error($conn);
        }
    }
}

// ======================================================
// PROSES LOGIN
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $selected_role = $_POST['role'];
    
    $sql = "SELECT u.*, r.nama_role FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.username = '$username' AND r.nama_role = '$selected_role'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        
        if ($user['is_active'] != 1) {
            $error = 'Akun Anda telah dinonaktifkan oleh Admin. Silakan hubungi pengurus panti.';
        } else if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['nama_role'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            
            logActivity($user['id'], 'Login ke sistem');
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Password salah!';
        }
    } else {
        $error = 'Username tidak ditemukan atau role tidak sesuai!';
    }
}

// Ambil kategori untuk dropdown donasi
$kategoris = query("SELECT * FROM kategori_donasi WHERE tipe IN ('donasi', 'both') ORDER BY nama_kategori ASC");

// Ambil program untuk crowdfunding
$sql_program = "SELECT p.*, 
                (SELECT COUNT(*) FROM donasi_program WHERE program_id = p.id AND status = 'success') as jumlah_donatur,
                (SELECT SUM(nominal) FROM donasi_program WHERE program_id = p.id AND status = 'success') as total_terkumpul
                FROM program_donasi p 
                WHERE p.status = 'aktif' 
                ORDER BY p.created_at DESC 
                LIMIT 3";
$program_list = query($sql_program);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panti Asuhan Al-Muthi - Sistem Informasi Donasi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .main-layout {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .login-section {
            flex: 1;
            min-width: 350px;
        }
        
        .donasi-section {
            flex: 1;
            min-width: 400px;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%);
            padding: 20px;
            text-align: center;
            color: white;
        }
        
        .card-header h2 {
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .card-header p {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .role-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            justify-content: center;
        }
        
        .role-option {
            flex: 1;
            text-align: center;
            cursor: pointer;
        }
        
        .role-option input {
            display: none;
        }
        
        .role-card {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 12px 8px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .role-option input:checked + .role-card {
            border-color: #50c878;
            background: linear-gradient(135deg, #50c87815 0%, #2e8b5715 100%);
        }
        
        .role-card:hover {
            transform: translateY(-3px);
        }
        
        .role-icon {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .role-title {
            font-weight: 600;
            font-size: 13px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 13px;
            color: #555;
        }
        
        .form-group label .required {
            color: #f44336;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #50c878;
        }
        
        .btn-login, .btn-donasi {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn-donasi:hover, .btn-login:hover {
            background: linear-gradient(135deg, #2e8b57 0%, #1a6a3a 100%);
        }
        
        .link-lupapassword {
            text-align: right;
            margin-top: 10px;
        }
        
        .link-lupapassword a {
            font-size: 12px;
            color: #50c878;
            text-decoration: none;
        }
        
        .register-info {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #888;
        }
        
        .register-info a {
            color: #50c878;
            text-decoration: none;
        }
        
        .demo-credentials {
            background: #f5f5f5;
            border-radius: 10px;
            padding: 10px;
            margin-top: 15px;
            font-size: 11px;
            color: #666;
        }
        
        .demo-credentials strong {
            color: #50c878;
        }
        
        .qris-image {
            text-align: center;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 10px;
            margin-top: 5px;
        }
        
        .qris-image img {
            width: 80px;
        }
        
        .alert {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        
        /* PROGRAM SECTION */
        .program-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-top: 25px;
        }
        
        .program-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .program-header h2 {
            font-size: 22px;
            color: #1a3a2a;
            margin-bottom: 5px;
        }
        
        .program-header p {
            font-size: 13px;
            color: #888;
        }
        
        .program-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .program-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease;
            border: 1px solid #e0e0e0;
        }
        
        .program-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #50c878;
        }
        
        .program-icon {
            margin-bottom: 15px;
            background: #f0f2f5;
            border-radius: 10px;
            overflow: hidden;
            height: 180px;
        }
        
        .program-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .program-icon i {
            font-size: 48px;
            color: #50c878;
            line-height: 180px;
        }
        
        .program-card h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .program-card p {
            font-size: 12px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .progress-fill {
            background: #50c878;
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        
        .progress-info {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #666;
            margin-bottom: 12px;
        }
        
        .donatur-count {
            font-size: 11px;
            color: #888;
            margin-bottom: 15px;
        }
        
        .btn-donasi-program {
            display: inline-block;
            background: #50c878;
            color: white;
            padding: 8px 15px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-donasi-program:hover {
            background: #2e8b57;
            transform: scale(1.02);
        }
        
        .program-footer {
            text-align: center;
        }
        
        .btn-lihat-semua {
            display: inline-block;
            background: transparent;
            color: #50c878;
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #50c878;
            transition: all 0.3s ease;
        }
        
        .btn-lihat-semua:hover {
            background: #50c878;
            color: white;
        }
        
        /* GALERI PREVIEW */
        .galeri-preview-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .galeri-preview-item {
            position: relative;
            aspect-ratio: 1;
            background: #f0f2f5;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
        }
        
        .galeri-preview-item img,
        .galeri-preview-item video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .btn-lihat-semua-galeri {
            display: inline-block;
            background: #50c878;
            color: white;
            padding: 8px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 13px;
            margin-top: 15px;
        }
        
        .btn-lihat-semua-galeri:hover {
            background: #2e8b57;
        }
        
        .info-text {
            text-align: center;
            font-size: 11px;
            color: #888;
            margin-top: 20px;
            padding: 15px;
        }
        
        @media (max-width: 900px) {
            .main-layout { flex-direction: column; }
            .galeri-preview-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .donasi-section .card-body form > div {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="main-layout">
        
        <!-- KOLOM KIRI: LOGIN -->
        <div class="login-section">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-sign-in-alt"></i> Login</h2>
                    <p>Masuk ke akun Anda</p>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="role-selector">
                            <label class="role-option">
                                <input type="radio" name="role" value="admin" required>
                                <div class="role-card">
                                    <div class="role-icon"><i class="fas fa-user-shield"></i></div>
                                    <div class="role-title">Admin</div>
                                </div>
                            </label>
                            <label class="role-option">
                                <input type="radio" name="role" value="pengasuh" required>
                                <div class="role-card">
                                    <div class="role-icon"><i class="fas fa-chalkboard-user"></i></div>
                                    <div class="role-title">Pengasuh</div>
                                </div>
                            </label>
                            <label class="role-option">
                                <input type="radio" name="role" value="donatur" required>
                                <div class="role-card">
                                    <div class="role-icon"><i class="fas fa-hand-holding-heart"></i></div>
                                    <div class="role-title">Donatur</div>
                                </div>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" placeholder="Masukkan username" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" placeholder="Masukkan password" required>
                        </div>
        
                        <div class="link-lupapassword">
                            <a href="lupa_password.php"><i class="fas fa-key"></i> Lupa Password?</a>
                        </div>
                        
                        <button type="submit" name="login" class="btn-login">LOGIN</button>
                    </form>
                    
                    <div class="register-info">
                        <i class="fas fa-user-plus"></i> Belum punya akun? 
                        <a href="https://wa.me/6282331696669?text=Saya%20ingin%20mendaftar%20akun%20donatur" target="_blank">
                            Hubungi Admin via WhatsApp
                        </a>
                    </div>
                    
                    <div class="demo-credentials">
                        <strong>🔐 Demo Akun:</strong><br>
                        Admin: admin / 12345678<br>
                        Pengasuh: pengasuh / 12345678<br>
                        Donatur: donatur / 12345678
                    </div>
                </div>
            </div>
        </div>
        
        <!-- KOLOM KANAN: DONASI -->
        <div class="donasi-section">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-hand-holding-heart"></i> Donasi Cepat</h2>
                    <p>Donasi tanpa login - cukup isi data Anda</p>
                </div>
                <div class="card-body">
                    <?php if ($donasi_success): ?>
                        <div class="alert alert-success"><?php echo $donasi_success; ?></div>
                    <?php endif; ?>
                    <?php if ($donasi_error): ?>
                        <div class="alert alert-error"><?php echo $donasi_error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <!-- FORM 2 KOLOM -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            
                            <!-- KOLOM KIRI -->
                            <div>
                                <div class="form-group">
                                    <label>Nama Donatur (Opsional)</label>
                                    <input type="text" name="nama_donatur" placeholder="Kosongkan jika ingin anonim">
                                    <small style="color:#888;">Nama akan tercatat sesuai isian</small>
                                </div>
                                
                                <!-- ====================================================== -->
                                <!-- TAMBAHAN: No WhatsApp WAJIB                           -->
                                <!-- ====================================================== -->
                                <div class="form-group">
                                    <label>No. WhatsApp <span class="required">*</span></label>
                                    <input type="text" name="no_whatsapp" placeholder="Contoh: 08123456789" required>
                                    <small style="color:#888;">Wajib diisi untuk konfirmasi donasi dan riwayat donasi Anda</small>
                                </div>
                                
                                <div class="form-group">
                                    <label>Nominal Donasi (Rp)</label>
                                    <input type="number" name="nominal" placeholder="Masukkan nominal" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Kategori Donasi</label>
                                    <select name="kategori_id" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php foreach ($kategoris as $k): ?>
                                            <option value="<?php echo $k['id']; ?>"><?php echo $k['nama_kategori']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Upload Bukti Transfer</label>
                                    <input type="file" name="bukti_transfer" accept="image/*,application/pdf" required>
                                    <small style="color:#888;">Format: JPG, PNG, PDF</small>
                                </div>
                            </div>
                            
                            <!-- KOLOM KANAN -->
                            <div>
                                <div class="form-group">
                                    <label>Keterangan (Opsional)</label>
                                    <textarea name="keterangan" rows="2" placeholder="Catatan donasi..."></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Titip Doa (Opsional)</label>
                                    <textarea name="catatan_doa" rows="2" placeholder="Tulis doa atau pesan..."></textarea>
                                </div>
                                
                                <!-- QRIS -->
                                <div class="qris-image">
                                    <p style="font-size: 11px; color: #888; margin-bottom: 8px;">
                                        <i class="fas fa-qrcode"></i> Scan QRIS
                                    </p>
                                    <img src="assets/image/qris.jpeg" alt="QRIS" onerror="this.src='assets/image/almuthi.png'">
                                    <p style="font-size: 10px; color: #888; margin-top: 8px;">
                                        BRI: 0821-3191-3839-9383-92
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="donasi" class="btn-donasi" style="margin-top: 10px; width: 100%;">
                            <i class="fas fa-paper-plane"></i> Kirim Donasi
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- ====================================================== -->
    <!-- PROGRAM KAMI (CROWDFUNDING)                            -->
    <!-- ====================================================== -->
    <div class="program-section">
        <div class="program-header">
            <h2><i class="fas fa-chalkboard-user"></i> Program Kami</h2>
            <p>Donasi untuk program-program panti asuhan Al-Muthi</p>
        </div>
        
        <div class="program-grid">
            <?php if (count($program_list) > 0): ?>
                <?php foreach ($program_list as $program):
                    $terkumpul = $program['total_terkumpul'] ?? 0;
                    $persen = ($program['target_nominal'] > 0) ? round(($terkumpul / $program['target_nominal']) * 100) : 0;
                    $persen = min($persen, 100);
                ?>
                <div class="program-card">
                    <div class="program-icon">
                        <?php if ($program['gambar']): ?>
                            <img src="assets/uploads/program/<?php echo $program['gambar']; ?>" alt="<?php echo $program['nama_program']; ?>">
                        <?php else: ?>
                            <i class="fas fa-hand-holding-heart"></i>
                        <?php endif; ?>
                    </div>
                    <h3><?php echo htmlspecialchars($program['nama_program']); ?></h3>
                    <p><?php echo htmlspecialchars(substr($program['deskripsi'], 0, 60)) . '...'; ?></p>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $persen; ?>%;"></div>
                    </div>
                    <div class="progress-info">
                        <span><?php echo $persen; ?>%</span>
                        <span>Rp <?php echo number_format($program['terkumpul'], 0, ',', '.'); ?> / Rp <?php echo number_format($program['target_nominal'], 0, ',', '.'); ?></span>
                    </div>
                    
                    <div class="donatur-count">
                        <i class="fas fa-users"></i> <?php echo $program['jumlah_donatur']; ?> Donatur
                    </div>
                    
                    <a href="program_detail.php?id=<?php echo $program['id']; ?>" class="btn-donasi-program">
                        <i class="fas fa-hand-holding-heart"></i> Donasi Sekarang
                    </a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="program-card">
                    <div class="program-icon"><i class="fas fa-chalkboard-user"></i></div>
                    <h3>Program akan segera hadir</h3>
                    <p>Pantau terus website ini untuk program-program terbaru dari Panti Asuhan Al-Muthi</p>
                    <div class="donatur-count">-</div>
                    <a href="#" class="btn-donasi-program disabled" style="background:#ccc; cursor:not-allowed;">Segera Hadir</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="program-footer">
            <a href="semua_program.php" class="btn-lihat-semua">
                <i class="fas fa-arrow-right"></i> Lihat Semua Program
            </a>
        </div>
    </div>
    
    <!-- GALERI PREVIEW -->
    <div class="card" style="margin-top: 0;">
        <div class="card-header">
            <h2><i class="fas fa-images"></i> Galeri Kegiatan</h2>
            <p>Dokumentasi kegiatan panti asuhan</p>
        </div>
        <div class="card-body">
            <div class="galeri-preview-grid">
                <?php
                $sql_galeri = "SELECT * FROM galeri WHERE status = 'aktif' ORDER BY created_at DESC LIMIT 6";
                $galeri_preview = query($sql_galeri);
                ?>
                <?php if (count($galeri_preview) > 0): ?>
                    <?php foreach ($galeri_preview as $g): ?>
                        <div class="galeri-preview-item" onclick="window.open('galeri.php', '_blank')">
                            <img src="assets/uploads/galeri/<?php echo $g['file_path']; ?>" alt="<?php echo $g['judul']; ?>">
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 20px;">Belum ada galeri</div>
                <?php endif; ?>
            </div>
            <div style="text-align: center;">
                <a href="galeri.php" class="btn-lihat-semua-galeri">Lihat Semua Galeri</a>
            </div>
        </div>
    </div>
    
    <div class="info-text">
        © 2025 Panti Asuhan Al-Muthi | Lembaga Amil Zakat Nasional
    </div>
</div>
</body>
</html>