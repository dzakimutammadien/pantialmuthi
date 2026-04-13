<?php
// ======================================================
// FILE: admin/anak_asuh.php
// HALAMAN DATA ANAK ASUH UNTUK ADMIN (READ ONLY)
// ======================================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/rbac.php';

requireRole('admin');

$currentUser = getCurrentUser();

// Ambil data anak asuh
$sql = "SELECT a.*, u.nama_lengkap as pengasuh_nama
        FROM anak_asuh a 
        JOIN users u ON a.created_by = u.id 
        ORDER BY a.created_at DESC";
$anakAsuh = query($sql);

echo "<!-- Jumlah data: " . count($anakAsuh) . " -->";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Anak Asuh - Admin Panti Asuhan Al-Muthi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; overflow-x: hidden; }
        
        /* SIDEBAR */
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
        .submenu { padding-left: 56px; max-height: 0; overflow: hidden; transition: max-height 0.3s; }
        .submenu.open { max-height: 300px; }
        .submenu-item { padding: 10px 20px; display: flex; align-items: center; gap: 12px; cursor: pointer; color: rgba(255,255,255,0.7); font-size: 13px; }
        .submenu-item:hover { color: #50c878; padding-left: 25px; }
        .submenu-item i { width: 20px; font-size: 14px; }
        .menu-item.has-submenu .arrow { margin-left: auto; transition: transform 0.3s; font-size: 12px; }
        .menu-item.has-submenu.open .arrow { transform: rotate(180deg); }
        
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
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; vertical-align: middle; }
        .foto-thumb { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .status-aktif { background: #e8f5e9; color: #4caf50; padding: 4px 12px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-keluar { background: #ffebee; color: #f44336; padding: 4px 12px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .btn-detail { background: #17a2b8; color: white; padding: 5px 10px; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; }
        
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
        .detail-image img { max-width: 100px; max-height: 100px; border-radius: 50%; object-fit: cover; }
        .btn-cancel { background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .modal-footer { display: flex; justify-content: flex-end; margin-top: 20px; }
        
        @media (max-width: 768px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
   <!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="../assets/image/almuthi.png" alt="Logo Al-Muthi" class="sidebar-logo" onerror="this.style.display='none'">
        <div>
            <h3>Panti Asuhan</h3>
            <p>Al-Muthi</p>
        </div>
    </div>
    <div class="sidebar-menu">
        <!-- Dashboard -->
        <div class="menu-item" onclick="location.href='dashboard.php'">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </div>
        
        <!-- Manajemen User -->
        <div class="menu-item" onclick="location.href='users.php'">
            <i class="fas fa-users"></i>
            <span>Manajemen User</span>
        </div>
        
        <!-- Transaksi (dengan submenu) -->
        <div class="menu-item has-submenu" onclick="toggleSubmenu(this)">
            <i class="fas fa-exchange-alt"></i>
            <span>Transaksi</span>
            <i class="fas fa-chevron-down arrow"></i>
        </div>
        <div class="submenu">
            <div class="submenu-item" onclick="location.href='donasi_donatur.php'">
                <i class="fas fa-hand-holding-heart"></i>
                <span>Donasi Donatur</span>
            </div>
            <div class="submenu-item" onclick="location.href='verifikasi_pengeluaran.php'">
                <i class="fas fa-money-bill-wave"></i>
                <span>Pengeluaran Panti</span>
            </div>
            <div class="submenu-item" onclick="location.href='laporan_keuangan.php'">
                <i class="fas fa-chart-line"></i>
                <span>Laporan Keuangan</span>
            </div>
        </div>
        
        <!-- Master Data (dengan submenu) -->
       <div class="menu-item has-submenu open" onclick="toggleSubmenu(this)">
    <i class="fas fa-database"></i>
    <span>Master Data</span>
    <i class="fas fa-chevron-down arrow"></i>
</div>
<div class="submenu open">
    <div class="submenu-item" onclick="location.href='kategori_donasi.php'">
        <i class="fas fa-tags"></i>
        <span>Kategori Transaksi</span>
    </div>
    <div class="submenu-item" onclick="location.href='kategori_role.php'">
        <i class="fas fa-user-tag"></i>
        <span>Kategori Role</span>
    </div>
    <div class="submenu-item active" onclick="location.href='anak_asuh.php'">
        <i class="fas fa-child"></i>
        <span>Data Anak Asuh</span>
    </div>
    <div class="submenu-item" onclick="location.href='doa_khusus.php'">
        <i class="fas fa-pray"></i>
        <span>Data Doa Khusus</span>
    </div>
</div>
    </div>
</div>
    <!-- MAIN CONTENT -->
<div class="main-content">
    <div class="topbar">
        <div class="page-title">
            <h2>Data Anak Asuh</h2>
            <p>Data anak asuh panti (Read Only)</p>
        </div>
        <div class="profile-dropdown">
            <div class="profile-icon"><i class="fas fa-cog"></i></div>
            <div class="dropdown-menu">
                <a href="profil.php"><i class="fas fa-user-circle"></i> Profil</a>
                <a href="log_aktivitas.php"><i class="fas fa-history"></i> Log Aktivitas</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
    
    <div class="content-card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Nama</th>
                        <th>Umur</th>
                        <th>Jenis Kelamin</th>
                        <th>Status</th>
                        <th>Pengasuh</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($anakAsuh) > 0): ?>
                        <?php foreach ($anakAsuh as $a): ?>
                        <tr>
                            <td><img src="../assets/uploads/anak_asuh/<?php echo $a['foto'] ?: 'default-anak.png'; ?>" class="foto-thumb" onerror="this.src='../assets/uploads/anak_asuh/default-anak.png'"></td>
                            <td><?php echo htmlspecialchars($a['nama_lengkap']); ?></td>
                            <td><?php echo $a['umur']; ?> tahun</td>
                            <td><?php echo $a['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                            <td><span class="status-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span></td>
                            <td><?php echo htmlspecialchars($a['pengasuh_nama']); ?></td>
                            <td><button class="btn-detail" onclick="openDetailModal(<?php echo $a['id']; ?>)"><i class="fas fa-info-circle"></i> Detail</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px;">Tidak ada data anak asuh</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
    <!-- MODAL DETAIL -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detail Anak Asuh</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div id="detailContent"></div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal()">Tutup</button>
            </div>
        </div>
    </div>
    
    <script>
        function toggleSubmenu(e) {
            e.classList.toggle('open');
            let s = e.nextElementSibling;
            if(s && s.classList.contains('submenu')) {
                s.classList.toggle('open');
            }
        }
        
        function closeModal() {
            document.getElementById('detailModal').classList.remove('show');
        }
        
        function openDetailModal(id) {
            fetch('get_anak_asuh.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        let a = data.data;
                        document.getElementById('detailContent').innerHTML = `
                            <div class="detail-image"><img src="../assets/uploads/anak_asuh/${a.foto || 'default-anak.png'}" onerror="this.src='../assets/uploads/anak_asuh/default-anak.png'"></div>
                            <div class="detail-item"><div class="detail-label">Nama</div><div class="detail-value">${a.nama_lengkap}</div></div>
                            <div class="detail-item"><div class="detail-label">Tempat Lahir</div><div class="detail-value">${a.tempat_lahir || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal Lahir</div><div class="detail-value">${a.tanggal_lahir}</div></div>
                            <div class="detail-item"><div class="detail-label">Umur</div><div class="detail-value">${a.umur} tahun</div></div>
                            <div class="detail-item"><div class="detail-label">Jenis Kelamin</div><div class="detail-value">${a.jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan'}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal Masuk</div><div class="detail-value">${a.tanggal_masuk}</div></div>
                            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">${a.status}</div></div>
                            <div class="detail-item"><div class="detail-label">Keterangan</div><div class="detail-value">${a.keterangan || '-'}</div></div>
                            <div class="detail-item"><div class="detail-label">Dibuat Oleh</div><div class="detail-value">${a.pengasuh_nama}</div></div>
                        `;
                        document.getElementById('detailModal').classList.add('show');
                    } else {
                        alert('Gagal mengambil data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan');
                });
        }
        
        window.onclick = function(event) {
            let modal = document.getElementById('detailModal');
            if(event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>