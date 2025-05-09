<?php
namespace application\controllers;

require_once __DIR__ . '/../Model/DB.php';

use Application\Model\DB;

/**
 * کلاس مدیریت چت
 */
class ChatController
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
     * دریافت وضعیت چت
     * @param int $match_id شناسه بازی
     * @return array
     */
    public function getChatStatus($match_id)
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
                    'message' => 'بازی یافت نشد.'
                ];
            }
            
            // بررسی اینکه کاربر در این بازی شرکت داشته باشد
            if ($match['player1_id'] != $user['id'] && $match['player2_id'] != $user['id']) {
                return [
                    'success' => false,
                    'message' => 'شما در این بازی شرکت نداشته‌اید.'
                ];
            }
            
            // دریافت اطلاعات چت
            $chat = DB::table('post_game_chats')
                ->where('match_id', $match_id)
                ->first();
                
            if (!$chat) {
                return [
                    'success' => false,
                    'message' => 'چت برای این بازی وجود ندارد یا به پایان رسیده است.'
                ];
            }
            
            // دریافت اطلاعات حریف
            $opponent_id = ($match['player1_id'] == $user['id']) ? $match['player2_id'] : $match['player1_id'];
            $opponent = DB::table('users')
                ->where('id', $opponent_id)
                ->first();
                
            if (!$opponent) {
                return [
                    'success' => false,
                    'message' => 'اطلاعات حریف یافت نشد.'
                ];
            }
            
            // بررسی فعال بودن چت
            $is_active = true;
            $opponent_active = true;
            
            if ($match['player1_id'] == $user['id']) {
                $is_active = (bool) $chat['player1_active'];
                $opponent_active = (bool) $chat['player2_active'];
            } else {
                $is_active = (bool) $chat['player2_active'];
                $opponent_active = (bool) $chat['player1_active'];
            }
            
            // بررسی زمان انقضای چت
            $current_time = time();
            $chat_end_time = strtotime($chat['end_time']);
            $is_expired = $current_time > $chat_end_time;
            
            return [
                'success' => true,
                'match_id' => $match_id,
                'match' => $match,
                'chat' => $chat,
                'opponent' => $opponent,
                'is_active' => $is_active,
                'opponent_active' => $opponent_active,
                'is_expired' => $is_expired,
                'chat_end_time' => $chat['end_time'],
                'remaining_seconds' => max(0, $chat_end_time - $current_time)
            ];
        } catch (\Exception $e) {
            error_log("Error in getChatStatus: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در دریافت وضعیت چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * تمدید زمان چت
     * @param int $match_id شناسه بازی
     * @return array
     */
    public function extendChatTime($match_id)
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
            
            // دریافت وضعیت چت
            $chatStatus = $this->getChatStatus($match_id);
            
            if (!$chatStatus['success']) {
                return [
                    'success' => false,
                    'message' => $chatStatus['message']
                ];
            }
            
            // بررسی منقضی نبودن چت
            if ($chatStatus['is_expired']) {
                return [
                    'success' => false,
                    'message' => 'چت قبلاً منقضی شده است و قابل تمدید نیست.'
                ];
            }
            
            // محاسبه زمان جدید انقضا (5 دقیقه بعد از زمان فعلی)
            $new_end_time = date('Y-m-d H:i:s', time() + 300);
            
            // به‌روزرسانی زمان انقضا
            $result = DB::table('post_game_chats')
                ->where('match_id', $match_id)
                ->update([
                    'end_time' => $new_end_time,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'خطا در تمدید زمان چت.'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'زمان چت با موفقیت به 5 دقیقه افزایش یافت.',
                'chat_end_time' => $new_end_time
            ];
        } catch (\Exception $e) {
            error_log("Error in extendChatTime: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در تمدید زمان چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * غیرفعال کردن چت
     * @param int $match_id شناسه بازی
     * @return array
     */
    public function deactivateChat($match_id)
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
            
            // دریافت وضعیت چت
            $chatStatus = $this->getChatStatus($match_id);
            
            if (!$chatStatus['success']) {
                return [
                    'success' => false,
                    'message' => $chatStatus['message']
                ];
            }
            
            // بررسی منقضی نبودن چت
            if ($chatStatus['is_expired']) {
                return [
                    'success' => false,
                    'message' => 'چت قبلاً منقضی شده است.'
                ];
            }
            
            // بررسی اینکه کاربر در این بازی شرکت داشته باشد
            $match = $chatStatus['match'];
            
            // به‌روزرسانی وضعیت فعال بودن چت
            $updateData = [
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($match['player1_id'] == $user['id']) {
                $updateData['player1_active'] = false;
            } else {
                $updateData['player2_active'] = false;
            }
            
            $result = DB::table('post_game_chats')
                ->where('match_id', $match_id)
                ->update($updateData);
                
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'خطا در غیرفعال کردن چت.'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'چت با موفقیت غیرفعال شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in deactivateChat: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در غیرفعال کردن چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * فعال‌سازی مجدد چت
     * @param int $match_id شناسه بازی
     * @return array
     */
    public function reactivateChat($match_id)
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
            
            // دریافت وضعیت چت
            $chatStatus = $this->getChatStatus($match_id);
            
            if (!$chatStatus['success']) {
                return [
                    'success' => false,
                    'message' => $chatStatus['message']
                ];
            }
            
            // بررسی منقضی نبودن چت
            if ($chatStatus['is_expired']) {
                return [
                    'success' => false,
                    'message' => 'چت قبلاً منقضی شده است و قابل فعال‌سازی نیست.'
                ];
            }
            
            // بررسی اینکه کاربر در این بازی شرکت داشته باشد
            $match = $chatStatus['match'];
            
            // به‌روزرسانی وضعیت فعال بودن چت
            $updateData = [
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($match['player1_id'] == $user['id']) {
                $updateData['player1_active'] = true;
            } else {
                $updateData['player2_active'] = true;
            }
            
            $result = DB::table('post_game_chats')
                ->where('match_id', $match_id)
                ->update($updateData);
                
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'خطا در فعال‌سازی مجدد چت.'
                ];
            }
            
            // ارسال اعلان به حریف
            $opponent = $chatStatus['opponent'];
            
            if ($opponent) {
                $this->sendChatReactivationNotification($user, $opponent, $match_id);
            }
            
            return [
                'success' => true,
                'message' => 'چت با موفقیت فعال شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in reactivateChat: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در فعال‌سازی مجدد چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * رد درخواست فعال‌سازی مجدد چت
     * @param int $match_id شناسه بازی
     * @return array
     */
    public function rejectReactivateChat($match_id)
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
            
            // دریافت وضعیت چت
            $chatStatus = $this->getChatStatus($match_id);
            
            if (!$chatStatus['success']) {
                return [
                    'success' => false,
                    'message' => $chatStatus['message']
                ];
            }
            
            // بررسی منقضی نبودن چت
            if ($chatStatus['is_expired']) {
                return [
                    'success' => false,
                    'message' => 'چت قبلاً منقضی شده است.'
                ];
            }
            
            // ارسال اعلان به حریف
            $opponent = $chatStatus['opponent'];
            
            if ($opponent) {
                $this->sendChatReactivationRejectedNotification($user, $opponent, $match_id);
            }
            
            return [
                'success' => true,
                'message' => 'رد درخواست فعال‌سازی مجدد چت با موفقیت انجام شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in rejectReactivateChat: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در رد درخواست فعال‌سازی مجدد چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * اضافه کردن ری‌اکشن به پیام
     * @param int $message_id شناسه پیام
     * @param string $emoji اموجی
     * @return array
     */
    public function addReaction($message_id, $emoji)
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
            
            // دریافت اطلاعات پیام
            $message = DB::table('chat_messages')
                ->where('message_id', $message_id)
                ->where('receiver_id', $user['id'])
                ->first();
                
            if (!$message) {
                return [
                    'success' => false,
                    'message' => 'پیام یافت نشد یا شما گیرنده این پیام نیستید.'
                ];
            }
            
            // دریافت اطلاعات بازی
            $match = DB::table('matches')
                ->where('id', $message['match_id'])
                ->first();
                
            if (!$match) {
                return [
                    'success' => false,
                    'message' => 'بازی مربوط به این پیام یافت نشد.'
                ];
            }
            
            // دریافت وضعیت چت
            $chatStatus = $this->getChatStatus($message['match_id']);
            
            if (!$chatStatus['success']) {
                return [
                    'success' => false,
                    'message' => $chatStatus['message']
                ];
            }
            
            // بررسی منقضی نبودن چت
            if ($chatStatus['is_expired']) {
                return [
                    'success' => false,
                    'message' => 'چت قبلاً منقضی شده است و امکان افزودن ری‌اکشن وجود ندارد.'
                ];
            }
            
            // بررسی فعال بودن چت
            if (!$chatStatus['is_active'] || !$chatStatus['opponent_active']) {
                return [
                    'success' => false,
                    'message' => 'چت غیرفعال است و امکان افزودن ری‌اکشن وجود ندارد.'
                ];
            }
            
            // بررسی معتبر بودن اموجی
            $validEmojis = $this->getAllReactions();
            
            if (!$validEmojis['success']) {
                return [
                    'success' => false,
                    'message' => $validEmojis['message']
                ];
            }
            
            $isValidEmoji = false;
            
            foreach ($validEmojis['reactions'] as $reaction) {
                if ($reaction['emoji'] === $emoji) {
                    $isValidEmoji = true;
                    break;
                }
            }
            
            if (!$isValidEmoji) {
                return [
                    'success' => false,
                    'message' => 'اموجی انتخاب شده معتبر نیست.'
                ];
            }
            
            // ثبت ری‌اکشن
            $result = DB::table('message_reactions')->insert([
                'message_id' => $message['id'],
                'user_id' => $user['id'],
                'reaction' => $emoji,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'خطا در ثبت ری‌اکشن.'
                ];
            }
            
            // ارسال اعلان به فرستنده پیام
            $sender = DB::table('users')
                ->where('id', $message['sender_id'])
                ->first();
                
            if ($sender) {
                $this->sendReactionNotification($user, $sender, $emoji, $message['text']);
            }
            
            return [
                'success' => true,
                'message' => 'ری‌اکشن با موفقیت ثبت شد.',
                'emoji' => $emoji
            ];
        } catch (\Exception $e) {
            error_log("Error in addReaction: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ثبت ری‌اکشن: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * دریافت همه ری‌اکشن‌های فعال
     * @return array
     */
    public function getAllReactions()
    {
        try {
            // دریافت ری‌اکشن‌های فعال از جدول reactions
            $reactions = DB::table('reactions')
                ->where('is_active', true)
                ->orderBy('order')
                ->get();
                
            if (empty($reactions)) {
                // ری‌اکشن‌های پیش‌فرض
                $reactions = [
                    ['emoji' => '👍', 'description' => 'لایک', 'is_active' => true, 'order' => 1],
                    ['emoji' => '👎', 'description' => 'دیس‌لایک', 'is_active' => true, 'order' => 2],
                    ['emoji' => '😍', 'description' => 'عاشق شدم', 'is_active' => true, 'order' => 3],
                    ['emoji' => '😂', 'description' => 'خنده', 'is_active' => true, 'order' => 4],
                    ['emoji' => '😭', 'description' => 'گریه', 'is_active' => true, 'order' => 5],
                    ['emoji' => '❤️', 'description' => 'قلب', 'is_active' => true, 'order' => 6],
                    ['emoji' => '🔥', 'description' => 'آتش', 'is_active' => true, 'order' => 7],
                    ['emoji' => '🎉', 'description' => 'جشن', 'is_active' => true, 'order' => 8],
                    ['emoji' => '😠', 'description' => 'عصبانیت', 'is_active' => true, 'order' => 9],
                    ['emoji' => '👏', 'description' => 'تشویق', 'is_active' => true, 'order' => 10],
                ];
                
                // ذخیره ری‌اکشن‌های پیش‌فرض در دیتابیس
                foreach ($reactions as $reaction) {
                    DB::table('reactions')->insert($reaction);
                }
            }
            
            return [
                'success' => true,
                'reactions' => $reactions
            ];
        } catch (\Exception $e) {
            error_log("Error in getAllReactions: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در دریافت ری‌اکشن‌ها: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * صدا زدن حریف
     * @param int $match_id شناسه بازی
     * @return array
     */
    public function callOpponent($match_id)
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
            
            // دریافت وضعیت چت
            $chatStatus = $this->getChatStatus($match_id);
            
            if (!$chatStatus['success']) {
                return [
                    'success' => false,
                    'message' => $chatStatus['message']
                ];
            }
            
            // بررسی منقضی نبودن چت
            if ($chatStatus['is_expired']) {
                return [
                    'success' => false,
                    'message' => 'چت قبلاً منقضی شده است.'
                ];
            }
            
            // بررسی فعال بودن چت
            if (!$chatStatus['is_active'] || !$chatStatus['opponent_active']) {
                return [
                    'success' => false,
                    'message' => 'چت غیرفعال است و امکان صدا زدن حریف وجود ندارد.'
                ];
            }
            
            // بررسی آخرین صدا زدن
            $lastCall = DB::table('player_calls')
                ->where('match_id', $match_id)
                ->where('caller_id', $user['id'])
                ->orderBy('created_at', 'DESC')
                ->first();
                
            // بررسی محدودیت زمانی (حداقل 30 ثانیه بین هر صدا زدن)
            if ($lastCall && (time() - strtotime($lastCall['created_at'])) < 30) {
                $remaining = 30 - (time() - strtotime($lastCall['created_at']));
                
                return [
                    'success' => false,
                    'message' => "شما اخیراً حریف خود را صدا زده‌اید. لطفاً {$remaining} ثانیه دیگر تلاش کنید."
                ];
            }
            
            // ثبت صدا زدن
            DB::table('player_calls')->insert([
                'match_id' => $match_id,
                'caller_id' => $user['id'],
                'called_id' => $chatStatus['opponent']['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // ارسال اعلان به حریف
            $this->sendCallNotification($user, $chatStatus['opponent'], $match_id);
            
            return [
                'success' => true,
                'message' => 'اعلان با موفقیت به حریف شما ارسال شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in callOpponent: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در صدا زدن حریف: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ذخیره پیام چت
     * @param int $match_id شناسه بازی
     * @param string $text متن پیام
     * @return array
     */
    public function saveMessage($match_id, $text)
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
            
            // دریافت وضعیت چت
            $chatStatus = $this->getChatStatus($match_id);
            
            if (!$chatStatus['success']) {
                return [
                    'success' => false,
                    'message' => $chatStatus['message']
                ];
            }
            
            // بررسی منقضی نبودن چت
            if ($chatStatus['is_expired']) {
                return [
                    'success' => false,
                    'message' => 'چت قبلاً منقضی شده است.'
                ];
            }
            
            // بررسی فعال بودن چت
            if (!$chatStatus['is_active'] || !$chatStatus['opponent_active']) {
                return [
                    'success' => false,
                    'message' => 'چت غیرفعال است و امکان ارسال پیام وجود ندارد.'
                ];
            }
            
            // ذخیره پیام
            $message_id = DB::table('chat_messages')->insert([
                'match_id' => $match_id,
                'chat_id' => $chatStatus['chat']['id'],
                'sender_id' => $user['id'],
                'receiver_id' => $chatStatus['opponent']['id'],
                'text' => $text,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$message_id) {
                return [
                    'success' => false,
                    'message' => 'خطا در ذخیره پیام.'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'پیام با موفقیت ذخیره شد.',
                'message_id' => $message_id
            ];
        } catch (\Exception $e) {
            error_log("Error in saveMessage: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ذخیره پیام: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ارسال اعلان فعال‌سازی مجدد چت
     * @param array $user کاربر
     * @param array $opponent حریف
     * @param int $match_id شناسه بازی
     * @return void
     */
    private function sendChatReactivationNotification($user, $opponent, $match_id)
    {
        try {
            // متن پیام
            $message = "🔔 *درخواست فعال‌سازی مجدد چت*\n\n";
            $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " درخواست فعال‌سازی مجدد چت پس از بازی را دارد.\n\n";
            $message .= "آیا می‌خواهید چت را فعال کنید؟";
            
            // ساخت دکمه‌ها
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ فعال کردن چت', 'callback_data' => "reactivate_chat_{$match_id}"],
                        ['text' => '❌ رد درخواست', 'callback_data' => "reject_reactivate_chat_{$match_id}"]
                    ]
                ]
            ]);
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($GLOBALS['token'], $opponent['telegram_id'], $message, 'Markdown', $reply_markup);
            }
        } catch (\Exception $e) {
            error_log("Error in sendChatReactivationNotification: " . $e->getMessage());
        }
    }
    
    /**
     * ارسال اعلان رد درخواست فعال‌سازی مجدد چت
     * @param array $user کاربر
     * @param array $opponent حریف
     * @param int $match_id شناسه بازی
     * @return void
     */
    private function sendChatReactivationRejectedNotification($user, $opponent, $match_id)
    {
        try {
            // متن پیام
            $message = "❌ *درخواست فعال‌سازی مجدد چت رد شد*\n\n";
            $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " درخواست فعال‌سازی مجدد چت شما را رد کرد.";
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($GLOBALS['token'], $opponent['telegram_id'], $message, 'Markdown');
            }
        } catch (\Exception $e) {
            error_log("Error in sendChatReactivationRejectedNotification: " . $e->getMessage());
        }
    }
    
    /**
     * ارسال اعلان ری‌اکشن
     * @param array $user کاربر
     * @param array $sender فرستنده پیام
     * @param string $emoji اموجی
     * @param string $messageText متن پیام
     * @return void
     */
    private function sendReactionNotification($user, $sender, $emoji, $messageText)
    {
        try {
            // متن پیام
            $message = "{$emoji} *ری‌اکشن جدید*\n\n";
            $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " به پیام شما ری‌اکشن {$emoji} داد.\n\n";
            $message .= "پیام شما: " . (mb_strlen($messageText, 'UTF-8') > 50 ? mb_substr($messageText, 0, 50, 'UTF-8') . "..." : $messageText);
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($GLOBALS['token'], $sender['telegram_id'], $message, 'Markdown');
            }
        } catch (\Exception $e) {
            error_log("Error in sendReactionNotification: " . $e->getMessage());
        }
    }
    
    /**
     * ارسال اعلان صدا زدن
     * @param array $user کاربر
     * @param array $opponent حریف
     * @param int $match_id شناسه بازی
     * @return void
     */
    private function sendCallNotification($user, $opponent, $match_id)
    {
        try {
            // متن پیام
            $message = "🔔 *اعلان*\n\n";
            $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " شما را در چت پس از بازی صدا زده است.";
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($GLOBALS['token'], $opponent['telegram_id'], $message, 'Markdown');
            }
        } catch (\Exception $e) {
            error_log("Error in sendCallNotification: " . $e->getMessage());
        }
    }
    
    /**
     * ایجاد چت پس از بازی
     * @param int $match_id شناسه بازی
     * @param int $duration مدت زمان چت (ثانیه)
     * @return array
     */
    public function createPostGameChat($match_id, $duration = 180)
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
                    'message' => 'بازی یافت نشد.'
                ];
            }
            
            // بررسی اینکه کاربر در این بازی شرکت داشته باشد
            if ($match['player1_id'] != $user['id'] && $match['player2_id'] != $user['id']) {
                return [
                    'success' => false,
                    'message' => 'شما در این بازی شرکت نداشته‌اید.'
                ];
            }
            
            // بررسی عدم وجود چت فعال
            $existingChat = DB::table('post_game_chats')
                ->where('match_id', $match_id)
                ->where('end_time', '>', date('Y-m-d H:i:s'))
                ->first();
                
            if ($existingChat) {
                return [
                    'success' => false,
                    'message' => 'چت برای این بازی قبلاً ایجاد شده است.'
                ];
            }
            
            // محاسبه زمان پایان
            $end_time = date('Y-m-d H:i:s', time() + $duration);
            
            // ایجاد چت
            $chat_id = DB::table('post_game_chats')->insert([
                'match_id' => $match_id,
                'player1_id' => $match['player1_id'],
                'player2_id' => $match['player2_id'],
                'player1_active' => true,
                'player2_active' => true,
                'start_time' => date('Y-m-d H:i:s'),
                'end_time' => $end_time,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$chat_id) {
                return [
                    'success' => false,
                    'message' => 'خطا در ایجاد چت.'
                ];
            }
            
            // دریافت اطلاعات حریف
            $opponent_id = ($match['player1_id'] == $user['id']) ? $match['player2_id'] : $match['player1_id'];
            $opponent = DB::table('users')
                ->where('id', $opponent_id)
                ->first();
                
            if ($opponent) {
                // ارسال اعلان به هر دو بازیکن
                $this->sendPostGameChatNotification($user, $opponent, $match_id, $end_time);
            }
            
            return [
                'success' => true,
                'message' => 'چت با موفقیت ایجاد شد.',
                'chat_id' => $chat_id,
                'end_time' => $end_time
            ];
        } catch (\Exception $e) {
            error_log("Error in createPostGameChat: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ایجاد چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ارسال اعلان چت پس از بازی
     * @param array $user کاربر
     * @param array $opponent حریف
     * @param int $match_id شناسه بازی
     * @param string $end_time زمان پایان چت
     * @return void
     */
    private function sendPostGameChatNotification($user, $opponent, $match_id, $end_time)
    {
        try {
            // متن پیام
            $message = "💬 *چت پس از بازی*\n\n";
            $message .= "چت شما تا " . date('H:i', strtotime($end_time)) . " برقرار است. چنانچه قصد افزایش این زمان یا قطع چت و برگشت به منوی اصلی ربات را دارید از دکمه‌های زیرِ پیام استفاده کنید.";
            
            // ساخت دکمه‌ها
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '⏰ افزایش به 5 دقیقه', 'callback_data' => "extend_chat_{$match_id}"]
                    ],
                    [
                        ['text' => '❌ قطع چت', 'callback_data' => "disable_chat_{$match_id}"]
                    ]
                ]
            ]);
            
            // ارسال پیام به کاربر فعلی
            if (function_exists('sendMessage')) {
                sendMessage($GLOBALS['token'], $user['telegram_id'], $message, 'Markdown', $reply_markup);
            }
            
            // ارسال پیام به حریف
            if (function_exists('sendMessage')) {
                sendMessage($GLOBALS['token'], $opponent['telegram_id'], $message, 'Markdown', $reply_markup);
            }
            
            // تنظیم وضعیت هر دو کاربر به حالت چت
            $user_state = [
                'state' => 'post_game_chat',
                'step' => 'chatting',
                'match_id' => $match_id,
                'opponent_id' => $opponent['telegram_id']
            ];
            
            DB::table('users')
                ->where('id', $user['id'])
                ->update(['state' => json_encode($user_state)]);
                
            $opponent_state = [
                'state' => 'post_game_chat',
                'step' => 'chatting',
                'match_id' => $match_id,
                'opponent_id' => $user['telegram_id']
            ];
            
            DB::table('users')
                ->where('id', $opponent['id'])
                ->update(['state' => json_encode($opponent_state)]);
        } catch (\Exception $e) {
            error_log("Error in sendPostGameChatNotification: " . $e->getMessage());
        }
    }
}