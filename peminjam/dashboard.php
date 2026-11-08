<?php
/** HL-11 Dashboard Peminjam (KF-01, KF-04, KF-09, BR-09) */
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../components/ui.php';

$user = require_role('peminjam');
$pdo  = db();

// Hanya data milik pengguna yang login (BR-09)
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status='menunggu'),0) AS menunggu,
            COALESCE(SUM(status='dipinjam'),0) AS dipinjam,
            COALESCE(SUM(status='selesai'),0)  AS selesai,
            COALESCE(SUM(status='ditolak'),0)  AS ditolak
     FROM borrowings WHERE user_id = ?"
);
$stmt->execute([$user['id']]);
$c = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT b.borrowing_code, b.borrow_date, b.planned_return_date, b.status, b.rejection_reason,
            GROUP_CONCAT(CONCAT(i.name, ' (', d.qty, ')') SEPARATOR ', ') AS items
     FROM borrowings b
     JOIN borrowing_details d ON d.borrowing_id = b.id
     JOIN inventories i ON i.id = d.inventory_id
     WHERE b.user_id = ?
     GROUP BY b.id
     ORDER BY b.created_at DESC
     LIMIT 5"
);
$stmt->execute([$user['id']]);
$recent = $stmt->fetchAll();

// Barang nonaktif tidak ditampilkan (KF-04)
$items = $pdo->query(
    'SELECT i.name, i.location, i.qty_available, c.name AS category
     FROM inventories i JOIN categories c ON c.id = i.category_id
     WHERE i.is_active = 1
     ORDER BY (i.qty_available > 0) DESC, i.name
     LIMIT 8'
)->fetchAll();

$pageTitle  = 'Dashboard Peminjam';
$activeMenu = 'dashboard';
require __DIR__ . '/../layouts/header.php';
?>

<section class="hero-strip">
  <div>
    <p class="eyebrow">Halo,</p>
    <h2><?= e($user['full_name']) ?></h2>
    <p class="muted">Pantau pengajuan dan barang yang sedang Anda pinjam.</p>
  </div>
  <div class="row-actions">
    <a class="btn" href="<?= e(url('peminjam/ajukan.php')) ?>">+ Ajukan Peminjaman</a>
  </div>
</section>

<section class="stats">
  <?= stat_card('Total pengajuan', $c['total'], 'neutral', (int)$c['ditolak'] . ' ditolak') ?>
  <?= stat_card('Menunggu persetujuan', $c['menunggu'], 'warning') ?>
  <?= stat_card('Sedang dipinjam', $c['dipinjam'], 'info') ?>
  <?= stat_card('Selesai', $c['selesai'], 'success') ?>
</section>

<div class="grid-2">
  <section class="card">
    <div class="card-head"><h3>Status pengajuan terbaru</h3></div>
    <?php if (!$recent): ?>
      <p class="empty">Anda belum pernah mengajukan peminjaman.</p>
    <?php else: ?>
      <ul class="timeline">
        <?php foreach ($recent as $r): ?>
          <li>
            <div class="timeline-head">
              <span class="mono"><?= e($r['borrowing_code']) ?></span>
              <?= status_badge($r['status']) ?>
            </div>
            <p><?= e($r['items']) ?></p>
            <p class="muted small"><?= e(format_date($r['borrow_date'])) ?> – <?= e(format_date($r['planned_return_date'])) ?></p>
            <?php if ($r['status'] === 'ditolak' && $r['rejection_reason']): ?>
              <p class="small note-danger">Alasan: <?= e($r['rejection_reason']) ?></p>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h3>Inventaris laboratorium</h3></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Barang</th><th>Kategori</th><th class="num">Tersedia</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['name']) ?><br><small class="muted"><?= e($it['location']) ?></small></td>
            <td><?= e($it['category']) ?></td>
            <td class="num mono"><?= (int)$it['qty_available'] ?></td>
            <td><?= availability_badge((int)$it['qty_available']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
