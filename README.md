# LAB Manager — Sistem Manajemen Laboratorium

Versi pengembangan untuk Armbian + Nginx + PHP 8.2 + MariaDB.

## Fitur utama
- Dashboard modern dengan sidebar kiri.
- Ringkasan logbook, penggunaan hari ini, alat aktif, kondisi alat, dan kepatuhan kalibrasi.
- Data Alat: KODE, NAMA ALAT, LOKASI, STATUS KALIBRASI, TANGGAL KALIBRASI, KONDISI ALAT.
- Field lanjutan: interval kalibrasi, nomor sertifikat, merk/tipe, tahun perolehan, catatan.
- Pencarian dan filter alat.
- Edit data alat.
- Hapus alat oleh admin hanya jika alat belum memiliki riwayat logbook. Jika sudah dipakai, nonaktifkan agar histori tetap aman.
- Peringatan kalibrasi yang jatuh tempo dalam 30 hari dan yang sudah lewat.
- Export Data Alat ke Excel `.xlsx`.
- Download Template Excel.
- Import Excel `.xlsx` atau `.csv`. KODE yang sama akan diperbarui.
- Logbook penggunaan alat.
- Laporan dan export Excel/PDF.
- Manajemen pengguna dan role admin/petugas/viewer.
- Tanpa Composer/external library untuk generator XLSX sederhana.

## Kolom Excel Data Alat
Wajib:
1. KODE
2. NAMA ALAT
3. LOKASI
4. STATUS KALIBRASI
5. TANGGAL KALIBRASI
6. KONDISI ALAT

Tambahan yang didukung:
7. INTERVAL KALIBRASI (BULAN)
8. NOMOR SERTIFIKAT
9. MERK / TIPE
10. TAHUN PEROLEHAN
11. CATATAN

Status kalibrasi yang diterima: `Terkalibrasi`, `Belum Kalibrasi`, `Kadaluarsa`.
Kondisi yang diterima: `Baik`, `Baik dengan catatan`, `Perlu pemeriksaan`, `Rusak`.

---
# CARA UPDATE DI SERVER ARMBIAN

> Contoh di bawah mengikuti pola instalasi pengguna: website berada di HDD pada `/media/devmon/sda3-ata-ST500LM012_HN-M5/SERVER/Website/lab-logbook`. Jika folder Anda berbeda, sesuaikan variabel `APP`.

## 1. Upload ZIP ke Armbian
Dari Windows, salin ZIP ke server. Contoh menggunakan PowerShell:

```powershell
scp .\lab-logbook_LMS_terbaru.zip root@192.168.0.105:/root/
```

Jika SSH memakai port lain, tambahkan `-P PORT`.

## 2. Backup aplikasi dan database terlebih dahulu
SSH ke Armbian:

```bash
ssh root@192.168.0.105
```

Set variabel:

```bash
APP="/media/devmon/sda3-ata-ST500LM012_HN-M5/SERVER/Website/lab-logbook"
BACKUP="/root/backup-lab-logbook-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP"
```

Backup folder website:

```bash
tar -czf "$BACKUP/website.tar.gz" -C "$(dirname "$APP")" "$(basename "$APP")"
```

Backup database:

```bash
mysqldump -u root -p lab_logbook > "$BACKUP/lab_logbook.sql"
```

## 3. Pastikan PHP memiliki dukungan XLSX
Import Excel membutuhkan `ZipArchive`.

```bash
apt update
apt install -y php8.2-mysql php8.2-zip
systemctl restart php8.2-fpm
```

Cek:

```bash
php -m | grep -E 'mysqli|pdo_mysql|zip'
```

Minimal harus terlihat `pdo_mysql` dan `zip`.

## 4. Extract ZIP di folder sementara
Misalnya ZIP berada di `/root/lab-logbook_LMS_terbaru.zip`:

```bash
rm -rf /root/lab-logbook-update
mkdir -p /root/lab-logbook-update
unzip -q /root/lab-logbook_LMS_terbaru.zip -d /root/lab-logbook-update
```

Cek:

```bash
find /root/lab-logbook-update -maxdepth 2 -type f | sort
```

## 5. Jangan langsung menimpa database
Jalankan migrasi struktur database:

```bash
mysql -u root -p lab_logbook < /root/lab-logbook-update/sql/upgrade_lab_management.sql
```

