# Sistem Pengelolaan Inventaris Laboratorium

Aplikasi web berbasis **PHP Native** dan **MySQL (MariaDB)** yang dirancang untuk mengelola inventaris laboratorium, pencatatan kondisi barang, stok seimbang otomatis, pengajuan peminjaman, persetujuan/penolakan oleh Admin/Laboran, pencatatan pengembalian bertahap maupun tuntas, serta pemantauan riwayat transaksi lengkap.

Aplikasi ini dibangun untuk memenuhi spesifikasi tugas **Ujian Tengah Semester (UTS) — Testing dan Implementasi Sistem** sesuai dokumen acuan `UTS_Tahap1_Inventaris_Laboratorium.docx`.

---

## 1. Deskripsi dan Tujuan Aplikasi

### 1.1 Deskripsi
Sistem Pengelolaan Inventaris Laboratorium adalah aplikasi terpusat untuk memantau siklus hidup aset dan alat laboratorium. Mengakomodasi dua peran pengguna:
1. **Admin/Laboran**: Mengelola katalog barang, kategori, pengguna, pencatatan kondisi fisik, memproses persetujuan/penolakan pengajuan peminjaman, memproses pengembalian barang, dan melihat rekap riwayat seluruh transaksi.
2. **Peminjam (Mahasiswa/Dosen)**: Melihat katalog barang dan ketersediaan stok secara real-time, mengajukan peminjaman secara mandiri, serta memantau status dan riwayat transaksi miliknya sendiri.

### 1.2 Tujuan
- **Sentralisasi Data**: Mencegah pencatatan ganda atau tercecer pada buku log manual.
- **Kondisi Terpantau**: Menjamin alat rusak ringan maupun berat tidak dapat dipinjam oleh mahasiswa/dosen.
- **Konsistensi Saldo Stok**: Menerapkan aturan bisnis ketat: `Total Stok = Stok Tersedia + Stok Dipinjam + Stok Rusak`.
- **Transparansi Transaksi**: Menyediakan jejak audit lengkap dari pengajuan, persetujuan, hingga riwayat pengembalian barang.

---

## 2. Teknologi yang Digunakan

- **Backend**: PHP Native (PHP 7.4 - PHP 8.5+).
- **Database**: MySQL 5.7+ / MariaDB 10.3+ (XAMPP).
- **Database Abstraction**: PHP PDO (Prepared Statements & Transaksional `BEGIN`, `COMMIT`, `ROLLBACK`).
- **Autentikasi & Keamanan**: PHP Session, `password_hash()` (Bcrypt), proteksi token CSRF di setiap request mutasi, escaping keluaran (`htmlspecialchars`), validasi masukan berlapis (server-side).
- **Frontend / Tampilan**: Semantic HTML5, CSS Kustom responsif dengan tema visual *"Buku Log Laboratorium"*, Vanilla JavaScript (tanpa ketergantungan framework JS eksternal).
- **Server**: Apache & MySQL via XAMPP.

---

## 3. Struktur Folder Proyek

```text
c:\xampp\htdocs\inventaris-lab/
├── admin/                     # Modul antarmuka Admin
│   ├── dashboard.php          # HL-02 Dashboard ringkasan Admin
│   ├── pengguna.php           # HL-10 Manajemen data pengguna
│   ├── pengguna_action.php    # Aksi simpan/status pengguna
│   ├── pengguna_form.php      # Formulir tambah/edit pengguna
│   └── riwayat.php            # HL-09 Riwayat transaksi seluruh laboratorium
├── assets/
│   ├── css/
│   │   └── style.css          # Desain visual responsif laboratorium
│   └── js/
│       └── app.js             # Interaksi modal, toggle password, sidebar responsif
├── auth/
│   ├── login.php              # HL-01 Autentikasi pengguna
│   ├── logout.php             # Terminasi sesi aman
│   └── session.php            # Verifikasi role, timeout sesi, hak akses server-side
├── components/
│   └── ui.php                 # Helper badge, format tanggal, stat card
├── config/
│   ├── config.php             # Konfigurasi database & URL aplikasi
│   └── database.php           # Koneksi PDO singleton & wrapper transaksi
├── database/
│   ├── inventaris_lab.sql     # Database master final (struktur + data demonstrasi)
│   └── upgrade_prompt3.sql    # Skrip migrasi opsional
├── includes/
│   └── helpers.php            # CSRF helper, flash messages, sanitizer, routing
├── layouts/
│   ├── footer.php             # Penutup layout HTML
│   ├── forbidden.php          # Halaman penolakan akses 403 Forbidden
│   ├── header.php             # Pembuka layout & header bar pengguna
│   └── sidebar.php            # Navigasi samping dinamis sesuai role
├── modules/
│   ├── inventaris/            # HL-03 s/d HL-05 Pengelolaan inventaris & kategori
│   │   ├── action.php
│   │   ├── detail.php
│   │   ├── form.php
│   │   └── index.php
│   ├── kondisi/               # HL-06 Pencatatan kondisi fisik unit barang
│   │   ├── action.php
│   │   ├── index.php
│   │   └── stock_rules.php
│   ├── peminjaman/            # HL-07 Persetujuan peminjaman oleh Admin
│   │   ├── action.php
│   │   ├── detail.php
│   │   └── index.php
│   └── pengembalian/          # HL-08 Pengembalian barang oleh Admin
│       ├── action.php
│       ├── index.php
│       └── proses.php
├── peminjam/                  # Modul antarmuka Peminjam (Mahasiswa/Dosen)
│   ├── ajukan.php             # HL-14 Formulir pengajuan peminjaman
│   ├── ajukan_action.php      # Validasi & penyimpanan pengajuan baru
│   ├── dashboard.php          # HL-11 Dashboard Peminjam
│   ├── detail_barang.php      # HL-13 Detail ketersediaan & tombol pinjam cepat
│   ├── inventaris.php         # HL-12 Katalog barang baca-saja
│   └── riwayat.php            # HL-15 Status & riwayat transaksi milik sendiri
├── tests/
│   ├── functional_test.php    # Pengujian otomatis 15 skenario & aturan stok
│   └── stock_rules_test.php   # Unit test transisi kondisi barang
├── index.php                  # Entry point routing awal
├── test_koneksi.php           # File pengecekan koneksi database cepat
└── README.md                  # Dokumentasi teknis proyek
```

