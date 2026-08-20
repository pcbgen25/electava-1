USE electava_workspace;

-- 1. Marketplace Users Tracking
CREATE TABLE IF NOT EXISTS marketplace_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_type VARCHAR(50),
    browser VARCHAR(100),
    page_visited VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Service Tokens
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
);

-- 3. Careers
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
);

-- Insert initial careers to match the marketplace hardcoded ones
INSERT INTO careers (title, team, location, type, summary, highlights_json) VALUES 
('Electronics Applications Engineer', 'Engineering', 'Bengaluru / Hybrid', 'Full-time', 'Support customer projects, review PCB requirements, and help shape better workflows across sourcing and manufacturing services.', '["Guide customers on PCB design, BOM analysis, and manufacturing readiness", "Work closely with product and operations teams on feature feedback", "Create practical technical documentation and solution recommendations"]'),
('Marketplace Operations Specialist', 'Operations', 'Bengaluru / On-site', 'Full-time', 'Improve manufacturer coordination, component data quality, and order execution across the Electava marketplace.', '["Coordinate with suppliers and manufacturing partners", "Keep catalog and service information accurate and easy to use", "Help build reliable internal workflows for quoting and fulfillment"]'),
('Frontend Product Developer', 'Product', 'Remote / Hybrid', 'Full-time', 'Design and build polished user experiences for component discovery, service requests, and project collaboration.', '["Build responsive pages and product flows across the customer journey", "Turn complex hardware workflows into simple interfaces", "Collaborate with design, support, and operations on fast improvements"]');
