<?php
/** HL-14 Action: Simpan pengajuan peminjaman baru (KF-05, BR-04, BR-05). */
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('peminjam');

// Hanya proses POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('peminjam/ajukan.php');
}

verify_csrf();

$pdo = db();

// --- Validasi input ---
$inventoryId       = valid_nonnegative_int($_POST['inventory_id'] ?? '');
$qty               = valid_nonnegative_int($_POST['qty'] ?? '');
$borrowDate        = trim((string)($_POST['borrow_date'] ?? ''));
$plannedReturnDate = trim((string)($_POST['planned_return_date'] ?? ''));
$purpose           = trim((string)($_POST['purpose'] ?? ''));

$errors = [];

if (!$inventoryId || $inventoryId < 1) {
    $errors[] = 'Pilih barang yang ingin dipinjam.';
}
if (!$qty || $qty < 1) {
    $errors[] = 'Jumlah barang minimal 1.';
}
if (!$borrowDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $borrowDate)) {
    $errors[] = 'Tanggal peminjaman tidak valid.';
} elseif ($borrowDate < date('Y-m-d')) {
    $errors[] = 'Tanggal peminjaman tidak boleh sebelum hari ini.';
}
if (!$plannedReturnDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $plannedReturnDate)) {
    $errors[] = 'Rencana tanggal pengembalian tidak valid.';
}
if ($borrowDate && $plannedReturnDate && $plannedReturnDate < $borrowDate) {
    $errors[] = 'Tanggal pengembalian tidak boleh lebih awal dari tanggal peminjaman.';
}
if ($purpose === '') {
    $errors[] = 'Keperluan peminjaman wajib diisi.';
}

if ($errors) {
    foreach ($errors as $err) {
        flash('error', $err);
    }
    redirect('peminjam/ajukan.php');
}

// --- Proses dalam transaksi ---
try {
    db_transaction(function (PDO $pdo) use ($user, $inventoryId, $qty, $borrowDate, $plannedReturnDate, $purpose) {

        // Kunci baris inventaris untuk cek stok real-time (BR-05)
        $stmt = $pdo->prepare(
            'SELECT id, name, is_active, qty_available
             FROM inventories WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$inventoryId]);
        $inv = $stmt->fetch();

        if (!$inv) {
            throw new RuntimeException('Barang tidak ditemukan.');
        }
        if (!(int)$inv['is_active']) {
            throw new RuntimeException('Barang tidak aktif dan tidak dapat dipinjam.');
        }
        if ((int)$inv['qty_available'] < $qty) {
            throw new RuntimeException(
                'Stok tersedia (' . (int)$inv['qty_available'] . ') tidak mencukupi jumlah yang diminta (' . $qty . ').'
            );
        }

        // Generate borrowing_code: PJM-YYYYMMDD-NNN
        $today      = date('Ymd');
        $prefix     = 'PJM-' . $today . '-';
        $codeStmt   = $pdo->prepare('SELECT borrowing_code FROM borrowings WHERE borrowing_code LIKE ? ORDER BY id DESC LIMIT 1');
        $codeStmt->execute([$prefix . '%']);
        $lastCode   = $codeStmt->fetchColumn();
        $seq        = 1;
        if ($lastCode && preg_match('/-(\d{3})$/', $lastCode, $m)) {
            $seq = (int)$m[1] + 1;
        }
        $borrowingCode = $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);

        // Insert borrowings (status 'menunggu' — TIDAK kurangi stok)
        $ins = $pdo->prepare(
            'INSERT INTO borrowings
                (borrowing_code, user_id, borrow_date, planned_return_date, purpose, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, \'menunggu\', NOW(), NOW())'
        );
        $ins->execute([$borrowingCode, $user['id'], $borrowDate, $plannedReturnDate, $purpose]);
        $borrowingId = (int)$pdo->lastInsertId();

        // Insert borrowing_details
        $det = $pdo->prepare(
            'INSERT INTO borrowing_details (borrowing_id, inventory_id, qty, qty_returned)
             VALUES (?, ?, ?, 0)'
        );
        $det->execute([$borrowingId, $inventoryId, $qty]);
    });

    flash('success', 'Pengajuan peminjaman berhasil dikirim dan sedang menunggu persetujuan Admin/Laboran.');
    redirect('peminjam/riwayat.php');

} catch (RuntimeException $e) {
    flash('error', $e->getMessage());
    redirect('peminjam/ajukan.php');
} catch (Throwable $e) {
    flash('error', 'Terjadi kesalahan sistem. Silakan coba lagi.');
    redirect('peminjam/ajukan.php');
}

