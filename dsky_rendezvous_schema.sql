-- Install after star_alignment_schema.sql and dsky_schema.sql.
-- Preserve current programs and controls. No burn is queued on installation.
ALTER TABLE dsky_programs MODIFY operation
 ENUM('burn_seconds','attitude_target','platform_align','timed_delta_v') NOT NULL;
CREATE TABLE IF NOT EXISTS dsky_dv_burns (
 craft_id INT UNSIGNED NOT NULL,
 register_no TINYINT UNSIGNED NOT NULL,
 target_tick BIGINT UNSIGNED NOT NULL,
 achieved_mps DOUBLE NOT NULL DEFAULT 0,
 last_sample_tick BIGINT UNSIGNED NULL,
 ignited_tick BIGINT UNSIGNED NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'SLEWING',
 PRIMARY KEY(craft_id,register_no),
 CONSTRAINT fk_dsky_dv_burn_reg FOREIGN KEY(craft_id,register_no)
 REFERENCES dsky_registers(craft_id,register_no)
) ENGINE=InnoDB;
INSERT INTO dsky_programs(craft_id,program_no,title,operation,config_json,
 field1_label,field1_min,field1_max,
 field2_label,field2_min,field2_max,
 field3_label,field3_min,field3_max,
 field4_label,field4_min,field4_max)
VALUES(3,32,'SPS TIG DELTA-V','timed_delta_v',JSON_OBJECT('engine_id',401,'scale',10),
 'MET HHMM',0,9959,'DV 0.1MPS',1,9999,'PITCH 0.1',0,3599,'YAW 0.1',0,3599)
ON DUPLICATE KEY UPDATE title=VALUES(title),operation=VALUES(operation),
 config_json=VALUES(config_json),field1_label=VALUES(field1_label),
 field1_min=VALUES(field1_min),field1_max=VALUES(field1_max),
 field2_label=VALUES(field2_label),field2_min=VALUES(field2_min),
 field2_max=VALUES(field2_max),
 field3_label=VALUES(field3_label),field3_min=VALUES(field3_min),
 field3_max=VALUES(field3_max),
 field4_label=VALUES(field4_label),field4_min=VALUES(field4_min),field4_max=VALUES(field4_max);
