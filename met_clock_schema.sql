-- Adds the same shared MET instrument to both spacecraft cockpit stations
-- (commander + pilot), and Flight Director console. FIDO includes it natively
-- in its header. No changes to sim_clock or any physics/mission reset.
-- Move the clock later using the existing instrument layout editor.
INSERT INTO console_instruments(console_id,file_name,pos_x,pos_y)
VALUES
 ('cmc','met_clock.php',1510,65),
 ('cmp','met_clock.php',1510,65),
 ('lmc','met_clock.php',1510,65),
 ('lmp','met_clock.php',1510,65),
 ('flight','met_clock.php',20,65)
ON DUPLICATE KEY UPDATE file_name=VALUES(file_name);
