<?php
/** Catat perubahan kondisi dengan saldo stok atomik dan riwayat. */
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/stock_rules.php';
$user = require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metode tidak diizinkan.'); }
verify_csrf();
$destination = 'modules/kondisi/index.php';
try {
    $inventoryId = valid_nonnegative_int($_POST['inventory_id'] ?? '');
    $qty = valid_nonnegative_int($_POST['qty'] ?? '');
    $from = (string)($_POST['from_condition'] ?? '');
    $to = (string)($_POST['to_condition'] ?? '');
    $notes = trim((string)($_POST['notes'] ?? ''));
    if (!$inventoryId || $inventoryId < 1 || $qty === null || mb_strlen($notes) > 255) throw new DomainException('Barang, jumlah, atau catatan tidak valid.');
    db_transaction(function (PDO $pdo) use ($inventoryId, $qty, $from, $to, $notes, $user): void {
        $stmt = $pdo->prepare('SELECT * FROM inventories WHERE id = ? AND is_active = 1 FOR UPDATE');
        $stmt->execute([$inventoryId]); $stock = $stmt->fetch();
        if (!$stock) throw new DomainException('Barang tidak ditemukan atau sudah nonaktif.');
        $next = adjust_inventory_condition($stock, $from, $to, $qty);
        $stmt = $pdo->prepare('UPDATE inventories SET qty_available=?, qty_damaged=?, qty_damaged_light=?, qty_damaged_heavy=? WHERE id=?');
        $stmt->execute([$next['qty_available'], $next['qty_damaged'], $next['qty_damaged_light'], $next['qty_damaged_heavy'], $inventoryId]);
        $stmt = $pdo->prepare("INSERT INTO condition_histories (inventory_id, from_condition, to_condition, qty, notes, source, recorded_by) VALUES (?, ?, ?, ?, ?, 'manual', ?)");
        $stmt->execute([$inventoryId, $from, $to, $qty, $notes ?: null, $user['id']]);
    });
    flash('success', 'Kondisi dan jumlah stok berhasil diperbarui.');
    redirect($destination);
} catch (DomainException $e) {
    flash('error', $e->getMessage());
} catch (Throwable $e) {
    error_log('Condition update failed: ' . $e->getMessage());
    flash('error', 'Perubahan kondisi tidak berhasil disimpan.');
}
redirect($destination);
