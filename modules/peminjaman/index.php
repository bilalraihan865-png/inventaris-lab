<?php
/** TASK 1 — Persetujuan Pengajuan Peminjaman (admin). */
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../components/ui.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_role('admin');
$pdo  = db();

// --- Filters ---
$status = (string)($_GET['status'] ?? '');
$q      = trim((string)($_GET['q'] ?? ''));

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

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Main query — aggregate item names per borrowing
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
    ORDER BY b.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$borrowings = $stmt->fetchAll();

// Count of pending requests
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status = 'menunggu'")->fetchColumn();

$pageTitle  = 'Persetujuan Peminjaman';
$activeMenu = 'peminjaman';
require __DIR__ . '/../../layouts/header.php';
?>
<section class="page-heading">
  <div>
    <p class="eyebrow">Manajemen peminjaman</p>
    <h2>Persetujuan Pengajuan</h2>
    <p class="muted">Setujui atau tolak pengajuan peminjaman dari peminjam.</p>
  </div>
</section>

<?php if ($pendingCount > 0): ?>
<div class="alert alert-warning">
  Terdapat <strong><?= $pendingCount ?></strong> pengajuan yang menunggu persetujuan.
</div>
<?php endif; ?>

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
    <div class="filter-actions">
      <button class="btn" type="submit">Terapkan</button>
      <a class="btn btn-light" href="<?= e(url('modules/peminjaman/index.php')) ?>">Reset</a>
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
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($borrowings as $b): ?>
          <tr>
            <td><span class="mono"><?= e($b['borrowing_code']) ?></span></td>
            <td><?= e($b['borrower_name']) ?></td>
            <td class="small"><?= e($b['item_names'] ?: '—') ?></td>
            <td class="nowrap"><?= e(format_date($b['borrow_date'])) ?></td>
            <td class="nowrap"><?= e(format_date($b['planned_return_date'])) ?></td>
            <td><?= status_badge($b['status']) ?></td>
            <td>
              <div class="row-actions">
                <a href="<?= e(url('modules/peminjaman/detail.php?id=' . (int)$b['id'])) ?>">Detail</a>
                <?php if ($b['status'] === 'menunggu'): ?>
                  <form method="post" action="<?= e(url('modules/peminjaman/action.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="setujui">
                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <button class="link-button" type="submit" data-confirm="Setujui peminjaman ini?">Setujui</button>
                  </form>
                  <form method="post" action="<?= e(url('modules/peminjaman/action.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="tolak">
                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <input type="hidden" name="rejection_reason" value="" id="reason-hidden-<?= (int)$b['id'] ?>">
                    <button class="link-button danger-link" type="button"
                      onclick="
                        var r = prompt('Alasan penolakan (wajib diisi):');
                        if (r && r.trim() !== '') {
                          document.getElementById('reason-hidden-<?= (int)$b['id'] ?>').value = r.trim();
                          this.closest('form').submit();
                        } else if (r !== null) {
                          alert('Alasan penolakan tidak boleh kosong.');
                        }
                      ">Tolak</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$borrowings): ?>
          <tr><td colspan="7" class="empty">Tidak ada pengajuan yang sesuai dengan filter.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
