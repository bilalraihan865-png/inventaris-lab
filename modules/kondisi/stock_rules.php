<?php
/** Perhitungan murni perpindahan jumlah unit antar kondisi inventaris. */
function adjust_inventory_condition(array $stock, string $from, string $to, int $qty): array
{
    $valid = ['baik', 'rusak_ringan', 'rusak_berat'];
    if (!in_array($from, $valid, true) || !in_array($to, $valid, true) || $from === $to) {
        throw new DomainException('Kondisi asal dan tujuan harus berbeda dan valid.');
    }
    if ($qty < 1) throw new DomainException('Jumlah unit minimal 1.');
    $available = (int)$stock['qty_available'];
    $damaged = (int)$stock['qty_damaged'];
    $light = (int)$stock['qty_damaged_light'];
    $heavy = (int)$stock['qty_damaged_heavy'];

    if ($from === 'baik' && $available < $qty) throw new DomainException('Jumlah melebihi stok baik yang tersedia.');
    if ($from === 'rusak_ringan' && $light < $qty) throw new DomainException('Jumlah melebihi stok rusak ringan.');
    if ($from === 'rusak_berat' && $heavy < $qty) throw new DomainException('Jumlah melebihi stok rusak berat.');

    if ($from === 'baik') $available -= $qty;
    if ($from === 'rusak_ringan') { $light -= $qty; $damaged -= $qty; }
    if ($from === 'rusak_berat') { $heavy -= $qty; $damaged -= $qty; }
    if ($to === 'baik') $available += $qty;
    if ($to === 'rusak_ringan') { $light += $qty; $damaged += $qty; }
    if ($to === 'rusak_berat') { $heavy += $qty; $damaged += $qty; }

    if ($available < 0 || $damaged < 0 || $light < 0 || $heavy < 0 || $damaged !== $light + $heavy) {
        throw new DomainException('Perubahan kondisi akan menghasilkan jumlah stok yang tidak valid.');
    }
    return ['qty_available'=>$available, 'qty_damaged'=>$damaged, 'qty_damaged_light'=>$light, 'qty_damaged_heavy'=>$heavy];
}
