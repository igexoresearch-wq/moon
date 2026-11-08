-- Apply ONCE to existing moonland database before uploading changed PHP files.
ALTER TABLE reliability_components
 ADD COLUMN amps DECIMAL(8,3) NOT NULL DEFAULT 0 AFTER alarm_name,
 ADD COLUMN failure_rule ENUM('legacy_rcs','imu_suffix_under_100','none') NOT NULL DEFAULT 'none' AFTER amps;
-- Existing direction controls keep their previous activation probability bands.
UPDATE reliability_components SET amps=0.100,failure_rule='legacy_rcs'
 WHERE component_key LIKE 'cm_rcs_%' OR component_key LIKE 'lm_rcs_%';
-- Equipment power switch for each whole RCS installation. Independent from individual jet faults.
INSERT IGNORE INTO reliability_components(component_key,component_name,spacecraft_id,state,amps,failure_rule)
 VALUES('cm_rcs_cluster','CSM RCS Cluster',4,1,0.000,'none'),
 ('lm_rcs_cluster','LM RCS Cluster',6,1,0.000,'none'),
 ('cm_imu','CM Inertial Measurement Unit',3,1,2.000,'imu_suffix_under_100'),
 ('lm_imu','LM Inertial Measurement Unit',6,1,2.000,'imu_suffix_under_100');
-- Set power draw only on the cluster to avoid double-counting 12 mutually exclusive commands.
UPDATE reliability_components SET amps=0.100 WHERE component_key IN ('cm_rcs_cluster','lm_rcs_cluster');
UPDATE reliability_components SET amps=0.000 WHERE component_key LIKE 'cm_rcs_%' AND component_key<>'cm_rcs_cluster';
UPDATE reliability_components SET amps=0.000 WHERE component_key LIKE 'lm_rcs_%' AND component_key<>'lm_rcs_cluster';
