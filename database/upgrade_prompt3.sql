-- One-time upgrade for a database created from the earlier Prompt 1/2 schema.
-- Back up inventaris_lab before applying this file. Do not run on an already-upgraded DB.
USE inventaris_lab;
ALTER TABLE inventories
  ADD COLUMN qty_damaged_light INT UNSIGNED NOT NULL DEFAULT 0 AFTER qty_damaged,
  ADD COLUMN qty_damaged_heavy INT UNSIGNED NOT NULL DEFAULT 0 AFTER qty_damaged_light;

-- The old schema only stored aggregate damaged stock. Assign each existing item's
-- damaged balance to its most recently recorded damage condition; default to light
-- damage where no usable condition history exists. Confirm these allocations with
-- a laboratory inspection if the old data included mixed damaged conditions.
UPDATE inventories i
LEFT JOIN (
  SELECT h.inventory_id, h.to_condition
  FROM condition_histories h
  JOIN (
    SELECT inventory_id, MAX(id) AS latest_id
    FROM condition_histories
    WHERE to_condition IN ('rusak_ringan', 'rusak_berat')
    GROUP BY inventory_id
  ) latest ON latest.latest_id = h.id
) last_damage ON last_damage.inventory_id = i.id
SET i.qty_damaged_light = CASE WHEN last_damage.to_condition = 'rusak_berat' THEN 0 ELSE i.qty_damaged END,
    i.qty_damaged_heavy = CASE WHEN last_damage.to_condition = 'rusak_berat' THEN i.qty_damaged ELSE 0 END;

ALTER TABLE inventories
  ADD CONSTRAINT chk_inventories_damage_split CHECK (qty_damaged = qty_damaged_light + qty_damaged_heavy);
