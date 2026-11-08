-- =====================================================================
-- Sistem Pengelolaan Inventaris Laboratorium
-- File SQL lengkap (struktur + data awal) untuk diimpor via phpMyAdmin
-- MySQL 5.7+ / MariaDB 10.3+ (XAMPP)
-- Aturan stok: total = tersedia + dipinjam + rusak (BR-12)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS inventaris_lab;
CREATE DATABASE inventaris_lab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventaris_lab;

-- ---------------------------------------------------------------------
-- 1. users  (KF-01, KF-10, BR-09, BR-11, KN-02)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  username      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,             -- password_hash() PHP (bcrypt)
  role          ENUM('admin','peminjam') NOT NULL DEFAULT 'peminjam',
  identity_no   VARCHAR(30)  NULL,                 -- NIM / NIDN
  user_type     ENUM('laboran','mahasiswa','dosen') NOT NULL DEFAULT 'mahasiswa',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,   -- akun nonaktif tidak bisa login
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_users_username UNIQUE (username)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. categories  (KF-02)
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_categories_name UNIQUE (name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. inventories  (KF-02, KF-03, KF-04, KF-08, BR-01, BR-02, BR-08, BR-10, BR-12)
--    Status ketersediaan TIDAK disimpan; dihitung otomatis:
--    Tersedia jika qty_available >= 1, Tidak Tersedia jika = 0 (lihat view).
-- ---------------------------------------------------------------------
CREATE TABLE inventories (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code            VARCHAR(30)  NOT NULL,
  name            VARCHAR(150) NOT NULL,
  category_id     INT UNSIGNED NOT NULL,
  location        VARCHAR(100) NOT NULL,
  description     TEXT NULL,
  qty_total       INT UNSIGNED NOT NULL DEFAULT 0,
  qty_available   INT UNSIGNED NOT NULL DEFAULT 0,
  qty_borrowed    INT UNSIGNED NOT NULL DEFAULT 0,
  qty_damaged     INT UNSIGNED NOT NULL DEFAULT 0, -- total rusak ringan + rusak berat
  qty_damaged_light INT UNSIGNED NOT NULL DEFAULT 0,
  qty_damaged_heavy INT UNSIGNED NOT NULL DEFAULT 0,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1, -- tidak dihapus permanen (BR-10)
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_inventories_code UNIQUE (code),
  CONSTRAINT fk_inventories_category FOREIGN KEY (category_id)
    REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_inventories_balance
    CHECK (qty_total = qty_available + qty_borrowed + qty_damaged),
  CONSTRAINT chk_inventories_damage_split
    CHECK (qty_damaged = qty_damaged_light + qty_damaged_heavy)
) ENGINE=InnoDB;

CREATE INDEX idx_inventories_name ON inventories(name);
CREATE INDEX idx_inventories_category ON inventories(category_id);

-- ---------------------------------------------------------------------
-- 4. condition_histories  (KF-03, UC-03)
--    Riwayat perubahan kondisi oleh Admin.
--    from_condition -> to_condition sebanyak qty unit.
-- ---------------------------------------------------------------------
CREATE TABLE condition_histories (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventory_id   INT UNSIGNED NOT NULL,
  from_condition ENUM('baik','rusak_ringan','rusak_berat') NOT NULL,
  to_condition   ENUM('baik','rusak_ringan','rusak_berat') NOT NULL,
  qty            INT UNSIGNED NOT NULL,
  notes          VARCHAR(255) NULL,
  source         ENUM('manual','pengembalian') NOT NULL DEFAULT 'manual',
  recorded_by    INT UNSIGNED NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_condhist_inventory FOREIGN KEY (inventory_id)
    REFERENCES inventories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_condhist_user FOREIGN KEY (recorded_by)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_condhist_qty CHECK (qty >= 1)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. borrowings  (KF-05, KF-06, KF-07, KF-09, BR-04, BR-05)
--    Status: menunggu, dipinjam, ditolak, selesai
-- ---------------------------------------------------------------------
CREATE TABLE borrowings (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  borrowing_code     VARCHAR(30)  NOT NULL,
  user_id            INT UNSIGNED NOT NULL,
  borrow_date        DATE NOT NULL,
  planned_return_date DATE NOT NULL,
  purpose            VARCHAR(255) NOT NULL,
  status             ENUM('menunggu','dipinjam','ditolak','selesai') NOT NULL DEFAULT 'menunggu',
  rejection_reason   VARCHAR(255) NULL,
  processed_by       INT UNSIGNED NULL,          -- admin yang menyetujui/menolak
  processed_at       DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_borrowings_code UNIQUE (borrowing_code),
  CONSTRAINT fk_borrowings_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_borrowings_admin FOREIGN KEY (processed_by)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_borrowings_dates CHECK (planned_return_date >= borrow_date)
) ENGINE=InnoDB;

CREATE INDEX idx_borrowings_status ON borrowings(status);
CREATE INDEX idx_borrowings_user ON borrowings(user_id);

-- ---------------------------------------------------------------------
-- 6. borrowing_details  (barang dalam satu peminjaman)
-- ---------------------------------------------------------------------
CREATE TABLE borrowing_details (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  borrowing_id  INT UNSIGNED NOT NULL,
  inventory_id  INT UNSIGNED NOT NULL,
  qty           INT UNSIGNED NOT NULL,
  qty_returned  INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_bdetail_borrowing FOREIGN KEY (borrowing_id)
    REFERENCES borrowings(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_bdetail_inventory FOREIGN KEY (inventory_id)
    REFERENCES inventories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT uq_bdetail_item UNIQUE (borrowing_id, inventory_id),
  CONSTRAINT chk_bdetail_qty CHECK (qty >= 1 AND qty_returned <= qty)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. return_histories  (KF-07, BR-06, BR-07)
--    Satu baris per kondisi pengembalian per barang.
-- ---------------------------------------------------------------------
CREATE TABLE return_histories (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  borrowing_detail_id INT UNSIGNED NOT NULL,
  qty                 INT UNSIGNED NOT NULL,
  return_condition    ENUM('baik','rusak_ringan','rusak_berat') NOT NULL,
  actual_return_date  DATE NOT NULL,
  is_late             TINYINT(1) NOT NULL DEFAULT 0,
  notes               VARCHAR(255) NULL,
  recorded_by         INT UNSIGNED NOT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_return_detail FOREIGN KEY (borrowing_detail_id)
    REFERENCES borrowing_details(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_return_user FOREIGN KEY (recorded_by)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_return_qty CHECK (qty >= 1)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- VIEW: status ketersediaan otomatis (KF-08, BR-08)
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_inventory_status AS
SELECT i.*, c.name AS category_name,
       CASE WHEN i.qty_available >= 1 THEN 'Tersedia' ELSE 'Tidak Tersedia' END AS availability_status
FROM inventories i
JOIN categories c ON c.id = i.category_id;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DATA AWAL DEMONSTRASI
-- Password: admin -> admin123 | peminjam -> peminjam123
-- =====================================================================
INSERT INTO users (id, full_name, username, password_hash, role, identity_no, user_type, is_active) VALUES
(1, 'Laboran Utama',      'admin',    '$2y$10$zvI4mOiy6F7LhvBR5q206.iE53ExBdFMzO2yywmpq6X5MTOfb4LQe', 'admin',    '198801012015', 'laboran',   1),
(2, 'Budi Santoso',       'budi',     '$2y$10$q/C6oF4Yc26XF4HtC39hFOFBIy/OireEz2AMycAVkVlAuQ8uUAgAe', 'peminjam', '2201010001',   'mahasiswa', 1),
(3, 'Siti Rahmawati',     'siti',     '$2y$10$q/C6oF4Yc26XF4HtC39hFOFBIy/OireEz2AMycAVkVlAuQ8uUAgAe', 'peminjam', '2201010002',   'mahasiswa', 1),
(4, 'Dr. Andi Wijaya',    'andi',     '$2y$10$q/C6oF4Yc26XF4HtC39hFOFBIy/OireEz2AMycAVkVlAuQ8uUAgAe', 'peminjam', '0012038501',   'dosen',     1),
(5, 'Rudi Nonaktif',      'rudi',     '$2y$10$q/C6oF4Yc26XF4HtC39hFOFBIy/OireEz2AMycAVkVlAuQ8uUAgAe', 'peminjam', '2201010003',   'mahasiswa', 0);

INSERT INTO categories (id, name, description) VALUES
(1, 'Komputer & Laptop',  'Perangkat komputasi'),
(2, 'Jaringan',           'Perangkat jaringan dan kabel'),
(3, 'Elektronika',        'Komponen dan alat ukur elektronika'),
(4, 'Multimedia',         'Proyektor, kamera, dan audio');

-- Seluruh baris memenuhi total = tersedia + dipinjam + rusak
INSERT INTO inventories (id, code, name, category_id, location, description, qty_total, qty_available, qty_borrowed, qty_damaged, qty_damaged_light, qty_damaged_heavy, is_active) VALUES
(1, 'INV-KMP-001', 'Laptop Lenovo ThinkPad E14', 1, 'Lab Komputer 1 - Lemari A', 'Core i5, RAM 8GB',      10, 7, 2, 1, 1, 0, 1),
(2, 'INV-KMP-002', 'Mouse USB Logitech',          1, 'Lab Komputer 1 - Laci 2',   NULL,                    25, 25, 0, 0, 0, 0, 1),
(3, 'INV-JRG-001', 'Router MikroTik hEX',         2, 'Lab Jaringan - Rak 1',      'RB750Gr3',              8, 5, 3, 0, 0, 0, 1),
(4, 'INV-JRG-002', 'Kabel LAN Cat6 5m',           2, 'Lab Jaringan - Rak 2',      NULL,                    30, 28, 0, 2, 2, 0, 1),
(5, 'INV-ELK-001', 'Multimeter Digital',          3, 'Lab Elektronika - Lemari B','Sanwa CD800a',          6, 0, 0, 6, 0, 6, 1),
(6, 'INV-ELK-002', 'Arduino Uno R3',              3, 'Lab Elektronika - Laci 1',  NULL,                    15, 15, 0, 0, 0, 0, 1),
(7, 'INV-MM-001',  'Proyektor Epson EB-X51',      4, 'Ruang Laboran',             NULL,                    3, 3, 0, 0, 0, 0, 1),
(8, 'INV-MM-002',  'Kamera DSLR Canon 1500D',     4, 'Ruang Laboran',             'Barang lama',           2, 2, 0, 0, 0, 0, 0);

INSERT INTO condition_histories (inventory_id, from_condition, to_condition, qty, notes, source, recorded_by, created_at) VALUES
(1, 'baik', 'rusak_ringan', 1, 'Engsel layar patah', 'manual', 1, '2026-10-01 09:00:00'),
(4, 'baik', 'rusak_ringan', 2, 'Konektor RJ45 lepas', 'manual', 1, '2026-10-03 10:00:00'),
(5, 'baik', 'rusak_berat', 6, 'Tidak menyala, menunggu servis', 'manual', 1, '2026-10-05 11:00:00');

-- Peminjaman: 2 dipinjam, 1 menunggu, 1 ditolak, 1 selesai
INSERT INTO borrowings (id, borrowing_code, user_id, borrow_date, planned_return_date, purpose, status, rejection_reason, processed_by, processed_at, created_at) VALUES
(1, 'PJM-20261101-001', 2, '2026-11-02', '2026-11-09', 'Praktikum Pemrograman Web', 'dipinjam', NULL, 1, '2026-11-01 14:00:00', '2026-11-01 10:00:00'),
(2, 'PJM-20261102-001', 4, '2026-11-03', '2026-11-10', 'Penelitian jaringan',       'dipinjam', NULL, 1, '2026-11-02 13:00:00', '2026-11-02 09:00:00'),
(3, 'PJM-20261105-001', 3, '2026-11-08', '2026-11-12', 'Tugas akhir IoT',           'menunggu', NULL, NULL, NULL,              '2026-11-05 08:30:00'),
(4, 'PJM-20261020-001', 3, '2026-10-21', '2026-10-22', 'Dokumentasi acara',         'ditolak',  'Proyektor dipakai untuk ujian', 1, '2026-10-20 15:00:00', '2026-10-20 09:00:00'),
(5, 'PJM-20261010-001', 2, '2026-10-11', '2026-10-13', 'Praktikum Jaringan',        'selesai',  NULL, 1, '2026-10-10 12:00:00', '2026-10-10 08:00:00');

INSERT INTO borrowing_details (id, borrowing_id, inventory_id, qty, qty_returned) VALUES
(1, 1, 1, 2, 0),
(2, 2, 3, 3, 0),
(3, 3, 6, 4, 0),
(4, 4, 7, 1, 0),
(5, 5, 4, 5, 5);

INSERT INTO return_histories (borrowing_detail_id, qty, return_condition, actual_return_date, is_late, notes, recorded_by) VALUES
(5, 5, 'baik', '2026-10-14', 1, 'Terlambat 1 hari, kondisi baik', 1);
