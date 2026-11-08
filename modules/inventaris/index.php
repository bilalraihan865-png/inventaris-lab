<?php
/** HL-03 Data Inventaris dan kategori barang. */
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../components/ui.php';
require_once __DIR__ . '/../../includes/helpers.php';
$user = require_role('admin');
$pdo = db();
$q = trim((string)($_GET['q'] ?? ''));
$categoryId = valid_nonnegative_int($_GET['category_id'] ?? '');
$condition = (string)($_GET['condition'] ?? '');
$categoryQuery = trim((string)($_GET['category_q'] ?? ''));
$active = (string)($_GET['active'] ?? '1');
$where = [];
$params = [];
if ($q !== '') { $where[] = '(i.code LIKE ? OR i.name LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
if ($categoryId) { $where[] = 'i.category_id = ?'; $params[] = $categoryId; }
if ($active === '0' || $active === '1') { $where[] = 'i.is_active = ?'; $params[] = (int)$active; }
if ($condition === 'baik') $where[] = 'i.qty_available > 0';
if ($condition === 'rusak_ringan') $where[] = 'i.qty_damaged_light > 0';
if ($condition === 'rusak_berat') $where[] = 'i.qty_damaged_heavy > 0';
$sql = 'SELECT i.*, c.name AS category_name FROM inventories i JOIN categories c ON c.id = i.category_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY i.name';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $items = $stmt->fetchAll();
$categorySql = 'SELECT c.*, (SELECT COUNT(*) FROM inventories i WHERE i.category_id = c.id) AS item_count FROM categories c';
$categoryParams = [];
if ($categoryQuery !== '') { $categorySql .= ' WHERE c.name LIKE ? OR c.description LIKE ?'; $categoryParams = ['%' . $categoryQuery . '%', '%' . $categoryQuery . '%']; }
$categorySql .= ' ORDER BY c.name';
$categoryStmt = $pdo->prepare($categorySql); $categoryStmt->execute($categoryParams); $categories = $categoryStmt->fetchAll();
$activeCategories = $pdo->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();
$pageTitle = 'Data Inventaris'; $activeMenu = 'inventaris';
require __DIR__ . '/../../layouts/header.php';
?>
<section class="page-heading">
  <div><p class="eyebrow">Pengelolaan barang</p><h2>Data inventaris laboratorium</h2><p class="muted">Jumlah rusak dipisahkan menurut kondisi dan tidak masuk stok tersedia.</p></div>
  <a class="btn" href="<?= e(url('modules/inventaris/form.php')) ?>">+ Tambah barang</a>
</section>
<section class="card">
  <form method="get" class="filter-bar">
    <label class="field"><span>Cari kode atau nama</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Mis. INV-KMP-001"></label>
    <label class="field"><span>Kategori</span><select name="category_id"><option value="">Semua kategori</option><?php foreach ($activeCategories as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $categoryId === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>Kondisi</span><select name="condition"><option value="">Semua kondisi</option><?php foreach (['baik'=>'Baik','rusak_ringan'=>'Rusak Ringan','rusak_berat'=>'Rusak Berat'] as $value=>$label): ?><option value="<?= e($value) ?>"<?= $condition === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>Status barang</span><select name="active"><option value="1"<?= $active === '1' ? ' selected' : '' ?>>Aktif</option><option value="0"<?= $active === '0' ? ' selected' : '' ?>>Nonaktif</option><option value="all"<?= $active === 'all' ? ' selected' : '' ?>>Semua</option></select></label>
    <div class="filter-actions"><button class="btn" type="submit">Terapkan</button><a class="btn btn-light" href="<?= e(url('modules/inventaris/index.php')) ?>">Reset</a></div>
  </form>
  <div class="table-wrap"><table class="table"><thead><tr><th>Kode / Barang</th><th>Kategori</th><th>Lokasi</th><th class="num">Total</th><th class="num">Tersedia</th><th class="num">Dipinjam</th><th class="num">Rusak</th><th>Kondisi</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
  <?php foreach ($items as $item): ?><tr>
    <td><span class="mono"><?= e($item['code']) ?></span><br><strong><?= e($item['name']) ?></strong></td><td><?= e($item['category_name']) ?></td><td><?= e($item['location']) ?></td>
    <td class="num"><?= (int)$item['qty_total'] ?></td><td class="num"><?= (int)$item['qty_available'] ?></td><td class="num"><?= (int)$item['qty_borrowed'] ?></td><td class="num"><?= (int)$item['qty_damaged'] ?></td>
    <td><?= e(inventory_condition_label($item)) ?></td><td><?= (int)$item['is_active'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-neutral">Nonaktif</span>' ?></td>
    <td><div class="row-actions"><a href="<?= e(url('modules/inventaris/detail.php?id=' . (int)$item['id'])) ?>">Detail</a><a href="<?= e(url('modules/inventaris/form.php?id=' . (int)$item['id'])) ?>">Edit</a><form method="post" action="<?= e(url('modules/inventaris/action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="inventory_toggle"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="link-button" data-confirm="Ubah status barang ini?" type="submit"><?= (int)$item['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></div></td>
  </tr><?php endforeach; ?>
  <?php if (!$items): ?><tr><td colspan="10" class="empty">Tidak ada barang yang sesuai dengan pencarian.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<section class="card category-section" id="kategori">
  <div class="card-head"><div><p class="eyebrow">Pengelompokan</p><h3>Kategori barang</h3></div></div>
  <form method="get" class="filter-bar category-search"><label class="field"><span>Cari kategori</span><input type="search" name="category_q" value="<?= e($categoryQuery) ?>" placeholder="Nama atau keterangan"></label><div class="filter-actions"><button class="btn" type="submit">Cari kategori</button><a class="btn btn-light" href="<?= e(url('modules/inventaris/index.php#kategori')) ?>">Reset</a></div></form>
  <div class="category-layout">
    <div class="table-wrap"><table class="table"><thead><tr><th>Nama kategori</th><th>Keterangan</th><th>Barang</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($categories as $category): ?><tr><td><strong><?= e($category['name']) ?></strong></td><td><?= e($category['description'] ?: '—') ?></td><td><?= (int)$category['item_count'] ?></td><td><div class="row-actions"><details class="category-edit"><summary>Edit</summary><form method="post" action="<?= e(url('modules/inventaris/action.php')) ?>" class="category-inline"><?= csrf_field() ?><input type="hidden" name="action" value="category_save"><input type="hidden" name="id" value="<?= (int)$category['id'] ?>"><label>Nama<input name="name" maxlength="100" required value="<?= e($category['name']) ?>"></label><label>Keterangan<input name="description" maxlength="255" value="<?= e($category['description']) ?>"></label><button class="btn btn-small" type="submit">Perbarui</button></form></details><form method="post" action="<?= e(url('modules/inventaris/action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="category_delete"><input type="hidden" name="id" value="<?= (int)$category['id'] ?>"><button type="submit" class="link-button danger-link" data-confirm="Hapus kategori yang belum digunakan?">Hapus</button></form></div></td></tr><?php endforeach; ?>
    <?php if (!$categories): ?><tr><td colspan="4" class="empty">Belum ada kategori.</td></tr><?php endif; ?>
    </tbody></table></div>
    <form method="post" action="<?= e(url('modules/inventaris/action.php')) ?>" class="category-create"><?= csrf_field() ?><input type="hidden" name="action" value="category_save"><h4>Tambah kategori</h4><label class="field"><span>Nama</span><input name="name" maxlength="100" required></label><label class="field"><span>Keterangan</span><input name="description" maxlength="255"></label><button class="btn" type="submit">Simpan kategori</button></form>
  </div>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
