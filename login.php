<?php
// ======================================================
// FILE: login.php
// HALAMAN LOGIN DENGAN PILIHAN ROLE
// ======================================================

require_once 'config/database.php';
require_once 'config/session.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $selected_role = $_POST['role'];
    
    // Query cek user
    $sql = "SELECT u.*, r.nama_role FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.username = '$username' AND r.nama_role = '$selected_role'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Cek apakah user aktif
        if ($user['is_active'] != 1) {
            $error = 'Akun Anda telah dinonaktifkan oleh Admin. Silakan hubungi pengurus panti.';
        } 
        // Verifikasi password
        else if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['nama_role'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            
            // Log aktivitas login
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Informasi Donasi Panti Asuhan Al-Muthi</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', 'Segoe UI', sans-serif;
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
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            background: linear-gradient(135deg,  #50c878 0%, #2e8b57 100%);
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
        
        .role-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
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
            padding: 15px 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .role-option input:checked + .role-card {
            border-color: #667eea;
            background: linear-gradient(135deg, #50c87815 0%, #2e8b5715 100%);
            transform: scale(1.02);
        }
        
        .role-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .role-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }
        
        .role-title {
            font-weight: 600;
            font-size: 14px;
            color: #333;
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
            border-color:  #50c878;
            box-shadow: 0 0 0 3px rgba(80,200,120,0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #50c878 0%, #2e8b57  100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(80,200,120,0.4);
        }
        
        .error-message {
            background: #fee;
            color: #c62828;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .info-text {
            text-align: center;
            font-size: 12px;
            color: #888;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>SISTEM INFORMASI DONASI</h1>
                <p>PANTI ASUHAN AL-MUTHI</p>
            </div>
            <div class="content">
                <?php if ($error): ?>
                    <div class="error-message">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="role-selector">
                        <label class="role-option">
                            <input type="radio" name="role" value="admin" required>
                            <div class="role-card">
                                <div class="role-icon">👑</div>
                                <div class="role-title">Admin</div>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="pengasuh" required>
                            <div class="role-card">
                                <div class="role-icon">👩‍🏫</div>
                                <div class="role-title">Pengasuh</div>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="donatur" required>
                            <div class="role-card">
                                <div class="role-icon">🙏</div>
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
                    
                    <button type="submit" class="btn-login">LOGIN</button>
                </form>
                
                <div class="demo-credentials">
                    <strong>🔐 Demo Akun:</strong><br>
                    Admin: admin / 12345678<br>
                    Pengasuh: pengasuh / 12345678<br>
                    Donatur: donatur / 12345678
                </div>
                
                <div class="info-text">
                    © 2024 Panti Asuhan Al-Muthi | All Rights Reserved
                </div>
            </div>
        </div>
    </div>
</body>
</html>