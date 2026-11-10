-- Run once after reliability_power_upgrade.sql and batteries_schema.sql.
-- Each radar and responder consumes 3 A while ON.
INSERT IGNORE INTO reliability_components
(component_key,component_name,spacecraft_id,master,alarm_name,state,amps,failure_rule)
VALUES
('cm_rendezvous_radar','CM Rendezvous Radar',3,1,'RNDZ RADAR',4,3.000,'none'),
('lm_rendezvous_radar','LM Rendezvous Radar',6,1,'RNDZ RADAR',4,3.000,'none'),
('cm_radar_responder','CM Radar Responder',3,1,'RADAR RESP',4,3.000,'none'),
('lm_radar_responder','LM Radar Responder',6,1,'RADAR RESP',4,3.000,'none');
-- Register values are independent for each spacecraft and available to future DSKY code.
CREATE TABLE IF NOT EXISTS computer_registers (
 craft_id INT UNSIGNED NOT NULL,
 register_key VARCHAR(45) NOT NULL,
 numeric_value DOUBLE NULL,
 valid TINYINT(1) NOT NULL DEFAULT 0,
 updated_tick BIGINT UNSIGNED NULL,
 PRIMARY KEY (craft_id,register_key)
) ENGINE=InnoDB;
INSERT IGNORE INTO computer_registers(craft_id,register_key,numeric_value,valid) VALUES
(3,'RENDEZVOUS_RANGE_M',NULL,0),(3,'RENDEZVOUS_OFF_AXIS_DEG',NULL,0),
(3,'RENDEZVOUS_AZIMUTH_DEG',NULL,0),(3,'RENDEZVOUS_ELEVATION_DEG',NULL,0),
(6,'RENDEZVOUS_RANGE_M',NULL,0),(6,'RENDEZVOUS_OFF_AXIS_DEG',NULL,0),
(6,'RENDEZVOUS_AZIMUTH_DEG',NULL,0),(6,'RENDEZVOUS_ELEVATION_DEG',NULL,0);
INSERT IGNORE INTO console_instruments(console_id,file_name,pos_x,pos_y)
VALUES ('cmc','rendezvous_radar.php',800,730),
       ('lmc','rendezvous_radar.php',790,420);
