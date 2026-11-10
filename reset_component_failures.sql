-- REUSABLE MANUAL RESET OF EXISTING RELIABILITY/ALARM FAULTS.
-- STOP execute.php cron/worker first; resume after updated PHP is deployed.
-- Intentional state 4 (OFF) is preserved. Failed states 2/3 return to working 1.
-- Run after reliability_10million_upgrade.sql to avoid immediate old-range errors.
-- No spacecraft physics, battery charge, or user instrument layout is changed.
START TRANSACTION;
UPDATE reliability_components
 SET state=1,state_since=NOW(),restore_at=NULL,last_command_at=NULL,auto_shed=0
 WHERE state IN (2,3);
-- Keep intentionally OFF components OFF; clear old auto-shed latch only.
UPDATE reliability_components SET auto_shed=0 WHERE state=4;
DELETE FROM reliability_failures;
UPDATE electrical_alarms SET active=0,acknowledged=0;
COMMIT;
