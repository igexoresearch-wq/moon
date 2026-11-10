-- Run ONCE against installed moonland, with execute.php cron/worker stopped.
-- Works when the previous reliability roll CHECK was 100000 or 10000000.
-- Preserves reliability_random data, component states and failure history.
-- This table should have only its reliability range CHECK constraint.
SET @moon_roll_check := (
 SELECT tc.CONSTRAINT_NAME
 FROM information_schema.TABLE_CONSTRAINTS tc
 WHERE tc.TABLE_SCHEMA = DATABASE()
   AND tc.TABLE_NAME = 'reliability_random'
   AND tc.CONSTRAINT_TYPE = 'CHECK'
 ORDER BY tc.CONSTRAINT_NAME LIMIT 1
);
SET @moon_drop_roll_sql := IF(
 @moon_roll_check IS NULL,
 'SELECT 1',
 CONCAT('ALTER TABLE reliability_random ',
   IF(VERSION() LIKE '%MariaDB%', 'DROP CONSTRAINT ', 'DROP CHECK '),
   '`', REPLACE(@moon_roll_check, '`', '``'), '`')
);
PREPARE moon_drop_roll_stmt FROM @moon_drop_roll_sql;
EXECUTE moon_drop_roll_stmt;
DEALLOCATE PREPARE moon_drop_roll_stmt;

ALTER TABLE reliability_random
 ADD CONSTRAINT chk_reliability_roll_range
 CHECK (roll BETWEEN 1 AND 70000000);
