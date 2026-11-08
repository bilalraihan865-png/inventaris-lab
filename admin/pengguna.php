<?php
/** KF-10 Daftar akun dan perubahan status pengguna. */
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../components/ui.php';
$user = require_role('admin');
$q = trim((string)($_GET['q'] ?? ''));
$active = (string)($_GET['active'] ?? 'all');
$where=[]; $params=[];
if ($q !== '') { $where[]='(full_name LIKE ? OR username LIKE ? OR identity_no LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like,$like); }
if ($active === '0' || $active === '1') { $where[]='is_active = ?'; $params[]=(int)$active; }
$sql='SELECT id,full_name,username,role,identity_no,user_type,is_active,created_at FROM users'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY full_name';
$stmt=db()->prepare($sql);$stmt->execute($params);$users=$stmt->fetchAll();
$pageTitle='Data Pengguna';$activeMenu='pengguna';require __DIR__ . '/../layouts/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">Akses sistem</p><h2>Data pengguna</h2><p class="muted">Akun dibuat oleh Admin/Laboran; tidak ada pendaftaran mandiri.</p></div><a class="btn" href="<?= e(url('admin/pengguna_form.php')) ?>">+ Tambah pengguna</a></section>
<section class="card"><form method="get" class="filter-bar"><label class="field"><span>Cari nama, username, atau NIM/NIDN</span><input type="search" name="q" value="<?= e($q) ?>"></label><label class="field"><span>Status akun</span><select name="active"><option value="all"<?= $active==='all'?' selected':'' ?>>Semua</option><option value="1"<?= $active==='1'?' selected':'' ?>>Aktif</option><option value="0"<?= $active==='0'?' selected':'' ?>>Nonaktif</option></select></label><div class="filter-actions"><button class="btn" type="submit">Cari</button><a class="btn btn-light" href="<?= e(url('admin/pengguna.php')) ?>">Reset</a></div></form>
<div class="table-wrap"><table class="table"><thead><tr><th>Nama / Username</th><th>NIM / NIDN</th><th>Jenis pengguna</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php foreach ($users as $row): ?><tr><td><strong><?= e($row['full_name']) ?></strong><br><span class="muted">@<?= e($row['username']) ?></span></td><td><?= e($row['identity_no'] ?: '—') ?></td><td><?= e(ucfirst($row['user_type'])) ?></td><td><?= $row['role']==='admin'?'Admin/Laboran':'Peminjam' ?></td><td><?= (int)$row['is_active'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-neutral">Nonaktif</span>' ?></td><td><div class="row-actions"><a href="<?= e(url('admin/pengguna_form.php?id='.(int)$row['id'])) ?>">Edit</a><?php if ((int)$row['id'] !== (int)$user['id']): ?><form method="post" action="<?= e(url('admin/pengguna_action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="link-button" type="submit" data-confirm="Ubah status akun ini?"><?= (int)$row['is_active']?'Nonaktifkan':'Aktifkan' ?></button></form><?php else: ?><span class="muted small">Akun Anda</span><?php endif; ?></div></td></tr><?php endforeach; ?><?php if (!$users): ?><tr><td colspan="6" class="empty">Pengguna tidak ditemukan.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
