-- Onboard terminal rendezvous filter and DSKY P36.
-- Execute after dsky_rendezvous_schema.sql and navigation_schema.sql.
ALTER TABLE dsky_programs MODIFY operation
 ENUM('burn_seconds','attitude_target','platform_align','timed_delta_v','timed_rcs_dv','terminal_match') NOT NULL;
CREATE TABLE IF NOT EXISTS terminal_rendezvous (
 craft_id INT UNSIGNED PRIMARY KEY,
 sample_tick BIGINT UNSIGNED NULL,
 sample_count INT UNSIGNED NOT NULL DEFAULT 0,
 rx DOUBLE NULL,ry DOUBLE NULL,rz DOUBLE NULL,
 vx DOUBLE NULL,vy DOUBLE NULL,vz DOUBLE NULL,
 target_x DOUBLE NULL,target_y DOUBLE NULL,target_z DOUBLE NULL,
 last_range_m DOUBLE NULL,
 status VARCHAR(60) NOT NULL DEFAULT 'ACQUIRING',
 FOREIGN KEY (craft_id) REFERENCES objects(id)
) ENGINE=InnoDB;
INSERT IGNORE INTO terminal_rendezvous(craft_id) VALUES(3),(6);
INSERT INTO dsky_programs
(craft_id,program_no,title,operation,config_json,
 field1_label,field1_min,field1_max)
VALUES
(3,36,'RCS TERMINAL MATCH','terminal_match',JSON_OBJECT('scale',1),
 'HOLD RANGE M',20,500),
(6,36,'RCS TERMINAL MATCH','terminal_match',JSON_OBJECT('scale',1),
 'HOLD RANGE M',20,500)
ON DUPLICATE KEY UPDATE title=VALUES(title),operation=VALUES(operation),
 config_json=VALUES(config_json),field1_label=VALUES(field1_label),
 field1_min=VALUES(field1_min),field1_max=VALUES(field1_max),
 field2_label=NULL,field3_label=NULL,field4_label=NULL;
