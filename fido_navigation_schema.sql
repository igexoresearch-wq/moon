-- FIDO independent ground orbit determination; run once after navigation_schema.sql.
-- No modifications to objects, navigation_states, existing DSKY or rendezvous.
CREATE TABLE IF NOT EXISTS fido_navigation_bodies (
 body_id INT UNSIGNED PRIMARY KEY,
 x DOUBLE NOT NULL, y DOUBLE NOT NULL, z DOUBLE NOT NULL,
 vx DOUBLE NOT NULL, vy DOUBLE NOT NULL, vz DOUBLE NOT NULL,
 mass_kg DOUBLE NOT NULL,
 last_tick BIGINT UNSIGNED NOT NULL,
 seeded_tick BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS fido_navigation_states (
 craft_id INT UNSIGNED PRIMARY KEY,
 x DOUBLE NOT NULL,y DOUBLE NOT NULL,z DOUBLE NOT NULL,
 vx DOUBLE NOT NULL,vy DOUBLE NOT NULL,vz DOUBLE NOT NULL,
 last_tick BIGINT UNSIGNED NOT NULL,
 seeded_tick BIGINT UNSIGNED NOT NULL,
 last_tracking_tick BIGINT UNSIGNED NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'INITIALIZED',
 CONSTRAINT fk_fido_navigation_craft FOREIGN KEY(craft_id) REFERENCES objects(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS fido_navigation_telemetry (
 craft_id INT UNSIGNED NOT NULL,
 tick BIGINT UNSIGNED NOT NULL,
 x DOUBLE NOT NULL,y DOUBLE NOT NULL,z DOUBLE NOT NULL,
 vx DOUBLE NOT NULL,vy DOUBLE NOT NULL,vz DOUBLE NOT NULL,
 status VARCHAR(40) NOT NULL,
 PRIMARY KEY(craft_id,tick),
 INDEX idx_fido_nav_tick(tick)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS fido_tracking_observations (
 craft_id INT UNSIGNED NOT NULL,
 tick BIGINT UNSIGNED NOT NULL,
 measured_x DOUBLE NOT NULL,measured_y DOUBLE NOT NULL,measured_z DOUBLE NOT NULL,
 measured_vx DOUBLE NOT NULL,measured_vy DOUBLE NOT NULL,measured_vz DOUBLE NOT NULL,
 method VARCHAR(50) NOT NULL DEFAULT 'SIMULATED GROUND TRACK',
 PRIMARY KEY(craft_id,tick)
) ENGINE=InnoDB;
