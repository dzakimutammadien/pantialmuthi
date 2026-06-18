<?php
// ======================================================
// FILE: edit_donasi_program.php
// HALAMAN EDIT DONASI PROGRAM
// ======================================================

require_once 'config/database.php';
require_once 'config/session.php';

if (!isLoggedIn() || getUserRole() != 'donatur') {
    header('Location: login.php');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Ambil data donasi program
$sql = "SELECT dp.*, p.nama_program 
        FROM donasi_program dp 
        JOIN program_donasi p ON dp.program_id = p.id 
        WHERE dp.id = $id AND dp.user_id = " . $_SESSION['user_id'];
$donasi = query($sql);

if (count($donasi) == 0) {
    header('Location: histori.php');
    exit();
}
$donasi = $donasi[0];

// Cek status harus pending atau failed
if (!in_array($donasi['status'], ['pending', 'failed'])) {
    $_SESSION['error'] = "Donasi yang sudah sukses tidak bisa diedit!";
    header('Location: histori.php');
    exit();
}

// Proses Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nominal = (float)$_POST['nominal'];
    $pesan = mysqli_real_escape_string($conn, $_POST['pesan']);
    $is_anonim = isset($_POST['is_anonim']) ? 1 : 0;
    $nama_donatur = mysqli_real_escape_string($conn, $_POST['nama_donatur']);
    
    $nama_donatur_final = ($is_anonim == 1) ? 'Hamba Allah' : ($nama_donatur ?: 'Hamba Allah');
    
    $sql_update = "UPDATE donasi_program SET 
                   nominal = $nominal,
                   pesan = '$pesan',
                   is_anonim = $is_anonim,
                   nama_donatur = '$nama_donatur_final'
                   WHERE id = $id AND user_id = " . $_SESSION['user_id'];
    
    if (mysqli_query($conn, $sql_update)) {
        $_SESSION['success'] = "Donasi program berhasil diupdate!";
        header('Location: histori.php');
        exit();
    } else {
        $error = "Gagal mengupdate: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Donasi Program</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; min-height: 100vh; padding: 40px 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { margin-bottom: 20px; color: #1a3a2a; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 10px; }
        .btn-save { background: #50c878; color: white; padding: 12px; border: none; border-radius: 10px; cursor: pointer; width: 100%; font-weight: 600; }
        .btn-back { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; text-decoration: none; display: inline-block; margin-bottom: 20px; }
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        .radio-group { display: flex; gap: 20px; margin: 10px 0; }
        .radio-group label { display: flex; align-items: center; gap: 5px; font-weight: normal; }
    </style>
</head>
<body>
<div class="container">
    <a href="histori.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
    <div class="card">
        <h2><i class="fas fa-edit"></i> Edit Donasi Program</h2>
        <p><strong>Program:</strong> <?php echo $donasi['nama_program']; ?></p>
        <p><strong>Status:</strong> <?php echo ucfirst($donasi['status']); ?></p>
        <hr style="margin: 15px 0;">
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Nominal Donasi (Rp)</label>
                <input type="number" name="nominal" value="<?php echo $donasi['nominal']; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Tampilkan Nama di Publik</label>
                <div class="radio-group">
                    <label><input type="radio" name="is_anonim" value="0" <?php echo $donasi['is_anonim'] == 0 ? 'checked' : ''; ?>> ✅ Ya, tampilkan nama asli</label>
                    <label><input type="radio" name="is_anonim" value="1" <?php echo $donasi['is_anonim'] == 1 ? 'checked' : ''; ?>> 🙈 Anonim</label>
                </div>
            </div>
            
            <div class="form-group" id="nama_field">
                <label>Nama (jika ditampilkan)</label>
                <input type="text" name="nama_donatur" value="<?php echo $donasi['nama_donatur'] != 'Hamba Allah' ? $donasi['nama_donatur'] : ''; ?>" placeholder="Masukkan nama Anda">
            </div>
            
            <div class="form-group">
                <label>Pesan (Opsional)</label>
                <textarea name="pesan" rows="3"><?php echo $donasi['pesan']; ?></textarea>
            </div>
            
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Simpan Perubahan</button>
        </form>
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