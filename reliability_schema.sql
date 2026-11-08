-- Apply ONCE to existing moonland database. No existing tables are changed.
CREATE TABLE IF NOT EXISTS reliability_random (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 roll INT UNSIGNED NOT NULL,
 sim_tick BIGINT UNSIGNED NOT NULL DEFAULT 0,
 generated_at DATETIME NOT NULL,
 CHECK (roll BETWEEN 1 AND 100000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO reliability_random (id,roll,sim_tick,generated_at) VALUES (1,50000,0,NOW());

CREATE TABLE IF NOT EXISTS reliability_components (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 component_key VARCHAR(90) NOT NULL UNIQUE,
 component_name VARCHAR(140) NOT NULL,
 spacecraft_id INT UNSIGNED NULL,
 state TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 working; 2 temporarily offline; 3 failed permanently',
 state_since DATETIME NULL,
 restore_at DATETIME NULL,
 last_command_at DATETIME NULL,
 INDEX (state,restore_at),
 INDEX (spacecraft_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reliability_failures (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 component_id INT UNSIGNED NOT NULL,
 component_key VARCHAR(90) NOT NULL,
 failure_type ENUM('missed','temporary','permanent') NOT NULL,
 description VARCHAR(255) NOT NULL,
 roll INT UNSIGNED NOT NULL,
 sim_tick BIGINT UNSIGNED NOT NULL,
 occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 restore_at DATETIME NULL,
 acknowledged TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 alarm pending; 1 acknowledged',
 acknowledged_at DATETIME NULL,
 INDEX (component_id,occurred_at),
 INDEX (acknowledged,occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registers a sample control; more may be inserted without changing reliability.php.
INSERT IGNORE INTO reliability_components (component_key,component_name,spacecraft_id)
VALUES ('cm_rcs_yaw_right','CM RCS — yaw right',3);