---

## 4. Persyaratan Instalasi

- **XAMPP** versi 7.4 ke atas (direkomendasikan versi PHP 8.0 - 8.2 atau lebih baru).
- **Web Server**: Apache aktif.
- **Database Server**: MySQL / MariaDB aktif.
- **Browser Modern**: Google Chrome, Mozilla Firefox, Microsoft Edge, atau Safari.

---

## 5. Panduan Instalasi dan Menjalankan di XAMPP

### Langkah 1: Penempatan Berkas
Pastikan seluruh folder proyek berada pada direktori htdocs XAMPP:
`C:\xampp\htdocs\inventaris-lab`

### Langkah 2: Mengaktifkan XAMPP
1. Buka aplikasi **XAMPP Control Panel**.
2. Klik tombol **Start** pada modul **Apache**.
3. Klik tombol **Start** pada modul **MySQL**.
4. Pastikan kedua modul berstatus warna hijau (Active).

### Langkah 3: Impor Database MySQL
1. Buka browser dan akses phpMyAdmin:
   `http://localhost/phpmyadmin`
2. Klik tab **Import** pada bagian atas.
3. Klik **Choose File** / **Pilih File**, lalu arahkan ke:
   `C:\xampp\htdocs\inventaris-lab\database\inventaris_lab.sql`
4. Klik tombol **Go** / **Kirim** di bagian bawah.
5. Database `inventaris_lab` beserta seluruh tabel, view, dan data awal akan otomatis terbuat.

*(Alternatif via Command Prompt/Terminal):*
```powershell
Get-Content "C:\xampp\htdocs\inventaris-lab\database\inventaris_lab.sql" | & "C:\xampp\mysql\bin\mysql.exe" -u root
```

### Langkah 4: Konfigurasi Database
Buka file `config/config.php`. Secara default, konfigurasi sudah disesuaikan dengan instalasi standar XAMPP:
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'inventaris_lab');
define('DB_USER', 'root');
define('DB_PASS', ''); // default password MySQL XAMPP kosong
define('BASE_URL', '/inventaris-lab');
```
*Catatan: Jika MySQL Anda menggunakan password root atau port non-standar, silakan ubah nilai `DB_PASS` atau `DB_PORT` pada file tersebut.*

### Langkah 5: Uji Koneksi dan Jalankan Aplikasi
1. Buka browser dan akses:
   `http://localhost/inventaris-lab/test_koneksi.php`
   Pastikan tabel status menampilkan konfirmasi *"Koneksi berhasil ke database inventaris_lab"* dan status stok *"Seimbang? Ya"*.
2. Akses halaman utama aplikasi:
   `http://localhost/inventaris-lab/`
   Sistem akan otomatis mengarahkan ke halaman login.

---

## 6. Akun Demonstrasi untuk Login

| Username | Password | Role | Peran / Deskripsi |
|---|---|---|---|
| **admin** | `admin123` | **admin** | Laboran Utama (Hak Akses Penuh) |
| **budi** | `peminjam123` | **peminjam** | Mahasiswa (NIM: 2201010001) |
| **siti** | `peminjam123` | **peminjam** | Mahasiswa (NIM: 2201010002) |
| **andi** | `peminjam123` | **peminjam** | Dosen (NIDN: 0012038501) |
| **rudi** | `peminjam123` | **peminjam** | Akun Nonaktif (Uji Coba Penolakan Login) |

---

## 7. Panduan Penggunaan Fitur Utama

