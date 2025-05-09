<?php
namespace application\controllers;
use Application\Model\DB;
use application\helpers\TelegramHelper;

class UserController extends HelperController
{
    private $telegram_id;
    public $is_ref=0;
    public function __construct($update , $ref_id=null)
    {
        $this->telegram_id = $this->getUserTelegramID($update);
        if ($this->telegram_id){
            if (!DB::table('users')->where('telegram_id', $this->telegram_id)->select('id')->get()) {

                if ($ref_id){
                    DB::table('users')->insert(['telegram_id' => $this->telegram_id]);
                    // اضافه کردن رکورد رفرال
                    $new_user_id = DB::table('users')->where('telegram_id', $this->telegram_id)->select('id')->first()['id'];
                    // اضافه کردن رکورد رفرال با فیلد started_rewarded=true
                    DB::table('referrals')->insert([
                        'referrer_id' => $ref_id, 
                        'referee_id' => $new_user_id, 
                        'created_at' => date('Y-m-d H:i:s'),
                        'started_rewarded' => true
                    ]);
                    
                    // اضافه کردن 0.5 دلتا کوین به ارجاع‌دهنده
                    try {
                        // دریافت موجودی فعلی کاربر ارجاع‌دهنده
                        $referrer_coins = DB::table('users_extra')
                            ->where('user_id', $ref_id)
                            ->value('delta_coins') ?? 0;
                        
                        // به‌روزرسانی موجودی
                        DB::table('users_extra')
                            ->where('user_id', $ref_id)
                            ->update(['delta_coins' => $referrer_coins + 0.5]);
                        
                        // دریافت اطلاعات تلگرام کاربر ارجاع‌دهنده برای اطلاع‌رسانی
                        $referrer_telegram_id = DB::table('users')
                            ->where('id', $ref_id)
                            ->value('telegram_id');
                        
                        if ($referrer_telegram_id) {
                            // ارسال پیام به کاربر ارجاع‌دهنده با استفاده از TelegramHelper
                            $new_balance = $referrer_coins + 0.5;
                            $message = "تبریک 🎉 یک نفر با لینک شما وارد ربات شد و شما 0.5 دلتاکوین دریافت کردید.\nچنانچه کاربر موردنظر اولین بُرد خود را در ربات ثبت کند 1.5 دلتاکوین دیگر دریافت خواهید کرد.\n\n💰موجودی جدید: {$new_balance} دلتاکوین";
                            TelegramHelper::sendMessage($referrer_telegram_id, $message);
                        }
                    } catch (\Exception $e) {
                        // در صورت خطا، ادامه روند ثبت‌نام
                        echo "خطا در پرداخت پاداش رفرال: " . $e->getMessage() . "\n";
                    }
                }else{
                    DB::table('users')->insert(['telegram_id' => $this->telegram_id]);
                }
                $id = DB::table('users')->where('telegram_id', $this->telegram_id)->select('id')->first()['id'];
                DB::table('users_extra')->insert(['user_id' => $id]);

                if ($ref_id){
                    $this->is_ref = '1';
                }

            }
            else{
                if ($ref_id){
                    $this->is_ref = 0;
                }
            }
            
            // اصلاح شده: استفاده از پارامتر به جای مقدار مستقیم در کوئری
            DB::rawQuery("UPDATE users 
SET updated_at = CURRENT_TIMESTAMP
WHERE telegram_id = ?;", [$this->telegram_id]);
        }
    }

    public function userData()
    {
        // اصلاح شده: بررسی معتبر بودن telegram_id
        if ($this->telegram_id) {
            return DB::table('users')->where(['telegram_id' => $this->telegram_id])->select('*')->first();
        }
        return null;
    }

    public function userExtra($userId)
    {
        // اصلاح شده: بررسی معتبر بودن userId
        if ($userId) {
            return DB::table('users_extra')->where(['user_id' => $userId])->select('*')->first();
        }
        return null;
    }

    public function userMatchesRank($userId)
    {
        // اصلاح شده: بررسی معتبر بودن userId
        if ($userId) {
            $x = DB::rawQuery('SELECT user_id, matches, user_rank FROM ( SELECT id, user_id, matches, RANK() OVER (ORDER BY matches DESC) AS user_rank FROM users_extra ) AS ranked_users WHERE user_id = ?;', [$userId]);
            if (!empty($x)) return $x[0];
        }
        return null;
    }

    public function userWinRateRank($userId)
    {
        // اصلاح شده: بررسی معتبر بودن userId
        if ($userId) {
            $x = DB::rawQuery('SELECT user_id, winRate, user_rank FROM ( SELECT user_id, (wins / matches) * 100 AS winRate, RANK() OVER (ORDER BY (wins / matches) DESC) AS user_rank FROM users_extra WHERE matches > 0 ) AS ranked_users WHERE user_id = ?;', [$userId]);
            if (!empty($x)) return $x[0];
        }
        return null;
    }

    public function userRank($userId)
    {
        // اصلاح شده: بررسی معتبر بودن userId
        if ($userId) {
            $x = DB::rawQuery('
            SELECT user_id, matches, winRate, match_rank, winRate_rank
            FROM (
                SELECT 
                    user_id, 
                    matches, 
                    (wins / matches) * 100 AS winRate, 
                    RANK() OVER (ORDER BY matches DESC) AS match_rank, 
                    RANK() OVER (ORDER BY (wins / matches) DESC) AS winRate_rank
                FROM users_extra
                WHERE matches > 0
            ) AS ranked_users
            WHERE user_id = ?;
        ', [$userId]);

            if (!empty($x)) return $x[0];
        }
        return null;
    }

    public function isAdmin(): bool
    {
        // اصلاح شده: بررسی معتبر بودن telegram_id
        if ($this->telegram_id) {
            $checkUser = DB::table('users')->where(['telegram_id' => $this->telegram_id])->select('*')->first();
            if ($checkUser){
                if ($checkUser['type'] == 'admin' or $checkUser['type'] == 'owner'){
                    return true;
                }
            }
        }
        return false;
    }
}