<?php
/** TASK 7 — Riwayat Semua Transaksi Peminjaman (admin). */
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../components/ui.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('admin');
$pdo  = db();

// --- Filters ---
$status    = (string)($_GET['status'] ?? '');
$q         = trim((string)($_GET['q'] ?? ''));
$dateFrom  = (string)($_GET['date_from'] ?? '');
$dateTo    = (string)($_GET['date_to'] ?? '');

$validStatuses = ['menunggu', 'dipinjam', 'ditolak', 'selesai'];

$where  = [];
$params = [];

if (in_array($status, $validStatuses, true)) {
    $where[]  = 'b.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[]  = '(b.borrowing_code LIKE ? OR u.full_name LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($dateFrom !== '') {
    $where[]  = 'DATE(b.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]  = 'DATE(b.created_at) <= ?';
    $params[] = $dateTo;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        b.id,
        b.borrowing_code,
        b.borrow_date,
        b.planned_return_date,
        b.status,
        b.created_at,
        u.full_name AS borrower_name,
        GROUP_CONCAT(DISTINCT i.name ORDER BY i.name SEPARATOR ', ') AS item_names,
        MAX(rh.actual_return_date) AS actual_return_date
    FROM borrowings b
    JOIN users u ON u.id = b.user_id
    LEFT JOIN borrowing_details bd ON bd.borrowing_id = b.id
    LEFT JOIN inventories i ON i.id = bd.inventory_id
    LEFT JOIN return_histories rh ON rh.borrowing_detail_id = bd.id
    $whereSql
    GROUP BY b.id, b.borrowing_code, b.borrow_date, b.planned_return_date, b.status, b.created_at, u.full_name
    ORDER BY b.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$borrowings = $stmt->fetchAll();

$pageTitle  = 'Riwayat Peminjaman';
$activeMenu = 'riwayat';
require __DIR__ . '/../layouts/header.php';
?>
<section class="page-heading">
  <div>
    <p class="eyebrow">Semua transaksi</p>
    <h2>Riwayat Peminjaman</h2>
    <p class="muted">Rekap seluruh transaksi peminjaman laboratorium.</p>
  </div>
</section>

<section class="card">
  <form method="get" class="filter-bar">
    <label class="field">
      <span>Cari kode / peminjam</span>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Mis. PJM-20260101-001">
    </label>
    <label class="field">
      <span>Status</span>
      <select name="status">
        <option value="">Semua status</option>
        <?php foreach (['menunggu' => 'Menunggu', 'dipinjam' => 'Dipinjam', 'ditolak' => 'Ditolak', 'selesai' => 'Selesai'] as $val => $lbl): ?>
          <option value="<?= e($val) ?>"<?= $status === $val ? ' selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">
      <span>Tanggal Dari</span>
      <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
    </label>
    <label class="field">
      <span>Tanggal Sampai</span>
      <input type="date" name="date_to" value="<?= e($dateTo) ?>">
    </label>
    <div class="filter-actions">
      <button class="btn" type="submit">Terapkan</button>
      <a class="btn btn-light" href="<?= e(url('admin/riwayat.php')) ?>">Reset</a>
    </div>
  </form>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Peminjam</th>
          <th>Barang</th>
          <th>Tgl Pengajuan</th>
          <th>Tgl Pinjam</th>
          <th>Batas Kembali</th>
          <th>Tgl Kembali Aktual</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($borrowings as $b): ?>
          <tr>
            <td>
              <a href="<?= e(url('modules/peminjaman/detail.php?id=' . (int)$b['id'])) ?>" class="mono">
                <?= e($b['borrowing_code']) ?>
              </a>
            </td>
            <td><?= e($b['borrower_name']) ?></td>
            <td class="small"><?= e($b['item_names'] ?: '—') ?></td>
            <td class="nowrap"><?= e(format_date($b['created_at'])) ?></td>
            <td class="nowrap"><?= e(format_date($b['borrow_date'])) ?></td>
            <td class="nowrap"><?= e(format_date($b['planned_return_date'])) ?></td>
            <td class="nowrap">
              <?php if ($b['status'] === 'selesai' && $b['actual_return_date']): ?>
                <?= e(format_date($b['actual_return_date'])) ?>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td><?= status_badge($b['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$borrowings): ?>
          <tr><td colspan="8" class="empty">Tidak ada riwayat peminjaman yang sesuai filter.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
