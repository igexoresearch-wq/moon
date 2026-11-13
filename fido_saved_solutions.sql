-- Saved FIDO RTCC advisory plans. No modifications to spacecraft state.
CREATE TABLE IF NOT EXISTS fido_saved_solutions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 estimate_tick BIGINT UNSIGNED NOT NULL,
 planning_tick BIGINT UNSIGNED NOT NULL,
 label VARCHAR(90) NOT NULL,
 snapshot_json LONGTEXT NOT NULL,
 plan_json LONGTEXT NOT NULL,
 prediction_json LONGTEXT NOT NULL,
 INDEX fido_saved_solution_epoch(estimate_tick)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
