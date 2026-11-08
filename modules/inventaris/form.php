<?php
/** HL-04 Form tambah/edit barang. */
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
$user = require_role('admin');
$id = valid_nonnegative_int($_GET['id'] ?? '0');
if ($id === null) { http_response_code(400); exit('ID barang tidak valid.'); }
$item = ['id'=>0,'code'=>'','name'=>'','category_id'=>'','location'=>'','description'=>'','qty_total'=>0];
if ($id > 0) {
  $stmt = db()->prepare('SELECT * FROM inventories WHERE id = ?'); $stmt->execute([$id]); $found = $stmt->fetch();
  if (!$found) { http_response_code(404); exit('Barang tidak ditemukan.'); }
  $item = $found;
}
$stmt = db()->prepare('SELECT id, name FROM categories WHERE is_active = 1 OR id = ? ORDER BY name'); $stmt->execute([$id > 0 ? $item['category_id'] : 0]); $categories = $stmt->fetchAll();
$pageTitle = $id > 0 ? 'Edit Inventaris' : 'Tambah Inventaris'; $activeMenu = 'inventaris';
require __DIR__ . '/../../layouts/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">Inventaris laboratorium</p><h2><?= e($pageTitle) ?></h2><p class="muted">Stok tersedia dihitung dari total setelah dikurangi unit dipinjam dan rusak.</p></div><a class="btn btn-light" href="<?= e(url('modules/inventaris/index.php')) ?>">Kembali ke daftar</a></section>
<section class="card form-card"><form method="post" action="<?= e(url('modules/inventaris/action.php')) ?>">
<?= csrf_field() ?><input type="hidden" name="action" value="inventory_save"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
<div class="form-grid"><label class="field"><span>Kode inventaris *</span><input name="code" maxlength="30" required value="<?= e($item['code']) ?>"></label><label class="field"><span>Nama barang *</span><input name="name" maxlength="150" required value="<?= e($item['name']) ?>"></label>
<label class="field"><span>Kategori *</span><select name="category_id" required><option value="">Pilih kategori</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"<?= (int)$item['category_id'] === (int)$category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label><label class="field"><span>Lokasi penyimpanan *</span><input name="location" maxlength="100" required value="<?= e($item['location']) ?>"></label>
<label class="field"><span>Total stok *</span><input type="number" name="qty_total" min="0" step="1" required value="<?= (int)$item['qty_total'] ?>"></label><label class="field field-wide"><span>Keterangan</span><textarea name="description" rows="4" maxlength="20000"><?= e($item['description']) ?></textarea></label></div>
<?php if ($id > 0): ?><p class="form-note">Dipinjam: <?= (int)$item['qty_borrowed'] ?> · Rusak: <?= (int)$item['qty_damaged'] ?>. Total baru tidak boleh kurang dari gabungan keduanya.</p><?php endif; ?>
<div class="form-actions"><a class="btn btn-light" href="<?= e(url('modules/inventaris/index.php')) ?>">Batal</a><button class="btn" type="submit">Simpan</button></div></form></section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
