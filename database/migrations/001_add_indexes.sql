-- ============================================================
-- Migration 001: Add missing indexes and constraints
-- Electava Workspace Database Hardening
-- Run: mysql -u root -p electava_workspace < 001_add_indexes.sql
-- ============================================================

-- Unique constraint on service_tokens.token_number (prevent collision)
ALTER TABLE service_tokens
    ADD CONSTRAINT uq_service_token_number UNIQUE (token_number);

-- Indexes for tasks table
ALTER TABLE tasks
    ADD INDEX idx_tasks_assigned_to  (assigned_to),
    ADD INDEX idx_tasks_status       (status),
    ADD INDEX idx_tasks_due_date     (due_date),
    ADD INDEX idx_tasks_created_by   (created_by);

-- Indexes for login_logs
ALTER TABLE login_logs
    ADD INDEX idx_login_logs_user_id    (user_id),
    ADD INDEX idx_login_logs_status     (status),
    ADD INDEX idx_login_logs_created_at (created_at);

-- Indexes for audit_logs
ALTER TABLE audit_logs
    ADD INDEX idx_audit_logs_user_id    (user_id),
    ADD INDEX idx_audit_logs_action     (action),
    ADD INDEX idx_audit_logs_created_at (created_at);

-- Indexes for service_tokens
ALTER TABLE service_tokens
    ADD INDEX idx_service_tokens_assigned_to (assigned_to),
    ADD INDEX idx_service_tokens_status      (status),
    ADD INDEX idx_service_tokens_user_email  (user_email);

-- Indexes for marketplace_tracking
ALTER TABLE marketplace_tracking
    ADD INDEX idx_tracking_session_id  (session_id),
    ADD INDEX idx_tracking_created_at  (created_at);

-- Indexes for users
ALTER TABLE users
    ADD INDEX idx_users_email    (email),
    ADD INDEX idx_users_username (username),
    ADD INDEX idx_users_role     (role),
    ADD INDEX idx_users_status   (status);

-- Indexes for orders (if table exists)
ALTER TABLE orders
    ADD INDEX idx_orders_customer_id (customer_id),
    ADD INDEX idx_orders_status      (status),
    ADD INDEX idx_orders_created_at  (created_at);

-- FK: service_tokens.assigned_to → users.id (SET NULL on user delete — preserve token history)
ALTER TABLE service_tokens
    ADD CONSTRAINT fk_service_tokens_assigned_to
    FOREIGN KEY (assigned_to) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE;
