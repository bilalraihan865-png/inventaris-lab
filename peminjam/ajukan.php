<?php
/** HL-14 Form Pengajuan Peminjaman oleh Peminjam (KF-05, BR-04). */
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../components/ui.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('peminjam');
$pdo  = db();

// Pre-select barang jika datang dari detail_barang atau inventaris
$preInventoryId = valid_nonnegative_int($_GET['inventory_id'] ?? '');

// Ambil semua inventaris aktif yang ada stok tersedia
$inventories = $pdo->query(
    'SELECT i.id, i.code, i.name, i.qty_available, c.name AS category_name
     FROM inventories i
     JOIN categories c ON c.id = i.category_id
     WHERE i.is_active = 1 AND c.is_active = 1 AND i.qty_available > 0
     ORDER BY i.name'
)->fetchAll();

// Buat map stok untuk JavaScript
$stockMap = [];
foreach ($inventories as $inv) {
    $stockMap[$inv['id']] = (int)$inv['qty_available'];
}

$pageTitle  = 'Ajukan Peminjaman';
$activeMenu = 'riwayat';
require __DIR__ . '/../layouts/header.php';
?>

<section class="page-heading">
  <div>
    <p class="eyebrow">HL-14 Pengajuan Peminjaman</p>
    <h2>Formulir Pengajuan</h2>
    <p class="muted">Isi formulir dengan lengkap. Pengajuan akan menunggu persetujuan Admin/Laboran.</p>
  </div>
  <a class="btn btn-light" href="<?= e(url('peminjam/inventaris.php')) ?>">← Kembali</a>
</section>

<section class="card form-card">
  <?php if (!$inventories): ?>
    <div class="alert alert-warning">
      Tidak ada barang yang tersedia untuk dipinjam saat ini. Silakan coba lagi nanti.
    </div>
  <?php else: ?>
  <form method="post" action="<?= e(url('peminjam/ajukan_action.php')) ?>" id="form-ajukan">
    <?= csrf_field() ?>

    <div class="form-grid">
      <label class="field field-wide">
        <span>Barang yang dipinjam <span style="color:var(--danger)">*</span></span>
        <select name="inventory_id" id="inv-select" required>
          <option value="">— Pilih barang —</option>
          <?php foreach ($inventories as $inv): ?>
            <option value="<?= (int)$inv['id'] ?>"
                    data-max="<?= (int)$inv['qty_available'] ?>"
              <?= $preInventoryId === (int)$inv['id'] ? ' selected' : '' ?>>
              <?= e($inv['name']) ?> (<?= e($inv['code']) ?>) — Tersedia: <?= (int)$inv['qty_available'] ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="field field-wide" id="stock-info-box" style="display:none;">
        <div class="stock-info">
          Stok tersedia: <strong id="stock-available">0</strong> unit.
          Jumlah yang diajukan tidak boleh melebihi stok tersedia.
        </div>
      </div>

      <label class="field">
        <span>Jumlah yang dipinjam <span style="color:var(--danger)">*</span></span>
        <input type="number" name="qty" id="qty-input" min="1" value="1" required>
      </label>

      <label class="field">
        <span>Tanggal peminjaman <span style="color:var(--danger)">*</span></span>
        <input type="date" name="borrow_date" id="borrow-date"
               value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
      </label>

      <label class="field">
        <span>Rencana tanggal pengembalian <span style="color:var(--danger)">*</span></span>
        <input type="date" name="planned_return_date" id="return-date"
               min="<?= date('Y-m-d') ?>" required>
      </label>

      <label class="field field-wide">
        <span>Keperluan peminjaman <span style="color:var(--danger)">*</span></span>
        <textarea name="purpose" rows="3" required
                  placeholder="Contoh: Praktikum Pemrograman Web, Penelitian Jaringan, dll."></textarea>
      </label>
    </div>

    <p class="form-note">
      Pengajuan yang dikirim akan berstatus <strong>Menunggu Persetujuan</strong> hingga Admin/Laboran
      memproses permintaan Anda. Stok belum berkurang sampai pengajuan disetujui.
    </p>

    <div class="form-actions">
      <a class="btn btn-light" href="<?= e(url('peminjam/inventaris.php')) ?>">Batal</a>
      <button class="btn" type="submit">Kirim Pengajuan</button>
    </div>
  </form>
  <?php endif; ?>
</section>

<script>
(function () {
  var select   = document.getElementById('inv-select');
  var qtyInput = document.getElementById('qty-input');
  var stockBox = document.getElementById('stock-info-box');
  var stockVal = document.getElementById('stock-available');
  var borrowDate = document.getElementById('borrow-date');
  var returnDate = document.getElementById('return-date');

  function updateStock() {
    var opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) {
      stockBox.style.display = 'none';
      qtyInput.removeAttribute('max');
      return;
    }
    var max = parseInt(opt.getAttribute('data-max'), 10) || 1;
    stockBox.style.display = '';
    stockVal.textContent = max;
    qtyInput.max = max;
    if (parseInt(qtyInput.value, 10) > max) qtyInput.value = max;
  }

  if (select) {
    select.addEventListener('change', updateStock);
    updateStock(); // init on page load (pre-select)
  }

  // Pastikan return_date >= borrow_date
  if (borrowDate && returnDate) {
    borrowDate.addEventListener('change', function () {
      if (returnDate.value && returnDate.value < borrowDate.value) {
        returnDate.value = borrowDate.value;
      }
      returnDate.min = borrowDate.value;
    });
  }
})();
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

