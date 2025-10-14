<?php
class Database {
    private $host = "localhost";
    private $db_name = "ai_village_life";
    private $username = "root";
    private $password = "";
    public $conn;
    
    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username, 
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
    
    public function initializeDatabase() {
        try {
            $this->conn = $this->getConnection();
            
            // Create players table
            $this->conn->exec("
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
                )
            ");
            
            // Create auth tokens table
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS auth_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    player_id VARCHAR(50) NOT NULL,
                    token VARCHAR(64) NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE,
                    INDEX idx_token (token),
                    INDEX idx_expires (expires_at)
                )
            ");
            
            // Create auction items table
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS auction_items (
                    id VARCHAR(50) PRIMARY KEY,
                    seller_id VARCHAR(50) NOT NULL,
                    item_name VARCHAR(100) NOT NULL,
                    description TEXT,
                    price INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NOT NULL,
                    status ENUM('active', 'sold', 'expired') DEFAULT 'active',
                    FOREIGN KEY (seller_id) REFERENCES players(player_id) ON DELETE CASCADE,
                    INDEX idx_seller (seller_id),
                    INDEX idx_status (status)
                )
            ");
            
            // Create forum posts table
            $this->conn->exec("
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
                )
            ");
            
            // Create transactions table
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS transactions (
                    id VARCHAR(50) PRIMARY KEY,
                    player_id VARCHAR(50) NOT NULL,
                    amount INT NOT NULL,
                    payment_method VARCHAR(50) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE,
                    INDEX idx_player (player_id),
                    INDEX idx_created (created_at)
                )
            ");
            
            error_log("Database initialized successfully");
            
        } catch(PDOException $exception) {
            error_log("Database initialization error: " . $exception->getMessage());
        }
    }
}
?>