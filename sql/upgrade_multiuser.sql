-- MIGRASI APLIKASI LAB-LOGBOOK LAMA -> MULTI-USER
-- Jalankan sekali pada database lab_logbook yang SUDAH ADA.
-- Tidak menghapus tabel alat/logbook dan tidak menghapus akun admin lama.

USE lab_logbook;

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=@db AND TABLE_NAME='admin' AND COLUMN_NAME='role')=0,
  "ALTER TABLE admin ADD COLUMN role ENUM('admin','petugas','viewer') NOT NULL DEFAULT 'admin' AFTER nama",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=@db AND TABLE_NAME='admin' AND COLUMN_NAME='aktif')=0,
  "ALTER TABLE admin ADD COLUMN aktif TINYINT(1) NOT NULL DEFAULT 1 AFTER role",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE admin SET role='admin' WHERE role IS NULL OR role='';

-- 6 akun awal (akun admin lama dengan username yang sama tidak ditimpa).
-- Password sementara:
-- admin123, petugas123, viewer123
INSERT IGNORE INTO admin(username,password,nama,role,aktif) VALUES
('admin','$2y$12$FF2zZfIGZkboAoA0TLN8aO84dJtLqjEKA55L0nGvfCSnxRKlWc39C','Administrator Laboratorium','admin',1),
('petugas1','$2y$12$d.UFoP6VMl/e09fCPMHiz.RfEFEGRgCPq7wXl2lJEth/zZ0.rbDnu','Petugas Laboratorium 1','petugas',1),
('petugas2','$2y$12$d.UFoP6VMl/e09fCPMHiz.RfEFEGRgCPq7wXl2lJEth/zZ0.rbDnu','Petugas Laboratorium 2','petugas',1),
('viewer1','$2y$12$Qn7xnwt2TtzZjLaADDcBveIcxj87Hyh5GDRuPG2aSmyYgpdfC5iEy','Viewer 1','viewer',1),
('viewer2','$2y$12$Qn7xnwt2TtzZjLaADDcBveIcxj87Hyh5GDRuPG2aSmyYgpdfC5iEy','Viewer 2','viewer',1),
('viewer3','$2y$12$Qn7xnwt2TtzZjLaADDcBveIcxj87Hyh5GDRuPG2aSmyYgpdfC5iEy','Viewer 3','viewer',1);


-- MIGRASI DATA ALAT: tambah informasi kalibrasi dan kondisi alat.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='alat' AND COLUMN_NAME='status_kalibrasi')=0,
"ALTER TABLE alat ADD COLUMN status_kalibrasi VARCHAR(40) NOT NULL DEFAULT 'Belum Kalibrasi' AFTER lokasi","SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='alat' AND COLUMN_NAME='tanggal_kalibrasi')=0,
"ALTER TABLE alat ADD COLUMN tanggal_kalibrasi DATE NULL AFTER status_kalibrasi","SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='alat' AND COLUMN_NAME='kondisi_alat')=0,
"ALTER TABLE alat ADD COLUMN kondisi_alat VARCHAR(60) NOT NULL DEFAULT 'Baik' AFTER tanggal_kalibrasi","SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE alat SET status_kalibrasi='Belum Kalibrasi' WHERE status_kalibrasi IS NULL OR status_kalibrasi='';
UPDATE alat SET kondisi_alat='Baik' WHERE kondisi_alat IS NULL OR kondisi_alat='';
