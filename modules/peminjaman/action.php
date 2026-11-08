<?php
/** TASK 2 — Proses Setujui / Tolak Peminjaman (admin). */
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role('admin');
verify_csrf();

// Support both 'action'/'id' (from detail.php) and 'aksi'/'borrowing_id' (from index.php inline forms)
$action = (string)($_POST['action'] ?? $_POST['aksi'] ?? '');
$id     = valid_nonnegative_int($_POST['id'] ?? $_POST['borrowing_id'] ?? '');

if (!$id || $id < 1 || !in_array($action, ['setujui', 'tolak'], true)) {
    flash('error', 'Permintaan tidak valid.');
    redirect('modules/peminjaman/index.php');
}

$pdo = db();

// Fetch borrowing — must be 'menunggu'
$stmt = $pdo->prepare('SELECT * FROM borrowings WHERE id = ? AND status = ?');
$stmt->execute([$id, 'menunggu']);
$borrowing = $stmt->fetch();

if (!$borrowing) {
    flash('error', 'Pengajuan tidak ditemukan atau sudah diproses sebelumnya.');
    redirect('modules/peminjaman/index.php');
}

if ($action === 'setujui') {
    try {
        db_transaction(function (PDO $pdo) use ($id, $user) {
            // Lock borrowing_details rows and their inventory rows
            $detailsStmt = $pdo->prepare(
                'SELECT bd.id, bd.inventory_id, bd.qty FROM borrowing_details bd WHERE bd.borrowing_id = ?'
            );
            $detailsStmt->execute([$id]);
            $details = $detailsStmt->fetchAll();

            if (empty($details)) {
                throw new RuntimeException('Tidak ada detail barang pada pengajuan ini.');
            }

            foreach ($details as $detail) {
                // Lock the inventory row
                $invStmt = $pdo->prepare(
                    'SELECT qty_available FROM inventories WHERE id = ? FOR UPDATE'
                );
                $invStmt->execute([$detail['inventory_id']]);
                $inv = $invStmt->fetch();

                if (!$inv) {
                    throw new RuntimeException('Barang tidak ditemukan (ID: ' . (int)$detail['inventory_id'] . ').');
                }
                if ((int)$inv['qty_available'] < (int)$detail['qty']) {
                    throw new RuntimeException('Stok tersedia tidak cukup untuk barang ID ' . (int)$detail['inventory_id'] . '.');
                }
            }

            // Update borrowing status
            $pdo->prepare(
                "UPDATE borrowings SET status = 'dipinjam', processed_by = ?, processed_at = NOW(), updated_at = NOW() WHERE id = ?"
            )->execute([$user['id'], $id]);

            // Adjust stock for each detail
            foreach ($details as $detail) {
                $pdo->prepare(
                    'UPDATE inventories SET qty_available = qty_available - ?, qty_borrowed = qty_borrowed + ? WHERE id = ?'
                )->execute([(int)$detail['qty'], (int)$detail['qty'], (int)$detail['inventory_id']]);
            }
        });

        flash('success', 'Pengajuan peminjaman berhasil disetujui.');
    } catch (Throwable $e) {
        flash('error', 'Gagal menyetujui: ' . $e->getMessage());
    }

} elseif ($action === 'tolak') {
    $rejectionReason = trim((string)($_POST['rejection_reason'] ?? ''));

    if ($rejectionReason === '') {
        flash('error', 'Alasan penolakan wajib diisi.');
        redirect('modules/peminjaman/index.php');
    }

    try {
        $pdo->prepare(
            "UPDATE borrowings SET status = 'ditolak', rejection_reason = ?, processed_by = ?, processed_at = NOW(), updated_at = NOW() WHERE id = ?"
        )->execute([$rejectionReason, $user['id'], $id]);

        flash('success', 'Pengajuan peminjaman berhasil ditolak.');
    } catch (Throwable $e) {
        flash('error', 'Gagal menolak pengajuan: ' . $e->getMessage());
    }
}

redirect('modules/peminjaman/index.php');
