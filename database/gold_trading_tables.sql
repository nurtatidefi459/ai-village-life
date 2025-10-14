-- Tabel untuk gold trading
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

-- Tabel untuk gold trading transactions
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

-- Update system settings untuk gold trading
INSERT INTO system_settings (setting_key, setting_value, description) VALUES 
('min_gold_price', '800', 'Minimum price per gold (in IDR)'),
('max_gold_price', '1500', 'Maximum price per gold (in IDR)'),
('gold_trading_fee', '5', 'Fee percentage for gold trading'),
('gold_listing_duration', '7', 'Gold listing duration in days');