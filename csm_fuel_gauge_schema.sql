-- Install CSM service propulsion fuel gauge on both command-module
-- consoles. No fuel values or propulsion state are changed.
-- The layout editor can move/remove it afterward.
INSERT INTO console_instruments (console_id,file_name,pos_x,pos_y)
VALUES
 ('cmc','csm_fuel.php',1510,275),
 ('cmp','csm_fuel.php',1510,275)
ON DUPLICATE KEY UPDATE file_name=VALUES(file_name);
