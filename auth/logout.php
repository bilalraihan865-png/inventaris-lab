<?php
/** Logout: hapus seluruh data sesi (KN-02). Hanya via POST agar tidak terpicu tautan. */
require_once __DIR__ . '/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

session_start();
flash('success', 'Anda telah keluar.');
redirect('auth/login.php');
