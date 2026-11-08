<?php
/** HL-06 Pengelolaan dan riwayat kondisi barang. */
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../components/ui.php';
require_once __DIR__ . '/../../includes/helpers.php';
$user = require_role('admin');
$pdo = db();
$inventoryId = valid_nonnegative_int($_GET['inventory_id'] ?? '');
$items = $pdo->query("SELECT i.*, c.name AS category_name FROM inventories i JOIN categories c ON c.id=i.category_id WHERE i.is_active=1 ORDER BY i.name")->fetchAll();
$sql = 'SELECT h.*, i.code, i.name, u.full_name FROM condition_histories h JOIN inventories i ON i.id=h.inventory_id JOIN users u ON u.id=h.recorded_by';
$params = [];
if ($inventoryId) { $sql .= ' WHERE h.inventory_id = ?'; $params[] = $inventoryId; }
$sql .= ' ORDER BY h.created_at DESC, h.id DESC LIMIT 200';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $history = $stmt->fetchAll();
$labels = ['baik'=>'Baik','rusak_ringan'=>'Rusak Ringan','rusak_berat'=>'Rusak Berat'];
$pageTitle = 'Kondisi Barang'; $activeMenu = 'kondisi'; require __DIR__ . '/../../layouts/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">Pemeriksaan unit</p><h2>Kondisi barang</h2><p class="muted">Perubahan kondisi menyesuaikan saldo tersedia dan rusak secara langsung.</p></div></section>
<section class="card form-card"><div class="card-head"><div><p class="eyebrow">Pencatatan</p><h3>Catat perubahan kondisi</h3></div></div>
<form method="post" action="<?= e(url('modules/kondisi/action.php')) ?>"><?= csrf_field() ?><div class="form-grid">
<label class="field field-wide"><span>Barang *</span><select name="inventory_id" required><option value="">Pilih barang</option><?php foreach ($items as $item): ?><option value="<?= (int)$item['id'] ?>"<?= $inventoryId === (int)$item['id'] ? ' selected' : '' ?>><?= e($item['code'] . ' — ' . $item['name'] . ' (baik ' . $item['qty_available'] . ', ringan ' . $item['qty_damaged_light'] . ', berat ' . $item['qty_damaged_heavy'] . ')') ?></option><?php endforeach; ?></select></label>
<label class="field"><span>Kondisi saat ini *</span><select name="from_condition" required><?php foreach ($labels as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><label class="field"><span>Kondisi baru *</span><select name="to_condition" required><?php foreach ($labels as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
<label class="field"><span>Jumlah unit *</span><input type="number" name="qty" min="1" step="1" required></label><label class="field"><span>Keterangan</span><input name="notes" maxlength="255" placeholder="Contoh: konektor longgar"></label></div><div class="form-actions"><button class="btn" type="submit">Simpan perubahan</button></div></form></section>
<section class="card"><div class="card-head"><div><p class="eyebrow">Riwayat</p><h3>Perubahan kondisi tercatat</h3></div><span class="muted small">Maksimal 200 perubahan terbaru</span></div><div class="table-wrap"><table class="table"><thead><tr><th>Tanggal</th><th>Barang</th><th>Perubahan</th><th>Jumlah</th><th>Keterangan</th><th>Admin</th></tr></thead><tbody><?php foreach ($history as $entry): ?><tr><td><?= e(format_date($entry['created_at'])) ?></td><td><a href="<?= e(url('modules/inventaris/detail.php?id=' . (int)$entry['inventory_id'])) ?>"><span class="mono"><?= e($entry['code']) ?></span><br><?= e($entry['name']) ?></a></td><td><?= e($labels[$entry['from_condition']] ?? $entry['from_condition']) ?> → <?= e($labels[$entry['to_condition']] ?? $entry['to_condition']) ?></td><td><?= (int)$entry['qty'] ?></td><td><?= e($entry['notes'] ?: '—') ?></td><td><?= e($entry['full_name']) ?></td></tr><?php endforeach; ?><?php if (!$history): ?><tr><td colspan="6" class="empty">Belum ada riwayat perubahan kondisi.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
