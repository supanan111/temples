-- Run against the temple_platform database before enabling member accounts.
-- These statements can be rerun if a previous attempt already added user_id.
ALTER TABLE users MODIFY role VARCHAR(20) NOT NULL;

SET @has_user_id = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bookings'
      AND COLUMN_NAME = 'user_id'
);
SET @migration_sql = IF(
    @has_user_id = 0,
    'ALTER TABLE bookings ADD COLUMN user_id INT NULL AFTER item_id',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @has_user_id_index = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bookings'
      AND INDEX_NAME = 'idx_bookings_user_id'
);
SET @migration_sql = IF(
    @has_user_id_index = 0,
    'CREATE INDEX idx_bookings_user_id ON bookings (user_id)',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
