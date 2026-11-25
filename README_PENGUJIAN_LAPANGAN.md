# Revisi Modul Pengujian Lapangan

Versi ini memfokuskan modul pada **form pengujian + perhitungan otomatis + penyimpanan + laporan resmi**, tanpa menambah statistik pengujian ke dashboard utama.

## Jenis pengujian
- Sondir/CPT: SNI 2827:2008
- Sand Cone: SNI 2828:2011
- CBR Lapangan: SNI 1738:2011
- DCP: Pedoman Cara Uji CBR dengan Dynamic Cone Penetrometer

## Alur
1. Pilih jenis pengujian.
2. Isi identitas dan data lapangan.
3. Sistem menghitung parameter yang dapat dihitung otomatis.
4. Klik **Simpan & Hitung**.
5. Hasil tersimpan di MariaDB.
6. Dari daftar data, gunakan **PDF** atau **XLSX** untuk satu pengujian.
7. `Export Semua XLSX` membuat rekap seluruh pengujian.

## PDF resmi
PDF A4 berisi:
- kop laboratorium;
- logo dari `assets/logo-lab.png` jika tersedia;
- identitas pengujian;
- standar/acuan;
- data pembacaan;
- hasil perhitungan;
- kesimpulan;
- catatan/penyimpangan;
- ruang tanda tangan pemeriksa dan kepala laboratorium.

Identitas kop diatur di `config.php` melalui konstanta `LAB_*`.

## Catatan teknis
- Sondir menggunakan luas konus dan luas selimut geser sebagai parameter alat, default 10 cm² dan 150 cm². Sesuaikan dengan alat/sertifikat dan prosedur laboratorium.
- CBR menghitung rasio beban pada penetrasi 2,54 mm dan 5,08 mm terhadap beban standar. Koreksi kurva dan kalibrasi proving ring tetap harus mengikuti prosedur alat/laboratorium.
- DCP menghitung penetrasi kumulatif dan DCPI. Korelasi DCPI ke CBR tidak dipaksakan karena persamaan korelasi harus dipilih sesuai pedoman/proyek yang berlaku.
- Untuk penggunaan resmi, verifikasi kembali formulir, faktor kalibrasi, satuan, dan kriteria penerimaan terhadap edisi standar/SOP laboratorium yang berlaku.

## Database
Jika tabel belum ada:

```bash
mysql -u labuser -p lab_logbook < sql/upgrade_pengujian_lapangan.sql
```

## PDF
Server perlu Chromium:

```bash
apt update
apt install -y chromium
```

## Logo
Simpan logo resmi sebagai:

```text
assets/logo-lab.png
```
