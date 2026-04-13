<?php
// ======================================================
// FILE: donatur/histori.php
// HALAMAN RIWAYAT DONASI UNTUK DONATUR
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('donatur');

$currentUser = getCurrentUser();

// ======================================================
// PROSES HAPUS DONASI (hanya jika status pending/failed)
// ======================================================
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    // Cek kepemilikan dan status
    $check = mysqli_query($conn, "SELECT status, bukti_transfer FROM donasi WHERE id = $id AND user_id = " . $currentUser['id']);
    $data = mysqli_fetch_assoc($check);
    
    if ($data) {
        if (in_array($data['status'], ['pending', 'failed'])) {
            // Hapus file bukti transfer
            if ($data['bukti_transfer'] && file_exists('../assets/uploads/bukti_transfer/' . $data['bukti_transfer'])) {
                unlink('../assets/uploads/bukti_transfer/' . $data['bukti_transfer']);
            }
            
            $sql = "DELETE FROM donasi WHERE id = $id";
            if (mysqli_query($conn, $sql)) {
                logActivity($currentUser['id'], "Menghapus donasi ID: $id");
                $_SESSION['success'] = "Donasi berhasil dihapus!";
            } else {
                $_SESSION['error'] = "Gagal menghapus donasi!";
            }
        } else {
            $_SESSION['error'] = "Donasi yang sudah sukses tidak bisa dihapus!";
        }
    }
    header("Location: histori.php");
    exit();
}

// ======================================================
// FILTER & PAGINATION
// ======================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_kategori = isset($_GET['kategori']) ? (int)$_GET['kategori'] : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_periode = isset($_GET['periode']) ? mysqli_real_escape_string($conn, $_GET['periode']) : '';

$where = "WHERE d.user_id = " . $currentUser['id'];

