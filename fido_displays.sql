-- Run once in the moonland database. Labels and page assignments are data,
-- while executable PHP stays in reviewed files, never in SQL.
CREATE TABLE IF NOT EXISTS fido_displays (
  display_key VARCHAR(40) NOT NULL PRIMARY KEY,
  button_label VARCHAR(32) NOT NULL,
  page_file VARCHAR(80) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  is_default TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO fido_displays(display_key,button_label,page_file,sort_order,enabled,is_default) VALUES
('summary','001 TRAJECTORY','summary.php',10,1,1),
('orbit','002 ORBITAL DATA','orbit.php',20,1,0),
('state','003 POWERED FLIGHT','state.php',30,1,0),
('rendezvous','004 RENDEZVOUS','rendezvous.php',40,1,0)
ON DUPLICATE KEY UPDATE button_label=VALUES(button_label),page_file=VALUES(page_file),
sort_order=VALUES(sort_order),is_default=VALUES(is_default);
