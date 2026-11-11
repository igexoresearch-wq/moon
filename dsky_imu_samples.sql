-- One prior IMU sample per computer. The DSKY derives angular rate from
-- successive IMU measurements, never from objects angular-rate columns.
-- Add-on: import after dsky_schema.sql. Does not overwrite IMU alignment.
CREATE TABLE IF NOT EXISTS dsky_imu_samples (
 craft_id INT UNSIGNED NOT NULL PRIMARY KEY,
 imu_tick BIGINT UNSIGNED NOT NULL,
 pitch_deg DOUBLE NOT NULL,
 yaw_deg DOUBLE NOT NULL,
 roll_deg DOUBLE NOT NULL,
 CONSTRAINT fk_dsky_imu_sample_craft FOREIGN KEY(craft_id) REFERENCES objects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
