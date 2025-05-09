<?php
namespace application\controllers;

require_once __DIR__ . '/../Model/DB.php';

use Application\Model\DB;

/**
 * کلاس مدیریت بازی و مچ‌میکینگ
 */
class MatchController
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
     * جستجوی حریف
     * @return array
     */
    public function findOpponent()
    {
        try {
            // دریافت اطلاعات کاربر فعلی
            $user = DB::table('users')
                ->where('telegram_id', $this->user_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // دریافت تعداد جام‌های کاربر
            $user_trophies = $user['trophies'] ?? 0;
            
            // محدوده جستجو (۲۰٪ بالا و پایین تعداد جام‌های کاربر)
            $min_trophies = max(0, $user_trophies * 0.8);
            $max_trophies = $user_trophies * 1.2;
            
            // جستجوی کاربران در صف انتظار با تعداد جام مشابه
            $waiting_user = DB::table('matchmaking_queue')
                ->where('user_id', '!=', $user['id'])
                ->where('status', 'waiting')
                ->where('trophies', '>=', $min_trophies)
                ->where('trophies', '<=', $max_trophies)
                ->orderBy('created_at', 'asc')
                ->first();
                
            if ($waiting_user) {
                // حریف پیدا شد، ایجاد بازی
                $match_id = $this->createMatch($user['id'], $waiting_user['user_id']);
                
                if (!$match_id) {
                    return [
                        'success' => false,
                        'message' => 'خطا در ایجاد بازی.'
                    ];
                }
                
                // حذف حریف از صف انتظار
                DB::table('matchmaking_queue')
                    ->where('user_id', $waiting_user['user_id'])
                    ->delete();
                    
                // دریافت اطلاعات حریف
                $opponent = DB::table('users')
                    ->where('id', $waiting_user['user_id'])
                    ->first();
                    
                return [
                    'success' => true,
                    'message' => 'حریف پیدا شد.',
                    'match_id' => $match_id,
                    'opponent' => $opponent,
                    'is_queue' => false
                ];
            } else {
                // حریفی پیدا نشد، افزودن به صف انتظار
                
                // بررسی وجود کاربر در صف انتظار
                $existing_queue = DB::table('matchmaking_queue')
                    ->where('user_id', $user['id'])
                    ->first();
                    
                if ($existing_queue) {
                    // به‌روزرسانی زمان درخواست
                    DB::table('matchmaking_queue')
                        ->where('user_id', $user['id'])
                        ->update([
                            'updated_at' => date('Y-m-d H:i:s'),
                            'status' => 'waiting'
                        ]);
                } else {
                    // افزودن به صف انتظار
                    DB::table('matchmaking_queue')->insert([
                        'user_id' => $user['id'],
                        'trophies' => $user_trophies,
                        'status' => 'waiting',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                return [
                    'success' => true,
                    'message' => 'در صف انتظار قرار گرفتید. در حال جستجوی حریف...',
                    'is_queue' => true
                ];
            }
        } catch (\Exception $e) {
            error_log("Error in findOpponent: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در جستجوی حریف: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * لغو جستجوی حریف
     * @return array
     */
    public function cancelMatchmaking()
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
            
            // حذف از صف انتظار
            $result = DB::table('matchmaking_queue')
                ->where('user_id', $user['id'])
                ->delete();
                
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'شما در صف انتظار نیستید.'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'جستجوی حریف لغو شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in cancelMatchmaking: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در لغو جستجوی حریف: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ایجاد بازی جدید
     * @param int $player1_id شناسه بازیکن اول
     * @param int $player2_id شناسه بازیکن دوم
     * @return int|false
     */
    private function createMatch($player1_id, $player2_id)
    {
        try {
            // تعیین اولویت حرکت (تصادفی)
            $first_move = (rand(0, 1) == 1) ? $player1_id : $player2_id;
            
            // ایجاد بازی
            $match_id = DB::table('matches')->insert([
                'player1' => $player1_id,
                'player2' => $player2_id,
                'current_player' => $first_move,
                'status' => 'active',
                'board' => json_encode(array_fill(0, 9, null)), // تخته ۳×۳ خالی برای بازی XO
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'last_action_time' => date('Y-m-d H:i:s'), // زمان آخرین کنش
                'chat_enabled' => true, // چت فعال باشد
                'chat_end_time' => null // زمان پایان چت (فعلاً خالی)
            ]);
            
            return $match_id;
        } catch (\Exception $e) {
            error_log("Error in createMatch: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ارسال درخواست بازی به دوست
     * @param int $friend_id شناسه دوست
     * @return array
     */
    public function sendGameRequest($friend_id)
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
            
            // دریافت اطلاعات دوست
            $friend = DB::table('users')
                ->where('id', $friend_id)
                ->first();
                
            if (!$friend) {
                return [
                    'success' => false,
                    'message' => 'کاربر دوست یافت نشد.'
                ];
            }
            
            // بررسی وجود دوستی
            $friendship = DB::table('friendships')
                ->where(function ($query) use ($user, $friend) {
                    $query->where('user_id_1', $user['id'])
                        ->where('user_id_2', $friend['id']);
                })
                ->orWhere(function ($query) use ($user, $friend) {
                    $query->where('user_id_1', $friend['id'])
                        ->where('user_id_2', $user['id']);
                })
                ->first();
                
            if (!$friendship) {
                return [
                    'success' => false,
                    'message' => 'شما و این کاربر دوست نیستید.'
                ];
            }
            
            // بررسی درخواست‌های قبلی
            $existing_request = DB::table('game_requests')
                ->where('sender_id', $user['id'])
                ->where('receiver_id', $friend['id'])
                ->where('status', 'pending')
                ->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-1 hour')))
                ->first();
                
            if ($existing_request) {
                return [
                    'success' => false,
                    'message' => 'شما در یک ساعت گذشته به این کاربر درخواست بازی ارسال کرده‌اید که هنوز پاسخ داده نشده است.'
                ];
            }
            
            // ایجاد درخواست بازی
            $request_id = DB::table('game_requests')->insert([
                'sender_id' => $user['id'],
                'receiver_id' => $friend['id'],
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$request_id) {
                return [
                    'success' => false,
                    'message' => 'خطا در ارسال درخواست بازی.'
                ];
            }
            
            // ارسال اعلان به دوست
            $this->sendGameRequestNotification($user, $friend, $request_id);
            
            return [
                'success' => true,
                'message' => 'درخواست بازی با موفقیت ارسال شد. پس از پذیرش به شما اطلاع داده خواهد شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in sendGameRequest: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ارسال درخواست بازی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * پذیرش درخواست بازی
     * @param int $request_id شناسه درخواست
     * @return array
     */
    public function acceptGameRequest($request_id)
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
            
            // دریافت اطلاعات درخواست
            $request = DB::table('game_requests')
                ->where('id', $request_id)
                ->first();
                
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'درخواست بازی یافت نشد.'
                ];
            }
            
            // بررسی اینکه درخواست برای کاربر فعلی باشد
            if ($request['receiver_id'] != $user['id']) {
                return [
                    'success' => false,
                    'message' => 'شما مجاز به پذیرش این درخواست بازی نیستید.'
                ];
            }
            
            // بررسی وضعیت درخواست
            if ($request['status'] !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'این درخواست بازی قبلاً پذیرفته یا رد شده است.'
                ];
            }
            
            // به‌روزرسانی وضعیت درخواست
            DB::table('game_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'accepted',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            // دریافت اطلاعات فرستنده درخواست
            $sender = DB::table('users')
                ->where('id', $request['sender_id'])
                ->first();
                
            if (!$sender) {
                return [
                    'success' => false,
                    'message' => 'اطلاعات فرستنده درخواست یافت نشد.'
                ];
            }
            
            // ایجاد بازی جدید
            $match_id = $this->createMatch($request['sender_id'], $user['id']);
            
            if (!$match_id) {
                return [
                    'success' => false,
                    'message' => 'خطا در ایجاد بازی.'
                ];
            }
            
            // ارسال اعلان به فرستنده
            $this->sendGameAcceptedNotification($user, $sender, $match_id);
            
            return [
                'success' => true,
                'message' => 'درخواست بازی با موفقیت پذیرفته شد.',
                'match_id' => $match_id,
                'opponent' => $sender
            ];
        } catch (\Exception $e) {
            error_log("Error in acceptGameRequest: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در پذیرش درخواست بازی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * رد درخواست بازی
     * @param int $request_id شناسه درخواست
     * @return array
     */
    public function rejectGameRequest($request_id)
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
            
            // دریافت اطلاعات درخواست
            $request = DB::table('game_requests')
                ->where('id', $request_id)
                ->first();
                
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'درخواست بازی یافت نشد.'
                ];
            }
            
            // بررسی اینکه درخواست برای کاربر فعلی باشد
            if ($request['receiver_id'] != $user['id']) {
                return [
                    'success' => false,
                    'message' => 'شما مجاز به رد این درخواست بازی نیستید.'
                ];
            }
            
            // بررسی وضعیت درخواست
            if ($request['status'] !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'این درخواست بازی قبلاً پذیرفته یا رد شده است.'
                ];
            }
            
            // به‌روزرسانی وضعیت درخواست
            DB::table('game_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'rejected',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            // دریافت اطلاعات فرستنده درخواست
            $sender = DB::table('users')
                ->where('id', $request['sender_id'])
                ->first();
                
            if ($sender) {
                // ارسال اعلان به فرستنده
                $this->sendGameRejectedNotification($user, $sender);
            }
            
            return [
                'success' => true,
                'message' => 'درخواست بازی با موفقیت رد شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in rejectGameRequest: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در رد درخواست بازی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * تغییر وضعیت چت
     * @param int $match_id شناسه بازی
     * @param bool $enabled وضعیت چت
     * @return array
     */
    public function toggleChat($match_id, $enabled = true)
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
            
            // دریافت اطلاعات بازی
            $match = DB::table('matches')
                ->where('id', $match_id)
                ->first();
                
            if (!$match) {
                return [
                    'success' => false,
                    'message' => 'بازی مورد نظر یافت نشد.'
                ];
            }
            
            // بررسی اینکه کاربر در این بازی باشد
            if ($match['player1'] != $user['id'] && $match['player2'] != $user['id']) {
                return [
                    'success' => false,
                    'message' => 'شما در این بازی نیستید.'
                ];
            }
            
            // تغییر وضعیت چت
            DB::table('matches')
                ->where('id', $match_id)
                ->update([
                    'chat_enabled' => $enabled,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            // تعیین حریف
            $opponent_id = ($match['player1'] == $user['id']) ? $match['player2'] : $match['player1'];
            
            // دریافت اطلاعات حریف
            $opponent = DB::table('users')
                ->where('id', $opponent_id)
                ->first();
                
            if ($opponent) {
                // ارسال اعلان به حریف
                $this->sendChatStatusNotification($user, $opponent, $enabled);
            }
            
            return [
                'success' => true,
                'message' => $enabled ? 'چت با موفقیت فعال شد.' : 'چت با موفقیت غیرفعال شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in toggleChat: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در تغییر وضعیت چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * تنظیم زمان پایان چت
     * @param int $match_id شناسه بازی
     * @param int $minutes مدت زمان به دقیقه
     * @return array
     */
    public function setChatEndTime($match_id, $minutes = 5)
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
            
            // دریافت اطلاعات بازی
            $match = DB::table('matches')
                ->where('id', $match_id)
                ->first();
                
            if (!$match) {
                return [
                    'success' => false,
                    'message' => 'بازی مورد نظر یافت نشد.'
                ];
            }
            
            // بررسی اینکه کاربر در این بازی باشد
            if ($match['player1'] != $user['id'] && $match['player2'] != $user['id']) {
                return [
                    'success' => false,
                    'message' => 'شما در این بازی نیستید.'
                ];
            }
            
            // تنظیم زمان پایان چت
            $chat_end_time = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
            
            DB::table('matches')
                ->where('id', $match_id)
                ->update([
                    'chat_end_time' => $chat_end_time,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            // تعیین حریف
            $opponent_id = ($match['player1'] == $user['id']) ? $match['player2'] : $match['player1'];
            
            // دریافت اطلاعات حریف
            $opponent = DB::table('users')
                ->where('id', $opponent_id)
                ->first();
                
            if ($opponent) {
                // ارسال اعلان به حریف
                $this->sendChatExtendedNotification($user, $opponent, $minutes);
            }
            
            return [
                'success' => true,
                'message' => "زمان چت به {$minutes} دقیقه افزایش یافت.",
                'chat_end_time' => $chat_end_time
            ];
        } catch (\Exception $e) {
            error_log("Error in setChatEndTime: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در تنظیم زمان پایان چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * بررسی وضعیت زمان چت
     * @param int $match_id شناسه بازی
     * @return array
     */
    public function checkChatEndTime($match_id)
    {
        try {
            // دریافت اطلاعات بازی
            $match = DB::table('matches')
                ->where('id', $match_id)
                ->first();
                
            if (!$match) {
                return [
                    'success' => false,
                    'message' => 'بازی مورد نظر یافت نشد.'
                ];
            }
            
            // بررسی زمان پایان چت
            if (!$match['chat_end_time']) {
                return [
                    'success' => true,
                    'message' => 'زمان پایان چت تنظیم نشده است.',
                    'remaining' => null
                ];
            }
            
            $current_time = new \DateTime();
            $end_time = new \DateTime($match['chat_end_time']);
            $interval = $current_time->diff($end_time);
            
            // تبدیل به ثانیه
            $remaining_seconds = $interval->s + ($interval->i * 60) + ($interval->h * 3600);
            
            // بررسی اتمام زمان
            if ($end_time < $current_time) {
                return [
                    'success' => true,
                    'message' => 'زمان چت به پایان رسیده است.',
                    'remaining' => 0,
                    'expired' => true
                ];
            }
            
            return [
                'success' => true,
                'message' => "زمان باقی‌مانده چت: {$interval->format('%i دقیقه و %s ثانیه')}",
                'remaining' => $remaining_seconds,
                'expired' => false
            ];
        } catch (\Exception $e) {
            error_log("Error in checkChatEndTime: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در بررسی زمان پایان چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ارسال اعلان درخواست بازی
     * @param array $sender فرستنده
     * @param array $receiver گیرنده
     * @param int $request_id شناسه درخواست
     */
    private function sendGameRequestNotification($sender, $receiver, $request_id)
    {
        try {
            // متن پیام
            $message = "🎮 *درخواست بازی جدید*\n\n";
            $message .= "کاربر " . ($sender['username'] ? '@' . $sender['username'] : $sender['first_name'] . ' ' . $sender['last_name']) . " برای شما درخواست بازی ارسال کرده است.\n\n";
            $message .= "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
            
            // ساخت دکمه‌ها
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ قبول بازی', 'callback_data' => "accept_game_{$request_id}"],
                        ['text' => '❌ رد کردن', 'callback_data' => "reject_game_{$request_id}"]
                    ],
                    [
                        ['text' => '👤 مشاهده پروفایل', 'callback_data' => "view_profile_{$sender['id']}"]
                    ]
                ]
            ]);
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($_ENV['TELEGRAM_TOKEN'], $receiver['telegram_id'], $message, 'Markdown', $reply_markup);
            }
        } catch (\Exception $e) {
            error_log("Error in sendGameRequestNotification: " . $e->getMessage());
        }
    }
    
    /**
     * ارسال اعلان پذیرش درخواست بازی
     * @param array $user کاربر پذیرنده
     * @param array $sender فرستنده درخواست
     * @param int $match_id شناسه بازی
     */
    private function sendGameAcceptedNotification($user, $sender, $match_id)
    {
        try {
            // متن پیام
            $message = "✅ *درخواست بازی پذیرفته شد*\n\n";
            $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " درخواست بازی شما را پذیرفت.\n\n";
            $message .= "برای شروع بازی روی دکمه زیر کلیک کنید:";
            
            // ساخت دکمه‌ها
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '🎮 شروع بازی', 'callback_data' => "start_match_{$match_id}"]
                    ]
                ]
            ]);
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($_ENV['TELEGRAM_TOKEN'], $sender['telegram_id'], $message, 'Markdown', $reply_markup);
            }
        } catch (\Exception $e) {
            error_log("Error in sendGameAcceptedNotification: " . $e->getMessage());
        }
    }
    
    /**
     * ارسال اعلان رد درخواست بازی
     * @param array $user کاربر رد کننده
     * @param array $sender فرستنده درخواست
     */
    private function sendGameRejectedNotification($user, $sender)
    {
        try {
            // متن پیام
            $message = "❌ *درخواست بازی رد شد*\n\n";
            $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " درخواست بازی شما را رد کرد.";
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($_ENV['TELEGRAM_TOKEN'], $sender['telegram_id'], $message, 'Markdown');
            }
        } catch (\Exception $e) {
            error_log("Error in sendGameRejectedNotification: " . $e->getMessage());
        }
    }
    
    /**
     * ارسال اعلان تغییر وضعیت چت
     * @param array $user کاربر تغییر دهنده
     * @param array $opponent حریف
     * @param bool $enabled وضعیت چت
     */
    private function sendChatStatusNotification($user, $opponent, $enabled)
    {
        try {
            if ($enabled) {
                // متن پیام
                $message = "✅ *چت فعال شد*\n\n";
                $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " قابلیت چت را فعال کرد. شما می‌توانید پیام ارسال کنید.";
            } else {
                // متن پیام
                $message = "❌ *چت غیرفعال شد*\n\n";
                $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " قابلیت چت را غیرفعال کرد. شما دیگر نمی‌توانید پیام ارسال کنید.";
            }
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($_ENV['TELEGRAM_TOKEN'], $opponent['telegram_id'], $message, 'Markdown');
            }
        } catch (\Exception $e) {
            error_log("Error in sendChatStatusNotification: " . $e->getMessage());
        }
    }
    
    /**
     * ارسال اعلان افزایش زمان چت
     * @param array $user کاربر تغییر دهنده
     * @param array $opponent حریف
     * @param int $minutes مدت زمان به دقیقه
     */
    private function sendChatExtendedNotification($user, $opponent, $minutes)
    {
        try {
            // متن پیام
            $message = "⏱ *زمان چت افزایش یافت*\n\n";
            $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " زمان چت را به {$minutes} دقیقه افزایش داد.";
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($_ENV['TELEGRAM_TOKEN'], $opponent['telegram_id'], $message, 'Markdown');
            }
        } catch (\Exception $e) {
            error_log("Error in sendChatExtendedNotification: " . $e->getMessage());
        }
    }
}