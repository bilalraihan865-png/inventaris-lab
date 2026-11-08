<?php
/** HL-02 Dashboard Admin (KF-01, KF-08, KF-09) */
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../components/ui.php';

$user = require_role('admin');
$pdo  = db();

$stock = $pdo->query(
    'SELECT COUNT(*) AS jenis,
            COALESCE(SUM(qty_total),0)     AS total,
            COALESCE(SUM(qty_available),0) AS tersedia,
            COALESCE(SUM(qty_borrowed),0)  AS dipinjam,
            COALESCE(SUM(qty_damaged),0)   AS rusak,
            COALESCE(SUM(qty_available = 0),0) AS habis
     FROM inventories WHERE is_active = 1'
)->fetch();

$counts = $pdo->query(
    "SELECT COALESCE(SUM(status='menunggu'),0) AS menunggu,
            COALESCE(SUM(status='dipinjam'),0) AS dipinjam,
            COALESCE(SUM(status='selesai'),0)  AS selesai,
            COALESCE(SUM(status='ditolak'),0)  AS ditolak,
            COALESCE(SUM(status='dipinjam' AND planned_return_date < CURDATE()),0) AS terlambat
     FROM borrowings"
)->fetch();

$recent = $pdo->query(
    "SELECT b.borrowing_code, b.borrow_date, b.planned_return_date, b.status, b.created_at,
            u.full_name,
            GROUP_CONCAT(CONCAT(i.name, ' (', d.qty, ')') SEPARATOR ', ') AS items
     FROM borrowings b
     JOIN users u ON u.id = b.user_id
     JOIN borrowing_details d ON d.borrowing_id = b.id
     JOIN inventories i ON i.id = d.inventory_id
     GROUP BY b.id
     ORDER BY b.created_at DESC
     LIMIT 8"
)->fetchAll();

$lowStock = $pdo->query(
    'SELECT code, name, qty_available, qty_total FROM inventories
     WHERE is_active = 1 ORDER BY qty_available ASC, name LIMIT 5'
)->fetchAll();

$pageTitle  = 'Dashboard Admin';
$activeMenu = 'dashboard';
require __DIR__ . '/../layouts/header.php';
?>

<section class="hero-strip">
  <div>
    <p class="eyebrow">Selamat datang</p>
    <h2><?= e($user['full_name']) ?></h2>
    <p class="muted">Ringkasan kondisi laboratorium per <?= e(format_date(date('Y-m-d'))) ?>.</p>
  </div>
  <?php if ((int)$counts['menunggu'] > 0): ?>
    <div class="callout">
      <strong><?= (int)$counts['menunggu'] ?></strong> pengajuan menunggu persetujuan Anda.
    </div>
  <?php endif; ?>
</section>

<section class="stats">
  <?= stat_card('Menunggu persetujuan', $counts['menunggu'], 'warning', 'Pengajuan baru butuh aksi') ?>
  <?= stat_card('Peminjaman aktif', $counts['dipinjam'], 'info', (int)$counts['terlambat'] . ' transaksi terlambat') ?>
  <?= stat_card('Barang dipinjam', $stock['dipinjam'], 'neutral', 'Unit barang di peminjam') ?>
  <?= stat_card('Transaksi selesai', $counts['selesai'], 'success', 'Pengembalian tuntas') ?>
  <?= stat_card('Stok tersedia', $stock['tersedia'], 'success', (int)$stock['habis'] . ' jenis tidak tersedia') ?>
  <?= stat_card('Barang rusak', $stock['rusak'], 'danger', 'Tidak dapat dipinjam') ?>
</section>

<div class="grid-2">
  <section class="card">
    <div class="card-head">
      <h3>Aktivitas peminjaman terbaru</h3>
    </div>
    <?php if (!$recent): ?>
      <p class="empty">Belum ada aktivitas peminjaman.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Kode</th><th>Peminjam</th><th>Barang</th><th>Periode</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td class="mono"><?= e($r['borrowing_code']) ?></td>
              <td><?= e($r['full_name']) ?></td>
              <td><?= e($r['items']) ?></td>
              <td class="nowrap"><?= e(format_date($r['borrow_date'])) ?> – <?= e(format_date($r['planned_return_date'])) ?></td>
              <td><?= status_badge($r['status']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h3>Stok paling sedikit</h3></div>
    <ul class="meter-list">
      <?php foreach ($lowStock as $s):
        $pct = $s['qty_total'] > 0 ? round($s['qty_available'] / $s['qty_total'] * 100) : 0; ?>
        <li>
          <div class="meter-row">
            <span><?= e($s['name']) ?></span>
            <span class="mono"><?= (int)$s['qty_available'] ?>/<?= (int)$s['qty_total'] ?></span>
          </div>
          <div class="meter"><span style="width: <?= $pct ?>%"></span></div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
