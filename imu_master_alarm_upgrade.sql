-- Run once against existing moonland database after reliability_power_upgrade.sql.
-- Each IMU appears on its own spacecraft's caution/warning panel.
-- This changes display configuration only; no component state is reset.
UPDATE reliability_components
SET master=1, alarm_name='IMU'
WHERE component_key IN ('cm_imu','lm_imu');
