-- Optional one-time manual install; execute.php also initializes these
-- tables before starting its first one-second transaction.
CREATE TABLE IF NOT EXISTS docking_contact(
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 touching TINYINT(1) NOT NULL DEFAULT 0,
 docked TINYINT(1) NOT NULL DEFAULT 0,
 last_tick BIGINT UNSIGNED NULL,
 last_event VARCHAR(80) NOT NULL DEFAULT 'NONE',
 last_speed_mps DOUBLE NULL,
 last_port_gap_m DOUBLE NULL,
 last_damage_cm SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 last_damage_lm SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO docking_contact(id) VALUES(1);
CREATE TABLE IF NOT EXISTS docking_events(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sim_tick BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(30) NOT NULL,
 speed_mps DOUBLE NOT NULL,
 port_gap_m DOUBLE NOT NULL,
 damage_cm SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 damage_lm SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX docking_events_tick(sim_tick)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO reliability_components(component_key,component_name,spacecraft_id,master,alarm_name,state)
VALUES('cm_docking_probe','CM Docking Probe',3,1,'DOCK PROBE',1),
('lm_docking_probe','LM Docking Probe',6,1,'DOCK PROBE',1);
INSERT INTO console_instruments(console_id,file_name,pos_x,pos_y)
VALUES('cmc','docking_sensor.php',1760,270),
('cmp','docking_sensor.php',1760,270),
('lmc','docking_sensor.php',1760,270),
('lmp','docking_sensor.php',1760,270)
ON DUPLICATE KEY UPDATE file_name=VALUES(file_name);
