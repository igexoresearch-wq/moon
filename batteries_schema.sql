-- Install once AFTER reliability_power_upgrade.sql and fuel_cells_schema.sql.
CREATE TABLE IF NOT EXISTS batteries (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 craft_id INT UNSIGNED NOT NULL,
 battery_number TINYINT UNSIGNED NOT NULL,
 capacity_ah DOUBLE NOT NULL,
 charge_ah DOUBLE NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY craft_battery(craft_id,battery_number),
 CHECK(capacity_ah>0), CHECK(charge_ah>=0)
) ENGINE=InnoDB;
INSERT IGNORE INTO batteries(craft_id,battery_number,capacity_ah,charge_ah,enabled)
VALUES(3,1,40,40,1),(3,2,40,40,1);
-- A load shed is switched OFF (state 4) without becoming a random hardware fault.
ALTER TABLE reliability_components ADD COLUMN auto_shed TINYINT(1) NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS electrical_alarms (
 craft_id INT UNSIGNED NOT NULL,
 alarm_key VARCHAR(40) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 0,
 acknowledged TINYINT(1) NOT NULL DEFAULT 0,
 updated_tick BIGINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(craft_id,alarm_key)
) ENGINE=InnoDB;
INSERT IGNORE INTO electrical_alarms(craft_id,alarm_key) VALUES
(3,'BUS UNDERVOLT'),(3,'LOAD SHED');
INSERT IGNORE INTO console_instruments(console_id,file_name,pos_x,pos_y)
VALUES('cmc','batteries.php',800,420);
