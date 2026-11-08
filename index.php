<?php
/** Titik masuk: arahkan sesuai peran. Halaman lengkap dibuat pada Prompt 2. */
require_once __DIR__ . '/auth/session.php';

$user = current_user();
if (!$user) {
    redirect('auth/login.php');
}
redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'peminjam/dashboard.php');
