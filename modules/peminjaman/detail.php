<?php
/** TASK 3 — Detail Pengajuan Peminjaman (admin). */
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../components/ui.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role('admin');
$pdo  = db();

$id = valid_nonnegative_int($_GET['id'] ?? '');
if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID peminjaman tidak valid.');
}

// Fetch borrowing with borrower and processor info
$stmt = $pdo->prepare("
    SELECT
        b.*,
        u.full_name   AS borrower_name,
        u.username    AS borrower_username,
        u.identity_no AS borrower_identity,
        u.user_type   AS borrower_type,
        p.full_name   AS processor_name
    FROM borrowings b
    JOIN users u ON u.id = b.user_id
    LEFT JOIN users p ON p.id = b.processed_by
    WHERE b.id = ?
");
$stmt->execute([$id]);
$borrowing = $stmt->fetch();

if (!$borrowing) {
    http_response_code(404);
    exit('Peminjaman tidak ditemukan.');
}

// Fetch borrowing details
$detailStmt = $pdo->prepare("
    SELECT
        bd.id,
        bd.inventory_id,
        bd.qty,
        bd.qty_returned,
        i.code  AS inventory_code,
        i.name  AS inventory_name,
        i.location AS inventory_location
    FROM borrowing_details bd
    JOIN inventories i ON i.id = bd.inventory_id
    WHERE bd.borrowing_id = ?
    ORDER BY i.name
");
$detailStmt->execute([$id]);
$details = $detailStmt->fetchAll();

// Fetch return histories for this borrowing
$returnStmt = $pdo->prepare("
    SELECT
        rh.*,
        i.name   AS inventory_name,
        u.full_name AS recorded_by_name
    FROM return_histories rh
    JOIN borrowing_details bd ON bd.id = rh.borrowing_detail_id
    JOIN inventories i ON i.id = bd.inventory_id
    JOIN users u ON u.id = rh.recorded_by
    WHERE bd.borrowing_id = ?
    ORDER BY rh.actual_return_date DESC, rh.created_at DESC
");
$returnStmt->execute([$id]);
$returnHistories = $returnStmt->fetchAll();

$conditionLabels = [
    'baik'        => 'Baik',
    'rusak_ringan' => 'Rusak Ringan',
    'rusak_berat'  => 'Rusak Berat',
];

$pageTitle  = 'Detail Peminjaman';
$activeMenu = 'peminjaman';
require __DIR__ . '/../../layouts/header.php';
?>
<section class="page-heading">
  <div>
    <p class="eyebrow">Detail transaksi</p>
    <h2><span class="mono"><?= e($borrowing['borrowing_code']) ?></span></h2>
    <p class="muted">Informasi lengkap pengajuan peminjaman.</p>
  </div>
  <div class="form-actions">
    <a class="btn btn-light" href="<?= e(url('modules/peminjaman/index.php')) ?>">Kembali</a>
    <?php if ($borrowing['status'] === 'menunggu'): ?>
      <form method="post" action="<?= e(url('modules/peminjaman/action.php')) ?>" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="setujui">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <button class="btn" type="submit" data-confirm="Setujui peminjaman ini?">Setujui</button>
      </form>
      <form method="post" action="<?= e(url('modules/peminjaman/action.php')) ?>" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="tolak">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="rejection_reason" value="" id="detail-rejection-reason">
        <button class="btn btn-light danger-link" type="button"
          onclick="
            var r = prompt('Alasan penolakan (wajib diisi):');
            if (r && r.trim() !== '') {
              document.getElementById('detail-rejection-reason').value = r.trim();
              this.closest('form').submit();
            } else if (r !== null) {
              alert('Alasan penolakan tidak boleh kosong.');
            }
          ">Tolak</button>
      </form>
    <?php elseif ($borrowing['status'] === 'dipinjam'): ?>
      <a class="btn" href="<?= e(url('modules/pengembalian/proses.php?id=' . (int)$id)) ?>">Proses Pengembalian</a>
    <?php endif; ?>
  </div>
</section>

<div class="grid-2">
  <section class="card detail-card">
    <div class="card-head"><div><p class="eyebrow">Info peminjam</p><h3>Data Peminjam</h3></div></div>
    <dl class="detail-grid">
      <div><dt>Nama</dt><dd><?= e($borrowing['borrower_name']) ?></dd></div>
      <div><dt>Username</dt><dd><?= e($borrowing['borrower_username']) ?></dd></div>
      <div><dt>No. Identitas</dt><dd><?= e($borrowing['borrower_identity'] ?: '—') ?></dd></div>
      <div><dt>Tipe</dt><dd><?= e(ucfirst($borrowing['borrower_type'] ?? '—')) ?></dd></div>
    </dl>
  </section>

  <section class="card detail-card">
    <div class="card-head"><div><p class="eyebrow">Info transaksi</p><h3>Data Peminjaman</h3></div></div>
    <dl class="detail-grid">
      <div><dt>Kode Peminjaman</dt><dd><span class="mono"><?= e($borrowing['borrowing_code']) ?></span></dd></div>
      <div><dt>Status</dt><dd><?= status_badge($borrowing['status']) ?></dd></div>
      <div><dt>Tanggal Pinjam</dt><dd><?= e(format_date($borrowing['borrow_date'])) ?></dd></div>
      <div><dt>Batas Kembali</dt><dd><?= e(format_date($borrowing['planned_return_date'])) ?></dd></div>
      <div><dt>Tanggal Pengajuan</dt><dd><?= e(format_date($borrowing['created_at'])) ?></dd></div>
      <?php if ($borrowing['processed_by']): ?>
        <div><dt>Diproses oleh</dt><dd><?= e($borrowing['processor_name']) ?></dd></div>
        <div><dt>Waktu diproses</dt><dd><?= e(format_date($borrowing['processed_at'])) ?></dd></div>
      <?php endif; ?>
      <?php if ($borrowing['status'] === 'ditolak' && $borrowing['rejection_reason']): ?>
        <div class="detail-wide">
          <dt>Alasan Penolakan</dt>
          <dd><?= nl2br(e($borrowing['rejection_reason'])) ?></dd>
        </div>
      <?php endif; ?>
      <div class="detail-wide"><dt>Keperluan</dt><dd><?= nl2br(e($borrowing['purpose'] ?: '—')) ?></dd></div>
    </dl>
  </section>
</div>

<section class="card">
  <div class="card-head">
    <div><p class="eyebrow">Rincian barang</p><h3>Daftar Barang Dipinjam</h3></div>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama Barang</th>
          <th>Lokasi</th>
          <th class="num">Qty Pinjam</th>
          <th class="num">Qty Dikembalikan</th>
          <th class="num">Sisa</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($details as $d): ?>
          <?php $remaining = (int)$d['qty'] - (int)$d['qty_returned']; ?>
          <tr>
            <td><span class="mono"><?= e($d['inventory_code']) ?></span></td>
            <td><?= e($d['inventory_name']) ?></td>
            <td><?= e($d['inventory_location']) ?></td>
            <td class="num"><?= (int)$d['qty'] ?></td>
            <td class="num"><?= (int)$d['qty_returned'] ?></td>
            <td class="num <?= $remaining > 0 ? '' : 'muted' ?>"><?= $remaining ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$details): ?>
          <tr><td colspan="6" class="empty">Tidak ada detail barang.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($returnHistories): ?>
<section class="card">
  <div class="card-head">
    <div><p class="eyebrow">Jejak pengembalian</p><h3>Riwayat Pengembalian</h3></div>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Barang</th>
          <th class="num">Qty</th>
          <th>Kondisi</th>
          <th>Tgl Kembali</th>
          <th>Terlambat</th>
          <th>Catatan</th>
          <th>Dicatat oleh</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($returnHistories as $rh): ?>
          <tr>
            <td><?= e($rh['inventory_name']) ?></td>
            <td class="num"><?= (int)$rh['qty'] ?></td>
            <td><?= e($conditionLabels[$rh['return_condition']] ?? $rh['return_condition']) ?></td>
            <td class="nowrap"><?= e(format_date($rh['actual_return_date'])) ?></td>
            <td><?= (int)$rh['is_late'] ? '<span class="badge badge-danger">Ya</span>' : '<span class="badge badge-success">Tidak</span>' ?></td>
            <td><?= e($rh['notes'] ?: '—') ?></td>
            <td><?= e($rh['recorded_by_name']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
