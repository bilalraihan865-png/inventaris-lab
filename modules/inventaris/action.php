<?php
/** Aksi inventaris dan kategori; seluruh perubahan dibatasi admin dan CSRF. */
require_once __DIR__ . '/../../auth/session.php';
$user = require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metode tidak diizinkan.'); }
verify_csrf();
$pdo = db();
$action = (string)($_POST['action'] ?? '');

function inventory_action_fail(string $message, string $destination = 'modules/inventaris/index.php'): void
{
    flash('error', $message);
    redirect($destination);
}

try {
    if ($action === 'category_save') {
        $id = valid_nonnegative_int($_POST['id'] ?? '0');
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) throw new DomainException('Nama kategori wajib diisi (maksimal 100 karakter).');
        if (mb_strlen($description) > 255) throw new DomainException('Keterangan kategori maksimal 255 karakter.');
        if ($id === null) throw new DomainException('Kategori tidak valid.');
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
            $stmt->execute([$name, $description ?: null, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            $stmt->execute([$name, $description ?: null]);
        }
        flash('success', 'Kategori berhasil disimpan.');
        redirect('modules/inventaris/index.php#kategori');
    }

    if ($action === 'category_delete') {
        $id = valid_nonnegative_int($_POST['id'] ?? '');
        if (!$id || $id < 1) throw new DomainException('Kategori tidak valid.');
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM inventories WHERE category_id = ?');
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) throw new DomainException('Kategori masih digunakan barang dan tidak dapat dihapus.');
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Kategori berhasil dihapus.');
        redirect('modules/inventaris/index.php#kategori');
    }

    if ($action === 'inventory_save') {
        $id = valid_nonnegative_int($_POST['id'] ?? '0');
        $code = trim((string)($_POST['code'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $categoryId = valid_nonnegative_int($_POST['category_id'] ?? '');
        $location = trim((string)($_POST['location'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $total = valid_nonnegative_int($_POST['qty_total'] ?? '');
        if (!$id && $id !== 0) throw new DomainException('Data barang tidak valid.');
        if ($code === '' || mb_strlen($code) > 30) throw new DomainException('Kode inventaris wajib diisi (maksimal 30 karakter).');
        if ($name === '' || mb_strlen($name) > 150) throw new DomainException('Nama barang wajib diisi (maksimal 150 karakter).');
        if (!$categoryId || $categoryId < 1) throw new DomainException('Pilih kategori yang valid.');
        if ($location === '' || mb_strlen($location) > 100) throw new DomainException('Lokasi wajib diisi (maksimal 100 karakter).');
        if ($total === null) throw new DomainException('Total stok harus bilangan bulat nol atau lebih.');
        if (mb_strlen($description) > 20000) throw new DomainException('Keterangan terlalu panjang.');
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
        $stmt->execute([$categoryId]);
        if (!(int)$stmt->fetchColumn()) throw new DomainException('Kategori tidak ditemukan.');
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM inventories WHERE code = ? AND id <> ?');
        $stmt->execute([$code, $id]);
        if ((int)$stmt->fetchColumn()) throw new DomainException('Kode inventaris sudah digunakan.');

        db_transaction(function (PDO $pdo) use ($id, $code, $name, $categoryId, $location, $description, $total): void {
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT qty_borrowed, qty_damaged FROM inventories WHERE id = ? FOR UPDATE');
                $stmt->execute([$id]);
                $current = $stmt->fetch();
                if (!$current) throw new DomainException('Barang tidak ditemukan.');
                $available = $total - (int)$current['qty_borrowed'] - (int)$current['qty_damaged'];
                if ($available < 0) throw new DomainException('Total stok tidak dapat lebih kecil daripada stok dipinjam dan rusak.');
                $stmt = $pdo->prepare('UPDATE inventories SET code = ?, name = ?, category_id = ?, location = ?, description = ?, qty_total = ?, qty_available = ? WHERE id = ?');
                $stmt->execute([$code, $name, $categoryId, $location, $description ?: null, $total, $available, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO inventories (code, name, category_id, location, description, qty_total, qty_available, qty_borrowed, qty_damaged, qty_damaged_light, qty_damaged_heavy) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0)');
                $stmt->execute([$code, $name, $categoryId, $location, $description ?: null, $total, $total]);
            }
        });
        flash('success', $id > 0 ? 'Data barang berhasil diperbarui.' : 'Barang berhasil ditambahkan.');
        redirect('modules/inventaris/index.php');
    }

    if ($action === 'inventory_toggle') {
        $id = valid_nonnegative_int($_POST['id'] ?? '');
        if (!$id || $id < 1) throw new DomainException('Barang tidak valid.');

        // Periksa status saat ini dan stok yang sedang dipinjam (BR-10)
        $stmt = $pdo->prepare('SELECT is_active, qty_borrowed FROM inventories WHERE id = ?');
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) throw new DomainException('Barang tidak ditemukan.');

        // Barang yang sedang dipinjam tidak boleh dinonaktifkan (BR-10)
        if ((int)$inv['is_active'] === 1 && (int)$inv['qty_borrowed'] > 0) {
            throw new DomainException('Barang yang masih memiliki peminjaman aktif tidak boleh dinonaktifkan.');
        }

        $stmt = $pdo->prepare('UPDATE inventories SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->rowCount()) throw new DomainException('Barang tidak ditemukan atau tidak berubah.');
        flash('success', 'Status barang berhasil diperbarui.');
        redirect('modules/inventaris/index.php');
    }
    inventory_action_fail('Aksi tidak dikenal.');
} catch (DomainException $e) {
    inventory_action_fail($e->getMessage());
} catch (PDOException $e) {
    error_log('Inventory/category action failed: ' . $e->getMessage());
    $message = $e->getCode() === '23000' ? 'Kode barang atau nama kategori sudah digunakan, atau data masih memiliki relasi.' : 'Perubahan tidak dapat disimpan. Periksa kembali data dan koneksi database.';
    inventory_action_fail($message);
} catch (Throwable $e) {
    error_log('Inventory/category action failed: ' . $e->getMessage());
    inventory_action_fail('Perubahan tidak berhasil disimpan.');
}
