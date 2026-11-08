<?php
/** HL-13 Detail inventaris + tombol ajukan peminjaman untuk Peminjam. */
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../components/ui.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('peminjam');

$id = valid_nonnegative_int($_GET['id'] ?? '');
if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID barang tidak valid.');
}

$stmt = db()->prepare(
    'SELECT i.*, c.name AS category_name
     FROM inventories i
     JOIN categories c ON c.id = i.category_id
     WHERE i.id = ? AND i.is_active = 1 AND c.is_active = 1'
);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    exit('Barang tidak ditemukan atau tidak aktif.');
}

$pageTitle  = 'Detail Barang';
$activeMenu = 'inventaris';
require __DIR__ . '/../layouts/header.php';
?>

<section class="page-heading">
  <div>
    <p class="eyebrow">Detail inventaris</p>
    <h2><?= e($item['name']) ?></h2>
    <p class="mono"><?= e($item['code']) ?></p>
  </div>
  <div class="row-actions">
    <?php if ((int)$item['qty_available'] > 0): ?>
      <a class="btn" href="<?= e(url('peminjam/ajukan.php?inventory_id=' . (int)$item['id'])) ?>">
        Pinjam Barang Ini
      </a>
    <?php else: ?>
      <span class="btn" style="opacity:.5;cursor:not-allowed;" title="Stok tidak tersedia">Pinjam Barang Ini</span>
    <?php endif; ?>
    <a class="btn btn-light" href="<?= e(url('peminjam/inventaris.php')) ?>">Kembali ke daftar</a>
  </div>
</section>

<section class="card detail-card">
  <dl class="detail-grid">
    <div><dt>Kategori</dt><dd><?= e($item['category_name']) ?></dd></div>
    <div><dt>Lokasi</dt><dd><?= e($item['location']) ?></dd></div>
    <div><dt>Kondisi tercatat</dt><dd><?= e(inventory_condition_label($item)) ?></dd></div>
    <div><dt>Status ketersediaan</dt><dd><?= availability_badge((int)$item['qty_available']) ?></dd></div>
    <div><dt>Total unit</dt><dd><?= (int)$item['qty_total'] ?></dd></div>
    <div><dt>Stok tersedia</dt><dd class="mono"><?= (int)$item['qty_available'] ?></dd></div>
    <div><dt>Sedang dipinjam</dt><dd><?= (int)$item['qty_borrowed'] ?></dd></div>
    <div><dt>Rusak ringan</dt><dd><?= (int)$item['qty_damaged_light'] ?></dd></div>
    <div><dt>Rusak berat</dt><dd><?= (int)$item['qty_damaged_heavy'] ?></dd></div>
    <div class="detail-wide"><dt>Keterangan</dt><dd><?= nl2br(e($item['description'] ?: '—')) ?></dd></div>
  </dl>
</section>

<?php if ((int)$item['qty_available'] > 0): ?>
<section class="card form-card">
  <div class="card-head"><h3>Ajukan Peminjaman Cepat</h3></div>
  <p class="form-note">
    Stok tersedia: <strong><?= (int)$item['qty_available'] ?> unit</strong>.
    Isi form di bawah untuk mengajukan peminjaman barang ini.
  </p>
  <form method="post" action="<?= e(url('peminjam/ajukan_action.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="inventory_id" value="<?= (int)$item['id'] ?>">
    <div class="form-grid">
      <label class="field">
        <span>Jumlah yang dipinjam</span>
        <input type="number" name="qty" min="1" max="<?= (int)$item['qty_available'] ?>"
               value="1" required>
      </label>
      <label class="field">
        <span>Tanggal peminjaman</span>
        <input type="date" name="borrow_date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
      </label>
      <label class="field">
        <span>Rencana tanggal pengembalian</span>
        <input type="date" name="planned_return_date" min="<?= date('Y-m-d') ?>" required>
      </label>
      <label class="field field-wide">
        <span>Keperluan peminjaman</span>
        <textarea name="purpose" rows="3" required placeholder="Contoh: Praktikum Pemrograman Web, Penelitian Jaringan, dll."></textarea>
      </label>
    </div>
    <div class="form-actions">
      <a class="btn btn-light" href="<?= e(url('peminjam/inventaris.php')) ?>">Batal</a>
      <button class="btn" type="submit">Kirim Pengajuan</button>
    </div>
  </form>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
