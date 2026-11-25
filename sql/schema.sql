CREATE DATABASE IF NOT EXISTS lab_logbook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lab_logbook;
CREATE TABLE IF NOT EXISTS admin (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(60) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, nama VARCHAR(120) NOT NULL, role ENUM('admin','petugas','viewer') NOT NULL DEFAULT 'admin', aktif TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS alat (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 kode_alat VARCHAR(40) NOT NULL UNIQUE,
 nama_alat VARCHAR(150) NOT NULL,
 lokasi VARCHAR(150) DEFAULT '',
 status_kalibrasi ENUM('Terkalibrasi','Belum Kalibrasi','Kadaluarsa') NOT NULL DEFAULT 'Belum Kalibrasi',
 tanggal_kalibrasi DATE NULL,
 interval_kalibrasi_bulan INT UNSIGNED NOT NULL DEFAULT 12,
 nomor_sertifikat VARCHAR(100) NOT NULL DEFAULT '',
 merk_tipe VARCHAR(150) NOT NULL DEFAULT '',
 tahun_perolehan YEAR NULL,
 kondisi_alat ENUM('Baik','Baik dengan catatan','Perlu pemeriksaan','Rusak') NOT NULL DEFAULT 'Baik',
 catatan TEXT NULL,
 aktif TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_alat_kalibrasi(status_kalibrasi,tanggal_kalibrasi)
);
CREATE TABLE IF NOT EXISTS logbook (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, alat_id INT UNSIGNED NOT NULL, nama_operator VARCHAR(150) NOT NULL, waktu_mulai DATETIME NOT NULL, waktu_selesai DATETIME NOT NULL, kondisi ENUM('Baik','Baik dengan catatan','Perlu pemeriksaan','Rusak') NOT NULL DEFAULT 'Baik', tujuan VARCHAR(255) DEFAULT '', nomor_sampel VARCHAR(100) DEFAULT '', catatan TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_waktu(waktu_mulai), INDEX idx_operator(nama_operator), CONSTRAINT fk_log_alat FOREIGN KEY(alat_id) REFERENCES alat(id));
