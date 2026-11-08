-- Run ONCE against an existing moonland database that already has the rcs table.
-- Calibrate the gauge to the original development loading; do not change current fuel.
ALTER TABLE rcs
 ADD COLUMN fuel_capacity_kg DOUBLE NOT NULL DEFAULT 0 AFTER fuel_kg,
 ADD COLUMN oxidizer_capacity_kg DOUBLE NOT NULL DEFAULT 0 AFTER oxidizer_kg;
UPDATE rcs SET fuel_capacity_kg=CASE craft_id WHEN 4 THEN 200 WHEN 3 THEN 35 WHEN 6 THEN 125 ELSE GREATEST(fuel_kg,0) END,
 oxidizer_capacity_kg=CASE craft_id WHEN 4 THEN 300 WHEN 3 THEN 55 WHEN 6 THEN 195 ELSE GREATEST(oxidizer_kg,0) END;
