USE electava_workspace;

-- Set up the new customers table for Marketplace
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    company VARCHAR(255),
    phone VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- First, rename the users table
RENAME TABLE users TO employees;

-- Add new enum values temporarily accommodating old & new
ALTER TABLE employees MODIFY COLUMN role ENUM('core', 'sub_core', 'core_admin', 'admin', 'service_team', 'employee', 'vendor') NOT NULL;

-- Migrate data
UPDATE employees SET role = 'core_admin' WHERE role = 'core';
UPDATE employees SET role = 'admin' WHERE role = 'sub_core';

-- Restrict enum to only the new valid roles
ALTER TABLE employees MODIFY COLUMN role ENUM('core_admin', 'admin', 'service_team', 'employee', 'vendor') NOT NULL;
