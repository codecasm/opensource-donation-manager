CREATE DATABASE donation_app_v2;
USE donation_app_v2;

-- Organizations with Extended Details
CREATE TABLE organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    pan_number VARCHAR(20),
    reg_number_80g VARCHAR(50),
    upi_id VARCHAR(100),
    bank_details TEXT, 
    website VARCHAR(150),
    social_links TEXT,
    footer_text TEXT, -- Text to print at bottom of receipt
    logo_path VARCHAR(255), -- Uploaded Logo
    qr_path VARCHAR(255), -- Uploaded QR Image
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users (Admin & Clients)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    mobile VARCHAR(15) UNIQUE NOT NULL,
    address TEXT,
    password VARCHAR(255), -- For Admin Login
    otp_code VARCHAR(6),
    otp_expiry DATETIME,
    is_active TINYINT DEFAULT 0, -- 0: Pending, 1: Active
    is_deleted TINYINT DEFAULT 0, -- Soft Delete Flag
    role ENUM('admin', 'client') DEFAULT 'client',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Org Mapping
CREATE TABLE user_org_mapping (
    user_id INT,
    org_id INT,
    PRIMARY KEY(user_id, org_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (org_id) REFERENCES organizations(id)
);

-- Donations with Cheque/Bank Details
CREATE TABLE donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(50) UNIQUE,
    org_id INT,
    collected_by INT,
    
    -- Donor Info
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    mobile VARCHAR(20),
    address TEXT,
    
    -- Payment Info
    amount DECIMAL(10, 2),
    payment_mode ENUM('Cash', 'UPI', 'Cheque', 'BankTransfer'),
    
    -- Cheque/Bank Specifics
    bank_name VARCHAR(100),
    branch_name VARCHAR(100),
    cheque_no VARCHAR(50),
    cheque_date DATE,
    utr_number VARCHAR(100), -- For UPI/NetBanking
    
    payment_status ENUM('Success', 'Pending'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (org_id) REFERENCES organizations(id),
    FOREIGN KEY (collected_by) REFERENCES users(id)
);

-- Insert Default Super Admin (Mobile: 9999999999, Pass: admin123)
-- NOTE: In production, use password_hash() for security.
INSERT INTO users (full_name, mobile, password, role, is_active) 
VALUES ('Super Admin', '9999999999', 'admin123', 'admin', 1);