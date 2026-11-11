-- Moon Program simplified DSKY. Execute ONCE in moonland, after rcs_schema.sql and propulsion_schema.sql.
-- Program "code" is declarative action configuration, never executable PHP stored in SQL.
-- PHP implements supported action types once; a new SQL row adds another program.
CREATE TABLE IF NOT EXISTS dsky_programs (
 craft_id INT UNSIGNED NOT NULL,
 program_no SMALLINT UNSIGNED NOT NULL,
 title VARCHAR(60) NOT NULL,
 operation ENUM('burn_seconds','attitude_target') NOT NULL,
 config_json JSON NOT NULL,
 field1_label VARCHAR(20) NULL,
 field2_label VARCHAR(20) NULL,
 field3_label VARCHAR(20) NULL,
 field4_label VARCHAR(20) NULL,
 field1_min DECIMAL(12,1) NULL,
 field1_max DECIMAL(12,1) NULL,
 field2_min DECIMAL(12,1) NULL,
 field2_max DECIMAL(12,1) NULL,
 field3_min DECIMAL(12,1) NULL,
 field3_max DECIMAL(12,1) NULL,
 field4_min DECIMAL(12,1) NULL,
 field4_max DECIMAL(12,1) NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 PRIMARY KEY(craft_id,program_no),
 CONSTRAINT fk_dsky_program_craft FOREIGN KEY(craft_id) REFERENCES objects(id)
) ENGINE=InnoDB;
-- Four slots per program are nullable; absent slots are not editable. Numeric
-- inputs use one implied decimal digit: 1532 => 153.2 degrees, 0010 => 1.0 s.
-- CM SPS burn time (seconds) is an integer input: scale=1, '0010' = 10 s.
INSERT INTO dsky_programs
(craft_id,program_no,title,operation,config_json,field1_label,field1_min,field1_max)
VALUES
(3,1,'SPS BURN TIME','burn_seconds',JSON_OBJECT('engine_id',401,'scale',1), 'SECONDS',1,9999),
(3,2,'PITCH TARGET','attitude_target',JSON_OBJECT('axis','pitch','scale',10),'PITCH DEG',0,3599),
(3,3,'YAW TARGET','attitude_target',JSON_OBJECT('axis','yaw','scale',10),'YAW DEG',0,3599),
(3,4,'ROLL TARGET','attitude_target',JSON_OBJECT('axis','roll','scale',10),'ROLL DEG',0,3599)
ON DUPLICATE KEY UPDATE program_no=VALUES(program_no);
INSERT INTO dsky_programs
(craft_id,program_no,title,operation,config_json,field1_label,field1_min,field1_max,field2_label,field2_min,field2_max,field3_label,field3_min,field3_max)
VALUES
(3,5,'THREE AXIS TARGET','attitude_target',JSON_OBJECT('axis','all','scale',10),
'PITCH DEG',0,3599,'YAW DEG',0,3599,'ROLL DEG',0,3599)
ON DUPLICATE KEY UPDATE program_no=VALUES(program_no);
CREATE TABLE IF NOT EXISTS dsky_drafts (
 craft_id INT UNSIGNED NOT NULL PRIMARY KEY,
 program_no SMALLINT UNSIGNED NULL,
 field1 DECIMAL(12,1) NULL,
 field2 DECIMAL(12,1) NULL,
 field3 DECIMAL(12,1) NULL,
 field4 DECIMAL(12,1) NULL,
 selected_field TINYINT UNSIGNED NULL,
 confirmed TINYINT(1) NOT NULL DEFAULT 0,
 CONSTRAINT fk_dsky_draft_craft FOREIGN KEY(craft_id) REFERENCES objects(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS dsky_registers (
 craft_id INT UNSIGNED NOT NULL,
 register_no TINYINT UNSIGNED NOT NULL,
 program_no SMALLINT UNSIGNED NULL,
 field1 DECIMAL(12,1) NULL,
 field2 DECIMAL(12,1) NULL,
 field3 DECIMAL(12,1) NULL,
 field4 DECIMAL(12,1) NULL,
 state ENUM('empty','queued','running','complete','failed','aborted') NOT NULL DEFAULT 'empty',
 started_tick BIGINT UNSIGNED NULL,
 elapsed_ticks INT UNSIGNED NOT NULL DEFAULT 0,
 message VARCHAR(120) NULL,
 PRIMARY KEY(craft_id,register_no),
 CONSTRAINT fk_dsky_register_craft FOREIGN KEY(craft_id) REFERENCES objects(id),
 CHECK(register_no BETWEEN 1 AND 5)
) ENGINE=InnoDB;
INSERT IGNORE INTO dsky_drafts(craft_id) VALUES(3),(6);
INSERT IGNORE INTO dsky_registers(craft_id,register_no) VALUES
(3,1),(3,2),(3,3),(3,4),(3,5),
(6,1),(6,2),(6,3),(6,4),(6,5);
