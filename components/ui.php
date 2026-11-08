<?php
/** Komponen tampilan yang dipakai ulang. */

function status_badge(string $status): string
{
    $map = [
        'menunggu' => ['Menunggu', 'warning'],
        'dipinjam' => ['Dipinjam', 'info'],
        'ditolak'  => ['Ditolak',  'danger'],
        'selesai'  => ['Selesai',  'success'],
    ];
    [$label, $tone] = $map[$status] ?? [ucfirst($status), 'neutral'];
    return '<span class="badge badge-' . $tone . '">' . e($label) . '</span>';
}

function availability_badge(int $available): string
{
    return $available >= 1
        ? '<span class="badge badge-success">Tersedia</span>'
        : '<span class="badge badge-danger">Tidak Tersedia</span>';
}

function stat_card(string $label, $value, string $tone = 'neutral', string $note = ''): string
{
    return '<article class="stat stat-' . $tone . '">'
        . '<p class="stat-label">' . e($label) . '</p>'
        . '<p class="stat-value">' . e(number_format((int)$value, 0, ',', '.')) . '</p>'
        . ($note !== '' ? '<p class="stat-note">' . e($note) . '</p>' : '')
        . '</article>';
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
