<?php
// ======================================================
// FILE: donatur/donasi.php
// HALAMAN DONASI UNTUK DONATUR
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('donatur');

$currentUser = getCurrentUser();

// Generate ID Donasi unik
$id_donasi = 'DON-' . date('Ymd') . '-' . rand(1000, 9999);

// Ambil kategori donasi (tipe donasi atau both)
$kategoris = query("SELECT * FROM kategori_donasi WHERE tipe IN ('donasi', 'both') ORDER BY nama_kategori ASC");

// Proses Donasi
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donasi'])) {
    $kategori_id = (int)$_POST['kategori_id'];
    $nominal = (float)$_POST['nominal'];
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $catatan_doa = mysqli_real_escape_string($conn, $_POST['catatan_doa']);
    $user_id = $currentUser['id'];
    
    // Upload bukti transfer
    $bukti_transfer = null;
    if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        
        if (in_array(strtolower($ext), $allowed)) {
            $filename = 'donasi_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target = '../assets/uploads/bukti_transfer/' . $filename;
            
            if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $target)) {
                $bukti_transfer = $filename;
            }
        }
    }
    
    $sql = "INSERT INTO donasi (user_id, kategori_id, nominal, bukti_transfer, catatan_doa, keterangan, status) 
            VALUES ($user_id, $kategori_id, $nominal, '$bukti_transfer', '$catatan_doa', '$keterangan', 'pending')";
    
    if (mysqli_query($conn, $sql)) {
        logActivity($currentUser['id'], "Melakukan donasi Rp " . number_format($nominal));
        $_SESSION['success'] = "Donasi berhasil dikirim! Menunggu verifikasi admin.";
        header("Location: donasi.php");
        exit();
    } else {
        $error = "Gagal melakukan donasi: " . mysqli_error($conn);
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
    <title>Donasi - Panti Asuhan Al-Muthi</title>
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
        
        /* DONASI CONTAINER */
        .donasi-container {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .metode-pembayaran {
            flex: 1;
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .metode-pembayaran h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #50c878;
        }
        
        .rekening-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .rekening-info h4 {
            font-size: 14px;
            color: #888;
            margin-bottom: 5px;
        }
        
        .rekening-info .nomor {
            font-size: 20px;
            font-weight: 700;
            color: #50c878;
            letter-spacing: 2px;
        }
        
        .rekening-info p {
            font-size: 12px;
            color: #666;
            margin-top: 10px;
        }
        
        .qris-image {
            text-align: center;
            margin-top: 20px;
        }
        
        .qris-image img {
            max-width: 200px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .form-donasi {
            flex: 1;
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-donasi h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #50c878;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 13px;
            color: #555;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 15px;
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
        
        .btn-donasi {
            background: #50c878;
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-donasi:hover {
            background: #2e8b57;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
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
        
        @media (max-width: 768px) {
            .sidebar { left: -280px; }
            .main-content { margin-left: 0; }
            .form-row { grid-template-columns: 1fr; }
        }
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
            <div class="menu-item active" onclick="location.href='donasi.php'"><i class="fas fa-hand-holding-heart"></i><span>Donasi Sekarang</span></div>
            <div class="menu-item" onclick="location.href='histori.php'"><i class="fas fa-history"></i><span>Riwayat Donasi</span></div>
            <div class="menu-item" onclick="location.href='laporan_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Pengeluaran Panti</span></div>
            <div class="menu-item" onclick="location.href='doa_saya.php'"><i class="fas fa-pray"></i><span>Laporan Khususon Do'a</span></div>
            <div class="menu-item" onclick="location.href='laporan.php'"><i class="fas fa-chart-line"></i><span>Laporan</span></div>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Donasi Sekarang</h2>
                <p>Salurkan donasi Anda untuk Panti Asuhan Al-Muthi</p>
            </div>
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
        
        <div class="donasi-container">
            <!-- METODE PEMBAYARAN -->
            <div class="metode-pembayaran">
                <h3><i class="fas fa-credit-card"></i> Metode Penuaian Donasi</h3>
                <div class="rekening-info">
                    <h4>Nomor Rekening</h4>
                    <div class="nomor">0821 3191 3839 9383 92</div>
                    <p><strong>BRI</strong> a.n Yayasan Sosial Bina Umat Al Mu'thi</p>
                    <p style="margin-top: 15px; font-size: 12px; color: #888;">
                        <i class="fas fa-info-circle"></i> Silahkan transfer donasi terlebih dahulu, 
                        kemudian upload bukti transfer donasi di kolom samping
                    </p>
                </div>
                <div class="qris-image">
                    <img src="../assets/image/qris.jpeg" alt="QRIS Pembayaran" onerror="this.src='../assets/image/qris.jpeg'">
                    <p style="margin-top: 10px; font-size: 12px; color: #888;">Scan QRIS untuk donasi cepat</p>
                </div>
            </div>
            
            <!-- FORM DONASI -->
            <div class="form-donasi">
                <h3><i class="fas fa-edit"></i> Form Donasi</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label>ID Donasi</label>
                            <input type="text" value="<?php echo $id_donasi; ?>" disabled style="background:#f5f5f5;">
                        </div>
                        <div class="form-group">
                            <label>Tanggal Donasi</label>
                            <input type="text" value="<?php echo date('d/m/Y'); ?>" disabled style="background:#f5f5f5;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Donatur</label>
                        <input type="text" value="<?php echo htmlspecialchars($currentUser['nama_lengkap']); ?>" disabled style="background:#f5f5f5;">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kategori Donasi</label>
                            <select name="kategori_id" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($kategoris as $k): ?>
                                    <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['nama_kategori']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nominal Donasi (Rp)</label>
                            <input type="number" name="nominal" placeholder="Masukkan nominal" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="keterangan" rows="2" placeholder="Keterangan donasi (opsional)"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Upload Bukti Transfer</label>
                        <input type="file" name="bukti_transfer" accept="image/*,application/pdf" required>
                        <small style="color:#888;">Format: JPG, PNG, PDF (Max 2MB)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Titip Do'a</label>
                        <textarea name="catatan_doa" rows="3" placeholder="Tulis do'a atau pesan yang ingin disampaikan (opsional)"></textarea>
                        <small style="color:#888;">Do'a akan disampaikan ke pengasuh panti</small>
                    </div>
                    
                    <button type="submit" name="donasi" class="btn-donasi">
                        <i class="fas fa-paper-plane"></i> Kirim Donasi
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>