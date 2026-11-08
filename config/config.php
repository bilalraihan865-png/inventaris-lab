<?php
/**
 * Konfigurasi aplikasi. Sesuaikan dengan instalasi XAMPP lokal.
 */
define('APP_NAME', 'Inventaris Laboratorium');
define('BASE_URL', '/inventaris-lab');   // nama folder di htdocs

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'inventaris_lab');
define('DB_USER', 'root');
define('DB_PASS', '');                    // default XAMPP: kosong
define('DB_CHARSET', 'utf8mb4');

date_default_timezone_set('Asia/Jakarta');

// Tampilkan error saat pengembalian lokal; matikan pada produksi
ini_set('display_errors', '1');
error_reporting(E_ALL);
