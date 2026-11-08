<?php
/** TASK 4 — Daftar Peminjaman Aktif / Pengembalian (admin). */
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../components/ui.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role('admin');
$pdo  = db();

$q = trim((string)($_GET['q'] ?? ''));

$where  = ["b.status = 'dipinjam'"];
$params = [];

if ($q !== '') {
    $where[]  = '(b.borrowing_code LIKE ? OR u.full_name LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT
        b.id,
        b.borrowing_code,
        b.borrow_date,
        b.planned_return_date,
        b.status,
        u.full_name AS borrower_name,
        GROUP_CONCAT(i.name ORDER BY i.name SEPARATOR ', ') AS item_names
    FROM borrowings b
    JOIN users u ON u.id = b.user_id
    LEFT JOIN borrowing_details bd ON bd.borrowing_id = b.id
    LEFT JOIN inventories i ON i.id = bd.inventory_id
    $whereSql
    GROUP BY b.id, b.borrowing_code, b.borrow_date, b.planned_return_date, b.status,
             u.full_name
    ORDER BY b.planned_return_date ASC, b.borrow_date ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$borrowings = $stmt->fetchAll();

$today = date('Y-m-d');

$pageTitle  = 'Pengembalian Barang';
$activeMenu = 'pengembalian';
require __DIR__ . '/../../layouts/header.php';
?>
<section class="page-heading">
  <div>
    <p class="eyebrow">Manajemen pengembalian</p>
    <h2>Pengembalian Barang</h2>
    <p class="muted">Daftar peminjaman aktif yang perlu diproses pengembaliannya.</p>
  </div>
</section>

<section class="card">
  <form method="get" class="filter-bar">
    <label class="field">
      <span>Cari kode / peminjam</span>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Mis. PJM-20260101-001">
    </label>
    <div class="filter-actions">
      <button class="btn" type="submit">Cari</button>
      <a class="btn btn-light" href="<?= e(url('modules/pengembalian/index.php')) ?>">Reset</a>
    </div>
  </form>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Peminjam</th>
          <th>Barang</th>
          <th>Tanggal Pinjam</th>
          <th>Batas Kembali</th>
          <th>Status</th>
          <th>Keterlambatan</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($borrowings as $b): ?>
          <?php $isLate = $b['planned_return_date'] < $today; ?>
          <tr>
            <td><span class="mono"><?= e($b['borrowing_code']) ?></span></td>
            <td><?= e($b['borrower_name']) ?></td>
            <td class="small"><?= e($b['item_names'] ?: '—') ?></td>
            <td class="nowrap"><?= e(format_date($b['borrow_date'])) ?></td>
            <td class="nowrap"><?= e(format_date($b['planned_return_date'])) ?></td>
            <td><?= status_badge($b['status']) ?></td>
            <td>
              <?php if ($isLate): ?>
                <span class="badge badge-danger">Terlambat</span>
              <?php else: ?>
                <span class="badge badge-success">Tepat Waktu</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <a href="<?= e(url('modules/pengembalian/proses.php?id=' . (int)$b['id'])) ?>">Proses Kembali</a>
                <a href="<?= e(url('modules/peminjaman/detail.php?id=' . (int)$b['id'])) ?>">Detail</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$borrowings): ?>
          <tr><td colspan="8" class="empty">Tidak ada peminjaman aktif<?= $q !== '' ? ' yang sesuai pencarian' : '' ?>.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
