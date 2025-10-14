-- AI VILLAGE LIFE - Database Setup
-- File: database/setup.sql

SET FOREIGN_KEY_CHECKS=0;

-- Players Table
CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id VARCHAR(50) UNIQUE NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    silver INT DEFAULT 100,
    gold INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_player_id (player_id),
    INDEX idx_email (email)
);

-- Auth Tokens Table
CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id VARCHAR(50) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- Player Bank Accounts Table
CREATE TABLE IF NOT EXISTS player_bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id VARCHAR(50) NOT NULL UNIQUE,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_holder VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE,
    INDEX idx_player (player_id)
);

-- Auction Items Table
CREATE TABLE IF NOT EXISTS auction_items (
    id VARCHAR(50) PRIMARY KEY,
    seller_id VARCHAR(50) NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    description TEXT,
    price INT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    buyer_id VARCHAR(50),
    sold_at TIMESTAMP NULL,
    status ENUM('active', 'sold', 'expired', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (seller_id) REFERENCES players(player_id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES players(player_id) ON DELETE CASCADE,
    INDEX idx_seller (seller_id),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at)
);

-- Auction Transactions Table
CREATE TABLE IF NOT EXISTS auction_transactions (
    id VARCHAR(50) PRIMARY KEY,
    item_id VARCHAR(50) NOT NULL,
    seller_id VARCHAR(50) NOT NULL,
    buyer_id VARCHAR(50) NOT NULL,
    price INT NOT NULL,
    fee_amount INT NOT NULL,
    seller_amount INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES players(player_id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES players(player_id) ON DELETE CASCADE,
    INDEX idx_seller (seller_id),
    INDEX idx_buyer (buyer_id),
    INDEX idx_created (created_at)
);

-- Forum Posts Table
CREATE TABLE IF NOT EXISTS forum_posts (
    id VARCHAR(50) PRIMARY KEY,
    author_id VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES players(player_id) ON DELETE CASCADE,
    INDEX idx_author (author_id),
    INDEX idx_created (created_at)
);

-- Top-up Transactions Table
CREATE TABLE IF NOT EXISTS topup_transactions (
    id VARCHAR(50) PRIMARY KEY,
    player_id VARCHAR(50) NOT NULL,
    gold_amount INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    proof_image TEXT,
    transfer_date DATE,
    bank_name VARCHAR(100),
    account_number VARCHAR(50),
    admin_notes TEXT,
    status ENUM('pending', 'waiting_verification', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE,
    INDEX idx_player_status (player_id, status),
    INDEX idx_created (created_at)
);

-- Gold Listings Table (Player-to-Player Gold Trading)
CREATE TABLE IF NOT EXISTS gold_listings (
    id VARCHAR(50) PRIMARY KEY,
    seller_id VARCHAR(50) NOT NULL,
    gold_amount INT NOT NULL,
    price_per_gold DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'sold', 'cancelled', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    buyer_id VARCHAR(50),
    sold_at TIMESTAMP NULL,
    fee_amount DECIMAL(10,2) DEFAULT 0,
    seller_amount DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (seller_id) REFERENCES players(player_id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES players(player_id) ON DELETE CASCADE,
    INDEX idx_seller (seller_id),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at),
    INDEX idx_price (price_per_gold)
);

-- Gold Transactions Table
CREATE TABLE IF NOT EXISTS gold_transactions (
    id VARCHAR(50) PRIMARY KEY,
    gold_listing_id VARCHAR(50) NOT NULL,
    buyer_id VARCHAR(50) NOT NULL,
    seller_id VARCHAR(50) NOT NULL,
    gold_amount INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    fee_amount DECIMAL(10,2) NOT NULL,
    seller_amount DECIMAL(10,2) NOT NULL,
    payment_proof_image TEXT,
    transfer_date DATE,
    buyer_bank_name VARCHAR(100),
    buyer_account_number VARCHAR(50),
    status ENUM('pending', 'waiting_verification', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gold_listing_id) REFERENCES gold_listings(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES players(player_id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES players(player_id) ON DELETE CASCADE,
    INDEX idx_buyer (buyer_id),
    INDEX idx_seller (seller_id),
    INDEX idx_status (status)
);

-- Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'support') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admin Tokens Table
CREATE TABLE IF NOT EXISTS admin_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- Admin Logs Table
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_admin_action (admin_id, action),
    INDEX idx_created (created_at)
);

-- System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- Insert Default Admin User (password: admin123)
INSERT IGNORE INTO admins (username, password, email, role) VALUES 
('superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@aivillagelife.com', 'super_admin');

-- Insert Default System Settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES 
('fee_percentage', '10', 'Auction house fee percentage'),
('topup_monthly_limit', '1', 'Monthly top-up limit per player'),
('max_gold_per_month', '500', 'Maximum gold per month per player'),
('game_version', '1.0.0', 'Current game version'),
('maintenance_mode', 'false', 'System maintenance mode'),
('min_gold_price', '800', 'Minimum price per gold (in IDR)'),
('max_gold_price', '1500', 'Maximum price per gold (in IDR)'),
('gold_trading_fee', '5', 'Fee percentage for gold trading'),
('gold_listing_duration', '7', 'Gold listing duration in days');

SET FOREIGN_KEY_CHECKS=1;

-- Display confirmation
SELECT 'Database setup completed successfully!' AS message;