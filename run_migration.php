<?php
/**
 * اسکریپت اجرای مایگریشن‌ها
 * این اسکریپت فایل‌های SQL موجود در پوشه migrations را اجرا می‌کند
 */

// لود شدن فایل‌های مورد نیاز
require_once __DIR__ . '/application/Model/Model.php';
require_once __DIR__ . '/application/Model/DB.php';

// تنظیمات محیط
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}

try {
    echo "شروع اجرای مایگریشن‌ها...\n";
    
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
            \Application\Model\Model::getPdo()->exec($sql);
            echo "مایگریشن " . basename($file) . " با موفقیت اجرا شد.\n";
        } catch (PDOException $e) {
            echo "خطا در اجرای مایگریشن " . basename($file) . ": " . $e->getMessage() . "\n";
        }
    }
    
    echo "اجرای مایگریشن‌ها با موفقیت به پایان رسید.\n";
    
} catch (Exception $e) {
    echo "خطا: " . $e->getMessage() . "\n";
    exit(1);
}