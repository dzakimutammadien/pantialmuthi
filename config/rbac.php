<?php
// ======================================================
// FILE: config/rbac.php
// PERMISSION & AKSES CONTROL (diatur sekali di awal)
// ======================================================

// Definisi permission per role
$permissions = [
    'admin' => [
        // Kelola user
        'users.view', 'users.create', 'users.edit', 'users.delete', 'users.toggle_active',
        // Master data
        'kategori_donasi.view', 'kategori_donasi.create', 'kategori_donasi.edit', 'kategori_donasi.delete',
        'kategori_role.view', 'kategori_role.create', 'kategori_role.edit', 'kategori_role.delete',
        // Verifikasi
        'verifikasi_donasi.view', 'verifikasi_donasi.approve', 'verifikasi_donasi.reject',
        'verifikasi_pengeluaran.view', 'verifikasi_pengeluaran.approve', 'verifikasi_pengeluaran.reject',
        // Laporan & Log
        'laporan.view', 'log_aktivitas.view',
        // Profil
        'profil.edit'
    ],
    
    'pengasuh' => [
        // Anak asuh
        'anak_asuh.view', 'anak_asuh.create', 'anak_asuh.edit', 'anak_asuh.delete',
        // Pengeluaran (edit/hapus hanya milik sendiri & status pending)
        'pengeluaran.view', 'pengeluaran.create', 'pengeluaran.edit_own', 'pengeluaran.delete_own',
        // Doa
        'doa.view', 'doa.verify',
        // Laporan
        'laporan.view',
        // Profil
        'profil.edit'
    ],
    
    'donatur' => [
        // Donasi
        'donasi.view', 'donasi.create',
        // Histori
        'histori.view',
        // Doa sendiri
        'doa_saya.view',
        // Profil
        'profil.edit'
    ]
];

// Cek apakah user memiliki permission
function hasPermission($permission) {
    global $permissions;
    $role = getUserRole();
    
    if (!$role || !isset($permissions[$role])) {
        return false;
    }
    
    return in_array($permission, $permissions[$role]);
}

// Cek permission dan redirect jika tidak punya akses
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        header('Location: ../dashboard.php?error=access_denied');
        exit();
    }
}
?>