-- Repair an existing g_meter_samples table without changing spacecraft physics.
-- Run once in moonland. This does NOT clear or overwrite existing samples.
-- Requires g_meter_schema.sql to have created g_meter_samples.
INSERT IGNORE INTO g_meter_samples (craft_id) VALUES (3),(6);

-- Ensure the required G-meter reliability components exist without overwriting
-- their current ON/OFF/failure states or power settings.
INSERT IGNORE INTO reliability_components
 (component_key,component_name,spacecraft_id,master,alarm_name,state,amps,failure_rule)
VALUES
 ('cm_g_meter','CM Accelerometer G Meter',3,1,'ACCEL G',1,0.100,'none'),
 ('lm_g_meter','LM Accelerometer / T-W Indicator',6,1,'T/W IND',1,0.100,'none');

-- Inspect the actual state. Values remain NULL until at least two powered ticks.
SELECT craft_id,sample_tick,sampled_at,g_magnitude,g_forward,valid
FROM g_meter_samples ORDER BY craft_id;
SELECT component_key,state,amps FROM reliability_components
WHERE component_key IN ('cm_g_meter','lm_g_meter') ORDER BY component_key;
SELECT tick,updated_at FROM sim_clock WHERE id=1;
