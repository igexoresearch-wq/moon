-- Pilot-only measured axial Delta-V accumulator.
-- g_forward is signed proper acceleration in units of standard gravity.
-- Reset is an explicit manual zero, not an edit of spacecraft velocity.
CREATE TABLE IF NOT EXISTS delta_v_accumulators (
 craft_id INT UNSIGNED PRIMARY KEY,
 accumulated_mps DOUBLE NOT NULL DEFAULT 0,
 last_sample_tick BIGINT UNSIGNED NULL,
 samples_integrated BIGINT UNSIGNED NOT NULL DEFAULT 0,
 reset_at_tick BIGINT UNSIGNED NULL,
 status VARCHAR(48) NOT NULL DEFAULT 'WAITING FOR G SAMPLE',
 CONSTRAINT fk_delta_v_acc_craft FOREIGN KEY(craft_id) REFERENCES objects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO delta_v_accumulators(craft_id) VALUES (3),(6);

-- Move the already-created DSKY panel to pilot stations only.
DELETE FROM console_instruments
WHERE file_name='dsky.php' AND console_id IN ('cmc','lmc');
INSERT INTO console_instruments(console_id,file_name,pos_x,pos_y)
VALUES ('cmp','dsky.php',780,60),('lmp','dsky.php',780,60)
ON DUPLICATE KEY UPDATE file_name=VALUES(file_name);

-- Pilot-only Delta-V indicator. Leave the existing G-meter in its place.
INSERT INTO console_instruments(console_id,file_name,pos_x,pos_y)
VALUES ('cmp','delta_v.php',780,560),('lmp','delta_v.php',780,560)
ON DUPLICATE KEY UPDATE file_name=VALUES(file_name);