### 7.1 Alur Transaksi Peminjaman dan Pengembalian
1. **Pengajuan (Peminjam)**:
   - Login dengan username `budi` / password `peminjam123`.
   - Buka menu **Daftar Inventaris**, pilih barang yang tersedia (misal: *Laptop Lenovo ThinkPad E14*), lalu klik **Pinjam**.
   - Masukkan jumlah, tanggal pinjam, rencana pengembalian, dan keperluan.
   - Klik **Kirim Pengajuan**. Status awal adalah `Menunggu Persetujuan`.
   - *Penting: Stok barang belum berkurang pada tahap ini.*
2. **Persetujuan (Admin/Laboran)**:
   - Logout lalu login dengan username `admin` / password `admin123`.
   - Buka menu **Persetujuan**.
   - Admin memeriksa detail permohonan. Klik **Setujui** untuk menerima atau **Tolak** dengan mengisi alasan penolakan.
   - Jika disetujui, stok tersedia berkurang dan stok dipinjam bertambah secara atomik. Status menjadi `Dipinjam`.
3. **Pengembalian (Admin/Laboran)**:
   - Saat peminjam mengembalikan alat, Admin membuka menu **Pengembalian**.
   - Klik **Proses Kembali** pada peminjaman aktif terkait.
   - Masukkan jumlah yang dikembalikan sekarang, pilih kondisi barang (*Baik*, *Rusak Ringan*, atau *Rusak Berat*), dan isi catatan jika diperlukan.
   - Klik **Simpan Pengembalian**.
   - Jika dikembalikan dalam kondisi Baik: stok dipinjam berkurang, stok tersedia bertambah.
   - Jika dikembalikan dalam kondisi Rusak: stok dipinjam berkurang, stok rusak bertambah.
   - Bila seluruh barang sudah kembali, status transaksi otomatis beralih menjadi `Selesai`.
4. **Riwayat Transaksi**:
   - Admin membuka menu **Riwayat Peminjaman** untuk memfilter, mencari, dan melihat jejak audit seluruh transaksi.
   - Peminjam membuka menu **Status & Riwayat** untuk melihat jejak pengembalian dan kondisi alat miliknya sendiri.

---

## 8. Pemetaan Halaman Aplikasi (HL-01 s/d HL-15)

| ID Halaman | Nama Halaman | Pengguna | Berkas File |
|---|---|---|---|
| **HL-01** | Login | Publik | `auth/login.php` |
| **HL-02** | Dashboard Admin | Admin | `admin/dashboard.php` |
| **HL-03** | Data Inventaris & Kategori | Admin | `modules/inventaris/index.php` |
| **HL-04** | Tambah/Edit Inventaris | Admin | `modules/inventaris/form.php` |
| **HL-05** | Detail Inventaris | Admin | `modules/inventaris/detail.php` |
| **HL-06** | Kondisi Barang | Admin | `modules/kondisi/index.php` |
| **HL-07** | Data dan Persetujuan Peminjaman | Admin | `modules/peminjaman/index.php` |
| **HL-08** | Pengembalian Barang | Admin | `modules/pengembalian/index.php` & `proses.php` |
| **HL-09** | Riwayat Peminjaman (Admin) | Admin | `admin/riwayat.php` |
| **HL-10** | Data Pengguna | Admin | `admin/pengguna.php` & `pengguna_form.php` |
| **HL-11** | Dashboard Peminjam | Peminjam | `peminjam/dashboard.php` |
| **HL-12** | Daftar Inventaris (Katalog) | Peminjam | `peminjam/inventaris.php` |
| **HL-13** | Detail Barang (Peminjam) | Peminjam | `peminjam/detail_barang.php` |
| **HL-14** | Form Pengajuan Peminjaman | Peminjam | `peminjam/ajukan.php` |
| **HL-15** | Status & Riwayat Peminjam | Peminjam | `peminjam/riwayat.php` |

---

## 9. Menjalankan Pengujian Otomatis

Proyek ini telah dilengkapi dengan skrip pengujian otomatis yang menguji ke-15 skenario fungsional dan aturan saldo stok:

Jalankan perintah berikut pada terminal PowerShell/CMD:
```powershell
php tests/functional_test.php
php tests/stock_rules_test.php
```

Hasil pengujian mencakup:
- Verifikasi otentikasi login, password salah, dan logout.
- Tambah, edit, dan nonaktifkan inventaris.
- Mutasi kondisi unit barang fisik dan pencatatan riwayatnya.
- Penegakan aturan stok: `Total = Tersedia + Dipinjam + Rusak`.
- Pengajuan, persetujuan, penolakan peminjaman.
- Pengembalian kondisi baik dan rusak (ringan/berat) serta pengembalian bertahap.
- Pemeriksaan isolasi data riwayat (peminjam tidak dapat melihat data pengguna lain).
- Proteksi otorisasi role (HTTP 403 Forbidden).

---

## 10. Catatan dan Kendala

- **Tidak Ditemukan Kendala Kritis**: Seluruh modul inti telah selesai, terintegrasi, dan teruji langsung di lingkungan XAMPP.
- **Batasan Ruang Lingkup**: Sesuai dokumen analisis kebutuhan UTS Tahap 1, fitur notifikasi email/SMS/WhatsApp dan sistem denda moneter berada di luar cakupan sistem.
