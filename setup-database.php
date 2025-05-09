<?php
/**
 * اسکریپت اجرای مایگریشن‌ها با قابلیت ایجاد جداول اولیه
 */

// لود شدن فایل‌های مورد نیاز
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/application/Model/Model.php';
require_once __DIR__ . '/application/Model/DB.php';

// تنظیمات محیط
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

try {
    echo "بررسی اتصال به پایگاه داده...\n";
    
    // اتصال به پایگاه داده
    $pdo = \Application\Model\Model::getPdo();
    echo "✅ اتصال به پایگاه داده با موفقیت برقرار شد.\n";
    
    // بررسی وجود جدول users
    $stmt = $pdo->query("SELECT to_regclass('public.users') as table_exists");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['table_exists'] === NULL) {
        echo "⚠️ جدول users یافت نشد. در حال ایجاد جدول اولیه...\n";
        
        // ایجاد جدول users
        $pdo->exec("
            CREATE TABLE users (
                id SERIAL PRIMARY KEY,
                telegram_id BIGINT UNIQUE NOT NULL,
                username VARCHAR(50) UNIQUE,
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                type VARCHAR(20) DEFAULT 'user',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP
            );
        ");
        echo "✅ جدول users با موفقیت ایجاد شد.\n";
        
        // ایجاد جدول users_extra
        $pdo->exec("
            CREATE TABLE users_extra (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL,
                wins INTEGER DEFAULT 0,
                loses INTEGER DEFAULT 0,
                draws INTEGER DEFAULT 0,
                matches INTEGER DEFAULT 0,
                delta_coins DECIMAL(10, 2) DEFAULT 0,
                doz_coin DECIMAL(10, 2) DEFAULT 0,
                friends JSON DEFAULT '[]',
                level INTEGER DEFAULT 1,
                experience INTEGER DEFAULT 0,
                last_daily_claim DATE,
                CONSTRAINT fk_users_extra_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");
        echo "✅ جدول users_extra با موفقیت ایجاد شد.\n";
        
        // ایجاد جدول matches
        $pdo->exec("
            CREATE TABLE matches (
                id SERIAL PRIMARY KEY,
                player1 BIGINT,
                player2 BIGINT,
                winner BIGINT,
                game_data JSON,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ended_at TIMESTAMP,
                status VARCHAR(20) DEFAULT 'active'
            );
        ");
        echo "✅ جدول matches با موفقیت ایجاد شد.\n";
        
        // ایجاد جدول bot_settings
        $pdo->exec("
            CREATE TABLE bot_settings (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) UNIQUE NOT NULL,
                value TEXT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP
            );
        ");
        echo "✅ جدول bot_settings با موفقیت ایجاد شد.\n";
    } else {
        echo "✅ جداول اصلی در دیتابیس وجود دارند.\n";
    }
    
    echo "\nشروع اجرای مایگریشن‌ها...\n";
    
    // بررسی وجود پوشه migrations
    if (!is_dir(__DIR__ . '/migrations')) {
        echo "خطا: پوشه migrations یافت نشد!\n";
        exit(1);
    }
    
    // دریافت لیست فایل‌های مایگریشن
    $migration_files = glob(__DIR__ . '/migrations/*.sql');
    
    if (empty($migration_files)) {
        echo "هیچ فایل مایگریشنی یافت نشد!\n";
        exit(0);
    }
    
    // اجرای هر فایل مایگریشن
    foreach ($migration_files as $file) {
        echo "در حال اجرای مایگریشن: " . basename($file) . "...\n";
        
        // خواندن محتوای فایل SQL
        $sql = file_get_contents($file);
        
        // اجرای کوئری SQL
        try {
            $pdo->exec($sql);
            echo "✅ مایگریشن " . basename($file) . " با موفقیت اجرا شد.\n";
        } catch (PDOException $e) {
            echo "⚠️ خطا در اجرای مایگریشن " . basename($file) . ": " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ اجرای مایگریشن‌ها با موفقیت به پایان رسید.\n";
    
} catch (Exception $e) {
    echo "❌ خطا: " . $e->getMessage() . "\n";
    exit(1);
}