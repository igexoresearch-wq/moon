-- Apply once to moonland. Individual RCS systems retain their own propellant.
CREATE TABLE IF NOT EXISTS rcs (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 craft_id INT UNSIGNED NOT NULL UNIQUE,
 system_name VARCHAR(80) NOT NULL,
 fuel_kg DOUBLE NOT NULL,
 fuel_capacity_kg DOUBLE NOT NULL,
 oxidizer_kg DOUBLE NOT NULL,
 oxidizer_capacity_kg DOUBLE NOT NULL,
 dry_fuel_kg DOUBLE NOT NULL DEFAULT 0,
 dry_oxidizer_kg DOUBLE NOT NULL DEFAULT 0,
 thrust_n DOUBLE NOT NULL,
 isp_s DOUBLE NOT NULL,
 torque_arm_m DOUBLE NOT NULL,
 inertia_kg_m2 DOUBLE NOT NULL,
 pulse_seconds DOUBLE NOT NULL DEFAULT 0.1,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 CHECK (fuel_kg>=0 AND oxidizer_kg>=0)
) ENGINE=InnoDB;
-- Approximate starting quantities / inertial models are configurable;
-- not claims of exact Apollo launch loading.
INSERT IGNORE INTO rcs (craft_id,system_name,fuel_kg,fuel_capacity_kg,oxidizer_kg,oxidizer_capacity_kg,thrust_n,isp_s,torque_arm_m,inertia_kg_m2)
VALUES (4,'SM RCS',200,200,300,300,445,289,3.0,80000),
       (3,'CM RCS',35,35,55,55,410,250,1.5,30000),
       (6,'LM RCS',125,125,195,195,445,290,2.0,16000);
-- Each discrete direction has an independent reliability record.
INSERT IGNORE INTO reliability_components (component_key,component_name,spacecraft_id)
VALUES
('cm_rcs_pitch_up','CM RCS Pitch Up',3),('cm_rcs_pitch_down','CM RCS Pitch Down',3),
('cm_rcs_yaw_left','CM RCS Yaw Left',3),('cm_rcs_roll_left','CM RCS Roll Left',3),
('cm_rcs_roll_right','CM RCS Roll Right',3),
('lm_rcs_pitch_up','LM RCS Pitch Up',6),('lm_rcs_pitch_down','LM RCS Pitch Down',6),
('lm_rcs_yaw_left','LM RCS Yaw Left',6),('lm_rcs_yaw_right','LM RCS Yaw Right',6),
('lm_rcs_roll_left','LM RCS Roll Left',6),('lm_rcs_roll_right','LM RCS Roll Right',6),
('cm_rcs_translate_forward','CM RCS Translate Forward',3),
('cm_rcs_translate_back','CM RCS Translate Back',3),
('cm_rcs_translate_left','CM RCS Translate Left',3),
('cm_rcs_translate_right','CM RCS Translate Right',3),
('cm_rcs_translate_up','CM RCS Translate Up',3),
('cm_rcs_translate_down','CM RCS Translate Down',3),
('lm_rcs_translate_forward','LM RCS Translate Forward',6),
('lm_rcs_translate_back','LM RCS Translate Back',6),
('lm_rcs_translate_left','LM RCS Translate Left',6),
('lm_rcs_translate_right','LM RCS Translate Right',6),
('lm_rcs_translate_up','LM RCS Translate Up',6),
('lm_rcs_translate_down','LM RCS Translate Down',6);
INSERT IGNORE INTO console_instruments (console_id,file_name,pos_x,pos_y)
VALUES ('cmc','rcs.php',440,60),('lmc','rcs.php',440,60);
