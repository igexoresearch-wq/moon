-- Add-on for existing moonland database. Run once after schema.sql.
-- Separate mutable estimates; objects remains the physical truth.
CREATE TABLE IF NOT EXISTS imu (
 object_id INT UNSIGNED PRIMARY KEY,
 pitch_deg DOUBLE NOT NULL DEFAULT 0,
 yaw_deg DOUBLE NOT NULL DEFAULT 0,
 roll_deg DOUBLE NOT NULL DEFAULT 0,
 reference_pitch_deg DOUBLE NOT NULL DEFAULT 0,
 reference_yaw_deg DOUBLE NOT NULL DEFAULT 0,
 reference_roll_deg DOUBLE NOT NULL DEFAULT 0,
 last_tick BIGINT UNSIGNED NULL,
 aligned_at_tick BIGINT UNSIGNED NULL,
 FOREIGN KEY (object_id) REFERENCES objects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO imu(object_id,pitch_deg,yaw_deg,roll_deg,last_tick,aligned_at_tick)
 SELECT id,pitch,yaw,roll,(SELECT tick FROM sim_clock WHERE id=1),(SELECT tick FROM sim_clock WHERE id=1)
 FROM objects WHERE id IN (3,6)
ON DUPLICATE KEY UPDATE object_id=object_id;
-- Independent, movable eight-ball instruments, never overwrite layout.
INSERT IGNORE INTO console_instruments(console_id,file_name,pos_x,pos_y)
VALUES ('cmc','eight_ball.php',450,90),('lmc','eight_ball.php',450,90);
