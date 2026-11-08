<?php
/** Fungsi bantu umum. */
require_once __DIR__ . '/../config/database.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sessionToken = $_SESSION['csrf'] ?? '';
    $postedToken = $_POST['csrf'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '' || !is_string($postedToken) || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman.');
    }
}

function availability_label(int $available): string
{
    return $available >= 1 ? 'Tersedia' : 'Tidak Tersedia';
}

function inventory_condition_label(array $item): string
{
    $labels = [];
    if (((int)($item['qty_available'] ?? 0) + (int)($item['qty_borrowed'] ?? 0)) > 0) $labels[] = 'Baik';
    if ((int)($item['qty_damaged_light'] ?? 0) > 0) $labels[] = 'Rusak Ringan';
    if ((int)($item['qty_damaged_heavy'] ?? 0) > 0) $labels[] = 'Rusak Berat';
    return $labels ? implode(' + ', $labels) : '—';
}

function valid_nonnegative_int($value): ?int
{
    if (!is_string($value) && !is_int($value)) return null;
    $parsed = filter_var((string)$value, FILTER_VALIDATE_INT);
    return $parsed !== false && $parsed >= 0 ? $parsed : null;
}

if (!function_exists('format_date')) {
    /**
     * Mengubah tanggal format MySQL (Y-m-d atau Y-m-d H:i:s)
     * menjadi format Indonesia yang mudah dibaca, misalnya: "08 November 2026".
     * Aman menangani null, string kosong, atau tanggal tidak valid tanpa Fatal Error.
     */
    function format_date(?string $date): string
    {
        if ($date === null || trim((string)$date) === '') {
            return '-';
        }

        $dateStr = trim((string)$date);
        if ($dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') {
            return '-';
        }

        $timestamp = strtotime($dateStr);
        if ($timestamp === false || $timestamp <= 0) {
            return '-';
        }

        $bulanIndo = [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        $day   = date('d', $timestamp);
        $month = (int)date('n', $timestamp);
        $year  = date('Y', $timestamp);

        $monthName = $bulanIndo[$month] ?? date('F', $timestamp);

        return sprintf('%s %s %s', $day, $monthName, $year);
    }
}
