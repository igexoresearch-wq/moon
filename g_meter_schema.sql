-- Run once on moonland after reliability_power_upgrade.sql and batteries_schema.sql.
-- The accelerometer measurements are intentionally separate from physical objects.
CREATE TABLE IF NOT EXISTS g_meter_samples (
 craft_id INT UNSIGNED NOT NULL PRIMARY KEY,
 sample_tick BIGINT UNSIGNED NULL,
 sampled_at DATETIME(6) NULL,
 velocity_x_mps DOUBLE NULL,
 velocity_y_mps DOUBLE NULL,
 velocity_z_mps DOUBLE NULL,
 gravity_x_mps2 DOUBLE NULL,
 gravity_y_mps2 DOUBLE NULL,
 gravity_z_mps2 DOUBLE NULL,
 acceleration_x_mps2 DOUBLE NULL,
 acceleration_y_mps2 DOUBLE NULL,
 acceleration_z_mps2 DOUBLE NULL,
 g_magnitude DOUBLE NULL,
 g_forward DOUBLE NULL,
 valid TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;
INSERT IGNORE INTO g_meter_samples(craft_id) VALUES (3),(6);

INSERT IGNORE INTO reliability_components
 (component_key,component_name,spacecraft_id,master,alarm_name,state,amps,failure_rule)
 VALUES ('cm_g_meter','CM Accelerometer G Meter',3,1,'ACCEL G',1,0.100,'none'),
        ('lm_g_meter','LM Accelerometer / T-W Indicator',6,1,'T/W IND',1,0.100,'none');

INSERT IGNORE INTO console_instruments(console_id,file_name,pos_x,pos_y)
 VALUES ('cmc','g_meter.php',1120,420),('lmc','g_meter.php',1130,420);
