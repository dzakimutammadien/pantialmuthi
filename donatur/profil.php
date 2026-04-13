<?php
// ======================================================
// FILE: donatur/profil.php
// HALAMAN PROFIL DONATUR
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('donatur');

$currentUser = getCurrentUser();

// ======================================================
// FUNGSI UPLOAD FOTO
// ======================================================
function uploadFoto($existing_foto = null) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            return ['success' => false, 'message' => 'Format file tidak diizinkan (JPG, PNG, GIF, WEBP)'];
        }
        
        $filename = 'user_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target = '../assets/uploads/users/' . $filename;
        
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
            if ($existing_foto && $existing_foto != 'default-user.png' && file_exists('../assets/uploads/users/' . $existing_foto)) {
                unlink('../assets/uploads/users/' . $existing_foto);
            }
            return ['success' => true, 'filename' => $filename];
        }
    }
    return ['success' => true, 'filename' => $existing_foto ?: 'default-user.png'];
}

// ======================================================
// PROSES UPDATE PROFIL
// ======================================================
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $no_whatsapp = mysqli_real_escape_string($conn, $_POST['no_whatsapp']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    
    // Upload foto
    $upload = uploadFoto($currentUser['foto_profil']);
    $foto = $upload['success'] ? $upload['filename'] : $currentUser['foto_profil'];
    
    // Update password jika diisi
    $password_sql = "";
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $password_sql = ", password = '$password'";
    }
    
    $sql = "UPDATE users SET 
            nama_lengkap = '$nama_lengkap',
            jenis_kelamin = '$jenis_kelamin',
            email = '$email',
            no_whatsapp = '$no_whatsapp',
            alamat = '$alamat',
            username = '$username',
            foto_profil = '$foto'
            $password_sql
            WHERE id = " . $currentUser['id'];
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Mengupdate profil");
        $_SESSION['success'] = "Profil berhasil diupdate!";
        
        // Update session
        $_SESSION['nama_lengkap'] = $nama_lengkap;
        $_SESSION['username'] = $username;
        
        header("Location: profil.php");
        exit();
    } else {
        $error = "Gagal mengupdate profil: " . mysqli_error($conn);
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
    <title>Profil Donatur - Panti Asuhan Al-Muthi</title>
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
        
        /* MAIN CONTENT */
        .main-content { margin-left: 280px; padding: 20px; min-height: 100vh; }
        
        /* TOPBAR */
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .page-title h2 { font-size: 20px; color: #333; }
        .page-title p { font-size: 13px; color: #888; margin-top: 5px; }
        .profile-dropdown { position: relative; }
        .profile-icon { width: 45px; height: 45px; background: linear-gradient(135deg, #50c878, #2e8b57); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; color: white; }
        .dropdown-menu { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 1000; }
        .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        .dropdown-menu a:hover { background: #f5f5f5; color: #50c878; }
        
        /* CONTENT CARD */
        .content-card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .profil-container { display: flex; gap: 40px; flex-wrap: wrap; }
        .foto-section { flex: 1; text-align: center; padding: 20px; background: #f8f9fa; border-radius: 20px; }
        .foto-section img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid #50c878; margin-bottom: 15px; }
        .form-section { flex: 2; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: #555; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; transition: all 0.3s ease; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #50c878; }
        .btn-simpan { background: #50c878; color: white; padding: 12px 30px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; margin-top: 10px; }
        .btn-simpan:hover { background: #2e8b57; transform: translateY(-2px); }
        .alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        .password-note { font-size: 11px; color: #888; margin-top: 5px; }
        
        @media (max-width: 768px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } .form-row { grid-template-columns: 1fr; } .profil-container { flex-direction: column; } }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/image/almuthi.png" alt="Logo Al-Muthi" class="sidebar-logo" onerror="this.style.display='none'">
            <div><h3>Panti Asuhan</h3><p>Al-Muthi</p></div>
        </div>
        <div class="sidebar-menu">
            <div class="menu-item" onclick="location.href='dashboard.php'"><i class="fas fa-tachometer-alt"></i><span>Beranda</span></div>
            <div class="menu-item" onclick="location.href='donasi.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Sekarang</span></div>
            <div class="menu-item" onclick="location.href='histori.php'"><i class="fas fa-history"></i><span>Riwayat Donasi</span></div>
            <div class="menu-item" onclick="location.href='laporan_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Laporan Pengeluaran Panti</span></div>
            <div class="menu-item" onclick="location.href='doa_saya.php'"><i class="fas fa-pray"></i><span>Laporan Khusus Do'a</span></div>
            <div class="menu-item" onclick="location.href='laporan.php'"><i class="fas fa-chart-line"></i><span>Laporan</span></div>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Profil Pengguna</h2>
                <p>Kelola informasi profil Anda</p>
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
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="profil-container">
                    <!-- BAGIAN FOTO -->
                    <div class="foto-section">
                        <img src="../assets/uploads/users/<?php echo $currentUser['foto_profil'] ?: 'default-user.png'; ?>" 
                             alt="Foto Profil" 
                             onerror="this.src='../assets/uploads/users/default-user.png'">
                        <div class="form-group">
                            <label>Upload Foto</label>
                            <input type="file" name="foto" accept="image/*">
                            <small style="color:#888;">Format: JPG, PNG, GIF (Max 2MB)</small>
                        </div>
                    </div>
                    
                    <!-- BAGIAN FORM -->
                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($currentUser['nama_lengkap']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Jenis Kelamin</label>
                                <select name="jenis_kelamin">
                                    <option value="L" <?php echo $currentUser['jenis_kelamin'] == 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                                    <option value="P" <?php echo $currentUser['jenis_kelamin'] == 'P' ? 'selected' : ''; ?>>Perempuan</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>">
                            </div>
                            <div class="form-group">
                                <label>No Whatsapp</label>
                                <input type="text" name="no_whatsapp" value="<?php echo htmlspecialchars($currentUser['no_whatsapp']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Alamat</label>
                            <textarea name="alamat" rows="2"><?php echo htmlspecialchars($currentUser['alamat']); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="username" value="<?php echo htmlspecialchars($currentUser['username']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Password Baru</label>
                                <input type="password" name="password" placeholder="Kosongkan jika tidak diubah">
                                <div class="password-note">Minimal 8 karakter</div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-simpan">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>