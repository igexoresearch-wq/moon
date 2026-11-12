-- Moon Program independent onboard navigation (install once; no changes to objects).
-- Position metres, velocity m/s, acceleration m/s^2, time in sim_clock ticks.
CREATE TABLE IF NOT EXISTS navigation_bodies (
 body_id INT UNSIGNED NOT NULL PRIMARY KEY,
 x DOUBLE NOT NULL,y DOUBLE NOT NULL,z DOUBLE NOT NULL,
 vx DOUBLE NOT NULL,vy DOUBLE NOT NULL,vz DOUBLE NOT NULL,
 mass_kg DOUBLE NOT NULL,
 last_tick BIGINT UNSIGNED NOT NULL,
 seeded_tick BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS navigation_states (
 craft_id INT UNSIGNED NOT NULL PRIMARY KEY,
 x DOUBLE NOT NULL,y DOUBLE NOT NULL,z DOUBLE NOT NULL,
 vx DOUBLE NOT NULL,vy DOUBLE NOT NULL,vz DOUBLE NOT NULL,
 last_tick BIGINT UNSIGNED NOT NULL,
 seeded_tick BIGINT UNSIGNED NOT NULL,
 last_sensor_tick BIGINT UNSIGNED NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'INITIALIZED',
 FOREIGN KEY (craft_id) REFERENCES objects(id)
) ENGINE=InnoDB;
-- One record each minute per craft, with state and measurement quality.
-- This is a historical log, not a second source of spacecraft truth.
CREATE TABLE IF NOT EXISTS navigation_telemetry (
 craft_id INT UNSIGNED NOT NULL,
 tick BIGINT UNSIGNED NOT NULL,
 x DOUBLE NOT NULL,y DOUBLE NOT NULL,z DOUBLE NOT NULL,
 vx DOUBLE NOT NULL,vy DOUBLE NOT NULL,vz DOUBLE NOT NULL,
 status VARCHAR(40) NOT NULL,
 PRIMARY KEY (craft_id,tick),
 INDEX idx_nav_history_tick(tick)
) ENGINE=InnoDB;
