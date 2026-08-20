-- ============================================================
-- Electava Workspace — Full Database Schema
-- Version 2.0 — Production-Ready
-- ============================================================

CREATE DATABASE IF NOT EXISTS electava_workspace;
USE electava_workspace;

-- ────────────────────────────────────────────────────────────
-- CORE TABLES
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    approval_required TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL DEFAULT '',
    role ENUM('core_admin','admin','employee','vendor') NOT NULL,
    domain_id INT DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    force_password_change TINYINT(1) DEFAULT 0,
    avatar VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    job_title VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    allowed_domains JSON DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- PROJECTS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    domain_id INT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    status ENUM('active', 'on_hold', 'completed', 'archived') DEFAULT 'active',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    budget DECIMAL(12,2) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- TASKS & APPROVALS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS task_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(100) NOT NULL,
    default_priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    estimated_hours DECIMAL(5,1) DEFAULT NULL,
    domain_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    created_by INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(100),
    related_id INT DEFAULT NULL,
    related_type VARCHAR(100) DEFAULT NULL,
    status ENUM('pending', 'in_progress', 'submitted', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    due_date DATE,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    estimated_hours DECIMAL(5,1) DEFAULT NULL,
    submission_notes TEXT DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS task_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    approved_by INT NOT NULL,
    action ENUM('approved', 'rejected') NOT NULL DEFAULT 'approved',
    comments TEXT,
    approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- COMPONENTS (Marketplace)
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    parent_id INT DEFAULT NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS manufacturers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    website VARCHAR(500) DEFAULT NULL,
    logo VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_number VARCHAR(255) NOT NULL,
    electava_part_number VARCHAR(255) DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    manufacturer_id INT DEFAULT NULL,
    category_id INT DEFAULT NULL,
    vendor_id INT DEFAULT NULL,
    stock INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,
    status ENUM('draft', 'pending_assignment', 'pending_approval', 'active', 'rejected', 'inactive') DEFAULT 'draft',
    specifications JSON DEFAULT NULL,
    created_by INT DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS component_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component_id INT NOT NULL,
    min_quantity INT NOT NULL,
    price DECIMAL(12,4) NOT NULL,
    FOREIGN KEY (component_id) REFERENCES components(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS component_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component_id INT NOT NULL,
    asset_type ENUM('datasheet', 'image', 'cad_model', 'symbol', 'footprint') NOT NULL,
    url VARCHAR(500) NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (component_id) REFERENCES components(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- SERVICE REQUESTS (PCB Services)
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS service_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    service_type ENUM('pcb_design', 'pcb_manufacturing', 'assembly', 'testing', 'consultation') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    layers INT DEFAULT 2,
    board_size VARCHAR(100) DEFAULT NULL,
    quantity INT DEFAULT 1,
    status ENUM('new', 'reviewing', 'quoted', 'design_in_progress', 'manufacturing', 'testing', 'completed', 'cancelled') DEFAULT 'new',
    quoted_price DECIMAL(12,2) DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    internal_notes TEXT DEFAULT NULL,
    requirement_notes TEXT DEFAULT NULL,
    vendor_notes TEXT DEFAULT NULL,
    verification_notes TEXT DEFAULT NULL,
    last_contact_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- VENDORS, INQUIRIES & ORDERS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS vendor_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_by INT DEFAULT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    total DECIMAL(12,2) DEFAULT 0,
    status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT,
    payment_method VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    component_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,4) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES components(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    phone VARCHAR(50) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    shipping_address TEXT DEFAULT NULL,
    payment_terms VARCHAR(255),
    bank_details TEXT DEFAULT NULL,
    rating DECIMAL(3,2) DEFAULT NULL,
    total_orders INT DEFAULT 0,
    on_time_delivery_rate DECIMAL(5,2) DEFAULT 100.00,
    is_approved TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    vendor_id INT NOT NULL,
    component_id INT DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,4) DEFAULT 0,
    total_price DECIMAL(12,2) DEFAULT 0,
    status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    tracking_number VARCHAR(255),
    shipping_carrier VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    ordered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES components(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- FILE MANAGEMENT
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT DEFAULT 0,
    mime_type VARCHAR(100) DEFAULT NULL,
    related_type ENUM('component', 'service_request', 'task', 'vendor', 'general') DEFAULT 'general',
    related_id INT DEFAULT NULL,
    uploaded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- NOTIFICATIONS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type ENUM('info', 'success', 'warning', 'error', 'task', 'approval') DEFAULT 'info',
    link VARCHAR(500) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- AUDIT LOGS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- LOGIN LOGS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    device_type VARCHAR(50) DEFAULT NULL,
    browser VARCHAR(100) DEFAULT NULL,
    status ENUM('success', 'failed') DEFAULT 'success',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- MODULE PERMISSIONS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    icon VARCHAR(100) DEFAULT 'fa-cube',
    is_global TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS module_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module_id INT NOT NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_user_module (user_id, module_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- SYSTEM SETTINGS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    setting_group VARCHAR(100) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- APPROVAL WORKFLOWS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS approval_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT DEFAULT NULL,
    action_type VARCHAR(100) NOT NULL,
    requires_approval TINYINT(1) DEFAULT 1,
    approver_role ENUM('core', 'sub_core') DEFAULT 'sub_core',
    multi_level TINYINT(1) DEFAULT 0,
    description TEXT DEFAULT NULL,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ════════════════════════════════════════════════════════════
-- SEED DATA
-- ════════════════════════════════════════════════════════════

-- Domains
INSERT IGNORE INTO domains (id, name, description, is_active, approval_required) VALUES
(1, 'Marketplace', 'Component marketplace operations', 1, 1),
(2, 'PCB Services', 'PCB design and manufacturing services', 1, 1),
(3, 'Vendor Management', 'Vendor coordination and procurement', 1, 0),
(4, 'System Settings', 'Internal system administration', 1, 0);

-- Default Users (password: Electava@2025)
-- Hash generated via password_hash('Electava@2025', PASSWORD_BCRYPT)
INSERT IGNORE INTO users (id, email, username, password_hash, full_name, role, domain_id, status) VALUES
(1, 'admin@electava.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Core Administrator', 'core_admin', NULL, 'active');

-- Modules
INSERT IGNORE INTO modules (id, name, display_name, description, icon) VALUES
(1, 'dashboard', 'Dashboard', 'Main overview dashboard', 'fa-chart-line'),
(2, 'users', 'User Management', 'Create and manage users', 'fa-users'),
(3, 'projects', 'Projects', 'Project tracking and assignment', 'fa-diagram-project'),
(4, 'tasks', 'Task Management', 'Task creation and execution', 'fa-list-check'),
(5, 'components', 'Components', 'Component catalog management', 'fa-microchip'),
(6, 'services', 'PCB Services', 'Service request management', 'fa-cogs'),
(7, 'vendors', 'Vendor Portal', 'Vendor coordination', 'fa-handshake'),
(8, 'orders', 'Purchase Orders', 'Order tracking and fulfillment', 'fa-truck-fast'),
(9, 'inventory', 'Inventory', 'Stock management', 'fa-boxes-stacked'),
(10, 'reports', 'Reports', 'Analytics and reporting', 'fa-chart-pie'),
(11, 'audit', 'Audit Logs', 'System activity logs', 'fa-shield-halved'),
(12, 'settings', 'Settings', 'System configuration', 'fa-gear');

-- System Settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_group) VALUES
('site_name', 'Electava Workspace', 'general'),
('currency', 'INR', 'general'),
('tax_rate', '18', 'general'),
('session_timeout', '3600', 'security'),
('maintenance_mode', '0', 'general'),
('smtp_host', '', 'email'),
('smtp_port', '587', 'email'),
('smtp_user', '', 'email'),
('smtp_pass', '', 'email'),
('smtp_from', 'noreply@electava.com', 'email');

-- Approval Rules
INSERT IGNORE INTO approval_rules (domain_id, action_type, requires_approval, approver_role, description) VALUES
(1, 'new_component', 1, 'admin', 'New component listings require Admin approval'),
(1, 'price_change', 1, 'admin', 'Price changes require Admin approval'),
(2, 'quote_approval', 1, 'admin', 'Service quotes require Admin approval'),
(3, 'new_vendor', 1, 'core_admin', 'New vendor registration requires Core Admin approval');

-- Categories
INSERT IGNORE INTO categories (id, name, parent_id) VALUES
(1, 'Resistors', NULL),
(2, 'Capacitors', NULL),
(3, 'Inductors', NULL),
(4, 'ICs & Semiconductors', NULL),
(5, 'Connectors', NULL),
(6, 'Diodes', NULL),
(7, 'Transistors', NULL),
(8, 'MCUs & Processors', 4),
(9, 'Op-Amps', 4),
(10, 'Voltage Regulators', 4);

-- Manufacturers
INSERT IGNORE INTO manufacturers (id, name, website) VALUES
(1, 'Texas Instruments', 'https://www.ti.com'),
(2, 'STMicroelectronics', 'https://www.st.com'),
(3, 'Microchip Technology', 'https://www.microchip.com'),
(4, 'Murata', 'https://www.murata.com'),
(5, 'Samsung Electro-Mechanics', 'https://www.samsungsem.com');

-- Task Templates
INSERT IGNORE INTO task_templates (id, name, description, type, default_priority, estimated_hours, domain_id) VALUES
(1, 'Create Component Listing', 'Add a new component with full specs, datasheet, and CAD files', 'component_create', 'medium', 2.0, 1),
(2, 'Upload Symbol File', 'Upload schematic symbol (.lib/.sym) for a component', 'component_upload', 'medium', 1.0, 1),
(3, 'Upload Footprint File', 'Upload PCB footprint (.kicad_mod) for a component', 'component_upload', 'medium', 1.0, 1),
(4, 'Update Service Request', 'Update status and add notes to a service request', 'service_update', 'high', 0.5, 2),
(5, 'Generate Quote', 'Create and submit a price quote for a service request', 'service_quote', 'high', 1.5, 2),
(6, 'Stock Audit', 'Verify and update inventory counts', 'inventory_audit', 'low', 3.0, 3),
(7, 'Vendor Onboarding', 'Complete vendor registration and documentation', 'vendor_onboard', 'medium', 4.0, 3);

-- ────────────────────────────────────────────────────────────
-- MARKETPLACE TRACKING
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS marketplace_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_type VARCHAR(50),
    browser VARCHAR(100),
    page_visited VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- CAREERS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS careers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    team VARCHAR(100),
    location VARCHAR(255),
    type VARCHAR(100),
    summary TEXT,
    highlights_json TEXT,
    status ENUM('active', 'draft', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- SERVICE TOKENS (Marketplace Inquiries)
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS service_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_number VARCHAR(50) UNIQUE NOT NULL,
    user_email VARCHAR(255),
    service_type VARCHAR(100),
    details TEXT,
    status ENUM('pending', 'in_progress', 'replied', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_to INT DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    requirement_notes TEXT DEFAULT NULL,
    vendor_notes TEXT DEFAULT NULL,
    verification_notes TEXT DEFAULT NULL,
    last_contact_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
