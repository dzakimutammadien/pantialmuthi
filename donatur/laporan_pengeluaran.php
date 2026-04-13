<?php
// ======================================================
// FILE: donatur/laporan_pengeluaran.php
// HALAMAN LAPORAN PENGELUARAN PANTI UNTUK DONATUR
// (HANYA MENAMPILKAN PENGELUARAN DENGAN STATUS DISETUJUI)
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('donatur');

$currentUser = getCurrentUser();

// ======================================================
// FILTER & PAGINATION
// ======================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_kategori = isset($_GET['kategori']) ? (int)$_GET['kategori'] : '';
$filter_pengasuh = isset($_GET['pengasuh']) ? (int)$_GET['pengasuh'] : '';
$filter_periode = isset($_GET['periode']) ? mysqli_real_escape_string($conn, $_GET['periode']) : '';

// HANYA MENAMPILKAN STATUS DISETUJUI
$where = "WHERE p.status = 'disetujui'";

if ($search != '') {
    $where .= " AND (p.deskripsi LIKE '%$search%' OR u.nama_lengkap LIKE '%$search%')";
}
if ($filter_kategori != '' && $filter_kategori > 0) {
    $where .= " AND p.kategori_id = $filter_kategori";
}
if ($filter_pengasuh != '' && $filter_pengasuh > 0) {
    $where .= " AND p.created_by = $filter_pengasuh";
}
if ($filter_periode != '' && $filter_periode != 'semua') {
    switch ($filter_periode) {
        case 'minggu_ini':
            $where .= " AND YEARWEEK(p.tanggal_pengeluaran, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'bulan_ini':
            $where .= " AND MONTH(p.tanggal_pengeluaran) = MONTH(CURDATE()) AND YEAR(p.tanggal_pengeluaran) = YEAR(CURDATE())";
            break;
    }
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Total data
$total_sql = "SELECT COUNT(*) as total FROM pengeluaran p 
              JOIN users u ON p.created_by = u.id 
              $where";
$total_result = mysqli_query($conn, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

// Ambil data pengeluaran
$sql = "SELECT p.*, u.nama_lengkap as pengasuh_nama, k.nama_kategori
        FROM pengeluaran p 
        JOIN users u ON p.created_by = u.id 
        JOIN kategori_donasi k ON p.kategori_id = k.id 
        $where 
        ORDER BY p.tanggal_pengeluaran DESC 
        LIMIT $offset, $limit";
$pengeluaranList = query($sql);

// Hitung total pengeluaran yang tampil
$total_sql_sum = "SELECT SUM(p.nominal) as total FROM pengeluaran p 
                  JOIN users u ON p.created_by = u.id 
                  $where";
$total_result_sum = mysqli_query($conn, $total_sql_sum);
$totalPengeluaran = mysqli_fetch_assoc($total_result_sum)['total'] ?? 0;

// Ambil kategori untuk filter
$kategoris = query("SELECT * FROM kategori_donasi WHERE tipe IN ('pengeluaran', 'both') ORDER BY nama_kategori ASC");

// Ambil daftar pengasuh untuk filter
$pengasuhList = query("SELECT id, nama_lengkap FROM users WHERE role_id = (SELECT id FROM roles WHERE nama_role = 'pengasuh') ORDER BY nama_lengkap ASC");

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pengeluaran Panti - Panti Asuhan Al-Muthi</title>
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
        
        /* FILTER */
        .filter-section { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-section input, .filter-section select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; }
        .filter-section input { flex: 2; }
        .filter-section select { flex: 1; }
        .btn-filter, .btn-reset { padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-filter { background: #50c878; color: white; }
        .btn-reset { background: #6c757d; color: white; text-decoration: none; display: inline-block; }
        
        /* TABLE */
        .table-wrapper { overflow-x: auto; width: 100%; }
        table { width: 100%; min-width: 700px; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; vertical-align: middle; }
        th { background: #f8f9fa; font-size: 13px; font-weight: 600; color: #666; }
        td { border-bottom: 1px solid #eee; font-size: 13px; color: #555; }
        td:last-child { white-space: nowrap; }
        
        .btn-action { padding: 5px 10px; border: none; border-radius: 8px; cursor: pointer; margin: 2px; font-size: 12px; display: inline-block; white-space: nowrap; }
        .btn-detail { background: #17a2b8; color: white; }
        
        /* PAGINATION */
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; text-decoration: none; }
        .pagination a { background: #f0f2f5; color: #555; }
        .pagination .active { background: #50c878; color: white; }
        
        .total-info { margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px; text-align: right; }
        .total-info span { font-weight: 700; font-size: 18px; color: #50c878; }
        
        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 500px; max-width: 90%; padding: 25px; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { font-size: 24px; cursor: pointer; }
        .detail-item { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
        .detail-label { font-weight: 600; font-size: 12px; color: #888; margin-bottom: 5px; }
        .detail-value { font-size: 14px; color: #333; }
        .detail-image { text-align: center; margin: 15px 0; }
        .detail-image img { max-width: 100%; max-height: 200px; border-radius: 10px; }
        .modal-footer { display: flex; justify-content: flex-end; margin-top: 20px; }
        .btn-cancel { background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; }
        
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
            <div class="menu-item" onclick="location.href='histori.php'"><i class="fas fa-history"></i><span>Riwayat Donasi</span></div>
            <div class="menu-item active" onclick="location.href='laporan_pengeluaran.php'"><i class="fas fa-money-bill-wave"></i><span>Laporan Pengeluaran Panti</span></div>
            <div class="menu-item" onclick="location.href='doa_saya.php'"><i class="fas fa-pray"></i><span>Laporan Khusus Do'a</span></div>
            <div class="menu-item" onclick="location.href='laporan.php'"><i class="fas fa-chart-line"></i><span>Laporan</span></div>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">
                <h2>Laporan Pengeluaran Panti</h2>
                <p>Data pengeluaran panti yang telah diverifikasi</p>
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
            <!-- FILTER -->
            <form method="GET" action="" class="filter-section">
                <input type="text" name="search" placeholder="Cari keterangan atau pengasuh..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="periode">
                    <option value="semua">Semua Periode</option>
                    <option value="minggu_ini" <?php echo $filter_periode == 'minggu_ini' ? 'selected' : ''; ?>>Minggu Ini</option>
                    <option value="bulan_ini" <?php echo $filter_periode == 'bulan_ini' ? 'selected' : ''; ?>>Bulan Ini</option>
                </select>
                <select name="kategori">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($kategoris as $k): ?>
                        <option value="<?php echo $k['id']; ?>" <?php echo $filter_kategori == $k['id'] ? 'selected' : ''; ?>><?php echo $k['nama_kategori']; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="pengasuh">
                    <option value="">Semua Pengasuh</option>
                    <?php foreach ($pengasuhList as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $filter_pengasuh == $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nama_lengkap']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="laporan_pengeluaran.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
            </form>
            
            <!-- TABLE -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Pengasuh</th>
                            <th>Tanggal</th>
                            <th>Kategori</th>
                            <th>Keterangan</th>
                            <th>Jumlah</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pengeluaranList) > 0): $no = $offset + 1; foreach ($pengeluaranList as $p): ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td><?php echo htmlspecialchars($p['pengasuh_nama']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p['tanggal_pengeluaran'])); ?></td>
                            <td><?php echo htmlspecialchars($p['nama_kategori']); ?></td>
                            <td><?php echo htmlspecialchars($p['deskripsi']) ?: '-'; ?></td>
                            <td>Rp <?php echo number_format($p['nominal'], 0, ',', '.'); ?></td>
                            <td>
                                <button class="btn-action btn-detail" onclick="openDetailModal(<?php echo $p['id']; ?>)"><i class="fas fa-info-circle"></i> Detail</button>
                        </td>
                         </tr
                        <?php endforeach; else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:40px;">Belum ada data pengeluaran yang diverifikasi</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&periode=<?php echo $filter_periode; ?>&kategori=<?php echo $filter_kategori; ?>&pengasuh=<?php echo $filter_pengasuh; ?>">« Sebelumnya</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&periode=<?php echo $filter_periode; ?>&kategori=<?php echo $filter_kategori; ?>&pengasuh=<?php echo $filter_pengasuh; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&periode=<?php echo $filter_periode; ?>&kategori=<?php echo $filter_kategori; ?>&pengasuh=<?php echo $filter_pengasuh; ?>">Selanjutnya »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- TOTAL PENGELUARAN -->
            <div class="total-info">
                <strong>Total Pengeluaran :</strong> <span>Rp <?php echo number_format($totalPengeluaran, 0, ',', '.'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- MODAL DETAIL -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detail Pengeluaran Panti</h3>
                <span class="close-modal" onclick="closeModal('detailModal')">&times;</span>
            </div>
            <div id="detailContent"></div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal('detailModal')">Tutup</button>
            </div>
        </div>
    </div>
    
    <script>
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function openDetailModal(id) {
            fetch('get_pengeluaran.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let p = data.data;
                        let imageHtml = '';
                        if (p.bukti_foto) {
                            imageHtml = `<div class="detail-image"><img src="../assets/uploads/pengeluaran/${p.bukti_foto}" onclick="window.open(this.src)"></div>`;
                        } else {
                            imageHtml = '<div class="detail-image"><p>Tidak ada gambar bukti</p></div>';
                        }
                        
                        document.getElementById('detailContent').innerHTML = `
                            <div class="detail-item"><div class="detail-label">ID</div><div class="detail-value">${p.id}</div></div>
                            <div class="detail-item"><div class="detail-label">Pengasuh</div><div class="detail-value">${p.pengasuh_nama}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal</div><div class="detail-value">${p.tanggal_pengeluaran}</div></div>
                            <div class="detail-item"><div class="detail-label">Kategori</div><div class="detail-value">${p.nama_kategori}</div></div>
                            <div class="detail-item"><div class="detail-label">Jumlah</div><div class="detail-value">Rp ${new Intl.NumberFormat('id-ID').format(p.nominal)}</div></div>
                            <div class="detail-item"><div class="detail-label">Keterangan</div><div class="detail-value">${p.deskripsi || '-'}</div></div>
                            ${imageHtml}
                        `;
                        document.getElementById('detailModal').classList.add('show');
                    }
                });
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>