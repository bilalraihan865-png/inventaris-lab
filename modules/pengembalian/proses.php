<?php
/** TASK 5 — Form Proses Pengembalian (admin). */
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

// Fetch borrowing — must be 'dipinjam'
$stmt = $pdo->prepare("
    SELECT
        b.*,
        u.full_name AS borrower_name,
        u.identity_no AS borrower_identity,
        u.user_type AS borrower_type
    FROM borrowings b
    JOIN users u ON u.id = b.user_id
    WHERE b.id = ? AND b.status = 'dipinjam'
");
$stmt->execute([$id]);
$borrowing = $stmt->fetch();

if (!$borrowing) {
    http_response_code(404);
    exit('Peminjaman aktif tidak ditemukan atau sudah selesai.');
}

// Fetch details with remaining qty
$detailStmt = $pdo->prepare("
    SELECT
        bd.id,
        bd.inventory_id,
        bd.qty,
        bd.qty_returned,
        (bd.qty - bd.qty_returned) AS qty_remaining,
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

$today = date('Y-m-d');
$isLate = $borrowing['planned_return_date'] < $today;

$pageTitle  = 'Proses Pengembalian';
$activeMenu = 'pengembalian';
require __DIR__ . '/../../layouts/header.php';
?>
<section class="page-heading">
  <div>
    <p class="eyebrow">Proses pengembalian</p>
    <h2><span class="mono"><?= e($borrowing['borrowing_code']) ?></span></h2>
    <p class="muted">Peminjam: <strong><?= e($borrowing['borrower_name']) ?></strong>
      · Batas kembali: <strong><?= e(format_date($borrowing['planned_return_date'])) ?></strong>
      <?php if ($isLate): ?><span class="badge badge-danger" style="margin-left:.4rem">Terlambat</span><?php endif; ?>
    </p>
  </div>
  <a class="btn btn-light" href="<?= e(url('modules/pengembalian/index.php')) ?>">Kembali</a>
</section>

<?php if ($isLate): ?>
  <div class="alert alert-warning">
    Peminjaman ini <strong>melewati batas pengembalian</strong> (<?= e(format_date($borrowing['planned_return_date'])) ?>).
    Pengembalian akan dicatat sebagai <strong>terlambat</strong>.
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('modules/pengembalian/action.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="borrowing_id" value="<?= (int)$id ?>">

  <section class="card">
    <div class="card-head">
      <div><p class="eyebrow">Detail barang</p><h3>Barang yang Dipinjam</h3></div>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Barang</th>
            <th class="num">Qty Pinjam</th>
            <th class="num">Sudah Kembali</th>
            <th class="num">Sisa</th>
            <th>Qty Dikembalikan Sekarang</th>
            <th>Kondisi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($details as $d): ?>
            <?php $remaining = (int)$d['qty_remaining']; ?>
            <tr>
              <td>
                <span class="mono small"><?= e($d['inventory_code']) ?></span><br>
                <strong><?= e($d['inventory_name']) ?></strong><br>
                <span class="muted small"><?= e($d['inventory_location']) ?></span>
              </td>
              <td class="num"><?= (int)$d['qty'] ?></td>
              <td class="num"><?= (int)$d['qty_returned'] ?></td>
              <td class="num <?= $remaining > 0 ? '' : 'muted' ?>"><?= $remaining ?></td>
              <td>
                <?php if ($remaining > 0): ?>
                  <input
                    type="number"
                    name="qty_return_<?= (int)$d['id'] ?>"
                    min="0"
                    max="<?= $remaining ?>"
                    value="<?= $remaining ?>"
                    class="field"
                    style="width:5rem"
                    aria-label="Qty dikembalikan untuk <?= e($d['inventory_name']) ?>"
                  >
                  <input type="hidden" name="detail_ids[]" value="<?= (int)$d['id'] ?>">
                <?php else: ?>
                  <span class="badge badge-success">Selesai</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($remaining > 0): ?>
                  <select name="condition_<?= (int)$d['id'] ?>" class="field" aria-label="Kondisi pengembalian <?= e($d['inventory_name']) ?>">
                    <option value="baik">Baik</option>
                    <option value="rusak_ringan">Rusak Ringan</option>
                    <option value="rusak_berat">Rusak Berat</option>
                  </select>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card form-card">
    <label class="field field-wide">
      <span>Catatan Pengembalian <span class="muted">(opsional)</span></span>
      <textarea name="catatan" rows="3" placeholder="Tambahkan catatan kondisi barang, kerusakan, dll."></textarea>
    </label>
    <div class="form-actions">
      <a class="btn btn-light" href="<?= e(url('modules/pengembalian/index.php')) ?>">Batal</a>
      <button class="btn" type="submit">Simpan Pengembalian</button>
    </div>
  </section>
</form>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
