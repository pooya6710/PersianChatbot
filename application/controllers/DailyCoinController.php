<?php
namespace application\controllers;

require_once __DIR__ . '/../Model/DB.php';

use Application\Model\DB;

/**
 * کلاس مدیریت دلتا کوین روزانه
 */
class DailyCoinController
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
    }
    
    /**
     * بررسی امکان دریافت دلتا کوین روزانه
     * @return array
     */
    public function checkDailyCoin()
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->user_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // بررسی عضویت در کانال‌های اسپانسر
            $sponsoredChannels = $this->getSponsoredChannels();
            $nonMemberChannels = [];
            
            foreach ($sponsoredChannels as $channel) {
                if (!$this->isChannelMember($user['telegram_id'], $channel['chat_id'])) {
                    $nonMemberChannels[] = $channel;
                }
            }
            
            if (!empty($nonMemberChannels)) {
                return [
                    'success' => false,
                    'message' => 'برای دریافت دلتا کوین روزانه، باید عضو کانال‌های اسپانسر باشید.',
                    'channels' => $nonMemberChannels
                ];
            }
            
            // بررسی آخرین دریافت دلتا کوین روزانه
            $lastClaim = DB::table('daily_coin_claims')
                ->where('user_id', $user['id'])
                ->orderBy('created_at', 'DESC')
                ->first();
                
            // بررسی محدودیت روزانه
            if ($lastClaim) {
                $lastClaimDate = date('Y-m-d', strtotime($lastClaim['created_at']));
                $today = date('Y-m-d');
                
                if ($lastClaimDate === $today) {
                    // کاربر امروز قبلاً دریافت کرده است
                    $nextClaimDate = date('Y-m-d', strtotime('+1 day'));
                    $hoursRemaining = 24 - (int)date('H');
                    $minutesRemaining = 60 - (int)date('i');
                    
                    return [
                        'success' => false,
                        'message' => "شما امروز قبلاً دلتا کوین روزانه خود را دریافت کرده‌اید. لطفاً فردا مجدداً تلاش کنید.\n\nزمان باقی‌مانده: {$hoursRemaining} ساعت و {$minutesRemaining} دقیقه",
                        'next_claim' => $nextClaimDate,
                        'already_claimed' => true
                    ];
                }
            }
            
            // تعیین مقدار دلتا کوین روزانه
            $amount = $this->getDailyAmount($user['id']);
            
            return [
                'success' => true,
                'message' => 'شما می‌توانید دلتا کوین روزانه خود را دریافت کنید.',
                'amount' => $amount,
                'can_claim' => true
            ];
        } catch (\Exception $e) {
            error_log("Error in checkDailyCoin: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در بررسی دلتا کوین روزانه: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * دریافت دلتا کوین روزانه
     * @return array
     */
    public function claimDailyCoin()
    {
        try {
            // بررسی امکان دریافت
            $checkResult = $this->checkDailyCoin();
            
            if (!$checkResult['success']) {
                return $checkResult;
            }
            
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->user_id)
                ->first();
                
            // دریافت اطلاعات اضافی کاربر
            $userExtra = DB::table('users_extra')
                ->where('user_id', $user['id'])
                ->first();
                
            if (!$userExtra) {
                // ایجاد رکورد جدید
                DB::table('users_extra')->insert([
                    'user_id' => $user['id'],
                    'delta_coins' => 0,
                    'trophies' => 0,
                    'wins' => 0,
                    'total_games' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                $userExtra = [
                    'user_id' => $user['id'],
                    'delta_coins' => 0,
                    'trophies' => 0,
                    'wins' => 0,
                    'total_games' => 0
                ];
            }
            
            // افزودن دلتا کوین
            $amount = $checkResult['amount'];
            $new_balance = $userExtra['delta_coins'] + $amount;
            
            $result = DB::table('users_extra')
                ->where('user_id', $user['id'])
                ->update([
                    'delta_coins' => $new_balance,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'خطا در افزودن دلتا کوین.'
                ];
            }
            
            // ثبت دریافت دلتا کوین روزانه
            DB::table('daily_coin_claims')->insert([
                'user_id' => $user['id'],
                'amount' => $amount,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // ثبت تراکنش
            DB::table('transactions')->insert([
                'user_id' => $user['id'],
                'amount' => $amount,
                'description' => 'دریافت دلتا کوین روزانه',
                'transaction_type' => 'daily_reward',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // به‌روزرسانی روزهای متوالی دریافت
            $this->updateConsecutiveDays($user['id']);
            
            return [
                'success' => true,
                'message' => 'دلتا کوین روزانه با موفقیت دریافت شد.',
                'amount' => $amount,
                'new_balance' => $new_balance
            ];
        } catch (\Exception $e) {
            error_log("Error in claimDailyCoin: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در دریافت دلتا کوین روزانه: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * دریافت کانال‌های اسپانسر
     * @return array
     */
    private function getSponsoredChannels()
    {
        try {
            // دریافت کانال‌های اسپانسر از دیتابیس
            $channels = DB::table('sponsored_channels')
                ->where('is_active', true)
                ->get();
                
            if (empty($channels)) {
                // کانال پیش‌فرض
                return [];
            }
            
            return $channels;
        } catch (\Exception $e) {
            error_log("Error in getSponsoredChannels: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * بررسی عضویت در کانال
     * @param int $user_id شناسه کاربر
     * @param string $chat_id شناسه چت کانال
     * @return bool
     */
    private function isChannelMember($user_id, $chat_id)
    {
        try {
            if (!function_exists('getChatMember')) {
                // اگر تابع API تلگرام وجود نداشت، فرض می‌کنیم عضو است
                return true;
            }
            
            $result = getChatMember($GLOBALS['token'], $chat_id, $user_id);
            
            if (isset($result['ok']) && $result['ok']) {
                $status = $result['result']['status'] ?? '';
                return in_array($status, ['creator', 'administrator', 'member']);
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Error in isChannelMember: " . $e->getMessage());
            return true; // در صورت خطا فرض می‌کنیم عضو است تا مشکلی برای کاربر پیش نیاید
        }
    }
    
    /**
     * تعیین مقدار دلتا کوین روزانه
     * @param int $user_id شناسه کاربر
     * @return int
     */
    private function getDailyAmount($user_id)
    {
        try {
            // بررسی روزهای متوالی دریافت
            $consecutiveDays = $this->getConsecutiveDays($user_id);
            
            // پایه دلتا کوین روزانه
            $baseAmount = 5;
            
            // افزایش براساس روزهای متوالی
            if ($consecutiveDays >= 30) {
                return $baseAmount + 10; // 15 دلتا کوین برای 30 روز متوالی یا بیشتر
            } elseif ($consecutiveDays >= 15) {
                return $baseAmount + 5; // 10 دلتا کوین برای 15 روز متوالی یا بیشتر
            } elseif ($consecutiveDays >= 7) {
                return $baseAmount + 3; // 8 دلتا کوین برای 7 روز متوالی یا بیشتر
            } elseif ($consecutiveDays >= 3) {
                return $baseAmount + 1; // 6 دلتا کوین برای 3 روز متوالی یا بیشتر
            }
            
            return $baseAmount; // 5 دلتا کوین برای کمتر از 3 روز متوالی
        } catch (\Exception $e) {
            error_log("Error in getDailyAmount: " . $e->getMessage());
            return 5; // مقدار پیش‌فرض در صورت خطا
        }
    }
    
    /**
     * دریافت تعداد روزهای متوالی دریافت
     * @param int $user_id شناسه کاربر
     * @return int
     */
    private function getConsecutiveDays($user_id)
    {
        try {
            // دریافت اطلاعات کاربر
            $userExtra = DB::table('users_extra')
                ->where('user_id', $user_id)
                ->first();
                
            if (!$userExtra) {
                return 0;
            }
            
            return $userExtra['consecutive_days'] ?? 0;
        } catch (\Exception $e) {
            error_log("Error in getConsecutiveDays: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * به‌روزرسانی روزهای متوالی دریافت
     * @param int $user_id شناسه کاربر
     * @return void
     */
    private function updateConsecutiveDays($user_id)
    {
        try {
            // دریافت آخرین دریافت دلتا کوین روزانه
            $claims = DB::table('daily_coin_claims')
                ->where('user_id', $user_id)
                ->orderBy('created_at', 'DESC')
                ->limit(2)
                ->get();
                
            // دریافت اطلاعات اضافی کاربر
            $userExtra = DB::table('users_extra')
                ->where('user_id', $user_id)
                ->first();
                
            if (!$userExtra) {
                return;
            }
            
            $consecutiveDays = $userExtra['consecutive_days'] ?? 0;
            
            // بررسی روز قبل
            if (count($claims) > 1) {
                $lastClaimDate = date('Y-m-d', strtotime($claims[1]['created_at']));
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                
                if ($lastClaimDate === $yesterday) {
                    // کاربر روز قبل هم دریافت کرده است
                    $consecutiveDays++;
                } else {
                    // تسلسل قطع شده است
                    $consecutiveDays = 1;
                }
            } else {
                // اولین دریافت
                $consecutiveDays = 1;
            }
            
            // به‌روزرسانی روزهای متوالی
            DB::table('users_extra')
                ->where('user_id', $user_id)
                ->update([
                    'consecutive_days' => $consecutiveDays,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } catch (\Exception $e) {
            error_log("Error in updateConsecutiveDays: " . $e->getMessage());
        }
    }
}