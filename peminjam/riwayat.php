<?php
/** HL-15 Status & Riwayat Peminjaman milik Peminjam yang login (BR-09, KF-09). */
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../components/ui.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('peminjam');
$pdo  = db();

// Filter status
$filterStatus = trim((string)($_GET['status'] ?? ''));
$validStatuses = ['', 'menunggu', 'dipinjam', 'ditolak', 'selesai'];
if (!in_array($filterStatus, $validStatuses, true)) {
    $filterStatus = '';
}

// Query riwayat peminjaman milik user ini saja (BR-09)
$where  = ['b.user_id = ?'];
$params = [(int)$user['id']];

if ($filterStatus !== '') {
    $where[]  = 'b.status = ?';
    $params[] = $filterStatus;
}

$stmt = $pdo->prepare(
    "SELECT
        b.id,
        b.borrowing_code,
        b.borrow_date,
        b.planned_return_date,
        b.status,
        b.purpose,
        b.rejection_reason,
        b.created_at,
        GROUP_CONCAT(
            CONCAT(i.name, ' (', d.qty, ')')
            ORDER BY i.name SEPARATOR ', '
        ) AS items,
        GROUP_CONCAT(DISTINCT d.id ORDER BY d.id) AS detail_ids,
        MAX(rh.actual_return_date) AS actual_return_date,
        GROUP_CONCAT(DISTINCT rh.return_condition ORDER BY rh.id) AS conditions
     FROM borrowings b
     JOIN borrowing_details d ON d.borrowing_id = b.id
     JOIN inventories i ON i.id = d.inventory_id
     LEFT JOIN return_histories rh ON rh.borrowing_detail_id = d.id
     WHERE " . implode(' AND ', $where) . "
     GROUP BY b.id
     ORDER BY b.created_at DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Statistik ringkasan (hanya milik user ini)
$stmtCount = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status='menunggu'),0)  AS menunggu,
        COALESCE(SUM(status='dipinjam'),0)  AS dipinjam,
        COALESCE(SUM(status='selesai'),0)   AS selesai,
        COALESCE(SUM(status='ditolak'),0)   AS ditolak
     FROM borrowings WHERE user_id = ?"
);
$stmtCount->execute([$user['id']]);
$counts = $stmtCount->fetch();

// Map label kondisi pengembalian
$condLabel = [
    'baik'         => 'Baik',
    'rusak_ringan' => 'Rusak Ringan',
    'rusak_berat'  => 'Rusak Berat',
];

$pageTitle  = 'Status & Riwayat Peminjaman';
$activeMenu = 'riwayat';
require __DIR__ . '/../layouts/header.php';
?>

<section class="page-heading">
  <div>
    <p class="eyebrow">HL-15 Riwayat Peminjaman</p>
    <h2>Status & Riwayat Peminjaman</h2>
    <p class="muted">Hanya transaksi milik akun Anda yang ditampilkan.</p>
  </div>
  <a class="btn" href="<?= e(url('peminjam/ajukan.php')) ?>">+ Ajukan Peminjaman</a>
</section>

<section class="stats">
  <?= stat_card('Total pengajuan', $counts['total'],    'neutral',  (int)$counts['ditolak'] . ' ditolak') ?>
  <?= stat_card('Menunggu',        $counts['menunggu'], 'warning') ?>
  <?= stat_card('Sedang dipinjam', $counts['dipinjam'], 'info') ?>
  <?= stat_card('Selesai',         $counts['selesai'],  'success') ?>
</section>

<section class="card">
  <!-- Filter -->
  <form method="get" class="filter-bar">
    <label class="field">
      <span>Filter status</span>
      <select name="status">
        <option value="">Semua status</option>
        <option value="menunggu"  <?= $filterStatus === 'menunggu'  ? 'selected' : '' ?>>Menunggu</option>
        <option value="dipinjam"  <?= $filterStatus === 'dipinjam'  ? 'selected' : '' ?>>Dipinjam</option>
        <option value="selesai"   <?= $filterStatus === 'selesai'   ? 'selected' : '' ?>>Selesai</option>
        <option value="ditolak"   <?= $filterStatus === 'ditolak'   ? 'selected' : '' ?>>Ditolak</option>
      </select>
    </label>
    <div class="filter-actions">
      <button class="btn" type="submit">Terapkan</button>
      <a class="btn btn-light" href="<?= e(url('peminjam/riwayat.php')) ?>">Reset</a>
    </div>
  </form>

  <?php if (!$rows): ?>
    <p class="empty">Tidak ada data transaksi<?= $filterStatus ? ' dengan status ' . e($filterStatus) : '' ?>.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Barang</th>
            <th>Keperluan</th>
            <th class="nowrap">Tgl Pengajuan</th>
            <th class="nowrap">Tgl Pinjam</th>
            <th class="nowrap">Batas Kembali</th>
            <th class="nowrap">Tgl Kembali</th>
            <th>Kondisi</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row):
            $isLate = $row['status'] === 'dipinjam' && $row['planned_return_date'] < date('Y-m-d');
            $condDisplay = '';
            if ($row['conditions']) {
                $parts = array_unique(explode(',', $row['conditions']));
                $condDisplay = implode(', ', array_map(fn($c) => $condLabel[trim($c)] ?? $c, $parts));
            }
          ?>
            <tr class="<?= $isLate ? 'late-row' : '' ?>">
              <td class="mono nowrap"><?= e($row['borrowing_code']) ?></td>
              <td><?= e($row['items']) ?></td>
              <td><?= e($row['purpose']) ?></td>
              <td class="nowrap"><?= e(format_date($row['created_at'])) ?></td>
              <td class="nowrap"><?= e(format_date($row['borrow_date'])) ?></td>
              <td class="nowrap">
                <?= e(format_date($row['planned_return_date'])) ?>
                <?php if ($isLate): ?>
                  <span class="badge badge-late">Terlambat</span>
                <?php endif; ?>
              </td>
              <td class="nowrap"><?= $row['actual_return_date'] ? e(format_date($row['actual_return_date'])) : '—' ?></td>
              <td><?= $condDisplay ?: '—' ?></td>
              <td>
                <?= status_badge($row['status']) ?>
                <?php if ($row['status'] === 'ditolak' && $row['rejection_reason']): ?>
                  <br><small class="muted note-danger"><?= e($row['rejection_reason']) ?></small>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

