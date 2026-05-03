<?php
// ======================================================
// FILE: lupa_password.php
// HALAMAN LUPA PASSWORD (Reset Password via WhatsApp Admin)
// ======================================================

require_once 'config/database.php';
require_once 'config/session.php';

$success = '';
$error = '';

// Proses reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Cek apakah user ada
    $sql = "SELECT id, username, nama_lengkap, no_whatsapp FROM users WHERE username = '$username' AND email = '$email' AND role_id = 3";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Generate password baru (random 8 karakter)
        $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password di database
        $update = "UPDATE users SET password = '$hashed_password' WHERE id = " . $user['id'];
        if (mysqli_query($conn, $update)) {
            // Simpan password baru ke session untuk ditampilkan
            $success = "Password baru telah dibuat!";
            $new_password_display = $new_password;
            
            // Log aktivitas
            logActivity($user['id'], "Reset password melalui halaman lupa password");
        } else {
            $error = "Gagal mereset password. Silakan coba lagi.";
        }
    } else {
        $error = "Username dan Email tidak cocok! Pastikan Anda menggunakan email yang terdaftar.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Panti Asuhan Al-Muthi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            width: 100%;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .header {
            background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #50c878;
            box-shadow: 0 0 0 3px rgba(80,200,120,0.1);
        }
        
        .btn-reset {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #50c878 0%, #2e8b57 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(80,200,120,0.4);
        }
        
        .btn-back {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #50c878;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-back:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
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
        
        .new-password {
            background: #f0fdf4;
            border: 1px solid #4caf50;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-top: 20px;
        }
        
        .new-password h4 {
            color: #2e7d32;
            margin-bottom: 10px;
        }
        
        .new-password .password-value {
            font-size: 20px;
            font-weight: 700;
            color: #50c878;
            letter-spacing: 2px;
            background: white;
            display: inline-block;
            padding: 8px 20px;
            border-radius: 10px;
            border: 1px dashed #4caf50;
        }
        
        .info-text {
            text-align: center;
            font-size: 12px;
            color: #888;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .wa-contact {
            background: #f5f5f5;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-top: 20px;
        }
        
        .wa-contact a {
            color: #25D366;
            font-weight: 600;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1><i class="fas fa-key"></i> Lupa Password</h1>
                <p>Reset password akun donatur Anda</p>
            </div>
            <div class="content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    
                    <?php if (isset($new_password_display)): ?>
                        <div class="new-password">
                            <h4><i class="fas fa-lock-open"></i> Password Baru Anda</h4>
                            <div class="password-value"><?php echo $new_password_display; ?></div>
                            <p style="margin-top: 10px; font-size: 12px;">
                                <i class="fas fa-exclamation-triangle"></i> Simpan password ini, lalu segera ganti di halaman profil setelah login.
                            </p>
                        </div>
                        <a href="login.php" class="btn-back"><i class="fas fa-sign-in-alt"></i> Kembali ke Halaman Login</a>
                    <?php endif; ?>
                    
                <?php elseif ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                    
                    <div class="wa-contact">
                        <i class="fab fa-whatsapp" style="font-size: 24px; color: #25D366;"></i>
                        <p style="margin: 10px 0;">Atau hubungi admin melalui WhatsApp untuk bantuan reset password:</p>
                        <a href="https://wa.me/6281234567890?text=Saya%20lupa%20password%20akun%20donatur%20dengan%20username:%20[isi%20username]%20dan%20email:%20[isi%20email]" target="_blank">
                            <i class="fab fa-whatsapp"></i> Hubungi Admin via WhatsApp
                        </a>
                    </div>
                    
                    <a href="login.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
                    
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Username</label>
                            <input type="text" name="username" placeholder="Masukkan username Anda" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="email" placeholder="Masukkan email yang terdaftar" required>
                            <small style="color:#888;">Email harus sesuai dengan yang didaftarkan ke admin</small>
                        </div>
                        
                        <button type="submit" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset Password</button>
                    </form>
                    
                    <div class="wa-contact">
                        <i class="fab fa-whatsapp" style="font-size: 24px; color: #25D366;"></i>
                        <p style="margin: 10px 0;">Belum punya akun? Atau lupa email yang terdaftar?</p>
                        <a href="https://wa.me/6281234567890?text=Saya%20butuh%20bantuan%20akun%20donatur" target="_blank">
                            <i class="fab fa-whatsapp"></i> Hubungi Admin via WhatsApp
                        </a>
                    </div>
                    
                    <a href="login.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
                <?php endif; ?>
                
                <div class="info-text">
                    © 2025 Panti Asuhan Al-Muthi | All Rights Reserved
                </div>
            </div>
        </div>
    </div>
</body>
</html>