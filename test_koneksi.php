<?php
/** Uji koneksi database. Buka http://localhost/inventaris-lab/test_koneksi.php lalu hapus file ini setelah berhasil. */
require_once __DIR__ . '/config/database.php';

$rows = db()->query('SELECT code, name, qty_total, qty_available, qty_borrowed, qty_damaged FROM inventories ORDER BY id')->fetchAll();
echo '<h2>Koneksi berhasil ke database ' . htmlspecialchars(DB_NAME) . '</h2><table border="1" cellpadding="6">';
echo '<tr><th>Kode</th><th>Nama</th><th>Total</th><th>Tersedia</th><th>Dipinjam</th><th>Rusak</th><th>Seimbang?</th></tr>';
foreach ($rows as $r) {
    $ok = $r['qty_total'] == $r['qty_available'] + $r['qty_borrowed'] + $r['qty_damaged'];
    printf('<tr><td>%s</td><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%s</td></tr>',
        htmlspecialchars($r['code']), htmlspecialchars($r['name']), $r['qty_total'], $r['qty_available'],
        $r['qty_borrowed'], $r['qty_damaged'], $ok ? 'Ya' : 'TIDAK');
}
echo '</table>';
