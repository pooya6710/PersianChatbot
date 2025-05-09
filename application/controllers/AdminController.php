<?php
namespace application\controllers;

require_once __DIR__ . '/../Model/DB.php';

use Application\Model\DB;

/**
 * کلاس مدیریت پنل ادمین - نسخه اصلاح شده
 */
class AdminController
{
    /**
     * شناسه کاربر
     * @var int
     */
    private $user_id;
    
    /**
     * سازنده
     * @param int $user_id شناسه کاربر
     */
    public function __construct($user_id)
    {
        $this->user_id = $user_id;
        $this->initializeRequiredTables();
    }
    
    /**
     * بررسی و ایجاد جداول مورد نیاز
     */
    private function initializeRequiredTables()
    {
        try {
            // بررسی و ایجاد جدول admin_permissions
            $tableExists = DB::rawQuery("SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public'
                AND table_name = 'admin_permissions'
            ) as exists");
            
            if (!$tableExists[0]['exists']) {
                DB::rawQuery("
                    CREATE TABLE IF NOT EXISTS admin_permissions (
                        id SERIAL PRIMARY KEY,
                        user_id INTEGER NOT NULL,
                        permissions JSON,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP
                    )
                ");
            }
            
            // بررسی و ایجاد جدول transactions
            $tableExists = DB::rawQuery("SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public'
                AND table_name = 'transactions'
            ) as exists");
            
            if (!$tableExists[0]['exists']) {
                DB::rawQuery("
                    CREATE TABLE IF NOT EXISTS transactions (
                        id SERIAL PRIMARY KEY,
                        user_id INTEGER NOT NULL,
                        amount DECIMAL(10, 2) NOT NULL,
                        type VARCHAR(50) NOT NULL,
                        description TEXT,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    )
                ");
            }
            
            // بررسی و ایجاد جدول chat_messages
            $tableExists = DB::rawQuery("SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public'
                AND table_name = 'chat_messages'
            ) as exists");
            
            if (!$tableExists[0]['exists']) {
                DB::rawQuery("
                    CREATE TABLE IF NOT EXISTS chat_messages (
                        id SERIAL PRIMARY KEY,
                        user_id INTEGER NOT NULL,
                        message TEXT,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    )
                ");
            }
            
            // بررسی و اصلاح جدول users
            $columnExists = DB::rawQuery("SELECT EXISTS (
                SELECT FROM information_schema.columns 
                WHERE table_schema = 'public'
                AND table_name = 'users'
                AND column_name = 'spam_limited'
            ) as exists");
            
            if (!$columnExists[0]['exists']) {
                DB::rawQuery("ALTER TABLE users ADD COLUMN IF NOT EXISTS spam_limited BOOLEAN DEFAULT FALSE");
            }
            
            // بررسی و اصلاح جدول users_extra
            $columnExists = DB::rawQuery("SELECT EXISTS (
                SELECT FROM information_schema.columns 
                WHERE table_schema = 'public'
                AND table_name = 'users_extra'
                AND column_name = 'trophy_count'
            ) as exists");
            
            if (!$columnExists[0]['exists']) {
                DB::rawQuery("ALTER TABLE users_extra ADD COLUMN IF NOT EXISTS trophy_count INTEGER DEFAULT 0");
            }
            
            // بررسی و اصلاح جدول bot_settings
            $columnExists = DB::rawQuery("SELECT EXISTS (
                SELECT FROM information_schema.columns 
                WHERE table_schema = 'public'
                AND table_name = 'bot_settings'
                AND column_name = 'description'
            ) as exists");
            
            if (!$columnExists[0]['exists']) {
                DB::rawQuery("
                    ALTER TABLE bot_settings 
                    ADD COLUMN IF NOT EXISTS description TEXT,
                    ADD COLUMN IF NOT EXISTS is_public BOOLEAN DEFAULT FALSE
                ");
            }
            
        } catch (\Exception $e) {
            error_log("Error initializing tables: " . $e->getMessage());
        }
    }
    
    /**
     * بررسی دسترسی ادمین
     * @return bool
     */
    public function isAdmin()
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->user_id)
                ->first();
                
            if (!$user) {
                echo "کاربر با آیدی {$this->user_id} در دیتابیس یافت نشد!\n";
                return false;
            }
            
            // ادمین‌های اصلی
            $owner_ids = [286420965, 6739124921]; // افزودن مالک جدید
            if (in_array($this->user_id, $owner_ids)) {
                echo "ادمین اصلی با آیدی {$this->user_id} شناسایی شد!\n";
                return true;
            }
            
            // بررسی فیلد is_admin
            if (isset($user['is_admin']) && $user['is_admin'] === true) {
                return true;
            }
            
            // بررسی وضعیت ادمین (برای سازگاری با نسخه‌های قبلی)
            return in_array($user['type'], ['admin', 'owner']);
        } catch (\Exception $e) {
            error_log("Error in isAdmin: " . $e->getMessage());
            echo "خطا در بررسی دسترسی ادمین: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * دریافت آمار ربات - نسخه بازنویسی شده با رفع خطاها
     * @return array
     */
    public function getBotStats()
    {
        try {
            if (!$this->isAdmin()) {
                return [
                    'success' => false,
                    'message' => 'شما دسترسی به این بخش ندارید.'
                ];
            }
            
            $stats = [];
            
            // تعداد کل کاربران
            $stats['total_users'] = DB::table('users')->count();
            
            // تعداد کل بازی‌های انجام شده
            try {
                $stats['total_games'] = DB::table('matches')->count();
            } catch (\Exception $e) {
                $stats['total_games'] = 0;
                echo "خطا در شمارش بازی‌ها: " . $e->getMessage() . "\n";
            }
            
            // تعداد بازی‌های در جریان
            try {
                $stats['active_games'] = DB::table('matches')
                    ->where('status', 'active')
                    ->count();
            } catch (\Exception $e) {
                $stats['active_games'] = 0;
                echo "خطا در شمارش بازی‌های فعال: " . $e->getMessage() . "\n";
            }
            
            // اطلاعات امروز
            $today = date('Y-m-d');
            
            // تعداد بازی‌های انجام شده امروز
            try {
                $stats['games_today'] = DB::table('matches')
                    ->where('created_at', '>=', $today . ' 00:00:00')
                    ->count();
            } catch (\Exception $e) {
                $stats['games_today'] = 0;
                echo "خطا در شمارش بازی‌های امروز: " . $e->getMessage() . "\n";
            }
            
            // میانگین دلتا کوین‌ها
            try {
                $avg_dc = DB::table('users_extra')->avg('delta_coins');
                $stats['avg_deltacoins'] = round($avg_dc, 2);
            } catch (\Exception $e) {
                $stats['avg_deltacoins'] = 0;
                echo "خطا در محاسبه میانگین دلتا کوین‌ها: " . $e->getMessage() . "\n";
            }
            
            // تعداد بازیکنان جدید امروز
            try {
                $stats['new_users_today'] = DB::table('users')
                    ->where('created_at', '>=', $today . ' 00:00:00')
                    ->count();
            } catch (\Exception $e) {
                $stats['new_users_today'] = 0;
                echo "خطا در شمارش کاربران جدید: " . $e->getMessage() . "\n";
            }
            
            return [
                'success' => true,
                'message' => 'آمار ربات با موفقیت دریافت شد.',
                'stats' => $stats
            ];
            
        } catch (\Exception $e) {
            error_log("Error in getBotStats: " . $e->getMessage());
            echo "Error in getBotStats: " . $e->getMessage() . "\n";
            
            return [
                'success' => false,
                'message' => 'خطا در دریافت آمار ربات: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ارسال پیام همگانی به تمام کاربران
     * 
     * @param string $message متن پیام همگانی
     * @param bool $includeStats آیا آمار ربات در پیام همگانی نمایش داده شود
     * @return array نتیجه عملیات
     */
    public function broadcastMessage($message, $includeStats = false)
    {
        try {
            // بررسی دسترسی‌های ادمین
            if (!$this->isAdmin()) {
                return [
                    'success' => false,
                    'message' => 'شما دسترسی لازم برای ارسال پیام همگانی را ندارید.'
                ];
            }
            
            // دریافت لیست کاربران
            $users = DB::table('users')->select('id', 'telegram_id')->get();
            $sentCount = 0;
            $failedCount = 0;
            
            // ارسال پیام به هر کاربر
            foreach ($users as $user) {
                try {
                    // چک کردن آیدی تلگرام
                    if (empty($user['telegram_id'])) {
                        $failedCount++;
                        continue;
                    }
                    
                    // ارسال پیام
                    $this->sendTelegramMessage($user['telegram_id'], $message);
                    $sentCount++;
                    
                    // کمی تأخیر برای جلوگیری از محدودیت‌های تلگرام
                    usleep(200000); // 0.2 ثانیه تأخیر
                } catch (\Exception $e) {
                    $failedCount++;
                    error_log("Failed to send broadcast to {$user['telegram_id']}: " . $e->getMessage());
                }
            }
            
            // ثبت در لاگ سیستم
            echo "پیام همگانی به {$sentCount} کاربر ارسال شد. {$failedCount} پیام ناموفق.\n";
            
            return [
                'success' => true,
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'message' => "پیام با موفقیت به {$sentCount} کاربر ارسال شد."
            ];
            
        } catch (\Exception $e) {
            error_log("Error in broadcastMessage: " . $e->getMessage());
            echo "خطا در ارسال پیام همگانی: " . $e->getMessage() . "\n";
            
            return [
                'success' => false,
                'message' => "خطا در ارسال پیام همگانی: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * ارسال پیام تلگرام (متد کمکی)
     */
    private function sendTelegramMessage($chatId, $message, $keyboard = null)
    {
        // پارامترهای پایه
        $params = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ];
        
        // اضافه کردن کیبورد در صورت وجود
        if ($keyboard) {
            $params['reply_markup'] = $keyboard;
        }
        
        // ساخت URL برای API تلگرام
        $url = "https://api.telegram.org/bot" . $_ENV['TELEGRAM_TOKEN'] . "/sendMessage";
        
        // ارسال درخواست
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new \Exception('Curl error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        $result = json_decode($response, true);
        
        if (!$result['ok']) {
            throw new \Exception('Telegram API error: ' . ($result['description'] ?? 'Unknown error'));
        }
        
        return $result;
    }
}