<?php
namespace application\controllers;

use Application\Model\DB;

class StatisticsController
{
    private $telegram_id;
    
    public function __construct($telegram_id = null)
    {
        $this->telegram_id = $telegram_id;
    }
    
    /**
     * استخراج و ذخیره آمار روزانه
     * این متد را می‌توان به صورت خودکار در نیمه شب اجرا کرد
     * @return bool
     */
    public function collectDailyStatistics()
    {
        try {
            // تاریخ امروز
            $today = date('Y-m-d');
            $today_start = $today . ' 00:00:00';
            $today_end = $today . ' 23:59:59';
            
            // بررسی آیا آمار امروز قبلاً جمع‌آوری شده است
            $existing = DB::table('admin_statistics')
                ->where('date', $today)
                ->first();
                
            if ($existing) {
                // اگر آمار امروز قبلاً ثبت شده، آن را به‌روز کنیم
                return $this->updateDailyStatistics($today);
            }
            
            // دریافت آمار مورد نیاز از پایگاه داده
            
            // تعداد کل کاربران
            $total_users = DB::table('users')->count();
            
            // کاربران فعال امروز
            $active_users_today = DB::table('users')
                ->where('last_activity', '>=', $today_start)
                ->count();
                
            // تعداد کل بازی‌ها
            $total_games = DB::table('matches')->count();
            
            // بازی‌های امروز
            $games_today = DB::table('matches')
                ->where('created_at', '>=', $today_start)
                ->where('created_at', '<=', $today_end)
                ->count();
                
            // تعداد کاربران جدید امروز
            $new_users_today = DB::table('users')
                ->where('created_at', '>=', $today_start)
                ->where('created_at', '<=', $today_end)
                ->count();
                
            // میانگین دلتا کوین‌ها
            $avg_delta_coins = DB::rawQuery("SELECT AVG(delta_coins) as avg_coins FROM users_extra")[0]['avg_coins'] ?? 0;
            
            // جمع کل دلتا کوین‌های در گردش
            $total_delta_coins = DB::rawQuery("SELECT SUM(delta_coins) as total_coins FROM users_extra")[0]['total_coins'] ?? 0;
            
            // تعداد درخواست‌های برداشت
            $pending_withdrawals = DB::table('withdrawal_requests')
                ->where('status', 'pending')
                ->count();
                
            // مجموع مبلغ درخواست‌های برداشت
            $pending_withdrawals_amount = DB::rawQuery("SELECT SUM(amount) as total_amount FROM withdrawal_requests WHERE status = 'pending'")[0]['total_amount'] ?? 0;
            
            // ثبت آمار در دیتابیس
            $stats_data = [
                'date' => $today,
                'total_users' => $total_users,
                'active_users' => $active_users_today,
                'total_games' => $total_games,
                'games_today' => $games_today,
                'new_users' => $new_users_today,
                'avg_delta_coins' => round($avg_delta_coins, 2),
                'total_delta_coins' => $total_delta_coins,
                'pending_withdrawals' => $pending_withdrawals,
                'pending_withdrawals_amount' => $pending_withdrawals_amount
            ];
            
            DB::table('admin_statistics')->insert($stats_data);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("خطا در جمع‌آوری آمار روزانه: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * به‌روزرسانی آمار روزانه
     * @param string $date تاریخ مورد نظر (به فرمت Y-m-d)
     * @return bool
     */
    private function updateDailyStatistics($date)
    {
        try {
            $date_start = $date . ' 00:00:00';
            $date_end = $date . ' 23:59:59';
            
            // دریافت آمار به‌روز
            
            // تعداد کل کاربران
            $total_users = DB::table('users')->count();
            
            // کاربران فعال امروز
            $active_users_today = DB::table('users')
                ->where('last_activity', '>=', $date_start)
                ->count();
                
            // تعداد کل بازی‌ها
            $total_games = DB::table('matches')->count();
            
            // بازی‌های امروز
            $games_today = DB::table('matches')
                ->where('created_at', '>=', $date_start)
                ->where('created_at', '<=', $date_end)
                ->count();
                
            // تعداد کاربران جدید امروز
            $new_users_today = DB::table('users')
                ->where('created_at', '>=', $date_start)
                ->where('created_at', '<=', $date_end)
                ->count();
                
            // میانگین دلتا کوین‌ها
            $avg_delta_coins = DB::rawQuery("SELECT AVG(delta_coins) as avg_coins FROM users_extra")[0]['avg_coins'] ?? 0;
            
            // جمع کل دلتا کوین‌های در گردش
            $total_delta_coins = DB::rawQuery("SELECT SUM(delta_coins) as total_coins FROM users_extra")[0]['total_coins'] ?? 0;
            
            // تعداد درخواست‌های برداشت
            $pending_withdrawals = DB::table('withdrawal_requests')
                ->where('status', 'pending')
                ->count();
                
            // مجموع مبلغ درخواست‌های برداشت
            $pending_withdrawals_amount = DB::rawQuery("SELECT SUM(amount) as total_amount FROM withdrawal_requests WHERE status = 'pending'")[0]['total_amount'] ?? 0;
            
            // به‌روزرسانی آمار در دیتابیس
            $stats_data = [
                'total_users' => $total_users,
                'active_users' => $active_users_today,
                'total_games' => $total_games,
                'games_today' => $games_today,
                'new_users' => $new_users_today,
                'avg_delta_coins' => round($avg_delta_coins, 2),
                'total_delta_coins' => $total_delta_coins,
                'pending_withdrawals' => $pending_withdrawals,
                'pending_withdrawals_amount' => $pending_withdrawals_amount,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            DB::table('admin_statistics')
                ->where('date', $date)
                ->update($stats_data);
                
            return true;
            
        } catch (\Exception $e) {
            error_log("خطا در به‌روزرسانی آمار روزانه: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت آمار یک روز مشخص
     * @param string $date تاریخ مورد نظر (به فرمت Y-m-d)
     * @return array|null
     */
    public function getDailyStatistics($date)
    {
        try {
            $stats = DB::table('admin_statistics')
                ->where('date', $date)
                ->first();
                
            return $stats;
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت آمار روزانه: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * دریافت آمار بازه زمانی
     * @param string $start_date تاریخ شروع (به فرمت Y-m-d)
     * @param string $end_date تاریخ پایان (به فرمت Y-m-d)
     * @return array
     */
    public function getStatisticsRange($start_date, $end_date)
    {
        try {
            $stats = DB::table('admin_statistics')
                ->where('date', '>=', $start_date)
                ->where('date', '<=', $end_date)
                ->orderBy('date', 'ASC')
                ->get();
                
            return $stats;
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت آمار بازه زمانی: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار هفته اخیر
     * @return array
     */
    public function getLastWeekStatistics()
    {
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-7 days'));
        
        return $this->getStatisticsRange($start_date, $end_date);
    }
    
    /**
     * دریافت آمار ماه اخیر
     * @return array
     */
    public function getLastMonthStatistics()
    {
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        return $this->getStatisticsRange($start_date, $end_date);
    }
    
    /**
     * دریافت آمار عمومی برای ارائه به کاربران
     * @return array
     */
    public function getPublicStatistics()
    {
        try {
            // تعداد کل کاربران
            $total_users = DB::table('users')->count();
            
            // تعداد کل بازی‌ها
            $total_games = DB::table('matches')->count();
            
            // تعداد کاربران آنلاین (فعالیت در 10 دقیقه اخیر)
            $online_users = DB::table('users')
                ->where('last_activity', '>=', date('Y-m-d H:i:s', strtotime('-10 minutes')))
                ->count();
                
            // تعداد بازی‌های در حال انجام
            $active_games = DB::table('matches')
                ->where('status', 'in_progress')
                ->count();
                
            return [
                'total_users' => $total_users,
                'total_games' => $total_games,
                'online_users' => $online_users,
                'active_games' => $active_games
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت آمار عمومی: " . $e->getMessage());
            return [
                'total_users' => 0,
                'total_games' => 0,
                'online_users' => 0,
                'active_games' => 0
            ];
        }
    }
    
    /**
     * دریافت گزارش آماری برای پنل ادمین
     * @return array
     */
    public static function getAdminReport()
    {
        try {
            $today = date('Y-m-d');
            $today_start = $today . ' 00:00:00';
            $today_end = $today . ' 23:59:59';
            
            // تعداد کل کاربران
            $total_users = DB::table('users')->count();
            
            // کاربران فعال امروز
            $active_users_today = DB::table('users')
                ->where('last_activity', '>=', $today_start)
                ->count();
                
            // تعداد کل بازی‌ها
            $total_games = DB::table('matches')->count();
            
            // بازی‌های فعال
            $active_games = DB::table('matches')
                ->where('status', 'in_progress')
                ->count();
                
            // بازی‌های امروز
            $games_today = DB::table('matches')
                ->where('created_at', '>=', $today_start)
                ->where('created_at', '<=', $today_end)
                ->count();
                
            // میانگین دلتا کوین‌ها
            $avg_delta_coins = DB::rawQuery("SELECT AVG(delta_coins) as avg_coins FROM users_extra")[0]['avg_coins'] ?? 0;
            $avg_delta_coins = round($avg_delta_coins, 2);
            
            // جمع کل درآمد (مثال)
            $total_income = DB::rawQuery("SELECT SUM(amount) as total FROM delta_coin_transactions WHERE amount < 0")[0]['total'] ?? 0;
            $total_income = abs($total_income);
            
            // بررسی آیا آمار امروز در دیتابیس موجود است
            $stats_today = DB::table('admin_statistics')
                ->where('date', $today)
                ->first();
                
            // استفاده از آمار ذخیره شده در دیتابیس اگر موجود باشد
            if ($stats_today) {
                return [
                    'total_users' => $stats_today['total_users'],
                    'active_users_today' => $stats_today['active_users'],
                    'total_games' => $stats_today['total_games'],
                    'active_games' => $active_games,
                    'games_today' => $stats_today['games_today'],
                    'avg_delta_coins' => $stats_today['avg_delta_coins'],
                    'new_users' => $stats_today['new_users'],
                    'total_delta_coins' => $stats_today['total_delta_coins'],
                    'pending_withdrawals' => $stats_today['pending_withdrawals'],
                    'pending_withdrawals_amount' => $stats_today['pending_withdrawals_amount']
                ];
            }
            
            // اگر آمار امروز موجود نباشد، اطلاعات پایه را برمی‌گردانیم
            return [
                'total_users' => $total_users,
                'active_users_today' => $active_users_today,
                'total_games' => $total_games,
                'active_games' => $active_games,
                'games_today' => $games_today,
                'avg_delta_coins' => $avg_delta_coins
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت گزارش ادمین: " . $e->getMessage());
            return [
                'total_users' => 0,
                'active_users_today' => 0,
                'total_games' => 0,
                'active_games' => 0,
                'games_today' => 0,
                'avg_delta_coins' => 0
            ];
        }
    }
}