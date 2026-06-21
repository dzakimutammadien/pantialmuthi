<?php
// ======================================================
// FILE: daftar.php
// HALAMAN PENDAFTARAN DONATUR BARU
// ======================================================

require_once 'config/database.php';
require_once 'config/session.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// ======================================================
// PROSES PENDAFTARAN
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daftar'])) {
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $no_whatsapp = mysqli_real_escape_string($conn, $_POST['no_whatsapp']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
    
    // ======================================================
    // VALIDASI
    // ======================================================
    if (empty($nama_lengkap)) {
        $error = "Nama lengkap wajib diisi!";
    } elseif (empty($username)) {
        $error = "Username wajib diisi!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif (empty($no_whatsapp)) {
        $error = "Nomor WhatsApp wajib diisi!";
    } elseif (empty($jenis_kelamin)) {
        $error = "Jenis kelamin wajib dipilih!";
    } else {
        // Cek username sudah ada di users
        $check_user = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
        if (mysqli_num_rows($check_user) > 0) {
            $error = "Username '$username' sudah digunakan! Silakan pilih username lain.";
        } else {
            // Cek username sudah ada di pendaftaran (pending/approved)
            $check_pendaftaran = mysqli_query($conn, "SELECT id FROM pendaftaran WHERE username = '$username' AND status IN ('pending', 'approved')");
            if (mysqli_num_rows($check_pendaftaran) > 0) {
                $error = "Username '$username' sudah dalam proses pendaftaran! Silakan tunggu verifikasi admin.";
            } else {
                // Upload foto (opsional)
                $foto = 'default-user.png';
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array(strtolower($ext), $allowed)) {
                        $filename = 'pendaftar_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                        $target = 'assets/uploads/users/' . $filename;
                        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
                            $foto = $filename;
                        }
                    }
                }
                
                // Hash password
                $hash_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Simpan ke tabel pendaftaran
                $sql = "INSERT INTO pendaftaran (nama_lengkap, username, password, email, no_whatsapp, alamat, jenis_kelamin, foto, status) 
                        VALUES ('$nama_lengkap', '$username', '$hash_password', '$email', '$no_whatsapp', '$alamat', '$jenis_kelamin', '$foto', 'pending')";
                
                if (mysqli_query($conn, $sql)) {
                    $success = "Pendaftaran berhasil! Silakan tunggu verifikasi dari admin melalui WhatsApp.";
                } else {
                    $error = "Gagal mendaftar: " . mysqli_error($conn);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Donatur - Panti Asuhan Al-Muthi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%);
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 600px;
            width: 100%;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%);
            padding: 25px;
            text-align: center;
            color: white;
        }
        
        .card-header h2 {
            font-size: 22px;
            margin-bottom: 5px;
        }
        
        .card-header p {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .card-body {
            padding: 30px;
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
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #50c878;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn-daftar {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-daftar:hover {
            background: linear-gradient(135deg, #2e8b57 0%, #1a6a3a 100%);
        }
        
        .btn-daftar:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .btn-login {
            display: inline-block;
            color: #50c878;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-login:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
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
        
        .info-text {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #888;
        }
        
        .info-text a {
            color: #50c878;
            text-decoration: none;
        }
        
        .info-text a:hover {
            text-decoration: underline;
        }
        
        small {
            color: #888;
            font-size: 11px;
            display: block;
            margin-top: 4px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .container { padding: 0 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-user-plus"></i> Pendaftaran Donatur</h2>
                <p>Daftar untuk menjadi donatur Panti Asuhan Al-Muthi</p>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <br><br>
                        <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Kembali ke Login</a>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nama Lengkap <span class="required">*</span></label>
                                <input type="text" name="nama_lengkap" placeholder="Masukkan nama lengkap" required>
                            </div>
                            <div class="form-group">
                                <label>Username <span class="required">*</span></label>
                                <input type="text" name="username" placeholder="Pilih username" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Password <span class="required">*</span></label>
                                <input type="password" name="password" placeholder="Minimal 6 karakter" required minlength="6">
                                <small>Minimal 6 karakter</small>
                            </div>
                            <div class="form-group">
                                <label>Jenis Kelamin <span class="required">*</span></label>
                                <select name="jenis_kelamin" required>
                                    <option value="">Pilih...</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" placeholder="Masukkan email (opsional)">
                            </div>
                            <div class="form-group">
                                <label>No. WhatsApp <span class="required">*</span></label>
                                <input type="text" name="no_whatsapp" placeholder="Contoh: 08123456789" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Alamat</label>
                            <textarea name="alamat" rows="2" placeholder="Masukkan alamat (opsional)"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Foto Profil (Opsional)</label>
                            <input type="file" name="foto" accept="image/*">
                            <small>Format: JPG, PNG (Max 2MB)</small>
                        </div>
                        
                        <button type="submit" name="daftar" class="btn-daftar">
                            <i class="fas fa-paper-plane"></i> Daftar Sekarang
                        </button>
                    </form>
                    
                    <div class="info-text">
                        <i class="fas fa-arrow-left"></i> Sudah punya akun? 
                        <a href="login.php">Login di sini</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>