<?php
/** HL-01 Login (KF-01, UC-01, BR-09, BR-11, KN-02) */
require_once __DIR__ . '/session.php';

if ($u = current_user()) {
    redirect(home_path($u['role']));
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = db()->prepare('SELECT id, full_name, username, password_hash, role, user_type, is_active
                               FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Username atau password salah.';
        } elseif ((int)$user['is_active'] !== 1) {
            $error = 'Akun Anda tidak aktif. Hubungi Admin/Laboran.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'        => (int)$user['id'],
                'full_name' => $user['full_name'],
                'username'  => $user['username'],
                'role'      => $user['role'],
                'user_type' => $user['user_type'],
            ];
            $_SESSION['last_activity'] = time();
            redirect(home_path($user['role']));
        }
    }
}
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="auth-body">
  <main class="auth">
    <section class="auth-brand">
      <div class="brand-mark">IL</div>
      <h1>Inventaris<br>Laboratorium</h1>
      <p>Catat barang, pantau kondisi, dan kelola peminjaman dalam satu tempat.</p>
      <ul class="auth-points">
        <li>Stok tersedia diperbarui otomatis</li>
        <li>Persetujuan peminjaman oleh Laboran</li>
        <li>Riwayat tercatat dan mudah ditelusuri</li>
      </ul>
    </section>

    <section class="auth-form-wrap">
      <form class="auth-form" method="post" action="" novalidate>
        <h2>Masuk</h2>
        <p class="muted">Gunakan akun yang diberikan Admin/Laboran.</p>

        <?php foreach ($flashes as $f): ?>
          <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
        <?php if ($error): ?>
          <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?= csrf_field() ?>
        <label class="field">
          <span>Username</span>
          <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
        </label>
        <label class="field">
          <span>Password</span>
          <div class="input-group">
            <input type="password" name="password" id="password" autocomplete="current-password" required>
            <button type="button" class="input-addon" data-toggle-password="password">Lihat</button>
          </div>
        </label>
        <button type="submit" class="btn btn-block">Masuk</button>
        <p class="hint">Belum punya akun? Akun dibuat oleh Admin/Laboran.</p>
      </form>
    </section>
  </main>
  <script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
