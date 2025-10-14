<?php
class PaymentConfig {
    // Rekening Developer
    public static $developer_account = [
        'bank_name' => 'BANK TABUNGAN NEGARA',
        'account_number' => '37901700008923',
        'account_holder' => 'AI VILLAGE LIFE',
        'fee_percentage' => 10 // 10% fee untuk auction house
    ];
    
    // Harga Gold
    public static $gold_prices = [
        10 => 10000,    // 10 Gold = Rp 10.000
        50 => 45000,    // 50 Gold = Rp 45.000 (diskon)
        100 => 80000,   // 100 Gold = Rp 80.000 (diskon)
        200 => 150000   // 200 Gold = Rp 150.000 (diskon)
    ];
    
    // Batasan Top-up
    public static $topup_limits = [
        'monthly_limit' => 1, // 1x per bulan
        'max_gold_per_month' => 500
    ];
}
?>