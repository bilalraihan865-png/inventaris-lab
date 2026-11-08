<?php
/** HL-05 Detail barang, saldo, dan riwayat kondisi. */
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../components/ui.php';
require_once __DIR__ . '/../../includes/helpers.php';
$user = require_role('admin');
$id = valid_nonnegative_int($_GET['id'] ?? '');
if (!$id || $id < 1) { http_response_code(400); exit('ID barang tidak valid.'); }
$stmt = db()->prepare('SELECT i.*, c.name AS category_name FROM inventories i JOIN categories c ON c.id = i.category_id WHERE i.id = ?'); $stmt->execute([$id]); $item = $stmt->fetch();
if (!$item) { http_response_code(404); exit('Barang tidak ditemukan.'); }
$stmt = db()->prepare('SELECT h.*, u.full_name FROM condition_histories h JOIN users u ON u.id = h.recorded_by WHERE h.inventory_id = ? ORDER BY h.created_at DESC, h.id DESC'); $stmt->execute([$id]); $history = $stmt->fetchAll();
$conditionLabels = ['baik'=>'Baik','rusak_ringan'=>'Rusak Ringan','rusak_berat'=>'Rusak Berat'];
$pageTitle = 'Detail Inventaris'; $activeMenu = 'inventaris';
require __DIR__ . '/../../layouts/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">Detail barang</p><h2><?= e($item['name']) ?></h2><p class="mono"><?= e($item['code']) ?></p></div><div class="form-actions"><a class="btn btn-light" href="<?= e(url('modules/inventaris/index.php')) ?>">Kembali</a><a class="btn" href="<?= e(url('modules/inventaris/form.php?id=' . $id)) ?>">Edit barang</a></div></section>
<section class="card detail-card"><dl class="detail-grid"><div><dt>Kategori</dt><dd><?= e($item['category_name']) ?></dd></div><div><dt>Lokasi</dt><dd><?= e($item['location']) ?></dd></div><div><dt>Status</dt><dd><?= (int)$item['is_active'] ? 'Aktif' : 'Nonaktif' ?></dd></div><div><dt>Ketersediaan</dt><dd><?= e(availability_label((int)$item['qty_available'])) ?></dd></div><div><dt>Total unit</dt><dd><?= (int)$item['qty_total'] ?></dd></div><div><dt>Stok tersedia</dt><dd><?= (int)$item['qty_available'] ?></dd></div><div><dt>Sedang dipinjam</dt><dd><?= (int)$item['qty_borrowed'] ?></dd></div><div><dt>Stok rusak</dt><dd><?= (int)$item['qty_damaged'] ?></dd></div><div><dt>Rusak ringan</dt><dd><?= (int)$item['qty_damaged_light'] ?></dd></div><div><dt>Rusak berat</dt><dd><?= (int)$item['qty_damaged_heavy'] ?></dd></div><div class="detail-wide"><dt>Kondisi tercatat</dt><dd><?= e(inventory_condition_label($item)) ?></dd></div><div class="detail-wide"><dt>Keterangan</dt><dd><?= nl2br(e($item['description'] ?: '—')) ?></dd></div></dl></section>
<section class="card"><div class="card-head"><div><p class="eyebrow">Jejak perubahan</p><h3>Riwayat kondisi</h3></div><a href="<?= e(url('modules/kondisi/index.php?inventory_id=' . $id)) ?>">Catat perubahan</a></div><div class="table-wrap"><table class="table"><thead><tr><th>Tanggal</th><th>Perubahan</th><th>Jumlah unit</th><th>Catatan</th><th>Dicatat oleh</th></tr></thead><tbody><?php foreach ($history as $entry): ?><tr><td><?= e(format_date($entry['created_at'])) ?></td><td><?= e($conditionLabels[$entry['from_condition']] ?? $entry['from_condition']) ?> → <?= e($conditionLabels[$entry['to_condition']] ?? $entry['to_condition']) ?></td><td><?= (int)$entry['qty'] ?></td><td><?= e($entry['notes'] ?: '—') ?></td><td><?= e($entry['full_name']) ?></td></tr><?php endforeach; ?><?php if (!$history): ?><tr><td colspan="5" class="empty">Belum ada perubahan kondisi.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
