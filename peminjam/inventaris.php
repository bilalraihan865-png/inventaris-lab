<?php
/** HL-12 Daftar inventaris baca-saja untuk Peminjam + link ajukan peminjaman (KF-04). */
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../components/ui.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('peminjam');
$pdo  = db();

$q          = trim((string)($_GET['q'] ?? ''));
$categoryId = valid_nonnegative_int($_GET['category_id'] ?? '');

$where  = ['i.is_active = 1', 'c.is_active = 1'];
$params = [];

if ($q !== '') {
    $where[]  = '(i.code LIKE ? OR i.name LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($categoryId) {
    $where[]  = 'i.category_id = ?';
    $params[] = $categoryId;
}

$stmt = $pdo->prepare(
    'SELECT i.*, c.name AS category_name
     FROM inventories i
     JOIN categories c ON c.id = i.category_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY (i.qty_available > 0) DESC, i.name'
);
$stmt->execute($params);
$items = $stmt->fetchAll();

$categories = $pdo->query(
    'SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name'
)->fetchAll();

$pageTitle  = 'Daftar Inventaris';
$activeMenu = 'inventaris';
require __DIR__ . '/../layouts/header.php';
?>

<section class="page-heading">
  <div>
    <p class="eyebrow">Katalog laboratorium</p>
    <h2>Inventaris tersedia</h2>
    <p class="muted">Barang nonaktif dan kategori nonaktif tidak ditampilkan.</p>
  </div>
  <a class="btn" href="<?= e(url('peminjam/ajukan.php')) ?>">+ Ajukan Peminjaman</a>
</section>

<section class="card">
  <form method="get" class="filter-bar">
    <label class="field">
      <span>Cari barang atau kode</span>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nama barang atau kode inventaris">
    </label>
    <label class="field">
      <span>Kategori</span>
      <select name="category_id">
        <option value="">Semua kategori</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>"<?= $categoryId === (int)$cat['id'] ? ' selected' : '' ?>>
            <?= e($cat['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="filter-actions">
      <button class="btn" type="submit">Terapkan</button>
      <a class="btn btn-light" href="<?= e(url('peminjam/inventaris.php')) ?>">Reset</a>
    </div>
  </form>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Kode / Barang</th>
          <th>Kategori</th>
          <th>Lokasi</th>
          <th>Kondisi</th>
          <th class="num">Tersedia</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td>
              <span class="mono"><?= e($item['code']) ?></span><br>
              <strong><?= e($item['name']) ?></strong>
            </td>
            <td><?= e($item['category_name']) ?></td>
            <td><?= e($item['location']) ?></td>
            <td><?= e(inventory_condition_label($item)) ?></td>
            <td class="num mono"><?= (int)$item['qty_available'] ?></td>
            <td><?= availability_badge((int)$item['qty_available']) ?></td>
            <td class="row-actions">
              <a href="<?= e(url('peminjam/detail_barang.php?id=' . (int)$item['id'])) ?>">Detail</a>
              <?php if ((int)$item['qty_available'] > 0): ?>
                <a class="btn btn-small"
                   href="<?= e(url('peminjam/ajukan.php?inventory_id=' . (int)$item['id'])) ?>">Pinjam</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
          <tr><td colspan="7" class="empty">Tidak ada barang yang sesuai.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
