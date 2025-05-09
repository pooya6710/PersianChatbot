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
            if (!$this->isAdmin() || !$this->hasPermission('can_send_broadcasts')) {
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
     * بررسی دسترسی ادمین به یک قابلیت خاص
     * @param string $permission نام دسترسی
     * @return bool
     */
    public function hasPermission($permission)
    {
        try {
            // آیدی‌های تلگرام مدیران اصلی (دسترسی کامل دارند)
            $owner_ids = [286420965, 6739124921];
            
            // بررسی آیا جزو مدیران اصلی است
            if (in_array($this->user_id, $owner_ids)) {
                return true;
            }
            
            // دریافت اطلاعات کاربر و نوع آن
            $user = DB::table('users')
                ->where('telegram_id', $this->user_id)
                ->first();
                
            if (!$user) {
                return false;
            }
            
            // اگر مالک است، دسترسی کامل دارد
            if ($user['type'] === 'owner') {
                return true;
            }
            
            // اگر ادمین نیست، دسترسی ندارد
            if ($user['type'] !== 'admin') {
                return false;
            }
            
            // بررسی دسترسی های خاص در جدول admin_permissions
            try {
                $tableExists = DB::rawQuery("SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public'
                    AND table_name = 'admin_permissions'
                ) as exists");
                
                if ($tableExists[0]['exists']) {
                    $permissions = DB::table('admin_permissions')
                        ->where('user_id', $user['id'])
                        ->first();
                        
                    if ($permissions && isset($permissions['permissions'])) {
                        $permArray = json_decode($permissions['permissions'], true);
                        if (is_array($permArray) && isset($permArray[$permission]) && $permArray[$permission] === true) {
                            return true;
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("Error checking permissions: " . $e->getMessage());
            }
            
            // دسترسی‌های پیش‌فرض برای ادمین‌ها
            $default_permissions = [
                'can_view_stats' => true,
                'can_send_message' => true,
                'can_send_broadcasts' => true,
                'can_lock_usernames' => true,
                'can_manage_users' => true,
                'can_manage_bot_settings' => true,
                'can_lock_groups' => true,
                'can_view_server_status' => true
            ];
            
            return isset($default_permissions[$permission]) && $default_permissions[$permission] === true;
            
        } catch (\Exception $e) {
            error_log("Error in hasPermission check: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت لیست ادمین‌ها
     * @return array
     */
    public function getAdminsList()
    {
        try {
            if (!$this->isAdmin()) {
                return [
                    'success' => false,
                    'message' => 'شما دسترسی به این بخش را ندارید.'
                ];
            }
            
            // ایجاد جدول admin_permissions اگر وجود نداشت
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
            
            // دریافت لیست کاربران ادمین
            $admins = DB::table('users')
                ->where(function ($query) {
                    $query->where('type', 'admin')
                          ->orWhere('type', 'owner');
                })
                ->select('id', 'telegram_id', 'username', 'first_name', 'last_name', 'type')
                ->get();
            
            $result = [];
            
            // پردازش ادمین ها
            foreach ($admins as $admin) {
                $username = $admin['username'] ?? '';
                $firstName = $admin['first_name'] ?? '';
                $lastName = $admin['last_name'] ?? '';
                $name = trim("$firstName $lastName");
                
                if (empty($name)) {
                    $name = $username ? "@$username" : "Admin #" . $admin['id'];
                }
                
                // دریافت دسترسی‌های ادمین
                $permissions = [];
                try {
                    $perms = DB::table('admin_permissions')
                        ->where('user_id', $admin['id'])
                        ->first();
                    
                    if ($perms && isset($perms['permissions'])) {
                        $permissions = json_decode($perms['permissions'], true) ?: [];
                    }
                } catch (\Exception $e) {
                    error_log("Error loading permissions: " . $e->getMessage());
                }
                
                $result[] = [
                    'id' => $admin['id'],
                    'telegram_id' => $admin['telegram_id'],
                    'username' => $username,
                    'name' => $name,
                    'is_owner' => $admin['type'] === 'owner',
                    'permissions' => $permissions
                ];
            }
            
            // اضافه کردن ادمین های اصلی
            $owner_ids = [286420965, 6739124921]; // مالکین اصلی ربات
            foreach ($owner_ids as $owner_id) {
                // بررسی آیا در لیست قبلی نیست
                $exists = false;
                foreach ($result as $admin) {
                    if ($admin['telegram_id'] == $owner_id) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    try {
                        // دریافت اطلاعات کاربر از تلگرام
                        $owner = DB::table('users')
                            ->where('telegram_id', $owner_id)
                            ->first();
                        
                        if ($owner) {
                            $username = $owner['username'] ?? '';
                            $firstName = $owner['first_name'] ?? '';
                            $lastName = $owner['last_name'] ?? '';
                            $name = trim("$firstName $lastName");
                            
                            if (empty($name)) {
                                $name = $username ? "@$username" : "Super Admin";
                            }
                            
                            $result[] = [
                                'id' => $owner['id'] ?? 0,
                                'telegram_id' => $owner_id,
                                'username' => $username,
                                'name' => $name,
                                'is_owner' => true,
                                'permissions' => []
                            ];
                        }
                    } catch (\Exception $e) {
                        error_log("Error loading owner: " . $e->getMessage());
                    }
                }
            }
            
            return [
                'success' => true,
                'message' => 'لیست ادمین‌ها با موفقیت دریافت شد.',
                'admins' => $result
            ];
            
        } catch (\Exception $e) {
            error_log("Error in getAdminsList: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در دریافت لیست ادمین‌ها: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * اضافه کردن ادمین جدید
     * @param string $identifier شناسه کاربر (آیدی تلگرام یا نام کاربری)
     * @return array
     */
    public function addAdmin($identifier)
    {
        try {
            if (!$this->isAdmin()) {
                return [
                    'success' => false,
                    'message' => 'شما دسترسی به این بخش را ندارید.'
                ];
            }
            
            // حذف @ از ابتدای نام کاربری
            if (substr($identifier, 0, 1) === '@') {
                $identifier = substr($identifier, 1);
            }
            
            // جستجوی کاربر بر اساس آیدی تلگرام یا نام کاربری
            $user = null;
            if (is_numeric($identifier)) {
                $user = DB::table('users')
                    ->where('telegram_id', $identifier)
                    ->first();
            } else {
                $user = DB::table('users')
                    ->where('username', $identifier)
                    ->first();
            }
            
            // بررسی وجود کاربر
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر مورد نظر یافت نشد. لطفاً آیدی تلگرام یا نام کاربری صحیح را وارد کنید.'
                ];
            }
            
            // بررسی آیا کاربر قبلاً ادمین است
            if ($user['type'] === 'admin' || $user['type'] === 'owner') {
                return [
                    'success' => false,
                    'message' => 'کاربر مورد نظر در حال حاضر ادمین است.'
                ];
            }
            
            // تغییر نوع کاربر به ادمین
            DB::table('users')
                ->where('id', $user['id'])
                ->update(['type' => 'admin']);
                
            // نام کاربر برای نمایش
            $username = $user['username'] ?? '';
            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            
            if (empty($name)) {
                $name = $username ? "@$username" : "کاربر #" . $user['id'];
            }
            
            return [
                'success' => true,
                'message' => "✅ کاربر «$name» با موفقیت به عنوان ادمین افزوده شد.",
                'user' => [
                    'id' => $user['id'],
                    'telegram_id' => $user['telegram_id'],
                    'username' => $username,
                    'name' => $name
                ]
            ];
            
        } catch (\Exception $e) {
            error_log("Error in addAdmin: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در افزودن ادمین: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * حذف ادمین
     * @param string $identifier شناسه کاربر (آیدی تلگرام یا نام کاربری)
     * @return array
     */
    public function removeAdmin($identifier)
    {
        try {
            if (!$this->isAdmin()) {
                return [
                    'success' => false,
                    'message' => 'شما دسترسی به این بخش را ندارید.'
                ];
            }
            
            // حذف @ از ابتدای نام کاربری
            if (substr($identifier, 0, 1) === '@') {
                $identifier = substr($identifier, 1);
            }
            
            // جستجوی کاربر بر اساس آیدی تلگرام یا نام کاربری
            $user = null;
            if (is_numeric($identifier)) {
                $user = DB::table('users')
                    ->where('telegram_id', $identifier)
                    ->first();
            } else {
                $user = DB::table('users')
                    ->where('username', $identifier)
                    ->first();
            }
            
            // بررسی وجود کاربر
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر مورد نظر یافت نشد. لطفاً آیدی تلگرام یا نام کاربری صحیح را وارد کنید.'
                ];
            }
            
            // بررسی آیا کاربر ادمین اصلی است
            $owner_ids = [286420965, 6739124921];
            if (in_array($user['telegram_id'], $owner_ids)) {
                return [
                    'success' => false,
                    'message' => 'امکان حذف ادمین اصلی وجود ندارد.'
                ];
            }
            
            // بررسی آیا کاربر ادمین است
            if ($user['type'] !== 'admin' && $user['type'] !== 'owner') {
                return [
                    'success' => false,
                    'message' => 'کاربر مورد نظر ادمین نیست.'
                ];
            }
            
            // تغییر نوع کاربر به کاربر عادی
            DB::table('users')
                ->where('id', $user['id'])
                ->update(['type' => 'user']);
                
            // نام کاربر برای نمایش
            $username = $user['username'] ?? '';
            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            
            if (empty($name)) {
                $name = $username ? "@$username" : "کاربر #" . $user['id'];
            }
            
            return [
                'success' => true,
                'message' => "✅ کاربر «$name» با موفقیت از لیست ادمین‌ها حذف شد.",
                'user' => [
                    'id' => $user['id'],
                    'telegram_id' => $user['telegram_id'],
                    'username' => $username,
                    'name' => $name
                ]
            ];
            
        } catch (\Exception $e) {
            error_log("Error in removeAdmin: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در حذف ادمین: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * بررسی وضعیت فعال بودن ربات
     * @return bool وضعیت فعال بودن ربات
     */
    public function isBotActive()
    {
        try {
            // بررسی وجود جدول options
            $tableExists = DB::rawQuery("SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public'
                AND table_name = 'options'
            ) as exists");
            
            if (!$tableExists[0]['exists']) {
                DB::rawQuery("
                    CREATE TABLE IF NOT EXISTS options (
                        id SERIAL PRIMARY KEY,
                        option_name VARCHAR(100) NOT NULL UNIQUE,
                        option_value TEXT,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP
                    )
                ");
                
                // مقداردهی اولیه گزینه‌ها
                DB::rawQuery("
                    INSERT INTO options (option_name, option_value)
                    VALUES ('bot_active', 'true')
                    ON CONFLICT (option_name) DO NOTHING
                ");
                
                return true;
            }
            
            // دریافت مقدار گزینه
            $option = DB::table('options')
                ->where('option_name', 'bot_active')
                ->first();
                
            if (!$option) {
                // اگر گزینه وجود نداشت، آن را ایجاد کنیم
                DB::table('options')->insert([
                    'option_name' => 'bot_active',
                    'option_value' => 'true',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                return true;
            }
            
            return $option['option_value'] === 'true';
            
        } catch (\Exception $e) {
            error_log("Error in isBotActive: " . $e->getMessage());
            // در صورت خطا، فرض می‌کنیم ربات فعال است
            return true;
        }
    }
    
    /**
     * تغییر وضعیت فعال بودن ربات
     * @param bool $active وضعیت جدید
     * @return bool موفقیت عملیات
     */
    public function setBotActive($active)
    {
        try {
            // بررسی دسترسی ادمین
            if (!$this->isAdmin() || !$this->hasPermission('can_manage_bot_settings')) {
                return false;
            }
            
            // بررسی وجود جدول options
            $tableExists = DB::rawQuery("SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public'
                AND table_name = 'options'
            ) as exists");
            
            if (!$tableExists[0]['exists']) {
                DB::rawQuery("
                    CREATE TABLE IF NOT EXISTS options (
                        id SERIAL PRIMARY KEY,
                        option_name VARCHAR(100) NOT NULL UNIQUE,
                        option_value TEXT,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP
                    )
                ");
            }
            
            // بررسی آیا گزینه وجود دارد
            $optionExists = DB::table('options')
                ->where('option_name', 'bot_active')
                ->exists();
                
            if ($optionExists) {
                // آپدیت گزینه
                DB::table('options')
                    ->where('option_name', 'bot_active')
                    ->update([
                        'option_value' => $active ? 'true' : 'false',
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                // ایجاد گزینه
                DB::table('options')->insert([
                    'option_name' => 'bot_active',
                    'option_value' => $active ? 'true' : 'false',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Error in setBotActive: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * فوروارد یک پیام به تمام کاربران ربات
     * 
     * @param int $from_chat_id آیدی چت مبدأ
     * @param int $message_id آیدی پیام برای فوروارد
     * @return array نتیجه عملیات
     */
    public function forwardMessageToAll($from_chat_id, $message_id)
    {
        try {
            // بررسی دسترسی‌های ادمین
            if (!$this->isAdmin() || !$this->hasPermission('can_send_broadcasts')) {
                return [
                    'success' => false,
                    'message' => 'شما دسترسی لازم برای فوروارد همگانی را ندارید.'
                ];
            }
            
            // دریافت لیست کاربران
            $users = DB::table('users')->select('id', 'telegram_id')->get();
            $sentCount = 0;
            $failedCount = 0;
            
            // فوروارد پیام به هر کاربر
            foreach ($users as $user) {
                try {
                    // چک کردن آیدی تلگرام
                    if (empty($user['telegram_id'])) {
                        $failedCount++;
                        continue;
                    }
                    
                    // فوروارد پیام
                    $url = "https://api.telegram.org/bot" . $_ENV['TELEGRAM_TOKEN'] . "/forwardMessage";
                    $params = [
                        'chat_id' => $user['telegram_id'],
                        'from_chat_id' => $from_chat_id,
                        'message_id' => $message_id,
                        'disable_notification' => false
                    ];
                    
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $response = curl_exec($ch);
                    $result = json_decode($response, true);
                    curl_close($ch);
                    
                    if ($result && $result['ok']) {
                        $sentCount++;
                    } else {
                        $failedCount++;
                        $error = isset($result['description']) ? $result['description'] : 'خطای نامشخص';
                        error_log("Failed to forward message to {$user['telegram_id']}: {$error}");
                    }
                    
                    // کمی تأخیر برای جلوگیری از محدودیت‌های تلگرام
                    usleep(200000); // 0.2 ثانیه تأخیر
                } catch (\Exception $e) {
                    $failedCount++;
                    error_log("Failed to forward message to {$user['telegram_id']}: " . $e->getMessage());
                }
            }
            
            // ثبت در لاگ سیستم
            echo "پیام همگانی به {$sentCount} کاربر فوروارد شد. {$failedCount} پیام ناموفق.\n";
            
            return [
                'success' => true,
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'message' => "پیام با موفقیت به {$sentCount} کاربر فوروارد شد."
            ];
            
        } catch (\Exception $e) {
            error_log("Error in forwardMessageToAll: " . $e->getMessage());
            echo "خطا در فوروارد پیام همگانی: " . $e->getMessage() . "\n";
            
            return [
                'success' => false,
                'message' => "خطا در فوروارد پیام همگانی: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * قفل کردن یک نام کاربری خاص
     * 
     * @param string $username نام کاربری برای قفل کردن
     * @return array نتیجه عملیات
     */
    public function lockUsername($username)
    {
        try {
            // بررسی دسترسی‌های ادمین
            if (!$this->isAdmin() || !$this->hasPermission('can_lock_usernames')) {
                return [
                    'success' => false,
                    'message' => 'شما دسترسی لازم برای قفل کردن نام کاربری را ندارید.'
                ];
            }
            
            // حذف @ از ابتدای نام کاربری اگر وجود داشت
            if (substr($username, 0, 1) === '@') {
                $username = substr($username, 1);
            }
            
            // تمیز کردن نام کاربری
            $username = trim(strtolower($username));
            
            // بررسی اعتبار نام کاربری
            if (!preg_match('/^[a-z0-9_]{5,32}$/', $username)) {
                return [
                    'success' => false,
                    'message' => 'نام کاربری وارد شده معتبر نیست. نام کاربری باید شامل حروف انگلیسی، اعداد و _ باشد (حداقل 5 و حداکثر 32 کاراکتر).'
                ];
            }
            
            // اطمینان از وجود جدول locked_usernames
            $this->initializeRequiredTables();
            $tableExists = DB::rawQuery("SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public'
                AND table_name = 'locked_usernames'
            ) as exists");
            
            if (!$tableExists[0]['exists']) {
                DB::rawQuery("
                    CREATE TABLE IF NOT EXISTS locked_usernames (
                        id SERIAL PRIMARY KEY,
                        username VARCHAR(32) UNIQUE NOT NULL,
                        locked_by INTEGER NOT NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    )
                ");
            }
            
            // بررسی آیا این نام کاربری قبلاً قفل شده است
            $existingLock = DB::table('locked_usernames')
                ->where('username', $username)
                ->first();
                
            if ($existingLock) {
                return [
                    'success' => false,
                    'message' => "نام کاربری «{$username}» قبلاً قفل شده است."
                ];
            }
            
            // قفل کردن نام کاربری
            DB::table('locked_usernames')->insert([
                'username' => $username,
                'locked_by' => $this->user_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'message' => "✅ نام کاربری «{$username}» با موفقیت قفل شد."
            ];
            
        } catch (\Exception $e) {
            error_log("Error in lockUsername: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "خطا در قفل کردن نام کاربری: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * قفل کردن یک گروه یا کانال
     * 
     * @param string $chatId آیدی یا لینک گروه/کانال
     * @param string $type نوع (گروه یا کانال)
     * @return array نتیجه عملیات
     */
    public function lockChat($chatId, $type = 'group')
    {
        try {
            // بررسی دسترسی‌های ادمین
            if (!$this->isAdmin() || !$this->hasPermission('can_lock_groups')) {
                return [
                    'success' => false,
                    'message' => 'شما دسترسی لازم برای قفل کردن گروه/کانال را ندارید.'
                ];
            }
            
            // تمیز کردن آیدی چت
            $chatId = trim($chatId);
            
            // اگر لینک است، استخراج آیدی
            if (strpos($chatId, 'https://t.me/') === 0) {
                $chatId = str_replace('https://t.me/', '', $chatId);
            }
            if (strpos($chatId, '@') === 0) {
                $chatId = substr($chatId, 1);
            }
            
            // اطمینان از وجود جدول locked_chats
            $this->initializeRequiredTables();
            $tableExists = DB::rawQuery("SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public'
                AND table_name = 'locked_chats'
            ) as exists");
            
            if (!$tableExists[0]['exists']) {
                DB::rawQuery("
                    CREATE TABLE IF NOT EXISTS locked_chats (
                        id SERIAL PRIMARY KEY,
                        chat_id VARCHAR(255) NOT NULL,
                        chat_type VARCHAR(20) NOT NULL,
                        locked_by INTEGER NOT NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE(chat_id)
                    )
                ");
            }
            
            // بررسی آیا این گروه/کانال قبلاً قفل شده است
            $existingLock = DB::table('locked_chats')
                ->where('chat_id', $chatId)
                ->first();
                
            if ($existingLock) {
                return [
                    'success' => false,
                    'message' => "این " . ($type == 'channel' ? 'کانال' : 'گروه') . " قبلاً قفل شده است."
                ];
            }
            
            // قفل کردن گروه/کانال
            DB::table('locked_chats')->insert([
                'chat_id' => $chatId,
                'chat_type' => $type,
                'locked_by' => $this->user_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'message' => "✅ " . ($type == 'channel' ? 'کانال' : 'گروه') . " با موفقیت قفل شد."
            ];
            
        } catch (\Exception $e) {
            error_log("Error in lockChat: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "خطا در قفل کردن " . ($type == 'channel' ? 'کانال' : 'گروه') . ": " . $e->getMessage()
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