Migrasi menggunakan `ADD COLUMN IF NOT EXISTS`, sehingga aman untuk kolom yang sudah ada dari versi sebelumnya.

## 6. Update file aplikasi
Set folder aplikasi:

```bash
APP="/media/devmon/sda3-ata-ST500LM012_HN-M5/SERVER/Website/lab-logbook"
```

Salin file aplikasi:

```bash
cp -a /root/lab-logbook-update/. "$APP/"
```

> Jika konfigurasi database Anda sudah benar, jangan menimpa `config.php` dengan konfigurasi yang salah. Setelah `cp`, periksa isi `config.php` dan pastikan DB_USER/DB_PASS sesuai database Anda.

## 7. Permission
Nginx/PHP-FPM perlu dapat membaca aplikasi:

```bash
chown -R www-data:www-data "$APP"
find "$APP" -type d -exec chmod 755 {} \;
find "$APP" -type f -exec chmod 644 {} \;
```

Jika Anda menyimpan backup database di dalam folder website, lebih baik pindahkan keluar dari web root:

```bash
mv "$APP/database-backup" /root/lab-logbook-database-backup 2>/dev/null || true
```

## 8. Pastikan konfigurasi Nginx
Contoh server block ada di `nginx-lab-logbook.conf`.

Jika website memang menggunakan port 8080 seperti instalasi sebelumnya, cek:

```bash
nginx -T | grep -A25 -B3 "server_name.*lab-logbook"
```

Pastikan `root` menunjuk ke:

```text
/media/devmon/sda3-ata-ST500LM012_HN-M5/SERVER/Website/lab-logbook
```

dan PHP menggunakan:

```text
/run/php/php8.2-fpm.sock
```

Test:

```bash
nginx -t
systemctl reload nginx
systemctl restart php8.2-fpm
```

## 9. Jika menggunakan Cloudflare Tunnel
Tidak perlu membuka port internet langsung. Pastikan ingress Cloudflare Tunnel Anda mengarah ke service lokal yang dipakai website, misalnya:

```yaml
ingress:
  - hostname: lab.example.my.id
    service: http://localhost:8080
  - service: http_status:404
```

Sesuaikan hostname dan port dengan konfigurasi Anda.

## 10. Tes aplikasi
Buka website melalui hostname Cloudflare Tunnel.

Login sebagai admin, kemudian buka:

`Dashboard → Data Alat`

Tes berurutan:
1. Tambah alat baru.
2. Edit alat.
3. Download Template Excel.
4. Isi beberapa baris.
5. Import Excel.
6. Export Excel.
7. Cek peringatan kalibrasi.
8. Catat satu penggunaan pada Logbook.
9. Cek Laporan.

## 11. Jika muncul error 500
Jalankan:

```bash
tail -n 80 /var/log/nginx/error.log
journalctl -u php8.2-fpm -n 80 --no-pager
```

Cek syntax:

```bash
cd "$APP"
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

## 12. Rollback
Jika update bermasalah:

```bash
systemctl stop php8.2-fpm
rm -rf "$APP"
mkdir -p "$(dirname "$APP")"
tar -xzf /root/backup-lab-logbook-YYYYMMDD-HHMMSS/website.tar.gz -C "$(dirname "$APP")"
mysql -u root -p lab_logbook < /root/backup-lab-logbook-YYYYMMDD-HHMMSS/lab_logbook.sql
systemctl start php8.2-fpm
nginx -t && systemctl reload nginx
```

Ganti nama folder backup sesuai hasil perintah `ls -lah /root/backup-lab-logbook-*`.

---
## Akun awal dari versi lama
Gunakan akun yang sudah ada pada database Anda. Jangan menjalankan `sql/schema.sql` pada database produksi lama karena schema tersebut ditujukan untuk instalasi baru.

Untuk database yang sudah berjalan, gunakan **`sql/upgrade_lab_management.sql`**.


## Perbaikan v2
- Menu **Laporan & Export** sekarang memiliki **Edit** dan **Hapus** untuk data logbook bagi `admin` dan `petugas`.
- Akun `viewer` tetap hanya melihat dan mengekspor.
- Import `.xlsx` pada Inventory Alat tidak lagi membutuhkan `simplexml_load_string()` / ekstensi SimpleXML; pembaca XLSX menggunakan parser XML bawaan PHP tanpa SimpleXML.
- Parser import mendukung XLSX inline strings, shared strings, dan tanggal Excel serial.
