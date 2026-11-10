-- Run this SQL on an existing moonland database after rendezvous_radar_schema.sql.
-- This changes only the four radar/responder electrical ratings.
UPDATE reliability_components
SET amps=3.000
WHERE component_key IN (
  'cm_rendezvous_radar',
  'lm_rendezvous_radar',
  'cm_radar_responder',
  'lm_radar_responder'
);
