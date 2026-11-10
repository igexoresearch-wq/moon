-- Apply once to the installed moonland database BEFORE running updated reliability.php.
-- Existing CHECK(roll BETWEEN 1 AND 100000) must be replaced, or rolls >100000 fail.
-- Works with MySQL 8 and MariaDB installations that enforce CHECK constraints.
-- Do not run while execute.php worker is active.
SET @moon_old_roll_check := (
 SELECT CONSTRAINT_NAME
 FROM information_schema.TABLE_CONSTRAINTS
 WHERE TABLE_SCHEMA=DATABASE()
   AND TABLE_NAME='reliability_random'
   AND CONSTRAINT_TYPE='CHECK'
 ORDER BY CONSTRAINT_NAME LIMIT 1
);
SET @moon_drop_statement := IF(
 @moon_old_roll_check IS NULL,
 'SELECT 1',
 CONCAT('ALTER TABLE reliability_random ',
        IF(VERSION() LIKE '%MariaDB%','DROP CONSTRAINT ','DROP CHECK '),
        '`',REPLACE(@moon_old_roll_check,'`','``'),'`')
);
PREPARE moon_roll_ddl FROM @moon_drop_statement;
EXECUTE moon_roll_ddl;
DEALLOCATE PREPARE moon_roll_ddl;
ALTER TABLE reliability_random
 ADD CONSTRAINT chk_reliability_roll_range CHECK (roll BETWEEN 1 AND 10000000);
-- Existing stored roll and sim tick are preserved.
