<?php /** Halaman 403 bila peran tidak sesuai (BR-09). */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Akses Ditolak — <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="auth-body">
  <main class="center-page">
    <div class="card narrow">
      <p class="eyebrow">Kode 403</p>
      <h1>Akses ditolak</h1>
      <p class="muted">Halaman ini tidak tersedia untuk peran Anda.</p>
      <a class="btn" href="<?= e(url(home_path(current_user()['role'] ?? ''))) ?>">Kembali ke dashboard</a>
    </div>
  </main>
</body>
</html>
