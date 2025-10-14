<?php
class GoldTradingConfig {
    // Batasan harga gold
    public static $min_price_per_gold = 800;  // Harga minimal per gold (Rp 800)
    public static $max_price_per_gold = 1500; // Harga maksimal per gold (Rp 1500)
    
    // Fee untuk platform
    public static $trading_fee_percentage = 5; // 5% fee
    
    // Durasi listing (hari)
    public static $listing_duration_days = 7;
    
    // Batasan jumlah gold
    public static $min_gold_per_listing = 10;   // Minimal 10 gold per listing
    public static $max_gold_per_listing = 1000; // Maksimal 1000 gold per listing
    
    public static function validatePrice($price_per_gold) {
        return $price_per_gold >= self::$min_price_per_gold && 
               $price_per_gold <= self::$max_price_per_gold;
    }
    
    public static function calculateFee($total_price) {
        return $total_price * (self::$trading_fee_percentage / 100);
    }
    
    public static function calculateSellerAmount($total_price) {
        $fee = self::calculateFee($total_price);
        return $total_price - $fee;
    }
    
    public static function validateGoldAmount($amount) {
        return $amount >= self::$min_gold_per_listing && 
               $amount <= self::$max_gold_per_listing;
    }
}
?>