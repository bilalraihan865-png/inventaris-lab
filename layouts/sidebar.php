<?php
/** Navigasi sesuai peran; setiap halaman tetap memeriksa role di server. */
$menus = $user['role'] === 'admin'
    ? [
        ['key' => 'dashboard',    'label' => 'Dashboard',           'href' => 'admin/dashboard.php'],
        ['key' => 'inventaris',   'label' => 'Data Inventaris',     'href' => 'modules/inventaris/index.php'],
        ['key' => 'kondisi',      'label' => 'Kondisi Barang',      'href' => 'modules/kondisi/index.php'],
        ['key' => 'peminjaman',   'label' => 'Persetujuan',         'href' => 'modules/peminjaman/index.php'],
        ['key' => 'pengembalian', 'label' => 'Pengembalian',        'href' => 'modules/pengembalian/index.php'],
        ['key' => 'riwayat',      'label' => 'Riwayat Peminjaman',  'href' => 'admin/riwayat.php'],
        ['key' => 'pengguna',     'label' => 'Data Pengguna',       'href' => 'admin/pengguna.php'],
      ]
    : [
        ['key' => 'dashboard',  'label' => 'Dashboard',         'href' => 'peminjam/dashboard.php'],
        ['key' => 'inventaris', 'label' => 'Daftar Inventaris', 'href' => 'peminjam/inventaris.php'],
        ['key' => 'riwayat',    'label' => 'Status & Riwayat',  'href' => 'peminjam/riwayat.php'],
      ];
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <span class="brand-mark sm">IL</span>
    <span>Inventaris<br><small>Laboratorium</small></span>
  </div>
  <nav class="nav">
    <?php foreach ($menus as $m): ?>
        <a class="nav-link<?= $activeMenu === $m['key'] ? ' active' : '' ?>" href="<?= e(url($m['href'])) ?>"><?= e($m['label']) ?></a>
    <?php endforeach; ?>
  </nav>
  <form method="post" action="<?= e(url('auth/logout.php')) ?>" class="sidebar-foot">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-ghost btn-block" data-confirm="Keluar dari aplikasi?">Keluar</button>
  </form>
</aside>
