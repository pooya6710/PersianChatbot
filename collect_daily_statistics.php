<?php
/**
 * این اسکریپت برای جمع‌آوری خودکار آمار روزانه استفاده می‌شود
 * باید به صورت کرون‌جاب در نیمه شب (00:01) اجرا شود
 * 
 * مثال کرون‌جاب:
 * 1 0 * * * php /path/to/collect_daily_statistics.php
 */

require_once __DIR__ . '/application/controllers/StatisticsController.php';
require_once __DIR__ . '/application/controllers/AdminController.php';

echo "شروع جمع‌آوری آمار روزانه: " . date('Y-m-d H:i:s') . "\n";

try {
    // ایجاد نمونه از کنترلر آمار
    $statisticsController = new \application\controllers\StatisticsController();
    
    // جمع‌آوری آمار روزانه
    $result = $statisticsController->collectDailyStatistics();
    
    if ($result) {
        echo "آمار روزانه با موفقیت جمع‌آوری و ذخیره شد.\n";
        
        // ارسال گزارش به ادمین‌های اصلی
        $admins = \application\controllers\AdminController::getAdminsWithPermission('is_owner');
        
        if (!empty($admins)) {
            // دریافت آمار امروز
            $today = date('Y-m-d');
            $stats = $statisticsController->getDailyStatistics($today);
            
            if ($stats) {
                $message = "📊 *گزارش آمار روزانه*\n\n";
                $message .= "تاریخ: " . $today . "\n";
                $message .= "👥 تعداد کل کاربران: {$stats['total_users']}\n";
                $message .= "👤 کاربران فعال: {$stats['active_users']}\n";
                $message .= "🆕 کاربران جدید: {$stats['new_users']}\n";
                $message .= "🎮 تعداد کل بازی‌ها: {$stats['total_games']}\n";
                $message .= "🎯 بازی‌های امروز: {$stats['games_today']}\n";
                $message .= "💰 میانگین دلتا کوین‌ها: {$stats['avg_delta_coins']}\n";
                $message .= "💵 مجموع دلتا کوین‌ها: {$stats['total_delta_coins']}\n";
                $message .= "💸 درخواست‌های برداشت: {$stats['pending_withdrawals']}\n";
                $message .= "💹 مبلغ درخواست‌های برداشت: {$stats['pending_withdrawals_amount']}\n";
                
                // تابع ارسال پیام تلگرام
                require_once __DIR__ . '/application/controllers/TelegramClass.php';
                $telegram_token = $_ENV['TELEGRAM_BOT_TOKEN'];
                
                foreach ($admins as $admin) {
                    // ارسال پیام به هر ادمین
                    sendMessage($telegram_token, $admin['telegram_id'], $message);
                    echo "گزارش آمار به ادمین {$admin['username']} ارسال شد.\n";
                }
            } else {
                echo "خطا: آمار امروز یافت نشد.\n";
            }
        } else {
            echo "هیچ ادمین اصلی برای ارسال گزارش پیدا نشد.\n";
        }
    } else {
        echo "خطا در جمع‌آوری آمار روزانه.\n";
    }
} catch (Exception $e) {
    echo "خطا: " . $e->getMessage() . "\n";
}

echo "پایان جمع‌آوری آمار روزانه: " . date('Y-m-d H:i:s') . "\n";