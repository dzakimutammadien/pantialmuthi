<?php
// ======================================================
// FILE: login.php
// HALAMAN LOGIN + DONASI ANONIM
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
// PROSES DONASI ANONIM (Tanpa Login)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donasi_anonim'])) {
    $kategori_id = (int)$_POST['kategori_id'];
    $nominal = (float)$_POST['nominal'];
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $catatan_doa = mysqli_real_escape_string($conn, $_POST['catatan_doa']);
    
    // Upload bukti transfer
    $bukti_transfer = null;
    if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        
        if (in_array(strtolower($ext), $allowed)) {
            $filename = 'donasi_anonim_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target = 'assets/uploads/bukti_transfer/' . $filename;
            
            if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $target)) {
                $bukti_transfer = $filename;
            }
        }
    }
    
    // Cari user anonim (Hamba Allah) atau buat baru jika belum ada
    $user_anonim = mysqli_query($conn, "SELECT id FROM users WHERE username = 'hamba_allah' AND role_id = 3");
    if (mysqli_num_rows($user_anonim) > 0) {
        $user_id = mysqli_fetch_assoc($user_anonim)['id'];
    } else {
        // Buat user anonim default
        $hash_password = password_hash('hamba123', PASSWORD_DEFAULT);
        mysqli_query($conn, "INSERT INTO users (username, password, nama_lengkap, role_id, is_active) 
                             VALUES ('hamba_allah', '$hash_password', 'Hamba Allah', 3, 1)");
        $user_id = mysqli_insert_id($conn);
    }
    
    $sql = "INSERT INTO donasi (user_id, kategori_id, nominal, bukti_transfer, catatan_doa, keterangan, status) 
            VALUES ($user_id, $kategori_id, $nominal, '$bukti_transfer', '$catatan_doa', '$keterangan', 'pending')";
    
    if (mysqli_query($conn, $sql)) {
        $donasi_success = "Donasi anonim berhasil dikirim! Menunggu verifikasi admin.";
    } else {
        $donasi_error = "Gagal mengirim donasi: " . mysqli_error($conn);
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

// Ambil kategori untuk dropdown donasi anonim
$kategoris = query("SELECT * FROM kategori_donasi WHERE tipe IN ('donasi', 'both') ORDER BY nama_kategori ASC");
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
        
        /* MAIN LAYOUT - 2 KOLOM */
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
        
        /* CARD STYLE */
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
        
        /* ROLE SELECTOR */
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
        
        /* FORM */
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
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
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
            transition: all 0.3s ease;
        }
        
        .btn-login:hover, .btn-donasi:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(80,200,120,0.4);
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
            font-weight: 500;
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
        
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #eee;
        }
        
        .qris-image {
            text-align: center;
            margin-top: 15px;
        }
        
        .qris-image img {
            width: 150px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
        
        @media (max-width: 900px) {
            .main-layout { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-layout">
            
            <!-- KOLOM KIRI: LOGIN FORM -->
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
            
            <!-- KOLOM KANAN: DONASI ANONIM -->
            <div class="donasi-section">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-hand-holding-heart"></i> Donasi Cepat (Tanpa Login)</h2>
                        <p>Donasi anonim - nama akan tercatat sebagai "Hamba Allah"</p>
                    </div>
                    <div class="card-body">
                        <?php if ($donasi_success): ?>
                            <div class="alert alert-success"><?php echo $donasi_success; ?></div>
                        <?php endif; ?>
                        <?php if ($donasi_error): ?>
                            <div class="alert alert-error"><?php echo $donasi_error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
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
                            
                            <div class="form-group">
                                <label>Keterangan (Opsional)</label>
                                <textarea name="keterangan" rows="2" placeholder="Catatan donasi..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Titip Doa (Opsional)</label>
                                <textarea name="catatan_doa" rows="2" placeholder="Tulis doa atau pesan..."></textarea>
                            </div>
                            
                            <button type="submit" name="donasi_anonim" class="btn-donasi">
                                <i class="fas fa-paper-plane"></i> Kirim Donasi Anonim
                            </button>
                        </form>
                        
                        <hr>
                        
                        <div class="qris-image">
                            <p style="font-size: 12px; color: #888; margin-bottom: 10px;">
                                <i class="fas fa-qrcode"></i> Scan QRIS untuk donasi cepat
                            </p>
                            <img src="assets/image/qris.jpeg" alt="QRIS" onerror="this.src='assets/image/almuthi.png'">
                            <p style="font-size: 11px; color: #888; margin-top: 10px;">
                                Bank BRI: 0821-3191-3839-9383-92<br>
                                a.n Yayasan Sosial Bina Umat Al-Muthi
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="info-text" style="text-align: center; font-size: 11px; color: #888; margin-top: 10px;">
                    © 2025 Panti Asuhan Al-Muthi | Lembaga Amil Zakat Nasional
                </div>
            </div>
        </div>
    </div>
</body>
</html>