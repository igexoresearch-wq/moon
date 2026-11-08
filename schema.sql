-- Moon Program V2: intentionally minimal, independent of legacy moonland tables.
-- Import into the EMPTY moonland schema. No DROP statements.
CREATE TABLE IF NOT EXISTS objects (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE,
 dry_mass DOUBLE NOT NULL DEFAULT 0,
 radius_length DOUBLE NOT NULL DEFAULT 0,
 x DOUBLE NOT NULL DEFAULT 0, y DOUBLE NOT NULL DEFAULT 0, z DOUBLE NOT NULL DEFAULT 0,
 delta_x DOUBLE NOT NULL DEFAULT 0, delta_y DOUBLE NOT NULL DEFAULT 0, delta_z DOUBLE NOT NULL DEFAULT 0,
 pitch DOUBLE NOT NULL DEFAULT 0, roll DOUBLE NOT NULL DEFAULT 0, yaw DOUBLE NOT NULL DEFAULT 0,
 pitch_rate DOUBLE NOT NULL DEFAULT 0, roll_rate DOUBLE NOT NULL DEFAULT 0, yaw_rate DOUBLE NOT NULL DEFAULT 0,
 dependency INT UNSIGNED NULL,
 dependent TINYINT(1) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 0,
 offset_distance DOUBLE NOT NULL DEFAULT 0,
 land INT UNSIGNED NULL,
 land_x DOUBLE NULL, land_y DOUBLE NULL, land_z DOUBLE NULL,
 INDEX (dependency), INDEX (land), INDEX (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS console_instruments (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 console_id ENUM('cmc','cmp','lmc','lmp','fido','flight') NOT NULL,
 file_name VARCHAR(100) NOT NULL,
 pos_x INT NOT NULL DEFAULT 0,
 pos_y INT NOT NULL DEFAULT 0,
 UNIQUE KEY console_instrument (console_id,file_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User-pasted HTML/CSS/JS gauges run in sandboxed iframes, never as PHP.
CREATE TABLE IF NOT EXISTS instrument_sources (
 file_name VARCHAR(100) PRIMARY KEY,
 source_html MEDIUMTEXT NOT NULL,
 width_px SMALLINT UNSIGNED NOT NULL DEFAULT 320,
 height_px SMALLINT UNSIGNED NOT NULL DEFAULT 220,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sim_clock (
 id TINYINT UNSIGNED PRIMARY KEY,
 tick BIGINT UNSIGNED NOT NULL DEFAULT 0,
 updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Earth is anchored at the origin; Moon is in a circular Earth-centered
-- approximation. CSM starts 1,000 km above the Earth's mean radius.
-- SI units throughout: meters, seconds, kg, degrees, degrees/second.
-- Moon 1018 m/s; CSM speed calculated using GM/r = 7353.8 m/s.
INSERT INTO objects
(id,name,dry_mass,radius_length,x,y,z,delta_x,delta_y,delta_z,dependency,dependent,active,offset_distance)
VALUES
(1,'Earth',5.9723e24,6371000,0,0,0,0,0,0,NULL,0,1,0),
(2,'Moon',7.342e22,1737400,384400000,0,0,0,1018.3,0,NULL,0,1,0),
(3,'Command Module',5560,3.9,7371000,0,0,0,7354.3,0,NULL,0,1,0),
(4,'Service Module',6100,7.5,7370997,0,0,0,7354.3,0,3,1,1,3,0),
(5,'Launch Escape Tower',3600,10,0,0,0,0,0,0,3,1,0,4,0),
(6,'LM Ascent Stage',2150,3,0,0,0,0,0,0,7,1,0,3,0),
(7,'LM Descent Stage',2100,4,0,0,0,0,0,0,8,1,0,4,0),
(8,'Saturn V Third Stage',11700,18,0,0,0,0,0,0,9,1,0,18,0),
(9,'Saturn V Second Stage',40000,25,0,0,0,0,0,0,10,1,0,25,0),
(10,'Saturn V First Stage',131000,42,0,0,0,0,0,0,NULL,0,0,42,0)
ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO console_instruments(console_id,file_name,pos_x,pos_y)
VALUES('cmc','window.php',20,60),('lmc','window.php',20,60)
ON DUPLICATE KEY UPDATE file_name=VALUES(file_name);
INSERT INTO sim_clock(id,tick) VALUES(1,0) ON DUPLICATE KEY UPDATE id=id;
