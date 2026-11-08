<?php
/** Manajemen sesi & hak akses server-side (KF-01, BR-09, KN-02). */
require_once __DIR__ . '/../includes/helpers.php';

const SESSION_TIMEOUT = 7200; // 2 jam tanpa aktivitas

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function home_path(string $role): string
{
    return $role === 'admin' ? 'admin/dashboard.php' : 'peminjam/dashboard.php';
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Wajib login. Status akun dicek ulang ke database setiap request
 * sehingga akun yang dinonaktifkan langsung kehilangan akses (BR-11).
 */
function require_login(): array
{
    $user = current_user();
    $expired = isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT;

    if ($user && !$expired) {
        $stmt = db()->prepare('SELECT role, is_active FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if ($row && (int)$row['is_active'] === 1) {
            $_SESSION['user']['role'] = $row['role'];
            $_SESSION['last_activity'] = time();
            return $_SESSION['user'];
        }
    }

    $_SESSION = [];
    session_regenerate_id(true);
    flash('error', $expired ? 'Sesi berakhir. Silakan login kembali.' : 'Silakan login terlebih dahulu.');
    redirect('auth/login.php');
}

/** Batasi halaman untuk satu peran; peran lain mendapat 403. */
function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        http_response_code(403);
        require __DIR__ . '/../layouts/forbidden.php';
        exit;
    }
    return $user;
}