if ($search != '') {
    $where .= " AND (d.keterangan LIKE '%$search%' OR k.nama_kategori LIKE '%$search%')";
}
if ($filter_kategori != '' && $filter_kategori > 0) {
    $where .= " AND d.kategori_id = $filter_kategori";
}
if ($filter_status != '' && $filter_status != 'semua') {
    $where .= " AND d.status = '$filter_status'";
}
if ($filter_periode != '' && $filter_periode != 'semua') {
    switch ($filter_periode) {
        case 'hari_ini':
            $where .= " AND DATE(d.tanggal_donasi) = CURDATE()";
            break;
        case 'minggu_ini':
            $where .= " AND YEARWEEK(d.tanggal_donasi, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'bulan_ini':
            $where .= " AND MONTH(d.tanggal_donasi) = MONTH(CURDATE()) AND YEAR(d.tanggal_donasi) = YEAR(CURDATE())";
            break;
    }
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Total data untuk pagination
$total_sql = "SELECT COUNT(*) as total FROM donasi d 
              JOIN kategori_donasi k ON d.kategori_id = k.id 
              $where";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

// Ambil data donasi
$sql = "SELECT d.*, k.nama_kategori 
        FROM donasi d 
        JOIN kategori_donasi k ON d.kategori_id = k.id 
        $where 
        ORDER BY d.tanggal_donasi DESC 
        LIMIT $offset, $limit";
$donasiList = query($sql);

// Hitung total donasi yang sukses
$total_sukses_sql = "SELECT SUM(nominal) as total FROM donasi WHERE user_id = " . $currentUser['id'] . " AND status = 'success'";
$total_sukses_result = mysqli_query($conn, $total_sukses_sql);
$totalDonasiSukses = mysqli_fetch_assoc($total_sukses_result)['total'] ?? 0;

// Ambil kategori untuk filter
$kategoris = query("SELECT * FROM kategori_donasi WHERE tipe IN ('donasi', 'both') ORDER BY nama_kategori ASC");

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Donasi - Panti Asuhan Al-Muthi</title>
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
        
        /* CONTENT */
        .content-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .filter-section { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-section input, .filter-section select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; }
        .filter-section input { flex: 2; }
        .filter-section select { flex: 1; }
        .btn-filter, .btn-reset { padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-filter { background: #50c878; color: white; }
        .btn-reset { background: #6c757d; color: white; text-decoration: none; display: inline-block; }
        
        /* TABLE */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; vertical-align: middle; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .status-pending { background: #fff3e0; color: #ff9800; }
        .status-success { background: #e8f5e9; color: #4caf50; }
        .status-failed { background: #ffebee; color: #f44336; }
        .btn-action { padding: 5px 10px; border: none; border-radius: 8px; cursor: pointer; margin: 2px; font-size: 12px; }
        .btn-detail { background: #17a2b8; color: white; }
        .btn-edit { background: #50c878; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        
        /* PAGINATION */
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; text-decoration: none; }
        .pagination a { background: #f0f2f5; color: #555; }
        .pagination .active { background: #50c878; color: white; }
        
        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 500px; max-width: 90%; padding: 25px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .detail-item { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
        .detail-label { font-weight: 600; font-size: 12px; color: #888; margin-bottom: 5px; }
        .detail-value { font-size: 14px; color: #333; }
        .detail-image { text-align: center; margin: 15px 0; }
        .detail-image img { max-width: 200px; border-radius: 10px; }
        .btn-cancel { background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .total-info { margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px; text-align: right; }
        .total-info span { font-weight: 700; font-size: 18px; color: #50c878; }
        
        @media (max-width: 768px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } }
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
            <div class="menu-item active" onclick="location.href='histori.php'"><i class="fas fa-history"></i><span>Riwayat Donasi</span></div>
            <div class="menu-item" onclick="location.href='laporan_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Pengeluaran Panti</span></div>
            <div class="menu-item" onclick="location.href='doa_saya.php'"><i class="fas fa-pray"></i><span>Laporan Khususon Do'a</span></div>
            <div class="menu-item" onclick="location.href='laporan.php'"><i class="fas fa-chart-line"></i><span>Laporan</span></div>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Riwayat Donasi</h2>
                <p>Histori donasi yang telah Anda lakukan</p>
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
            
            <!-- FILTER -->
            <form method="GET" action="" class="filter-section">
                <input type="text" name="search" placeholder="Cari kategori atau keterangan..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="periode">
                    <option value="semua">Semua Periode</option>
                    <option value="hari_ini" <?php echo $filter_periode == 'hari_ini' ? 'selected' : ''; ?>>Hari Ini</option>
                    <option value="minggu_ini" <?php echo $filter_periode == 'minggu_ini' ? 'selected' : ''; ?>>Minggu Ini</option>
                    <option value="bulan_ini" <?php echo $filter_periode == 'bulan_ini' ? 'selected' : ''; ?>>Bulan Ini</option>
                </select>
                <select name="kategori">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($kategoris as $k): ?>
                        <option value="<?php echo $k['id']; ?>" <?php echo $filter_kategori == $k['id'] ? 'selected' : ''; ?>><?php echo $k['nama_kategori']; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="semua">Semua Status</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                    <option value="success" <?php echo $filter_status == 'success' ? 'selected' : ''; ?>>Sukses</option>
                    <option value="failed" <?php echo $filter_status == 'failed' ? 'selected' : ''; ?>>Tidak Valid</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="histori.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
            </form>
            
            <!-- TABLE -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Kategori Donasi</th>
                            <th>Keterangan</th>
                            <th>Nominal</th>
                            <th>Status Donasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($donasiList) > 0): $no = $offset + 1; foreach ($donasiList as $d): ?>
                            <?php 
                                $statusClass = '';
                                $statusText = '';
                                if ($d['status'] == 'pending') {
                                    $statusClass = 'status-pending';
                                    $statusText = 'Menunggu';
                                } elseif ($d['status'] == 'success') {
                                    $statusClass = 'status-success';
                                    $statusText = 'Sukses';
                                } else {
                                    $statusClass = 'status-failed';
                                    $statusText = 'Tidak Valid';
                                }
                                $canEditDelete = in_array($d['status'], ['pending', 'failed']);
                            ?>
                            <tr>
                                <td><?php echo $d['id']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($d['tanggal_donasi'])); ?></td>
                                <td><?php echo htmlspecialchars($d['nama_kategori']); ?></td>
                                <td><?php echo htmlspecialchars($d['keterangan']) ?: '-'; ?></td>
                                <td>Rp <?php echo number_format($d['nominal'], 0, ',', '.'); ?></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <td>
                                    <button class="btn-action btn-detail" onclick="openDetailModal(<?php echo $d['id']; ?>)"><i class="fas fa-info-circle"></i> Detail</button>
                                    <?php if ($canEditDelete): ?>
                                        <button class="btn-action btn-edit" onclick="location.href='edit_donasi.php?id=<?php echo $d['id']; ?>'"><i class="fas fa-edit"></i> Edit</button>
                                        <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $d['id']; ?>)"><i class="fas fa-trash"></i> Hapus</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:40px;">Belum ada data donasi</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&periode=<?php echo $filter_periode; ?>&kategori=<?php echo $filter_kategori; ?>&status=<?php echo $filter_status; ?>">« Sebelumnya</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&periode=<?php echo $filter_periode; ?>&kategori=<?php echo $filter_kategori; ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&periode=<?php echo $filter_periode; ?>&kategori=<?php echo $filter_kategori; ?>&status=<?php echo $filter_status; ?>">Selanjutnya »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- TOTAL DONASI SUKSES -->
            <div class="total-info">
                <strong>Total Donasi (Sukses):</strong> <span>Rp <?php echo number_format($totalDonasiSukses, 0, ',', '.'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- MODAL DETAIL -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detail Donasi</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div id="detailContent"></div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal()">Tutup</button>
            </div>
        </div>
    </div>
    
    <script>
        function closeModal() {
            document.getElementById('detailModal').classList.remove('show');
        }
        
        function openDetailModal(id) {
            fetch('get_donasi.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let d = data.data;
                        let statusClass = d.status == 'pending' ? 'status-pending' : (d.status == 'success' ? 'status-success' : 'status-failed');
                        let statusText = d.status == 'pending' ? 'Menunggu' : (d.status == 'success' ? 'Sukses' : 'Tidak Valid');
                        let imageHtml = d.bukti_transfer ? `<div class="detail-image"><img src="../assets/uploads/bukti_transfer/${d.bukti_transfer}" onclick="window.open(this.src)"></div>` : '<div class="detail-image"><p>Tidak ada bukti transfer</p></div>';
                        
                        document.getElementById('detailContent').innerHTML = `
                            <div class="detail-item"><div class="detail-label">ID Donasi</div><div class="detail-value">${d.id}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal Donasi</div><div class="detail-value">${d.tanggal_donasi}</div></div>
                            <div class="detail-item"><div class="detail-label">Kategori</div><div class="detail-value">${d.nama_kategori}</div></div>
                            <div class="detail-item"><div class="detail-label">Nominal</div><div class="detail-value">Rp ${new Intl.NumberFormat('id-ID').format(d.nominal)}</div></div>
                            <div class="detail-item"><div class="detail-label">Keterangan</div><div class="detail-value">${d.keterangan || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Catatan Doa</div><div class="detail-value">${d.catatan_doa || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value"><span class="status-badge ${statusClass}">${statusText}</span></div></div>
                            ${imageHtml}
                        `;
                        document.getElementById('detailModal').classList.add('show');
                    } else {
                        alert('Gagal mengambil data donasi');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan');
                });
        }
        
        function confirmDelete(id) {
            if (confirm('Apakah Anda yakin ingin menghapus donasi ini? Data yang dihapus tidak dapat dikembalikan.')) {
                window.location.href = 'histori.php?hapus=' + id;
            }
        }
        
        window.onclick = function(event) {
            let modal = document.getElementById('detailModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>