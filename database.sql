-- ======================================================
-- DATABASE SKRIPSI: pantialmuthi
-- RANCANG BANGUN RBAC PADA SISTEM INFORMASI DONASI
-- PANTI ASUHAN AL-MUTHI
-- ======================================================

DROP DATABASE IF EXISTS pantialmuthi;
CREATE DATABASE pantialmuthi;
USE pantialmuthi;

-- ======================================================
-- 1. TABEL roles
-- ======================================================
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_role VARCHAR(50) UNIQUE NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ======================================================
-- 2. TABEL users
-- ======================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    nama_lengkap VARCHAR(100),
    role_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    foto_profil VARCHAR(255) DEFAULT 'default.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- ======================================================
-- 3. TABEL kategori_donasi
-- ======================================================
CREATE TABLE kategori_donasi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ======================================================
-- 4. TABEL donasi
-- ======================================================
CREATE TABLE donasi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    kategori_id INT NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    bukti_transfer VARCHAR(255),
    catatan_doa TEXT,
    status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    tanggal_donasi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (kategori_id) REFERENCES kategori_donasi(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- ======================================================
-- 5. TABEL doa (otomatis dari donasi success yang ada catatan)
-- ======================================================
CREATE TABLE doa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donasi_id INT NOT NULL,
    user_id INT NOT NULL,
    catatan_doa TEXT NOT NULL,
    status_doa ENUM('pending', 'dibaca', 'didoakan') DEFAULT 'pending',
    dibaca_oleh INT,
    dibaca_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donasi_id) REFERENCES donasi(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (dibaca_oleh) REFERENCES users(id)
);

-- ======================================================
-- 6. TABEL anak_asuh
-- ======================================================
CREATE TABLE anak_asuh (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_lengkap VARCHAR(100) NOT NULL,
    jenis_kelamin ENUM('L', 'P') NOT NULL,
    tanggal_lahir DATE NOT NULL,
    tempat_lahir VARCHAR(100),
    pendidikan VARCHAR(50),
    foto VARCHAR(255) DEFAULT 'default-anak.png',
    keterangan TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ======================================================
-- 7. TABEL kategori_pengeluaran
-- ======================================================
CREATE TABLE kategori_pengeluaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ======================================================
-- 8. TABEL pengeluaran
-- ======================================================
CREATE TABLE pengeluaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kategori_id INT NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    deskripsi TEXT,
    bukti_foto VARCHAR(255),
    status ENUM('pending', 'disetujui', 'ditolak') DEFAULT 'pending',
    tanggal_pengeluaran DATE NOT NULL,
    created_by INT NOT NULL,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    catatan_verifikasi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori_pengeluaran(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- ======================================================
-- 9. TABEL log_aktivitas
-- ======================================================
CREATE TABLE log_aktivitas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    aktivitas VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ======================================================
-- 10. INSERT DATA DEFAULT
-- ======================================================

-- Insert roles
INSERT INTO roles (nama_role, deskripsi) VALUES
('admin', 'Administrator dengan akses penuh ke sistem'),
('pengasuh', 'Pengasuh yang mengelola anak asuh dan pengeluaran'),
('donatur', 'Donatur yang melakukan donasi');

-- Insert kategori donasi default
INSERT INTO kategori_donasi (nama_kategori, deskripsi) VALUES
('Donasi Pendidikan', 'Donasi untuk biaya pendidikan anak asuh'),
('Donasi Kesehatan', 'Donasi untuk kesehatan anak asuh'),
('Donasi Makanan', 'Donasi untuk kebutuhan makan sehari-hari'),
('Donasi Infrastruktur', 'Donasi untuk pembangunan dan perawatan panti'),
('Donasi Bebas', 'Donasi tanpa kategori khusus');

-- Insert kategori pengeluaran default
INSERT INTO kategori_pengeluaran (nama_kategori, deskripsi) VALUES
('Biaya Pendidikan', 'Biaya SPP, buku, seragam, dll'),
('Biaya Kesehatan', 'Biaya berobat, vaksin, vitamin'),
('Biaya Makan', 'Biaya konsumsi harian'),
('Biaya Listrik & Air', 'Tagihan utilitas'),
('Biaya Perawatan', 'Perawatan bangunan dan fasilitas'),
('Gaji Pengasuh', 'Honor pengasuh'),
('Lain-lain', 'Pengeluaran lainnya');

-- Insert user default (password: 12345678)
-- admin: admin / 12345678
-- pengasuh: pengasuh / 12345678  
-- donatur: donatur / 12345678

INSERT INTO users (username, password, email, nama_lengkap, role_id, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@pantialmuthi.com', 'Administrator Panti', 1, 1),
('pengasuh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengasuh@pantialmuthi.com', 'Ustadzah Siti', 2, 1),
('donatur', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'donatur@pantialmuthi.com', 'Budi Santoso', 3, 1);

-- ======================================================
-- 11. TRIGGER: Auto insert doa saat donasi success & ada catatan
-- ======================================================
DELIMITER $$
CREATE TRIGGER after_donasi_success
AFTER UPDATE ON donasi
FOR EACH ROW
BEGIN
    IF NEW.status = 'success' AND OLD.status != 'success' THEN
        IF NEW.catatan_doa IS NOT NULL AND NEW.catatan_doa != '' THEN
            INSERT INTO doa (donasi_id, user_id, catatan_doa, status_doa)
            VALUES (NEW.id, NEW.user_id, NEW.catatan_doa, 'pending');
        END IF;
    END IF;
END$$
DELIMITER ;

-- ======================================================
-- 12. VIEW untuk laporan
-- ======================================================

-- View laporan donasi per bulan
CREATE VIEW v_laporan_donasi AS
SELECT 
    DATE_FORMAT(tanggal_donasi, '%Y-%m') as bulan,
    k.nama_kategori,
    COUNT(d.id) as jumlah_transaksi,
    SUM(d.nominal) as total_nominal,
    SUM(CASE WHEN d.status = 'success' THEN d.nominal ELSE 0 END) as total_terverifikasi
FROM donasi d
JOIN kategori_donasi k ON d.kategori_id = k.id
GROUP BY DATE_FORMAT(tanggal_donasi, '%Y-%m'), k.nama_kategori;

-- View laporan pengeluaran per bulan
CREATE VIEW v_laporan_pengeluaran AS
SELECT 
    DATE_FORMAT(tanggal_pengeluaran, '%Y-%m') as bulan,
    k.nama_kategori,
    COUNT(p.id) as jumlah_transaksi,
    SUM(p.nominal) as total_nominal,
    SUM(CASE WHEN p.status = 'disetujui' THEN p.nominal ELSE 0 END) as total_disetujui
FROM pengeluaran p
JOIN kategori_pengeluaran k ON p.kategori_id = k.id
GROUP BY DATE_FORMAT(tanggal_pengeluaran, '%Y-%m'), k.nama_kategori;

-- ======================================================
-- SELESAI
-- ======================================================