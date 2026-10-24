-- Run against the temple_platform database before enabling online donations.
-- Existing donation rows remain unchanged.

SET @has_promptpay_id = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'temples'
      AND COLUMN_NAME = 'promptpay_id'
);
SET @migration_sql = IF(
    @has_promptpay_id = 0,
    'ALTER TABLE temples ADD COLUMN promptpay_id VARCHAR(13) NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @has_temple_id = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'donations'
      AND COLUMN_NAME = 'temple_id'
);
SET @migration_sql = IF(
    @has_temple_id = 0,
    'ALTER TABLE donations ADD COLUMN temple_id INT NULL AFTER id',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @has_slip_image = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'donations'
      AND COLUMN_NAME = 'slip_image'
);
SET @migration_sql = IF(
    @has_slip_image = 0,
    'ALTER TABLE donations ADD COLUMN slip_image VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @has_temple_created_index = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'donations'
      AND INDEX_NAME = 'idx_donations_temple_created'
);
SET @migration_sql = IF(
    @has_temple_created_index = 0,
    'CREATE INDEX idx_donations_temple_created ON donations (temple_id, created_at)',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
