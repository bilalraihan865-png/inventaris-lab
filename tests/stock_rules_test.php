<?php
require_once __DIR__ . '/../modules/kondisi/stock_rules.php';
function expect(bool $condition, string $message): void { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
$start=['qty_total'=>10,'qty_available'=>7,'qty_borrowed'=>2,'qty_damaged'=>1,'qty_damaged_light'=>1,'qty_damaged_heavy'=>0];
$light=adjust_inventory_condition($start,'baik','rusak_ringan',2);
expect($light['qty_available']===5 && $light['qty_damaged']===3 && $light['qty_damaged_light']===3 && $light['qty_damaged_heavy']===0,'Baik → Rusak Ringan shifts available units into damaged stock.');
$restored=adjust_inventory_condition($light,'rusak_ringan','baik',1);
expect($restored['qty_available']===6 && $restored['qty_damaged']===2 && $restored['qty_damaged_light']===2,'Rusak Ringan → Baik restores available stock.');
$heavy=adjust_inventory_condition($start,'baik','rusak_berat',1);
expect($heavy['qty_damaged_heavy']===1 && $heavy['qty_damaged']===2 && $start['qty_total']===$heavy['qty_available']+$start['qty_borrowed']+$heavy['qty_damaged'],'Total stock balance is preserved on damage transition.');
foreach ([['baik','rusak_ringan',8],['rusak_berat','baik',1],['baik','baik',1],['baik','rusak_berat',0]] as [$from,$to,$qty]) {
  try { adjust_inventory_condition($start,$from,$to,$qty); fwrite(STDERR,"FAIL: invalid transition was accepted.\n"); exit(1); }
  catch (DomainException $expected) { }
}
echo "Stock condition rules: 4 checks passed.\n";
