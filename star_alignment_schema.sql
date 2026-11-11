-- Import once into existing moonland after dsky_schema.sql and imu_schema.sql.
-- P30 reads three known reference stars A/B/C as packed XXYY optical sightings.
-- P31 commands the IMU-referenced 000 / 000 / 000 verification attitude.
ALTER TABLE dsky_programs MODIFY operation ENUM('burn_seconds','attitude_target','platform_align') NOT NULL;
INSERT INTO dsky_programs
(craft_id,program_no,title,operation,config_json,
 field1_label,field1_min,field1_max,
 field2_label,field2_min,field2_max,
 field3_label,field3_min,field3_max)
VALUES
(3,30,'PLATFORM ALIGN','platform_align',JSON_OBJECT('encoding','xy_halfdegree','stars',JSON_ARRAY('A','B','C')),
 'STAR A XY',0,9999,'STAR B XY',0,9999,'STAR C XY',0,9999),
(6,30,'PLATFORM ALIGN','platform_align',JSON_OBJECT('encoding','xy_halfdegree','stars',JSON_ARRAY('A','B','C')),
 'STAR A XY',0,9999,'STAR B XY',0,9999,'STAR C XY',0,9999),
(3,31,'ALIGN CHECK 000','attitude_target',JSON_OBJECT('axis','all','scale',10),
 'PITCH',0,3599,'YAW',0,3599,'ROLL',0,3599),
(6,31,'ALIGN CHECK 000','attitude_target',JSON_OBJECT('axis','all','scale',10),
 'PITCH',0,3599,'YAW',0,3599,'ROLL',0,3599)
ON DUPLICATE KEY UPDATE title=VALUES(title),operation=VALUES(operation),
 config_json=VALUES(config_json),field1_label=VALUES(field1_label),
 field1_min=VALUES(field1_min),field1_max=VALUES(field1_max),
 field2_label=VALUES(field2_label),field2_min=VALUES(field2_min),
 field2_max=VALUES(field2_max),field3_label=VALUES(field3_label),
 field3_min=VALUES(field3_min),field3_max=VALUES(field3_max);
-- Add sextant only to CM and LM pilot pages, not Commander pages.
INSERT INTO console_instruments(console_id,file_name,pos_x,pos_y)
VALUES ('cmp','sextant.php',1190,60),('lmp','sextant.php',1190,60)
ON DUPLICATE KEY UPDATE file_name=VALUES(file_name);
