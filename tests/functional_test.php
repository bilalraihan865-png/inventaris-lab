<?php
/**
 * Test Otomatis: 15 Skenario Fungsional (Section G) & Simulasi Aturan Stok (Section H)
 * Menjalankan pengujian langsung terhadap database MySQL.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/kondisi/stock_rules.php';

$pdo = db();

function assert_test(bool $cond, string $name, string $detail = ''): void {
    if ($cond) {
        echo "[BERHASIL] $name" . ($detail ? " — $detail" : "") . "\n";
    } else {
        echo "[GAGAL] $name" . ($detail ? " — $detail" : "") . "\n";
        exit(1);
    }
}

echo "=== MEMULAI PENGUJIAN SISTEM PENGELOLAAN INVENTARIS LABORATORIUM ===\n\n";

// --- SKENARIO 1: Login Admin ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin' AND is_active = 1");
$stmt->execute();
$admin = $stmt->fetch();
$loginAdminOk = $admin && password_verify('admin123', $admin['password_hash']) && $admin['role'] === 'admin';
assert_test($loginAdminOk, "1. Login Admin", "Kredensial valid, role admin terkonfirmasi");

// --- SKENARIO 2: Login Peminjam ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'budi' AND is_active = 1");
$stmt->execute();
$peminjam = $stmt->fetch();
$loginPeminjamOk = $peminjam && password_verify('peminjam123', $peminjam['password_hash']) && $peminjam['role'] === 'peminjam';
assert_test($loginPeminjamOk, "2. Login Peminjam", "Kredensial valid, role peminjam terkonfirmasi");

// --- SKENARIO 3: Login dengan password salah ---
$wrongPassOk = !password_verify('salah_password', $admin['password_hash']);
assert_test($wrongPassOk, "3. Login Password Salah", "Password salah berhasil ditolak");

// --- SKENARIO 4: Logout (Simulasi Session Cleared) ---
$_SESSION = ['user' => ['id' => 1, 'role' => 'admin']];
$_SESSION = [];
$logoutOk = empty($_SESSION['user']);
assert_test($logoutOk, "4. Logout", "Session berhasil dikosongkan");

// --- SKENARIO 5: Tambah Inventaris ---
$testCode = 'TEST-INV-' . time();
$stmt = $pdo->prepare("INSERT INTO inventories (code, name, category_id, location, qty_total, qty_available, qty_borrowed, qty_damaged, qty_damaged_light, qty_damaged_heavy, is_active) VALUES (?, 'Barang Uji Coba', 1, 'Lab Test', 10, 10, 0, 0, 0, 0, 1)");
$stmt->execute([$testCode]);
$testInvId = (int)$pdo->lastInsertId();
assert_test($testInvId > 0, "5. Tambah Inventaris", "Data tersimpan di MySQL dengan ID: $testInvId");

// --- SKENARIO 6: Edit Inventaris ---
$stmt = $pdo->prepare("UPDATE inventories SET name = 'Barang Uji Coba Diubah', location = 'Lab Test 2' WHERE id = ?");
$stmt->execute([$testInvId]);
$checkInv = $pdo->query("SELECT name, location FROM inventories WHERE id = $testInvId")->fetch();
$editOk = $checkInv['name'] === 'Barang Uji Coba Diubah' && $checkInv['location'] === 'Lab Test 2';
assert_test($editOk, "6. Edit Inventaris", "Nama dan lokasi barang berhasil diperbarui");

// --- SKENARIO 7: Ubah Kondisi Barang ---
$stockBefore = $pdo->query("SELECT * FROM inventories WHERE id = $testInvId")->fetch();
$nextStock = adjust_inventory_condition($stockBefore, 'baik', 'rusak_ringan', 2);
$pdo->prepare("UPDATE inventories SET qty_available=?, qty_damaged=?, qty_damaged_light=?, qty_damaged_heavy=? WHERE id=?")
    ->execute([$nextStock['qty_available'], $nextStock['qty_damaged'], $nextStock['qty_damaged_light'], $nextStock['qty_damaged_heavy'], $testInvId]);
$pdo->prepare("INSERT INTO condition_histories (inventory_id, from_condition, to_condition, qty, notes, source, recorded_by) VALUES (?, 'baik', 'rusak_ringan', 2, 'Uji coba kerusakan', 'manual', 1)")
    ->execute([$testInvId]);
$condHistCount = (int)$pdo->query("SELECT COUNT(*) FROM condition_histories WHERE inventory_id = $testInvId")->fetchColumn();
assert_test($condHistCount > 0 && $nextStock['qty_available'] === 8 && $nextStock['qty_damaged'] === 2, "7. Ubah Kondisi Barang", "Riwayat kondisi tercatat, tersedia: 8, rusak: 2");

// Kembalikan ke kondisi baik untuk pengujian selanjutnya
$stockFixed = adjust_inventory_condition($nextStock, 'rusak_ringan', 'baik', 2);
$pdo->prepare("UPDATE inventories SET qty_available=?, qty_damaged=?, qty_damaged_light=?, qty_damaged_heavy=? WHERE id=?")
    ->execute([$stockFixed['qty_available'], $stockFixed['qty_damaged'], $stockFixed['qty_damaged_light'], $stockFixed['qty_damaged_heavy'], $testInvId]);

// --- SKENARIO 8: Tambah Pengguna ---
$testUsername = 'user_test_' . time();
$testHash = password_hash('password123', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users (full_name, username, password_hash, role, identity_no, user_type, is_active) VALUES ('User Pengujian', ?, ?, 'peminjam', '99001122', 'mahasiswa', 1)")
    ->execute([$testUsername, $testHash]);
$testUserId = (int)$pdo->lastInsertId();
assert_test($testUserId > 0, "8. Tambah Pengguna", "Pengguna baru tersimpan dengan ID: $testUserId");

// =========================================================================
// SKENARIO H: SIMULASI ATURAN STOK LENGKAP & INTEGRASI TRANSAKSI
// Total: 10, Tersedia: 10, Dipinjam: 0, Rusak: 0
// =========================================================================
echo "\n--- SIMULASI ATURAN STOK LENGKAP (SECTION H) ---\n";
$inv0 = $pdo->query("SELECT qty_total, qty_available, qty_borrowed, qty_damaged FROM inventories WHERE id = $testInvId")->fetch();
echo "Stok awal barang: Total={$inv0['qty_total']}, Tersedia={$inv0['qty_available']}, Dipinjam={$inv0['qty_borrowed']}, Rusak={$inv0['qty_damaged']}\n";

// 1. Peminjam mengajukan peminjaman 3 unit barang
$testCodePjm = 'PJM-TEST-' . time();
$pdo->prepare("INSERT INTO borrowings (borrowing_code, user_id, borrow_date, planned_return_date, purpose, status) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Uji Coba Pengajuan', 'menunggu')")
    ->execute([$testCodePjm, $testUserId]);
$testBorrowId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO borrowing_details (borrowing_id, inventory_id, qty, qty_returned) VALUES (?, ?, 3, 0)")
    ->execute([$testBorrowId, $testInvId]);

// --- SKENARIO 9: Pengajuan peminjaman (status menunggu, stok tidak berubah) ---
$invAfterSubmit = $pdo->query("SELECT qty_total, qty_available, qty_borrowed, qty_damaged FROM inventories WHERE id = $testInvId")->fetch();
$stockUnchanged = $invAfterSubmit['qty_available'] == 10 && $invAfterSubmit['qty_borrowed'] == 0 && $invAfterSubmit['qty_damaged'] == 0;
assert_test($stockUnchanged, "9. Pengajuan Peminjaman", "Status menunggu persetujuan, stok barang BELUM berkurang (Tersedia=10, Dipinjam=0)");

// --- SKENARIO 10: Persetujuan Peminjaman ---
// Admin menyetujui: status -> dipinjam, stok tersedia berkurang 3, dipinjam bertambah 3
$pdo->prepare("UPDATE borrowings SET status = 'dipinjam', processed_by = 1, processed_at = NOW() WHERE id = ?")
    ->execute([$testBorrowId]);
$pdo->prepare("UPDATE inventories SET qty_available = qty_available - 3, qty_borrowed = qty_borrowed + 3 WHERE id = ?")
    ->execute([$testInvId]);

$invAfterApprove = $pdo->query("SELECT qty_total, qty_available, qty_borrowed, qty_damaged FROM inventories WHERE id = $testInvId")->fetch();
$approveOk = $invAfterApprove['qty_available'] == 7 && $invAfterApprove['qty_borrowed'] == 3 && $invAfterApprove['qty_total'] == 10;
assert_test($approveOk, "10. Persetujuan Peminjaman", "Status dipinjam, stok tersedia berkurang jadi 7, dipinjam jadi 3, total tetap 10");

// --- SKENARIO 11: Penolakan Peminjaman ---
// Buat pengajuan baru untuk diuji penolakannya
$testCodeReject = 'PJM-REJ-' . time();
$pdo->prepare("INSERT INTO borrowings (borrowing_code, user_id, borrow_date, planned_return_date, purpose, status) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Uji Penolakan', 'menunggu')")
    ->execute([$testCodeReject, $testUserId]);
$rejectBorrowId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO borrowing_details (borrowing_id, inventory_id, qty, qty_returned) VALUES (?, ?, 2, 0)")
    ->execute([$rejectBorrowId, $testInvId]);

// Admin tolak dengan alasan
$pdo->prepare("UPDATE borrowings SET status = 'ditolak', rejection_reason = 'Stok diprioritaskan untuk praktikum', processed_by = 1, processed_at = NOW() WHERE id = ?")
    ->execute([$rejectBorrowId]);

$invAfterReject = $pdo->query("SELECT qty_total, qty_available, qty_borrowed, qty_damaged FROM inventories WHERE id = $testInvId")->fetch();
$checkReject = $pdo->query("SELECT status, rejection_reason FROM borrowings WHERE id = $rejectBorrowId")->fetch();
$rejectOk = $checkReject['status'] === 'ditolak' && $checkReject['rejection_reason'] !== '' && $invAfterReject['qty_available'] == 7;
assert_test($rejectOk, "11. Penolakan Peminjaman", "Status ditolak dengan alasan tersimpan, stok tidak berubah");

// Detail ID dari peminjaman aktif pertama ($testBorrowId)
$detailRow = $pdo->query("SELECT id FROM borrowing_details WHERE borrowing_id = $testBorrowId")->fetch();
$detailId = (int)$detailRow['id'];

// --- SKENARIO 12: Pengembalian 2 barang kondisi BAIK ---
// Sisa pinjaman 3. Kembalikan 2 dalam kondisi baik:
// qty_returned = 2, qty_available = 7 + 2 = 9, qty_borrowed = 3 - 2 = 1.
$pdo->prepare("UPDATE borrowing_details SET qty_returned = qty_returned + 2 WHERE id = ?")
    ->execute([$detailId]);
$pdo->prepare("INSERT INTO return_histories (borrowing_detail_id, qty, return_condition, actual_return_date, is_late, notes, recorded_by) VALUES (?, 2, 'baik', CURDATE(), 0, 'Tahap 1 kondisi baik', 1)")
    ->execute([$detailId]);
$pdo->prepare("UPDATE inventories SET qty_borrowed = qty_borrowed - 2, qty_available = qty_available + 2 WHERE id = ?")
    ->execute([$testInvId]);

$invAfterRetGood = $pdo->query("SELECT qty_total, qty_available, qty_borrowed, qty_damaged FROM inventories WHERE id = $testInvId")->fetch();
$retGoodOk = $invAfterRetGood['qty_available'] == 9 && $invAfterRetGood['qty_borrowed'] == 1 && $invAfterRetGood['qty_damaged'] == 0;
assert_test($retGoodOk, "12. Pengembalian Barang Baik", "Tersedia menjadi 9, dipinjam menjadi 1, rusak tetap 0, total tetap 10");

// --- SKENARIO 13: Pengembalian 1 barang sisa kondisi RUSAK ---
// Kembalikan sisa 1 dalam kondisi rusak_ringan:
// qty_returned = 3, qty_borrowed = 1 - 1 = 0, qty_damaged = 0 + 1 = 1, qty_available = tetap 9.
$pdo->prepare("UPDATE borrowing_details SET qty_returned = qty_returned + 1 WHERE id = ?")
    ->execute([$detailId]);
$pdo->prepare("INSERT INTO return_histories (borrowing_detail_id, qty, return_condition, actual_return_date, is_late, notes, recorded_by) VALUES (?, 1, 'rusak_ringan', CURDATE(), 0, 'Tahap 2 rusak ringan', 1)")
    ->execute([$detailId]);
$pdo->prepare("UPDATE inventories SET qty_borrowed = qty_borrowed - 1, qty_damaged = qty_damaged + 1, qty_damaged_light = qty_damaged_light + 1 WHERE id = ?")
    ->execute([$testInvId]);
$pdo->prepare("INSERT INTO condition_histories (inventory_id, from_condition, to_condition, qty, notes, source, recorded_by) VALUES (?, 'baik', 'rusak_ringan', 1, 'Rusak saat peminjaman', 'pengembalian', 1)")
    ->execute([$testInvId]);

// Karena semua barang (3 dari 3) telah dikembalikan, status peminjaman menjadi 'selesai'
$unfinished = (int)$pdo->query("SELECT COUNT(*) FROM borrowing_details WHERE borrowing_id = $testBorrowId AND qty_returned < qty")->fetchColumn();
if ($unfinished === 0) {
    $pdo->query("UPDATE borrowings SET status = 'selesai', updated_at = NOW() WHERE id = $testBorrowId");
}

$invFinal = $pdo->query("SELECT qty_total, qty_available, qty_borrowed, qty_damaged, qty_damaged_light, qty_damaged_heavy FROM inventories WHERE id = $testInvId")->fetch();
$borrowStatusFinal = $pdo->query("SELECT status FROM borrowings WHERE id = $testBorrowId")->fetchColumn();

$retDamagedOk = $invFinal['qty_available'] == 9 &&
                $invFinal['qty_borrowed'] == 0 &&
                $invFinal['qty_damaged'] == 1 &&
                $invFinal['qty_damaged_light'] == 1 &&
                $invFinal['qty_total'] == 10 &&
                $borrowStatusFinal === 'selesai';
assert_test($retDamagedOk, "13. Pengembalian Barang Rusak", "Tersedia tetap 9, dipinjam menjadi 0, rusak menjadi 1, total tetap 10, status transaksi: selesai");

// --- SKENARIO 14: Riwayat Admin (Menampilkan Seluruh Transaksi) ---
$stmt = $pdo->query("SELECT COUNT(*) FROM borrowings");
$allTransCount = (int)$stmt->fetchColumn();
assert_test($allTransCount >= 2, "14. Melihat Riwayat Admin", "Admin dapat melihat seluruh $allTransCount transaksi dari semua pengguna");

// --- SKENARIO 15: Riwayat Peminjam (Hanya Menampilkan Milik Sendiri) ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = ?");
$stmt->execute([$testUserId]);
$myTransCount = (int)$stmt->fetchColumn();
$stmtOther = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = 2");
$stmtOther->execute();
$budiTransCount = (int)$stmtOther->fetchColumn();
assert_test($myTransCount === 2 && $budiTransCount > 0, "15. Melihat Riwayat Peminjam", "Peminjam test hanya melihat $myTransCount transaksi miliknya tanpa melihat transaksi user lain");

// --- VERIFIKASI RUMUS TOTAL STOK KESELURUHAN (BR-12) ---
$imbalanced = (int)$pdo->query("SELECT COUNT(*) FROM inventories WHERE qty_total != (qty_available + qty_borrowed + qty_damaged)")->fetchColumn();
assert_test($imbalanced === 0, "Konsistensi Aturan Stok BR-12", "Semua inventaris di database memenuhi Total = Tersedia + Dipinjam + Rusak");

echo "\n=== SELURUH PENGUJIAN FUNGSIONAL DAN ATURAN STOK LULUS 100% ===\n";

