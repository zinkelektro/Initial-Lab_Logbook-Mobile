# Lab_Logbook Mobile (APK + offline queue)

Paket ini membungkus dashboard PHP Lab_Logbook yang sudah ada ke dalam APK Android. APK membuka dashboard server yang sama melalui HTTPS; tidak mengganti sistem login, role, MariaDB, halaman, atau generator laporan yang asli.

## Isi paket
- `android/`: source Android WebView (Kotlin) dan konfigurasi Gradle.
- `.github/workflows/android-apk.yml`: build APK otomatis pada push ke `main`/`master` dan lewat tombol **Run workflow**.
- `sw.js`, `mobile/`: service worker, cache halaman per ID pengguna, banner koneksi, dan antrean POST offline.
- File PHP, CSS, SQL, import/export, dan format laporan berasal dari arsip Lab_Logbook yang diunggah.

## Perilaku online/offline yang perlu dipahami
**Online**
- APK membuka dashboard PHP asli di server.
- Autentikasi dan role `admin`, `petugas`, `viewer` tetap divalidasi server.
- Dashboard, manajemen alat, logbook, pengujian, laporan, impor, ekspor Excel, dan PDF/cetak memakai halaman dan endpoint asli.
- Unduhan ditangani Android DownloadManager dan disimpan ke folder Download.

**Offline**
- Halaman yang sebelumnya berhasil dibuka dapat tersedia dari cache lokal per akun.
- Form POST yang sudah terbuka dapat disimpan sebagai antrean lokal (IndexedDB), termasuk field berkas yang dipilih; antrean dikirim ulang ketika jaringan kembali dan akun yang sama sudah login.
- Login pertama, halaman yang belum pernah dibuka, query database langsung, impor/ekspor server-side, dan pembuatan PDF/XLSX baru tetap memerlukan server.
- Saat offline, hasil cetak/Excel yang belum pernah diunduh tidak dapat dibuat karena generator aslinya berjalan di PHP server.
- Cache halaman berisi data yang ditampilkan pada perangkat. Jangan gunakan perangkat bersama tanpa logout dan pengamanan layar. Uji kebijakan cache dan antrean sebelum pemakaian operasional.
- Antrean offline adalah mekanisme best-effort. Jangan menghapus data aplikasi/browser sebelum antrean berhasil tersinkron. Untuk penggunaan kritis, cek hasil sinkronisasi pada laporan server.

Dengan demikian, paket ini menyediakan mode offline untuk halaman yang sudah tersimpan dan pengantrean formulir; ini bukan salinan database MariaDB yang bisa menjalankan seluruh PHP secara mandiri di Android.

## 1. Persiapan server
1. Backup folder aplikasi dan database.
2. Upload file `sw.js` ke document root yang sama dengan `index.php`.
3. Upload folder `mobile/` ke document root.
4. File PHP yang memiliki halaman HTML sudah diberi meta pengguna dan loader `mobile/mobile.js`.
5. Pastikan URL publik menggunakan **HTTPS** dan service worker dapat diakses:
   - `https://DOMAIN-ANDA/sw.js`
   - `https://DOMAIN-ANDA/mobile/mobile.js`
   - `https://DOMAIN-ANDA/mobile/offline.html`
6. Pastikan Nginx tidak memblokir file JavaScript/JSON. Service worker harus disajikan dari origin yang sama dan scope `/`.

> Jika aplikasi dipasang di subfolder, bukan di document root domain, sesuaikan URL absolut `/sw.js`, `/mobile/...` dan scope pada PHP/JS sebelum deploy. Paket awal mengasumsikan aplikasi berada di root hostname.

## 2. Konfigurasi database aman
`config.php` menggunakan variabel environment bila tersedia:
- `LAB_DB_HOST`
- `LAB_DB_NAME`
- `LAB_DB_USER`
- `LAB_DB_PASS`

Set nilai tersebut pada environment PHP-FPM/server sesuai kredensial MariaDB Anda. Jangan commit password database ke GitHub. Jangan upload folder backup database ke repository publik. Arsip input memuat `database-backup/backup-mariadb.sql`; file dump tersebut sengaja tidak disertakan dalam paket source mobile untuk mencegah data akun/operasional masuk ke repository.

## 3. Build APK melalui GitHub
1. Buat repository GitHub baru. Untuk data operasional, pilih **Private**.
2. Extract ZIP paket ini di komputer.
3. Buka terminal pada folder hasil extract.
4. Jalankan:

```bash
git init
git add .
git commit -m "Initial Lab_Logbook Mobile"
git branch -M main
git remote add origin https://github.com/USERNAME/REPOSITORY.git
git push -u origin main
```

5. Buka tab **Actions** pada repository. Workflow `Build Lab_Logbook Android APK` berjalan otomatis.
6. Setelah status hijau, buka run terbaru → bagian **Artifacts** → unduh `Lab_Logbook-Mobile-debug`.
7. Extract artifact dan install `app-debug.apk` pada perangkat Android. Untuk instalasi manual, Android mungkin meminta izin **Install unknown apps** untuk aplikasi pengelola file/browser yang digunakan.

Untuk build ulang tanpa commit baru: GitHub → **Actions** → `Build Lab_Logbook Android APK` → **Run workflow**.

APK hasil workflow adalah debug-signed untuk pengujian/internal. Untuk distribusi publik/Play Store, buat konfigurasi signing release dengan keystore milik organisasi dan GitHub Actions Secrets; jangan menyimpan keystore atau password di repository.

## 4. Penggunaan APK
1. Buka aplikasi.
2. Masukkan URL HTTPS dashboard Lab_Logbook, contoh `https://logbook.domain-anda.tld`.
3. Login menggunakan akun multi-user yang sudah ada di database.
4. Buka halaman yang akan dipakai saat offline setidaknya sekali ketika online.
5. Saat offline, gunakan formulir yang sudah dibuka; data akan masuk antrean lokal.
6. Sambungkan internet kembali dan pastikan akun yang sama aktif agar antrean dikirim. Verifikasi data di halaman laporan server.

## 5. Fitur dan kompatibilitas
Tidak ada perubahan pada schema database untuk wrapper Android ini. Fitur web asli tetap dipanggil dari server, termasuk:
- dashboard dan ringkasan;
- inventaris alat, import/template/export Excel;
- logbook penggunaan;
- pengujian lapangan dan perhitungan;
- laporan, edit/hapus sesuai role;
- ekspor `.xlsx`, PDF siap cetak;
- multi-user dan role.

## 6. Catatan sebelum produksi
- Uji seluruh alur pada server staging dan backup database sebelum mengganti instalasi aktif.
- Uji sinkronisasi saat koneksi putus ketika submit dan saat sesi login kedaluwarsa.
- Pengiriman ulang POST yang responsnya terputus tepat setelah server menyimpan data berpotensi perlu pemeriksaan duplikasi manual; antrean offline tidak menggantikan mekanisme idempotensi transaksi.
- Android WebView membutuhkan HTTPS dengan sertifikat valid. Aplikasi sengaja tidak melewati peringatan sertifikat.
- Jangan gunakan akun default/contoh; buat password unik dan pastikan hanya admin yang mengelola akun.

## 7. Struktur penting
```text
Lab_Logbook/
├── android/
│   ├── app/src/main/...
│   ├── build.gradle
│   └── settings.gradle
├── mobile/
│   ├── mobile.js
│   ├── offline.html
│   └── manifest.webmanifest
├── .github/workflows/android-apk.yml
├── sw.js
├── config.php
├── index.php
├── logbook.php
├── pengujian.php
├── laporan.php
├── alat.php
└── ...
```
