-- ============================================================
-- Create least-privilege application database user
-- Run ONCE as MySQL root before deploying the application.
-- Replace CHANGE_ME_PASSWORD with a strong, unique password.
-- ============================================================

-- The application user has NO CREATE, DROP, ALTER, or GRANT privileges.
-- Schema migrations must be run separately with elevated credentials.

CREATE USER IF NOT EXISTS 'electava_app'@'127.0.0.1'
    IDENTIFIED BY 'CHANGE_ME_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE
    ON electava_workspace.*
    TO 'electava_app'@'127.0.0.1';

FLUSH PRIVILEGES;

-- Verify:
-- SHOW GRANTS FOR 'electava_app'@'127.0.0.1';
