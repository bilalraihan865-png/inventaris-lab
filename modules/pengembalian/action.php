<?php
/** TASK 6 — Simpan Pengembalian (admin). */
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role('admin');
verify_csrf();

$borrowingId = valid_nonnegative_int($_POST['borrowing_id'] ?? '');
$detailIds   = $_POST['detail_ids'] ?? [];
$catatan     = trim((string)($_POST['catatan'] ?? ''));

if (!$borrowingId || $borrowingId < 1) {
    flash('error', 'ID peminjaman tidak valid.');
    redirect('modules/pengembalian/index.php');
}
if (!is_array($detailIds) || empty($detailIds)) {
    flash('error', 'Tidak ada detail barang yang disubmit.');
    redirect('modules/pengembalian/index.php');
}

$pdo  = db();
$today = date('Y-m-d');

// Verify borrowing exists and is 'dipinjam'
$stmt = $pdo->prepare("SELECT * FROM borrowings WHERE id = ? AND status = 'dipinjam'");
$stmt->execute([$borrowingId]);
$borrowing = $stmt->fetch();

if (!$borrowing) {
    flash('error', 'Peminjaman tidak ditemukan atau sudah selesai.');
    redirect('modules/pengembalian/index.php');
}

$isLate = $borrowing['planned_return_date'] < $today ? 1 : 0;

try {
    db_transaction(function (PDO $pdo) use ($borrowingId, $detailIds, $catatan, $today, $isLate, $user) {
        $processedAny = false;

        foreach ($detailIds as $rawDetailId) {
            $detailId = valid_nonnegative_int($rawDetailId);
            if (!$detailId || $detailId < 1) {
                continue;
            }

            $qtyReturn = (int)($_POST['qty_return_' . $detailId] ?? 0);
            $condition = (string)($_POST['condition_' . $detailId] ?? 'baik');

            if (!in_array($condition, ['baik', 'rusak_ringan', 'rusak_berat'], true)) {
                $condition = 'baik';
            }

            if ($qtyReturn <= 0) {
                continue;
            }

            // Lock the borrowing_detail row
            $detailStmt = $pdo->prepare(
                'SELECT bd.*, (bd.qty - bd.qty_returned) AS qty_remaining
                 FROM borrowing_details bd
                 WHERE bd.id = ? AND bd.borrowing_id = ?
                 FOR UPDATE'
            );
            $detailStmt->execute([$detailId, $borrowingId]);
            $detail = $detailStmt->fetch();

            if (!$detail) {
                throw new RuntimeException('Detail barang ID ' . $detailId . ' tidak ditemukan.');
            }

            $remaining = (int)$detail['qty'] - (int)$detail['qty_returned'];

            if ($qtyReturn > $remaining) {
                throw new RuntimeException(
                    'Jumlah pengembalian melebihi sisa pinjaman untuk detail ID ' . $detailId . '.'
                );
            }

            // Update qty_returned on borrowing_details
            $pdo->prepare(
                'UPDATE borrowing_details SET qty_returned = qty_returned + ? WHERE id = ?'
            )->execute([$qtyReturn, $detailId]);

            // Insert return_history record
            $pdo->prepare(
                "INSERT INTO return_histories
                    (borrowing_detail_id, qty, return_condition, actual_return_date, is_late, notes, recorded_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            )->execute([$detailId, $qtyReturn, $condition, $today, $isLate, $catatan ?: null, $user['id']]);

            // Lock inventory row and update stock
            $invStmt = $pdo->prepare('SELECT id FROM inventories WHERE id = ? FOR UPDATE');
            $invStmt->execute([$detail['inventory_id']]);

            if ($condition === 'baik') {
                $pdo->prepare(
                    'UPDATE inventories SET qty_borrowed = qty_borrowed - ?, qty_available = qty_available + ? WHERE id = ?'
                )->execute([$qtyReturn, $qtyReturn, $detail['inventory_id']]);
            } elseif ($condition === 'rusak_ringan') {
                $pdo->prepare(
                    'UPDATE inventories
                     SET qty_borrowed = qty_borrowed - ?,
                         qty_damaged  = qty_damaged  + ?,
                         qty_damaged_light = qty_damaged_light + ?
                     WHERE id = ?'
                )->execute([$qtyReturn, $qtyReturn, $qtyReturn, $detail['inventory_id']]);

                // Insert condition_history for damaged items
                $pdo->prepare(
                    "INSERT INTO condition_histories
                        (inventory_id, from_condition, to_condition, qty, notes, source, recorded_by, created_at)
                     VALUES (?, 'baik', 'rusak_ringan', ?, ?, 'pengembalian', ?, NOW())"
                )->execute([$detail['inventory_id'], $qtyReturn, $catatan ?: null, $user['id']]);
            } else { // rusak_berat
                $pdo->prepare(
                    'UPDATE inventories
                     SET qty_borrowed = qty_borrowed - ?,
                         qty_damaged  = qty_damaged  + ?,
                         qty_damaged_heavy = qty_damaged_heavy + ?
                     WHERE id = ?'
                )->execute([$qtyReturn, $qtyReturn, $qtyReturn, $detail['inventory_id']]);

                // Insert condition_history for damaged items
                $pdo->prepare(
                    "INSERT INTO condition_histories
                        (inventory_id, from_condition, to_condition, qty, notes, source, recorded_by, created_at)
                     VALUES (?, 'baik', 'rusak_berat', ?, ?, 'pengembalian', ?, NOW())"
                )->execute([$detail['inventory_id'], $qtyReturn, $catatan ?: null, $user['id']]);
            }

            $processedAny = true;
        }

        if (!$processedAny) {
            throw new RuntimeException('Tidak ada jumlah pengembalian yang valid (semua nol).');
        }

        // Check if all details are fully returned — if so, mark borrowing as 'selesai'
        $allReturnedStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM borrowing_details WHERE borrowing_id = ? AND qty_returned < qty'
        );
        $allReturnedStmt->execute([$borrowingId]);
        $unfinished = (int)$allReturnedStmt->fetchColumn();

        if ($unfinished === 0) {
            $pdo->prepare(
                "UPDATE borrowings SET status = 'selesai', updated_at = NOW() WHERE id = ?"
            )->execute([$borrowingId]);
        } else {
            $pdo->prepare(
                "UPDATE borrowings SET updated_at = NOW() WHERE id = ?"
            )->execute([$borrowingId]);
        }
    });

    flash('success', 'Pengembalian barang berhasil disimpan.');
} catch (Throwable $e) {
    flash('error', 'Gagal memproses pengembalian: ' . $e->getMessage());
}

redirect('modules/pengembalian/index.php');
