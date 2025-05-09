<?php
namespace application\handlers;

require_once __DIR__ . '/../Model/DB.php';
require_once __DIR__ . '/../controllers/FriendshipController.php';
require_once __DIR__ . '/../controllers/ChatController.php';
require_once __DIR__ . '/../controllers/ProfileController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../controllers/RequestTimeoutController.php';
require_once __DIR__ . '/../controllers/WithdrawalController.php';
require_once __DIR__ . '/../controllers/DailyCoinController.php';

use Application\Model\DB;
use application\controllers\FriendshipController;
use application\controllers\ChatController;
use application\controllers\ProfileController;
use application\controllers\AdminController;
use application\controllers\RequestTimeoutController;
use application\controllers\WithdrawalController;
use application\controllers\DailyCoinController;

/**
 * کلاس پردازش کالبک‌های تلگرام
 */
class CallbackHandler
{
    /**
     * پردازش کالبک
     * @param array $callback_query داده‌های کالبک
     * @param string $token توکن تلگرام
     * @return void
     */
    public static function processCallback($callback_query, $token)
    {
        $callback_id = $callback_query['id'] ?? '';
        $user_id = $callback_query['from']['id'] ?? 0;
        $chat_id = $callback_query['message']['chat']['id'] ?? 0;
        $message_id = $callback_query['message']['message_id'] ?? 0;
        $data = $callback_query['data'] ?? '';
        
        // لاگ کالبک
        error_log("Callback received: {$data} from user {$user_id}");
        
        // پردازش کالبک‌های مختلف
        try {
            if (strpos($data, 'view_profile_') === 0) {
                // نمایش پروفایل
                self::handleViewProfile($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'accept_friend_') === 0) {
                // پذیرش درخواست دوستی
                self::handleAcceptFriend($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'reject_friend_') === 0) {
                // رد درخواست دوستی
                self::handleRejectFriend($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'add_friend_') === 0) {
                // ارسال درخواست دوستی
                self::handleAddFriend($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'extend_chat_') === 0) {
                // تمدید چت
                self::handleExtendChat($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'disable_chat_') === 0) {
                // غیرفعال کردن چت
                self::handleDisableChat($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'confirm_disable_chat_') === 0) {
                // تایید غیرفعال کردن چت
                self::handleConfirmDisableChat($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'cancel_disable_chat_') === 0) {
                // لغو غیرفعال کردن چت
                self::handleCancelDisableChat($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'reactivate_chat_') === 0) {
                // فعال‌سازی مجدد چت
                self::handleReactivateChat($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'reject_reactivate_chat_') === 0) {
                // رد درخواست فعال‌سازی مجدد چت
                self::handleRejectReactivateChat($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'start_game_') === 0) {
                // شروع بازی
                self::handleStartGame($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'call_opponent_') === 0) {
                // صدا زدن حریف
                self::handleCallOpponent($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'approve_photo_') === 0) {
                // تایید عکس پروفایل
                self::handleApprovePhoto($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'reject_photo_') === 0) {
                // رد عکس پروفایل
                self::handleRejectPhoto($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'approve_bio_') === 0) {
                // تایید بیوگرافی
                self::handleApproveBio($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'reject_bio_') === 0) {
                // رد بیوگرافی
                self::handleRejectBio($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'withdrawal_') === 0) {
                // مدیریت برداشت
                self::handleWithdrawal($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'approve_withdrawal_') === 0) {
                // تایید برداشت
                self::handleApproveWithdrawal($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'reject_withdrawal_') === 0) {
                // رد برداشت
                self::handleRejectWithdrawal($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'claim_daily_') === 0) {
                // دریافت دلتا کوین روزانه
                self::handleClaimDaily($data, $user_id, $chat_id, $message_id, $token);
            } elseif (strpos($data, 'reaction_') === 0) {
                // ری‌اکشن به پیام
                self::handleReaction($data, $user_id, $chat_id, $message_id, $token);
            } elseif ($data === 'admin_panel') {
                // برگشت به پنل مدیریت
                self::handleBackToAdminPanel($user_id, $chat_id, $token);
            } elseif (strpos($data, 'user_info_') === 0) {
                // نمایش اطلاعات کاربر
                self::handleUserInfo($data, $user_id, $chat_id, $message_id, $token);
            }
            
            // پاسخ به کالبک برای حذف نوتیفیکیشن
            if (function_exists('answerCallbackQuery')) {
                answerCallbackQuery($token, $callback_id);
            }
            
        } catch (\Exception $e) {
            error_log("Error processing callback: " . $e->getMessage());
            if (function_exists('answerCallbackQuery')) {
                answerCallbackQuery($token, $callback_id, "خطا: " . $e->getMessage());
            }
        }
    }
    
    /**
     * پردازش کالبک نمایش پروفایل
     * @param string $data داده کالبک
     * @param int $user_id آیدی کاربر
     * @param int $chat_id آیدی چت
     * @param int $message_id آیدی پیام
     * @param string $token توکن تلگرام
     * @return void
     */
    private static function handleViewProfile($data, $user_id, $chat_id, $message_id, $token)
    {
        $target_id = str_replace('view_profile_', '', $data);
        
        // دریافت اطلاعات کاربر هدف
        $targetUser = DB::table('users')
            ->where('id', $target_id)
            ->first();
            
        if (!$targetUser) {
            if (function_exists('editMessageText')) {
                editMessageText($token, $chat_id, $message_id, "کاربر مورد نظر یافت نشد.");
            }
            return;
        }
        
        // استفاده از کنترلر دوستی
        $friendshipController = new FriendshipController($user_id);
        $result = $friendshipController->viewUserProfile($targetUser['username'] ?? $targetUser['telegram_id']);
        
        if (!$result['success']) {
            if (function_exists('editMessageText')) {
                editMessageText($token, $chat_id, $message_id, $result['message']);
            }
            return;
        }
        
        $profile = $result['profile'];
        
        // ساخت متن پروفایل
        $message = "👤 *پروفایل کاربر*\n\n";
        $message .= "نام: " . $profile['name'] . "\n";
        $message .= "نام کاربری: " . ($profile['username'] ? '@' . $profile['username'] : 'تنظیم نشده') . "\n";
        $message .= "وضعیت: " . ($profile['is_online'] ? "آنلاین 🟢" : "آفلاین ⚪") . "\n";
        $message .= "جام‌ها: " . $profile['trophies'] . " 🏆\n";
        $message .= "تعداد بازی‌ها: " . $profile['total_games'] . " 🎮\n";
        $message .= "تعداد برد: " . $profile['wins'] . " 🎯\n";
        $message .= "نرخ برد: " . $profile['win_ratio'] . "% 📊\n";
        
        if (isset($profile['profile'])) {
            $message .= "\n*اطلاعات تکمیلی*\n";
            
            if (isset($profile['profile']['gender']) && $profile['profile']['gender']) {
                $message .= "جنسیت: " . ($profile['profile']['gender'] == 'male' ? "مرد 👨" : "زن 👩") . "\n";
            }
            
            if (isset($profile['profile']['age']) && $profile['profile']['age']) {
                $message .= "سن: " . $profile['profile']['age'] . " 🔢\n";
            }
            
            if (isset($profile['profile']['province']) && $profile['profile']['province']) {
                $message .= "استان: " . $profile['profile']['province'] . " 🌍\n";
            }
            
            if (isset($profile['profile']['city']) && $profile['profile']['city']) {
                $message .= "شهر: " . $profile['profile']['city'] . " 🏙️\n";
            }
            
            if (isset($profile['profile']['bio']) && $profile['profile']['bio']) {
                $message .= "\n*بیوگرافی*\n" . $profile['profile']['bio'] . "\n";
            }
        }
        
        // ساخت دکمه‌ها
        $buttons = [];
        
        if (!$profile['is_friend'] && !$profile['has_pending_request']) {
            $buttons[] = [['text' => '👋 ارسال درخواست دوستی', 'callback_data' => "add_friend_{$targetUser['id']}"]];
        }
        
        if ($profile['is_friend']) {
            $buttons[] = [['text' => '🎮 دعوت به بازی', 'callback_data' => "start_game_{$targetUser['id']}"]];
        }
        
        $buttons[] = [['text' => '🔙 بازگشت', 'callback_data' => "back_to_friends"]];
        
        $reply_markup = json_encode([
            'inline_keyboard' => $buttons
        ]);
        
        // ارسال یا ویرایش پیام
        if ($message_id && function_exists('editMessageText')) {
            editMessageText($token, $chat_id, $message_id, $message, 'Markdown', $reply_markup);
        } else if (function_exists('sendMessage')) {
            sendMessage($token, $chat_id, $message, 'Markdown', $reply_markup);
        }
    }
    
    /**
     * پردازش کالبک پذیرش درخواست دوستی
     * @param string $data داده کالبک
     * @param int $user_id آیدی کاربر
     * @param int $chat_id آیدی چت
     * @param int $message_id آیدی پیام
     * @param string $token توکن تلگرام
     * @return void
     */
    private static function handleAcceptFriend($data, $user_id, $chat_id, $message_id, $token)
    {
        $request_id = str_replace('accept_friend_', '', $data);
        
        // پذیرش درخواست دوستی
        $friendshipController = new FriendshipController($user_id);
        $result = $friendshipController->acceptFriendRequest($request_id);
        
        // ویرایش پیام
        if ($result['success'] && function_exists('editMessageText')) {
            $message = "✅ *درخواست دوستی پذیرفته شد*\n\n";
            $message .= "شما و " . ($result['friend']['username'] ? '@' . $result['friend']['username'] : $result['friend']['first_name'] . ' ' . $result['friend']['last_name']) . " اکنون دوست هستید.";
            
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '🎮 شروع بازی', 'callback_data' => "start_game_{$result['friend']['id']}"]
                    ]
                ]
            ]);
            
            editMessageText($token, $chat_id, $message_id, $message, 'Markdown', $reply_markup);
        } else if (function_exists('editMessageText')) {
            editMessageText($token, $chat_id, $message_id, "خطا: " . $result['message']);
        }
    }
    
    /**
     * پردازش کالبک رد درخواست دوستی
     * @param string $data داده کالبک
     * @param int $user_id آیدی کاربر
     * @param int $chat_id آیدی چت
     * @param int $message_id آیدی پیام
     * @param string $token توکن تلگرام
     * @return void
     */
    private static function handleRejectFriend($data, $user_id, $chat_id, $message_id, $token)
    {
        $request_id = str_replace('reject_friend_', '', $data);
        
        // رد درخواست دوستی
        $friendshipController = new FriendshipController($user_id);
        $result = $friendshipController->rejectFriendRequest($request_id);
        
        // ویرایش پیام
        if ($result['success'] && function_exists('editMessageText')) {
            $message = "❌ *درخواست دوستی رد شد*";
            editMessageText($token, $chat_id, $message_id, $message);
        } else if (function_exists('editMessageText')) {
            editMessageText($token, $chat_id, $message_id, "خطا: " . $result['message']);
        }
    }
    
    /**
     * پردازش کالبک صدا زدن حریف
     * @param string $data داده کالبک
     * @param int $user_id آیدی کاربر
     * @param int $chat_id آیدی چت
     * @param int $message_id آیدی پیام
     * @param string $token توکن تلگرام
     * @return void
     */
    private static function handleCallOpponent($data, $user_id, $chat_id, $message_id, $token)
    {
        $match_id = str_replace('call_opponent_', '', $data);
        
        // صدا زدن حریف
        $chatController = new ChatController($user_id);
        $result = $chatController->callOpponent($match_id);
        
        // ارسال پیام به کاربر
        if ($result['success'] && function_exists('editMessageText')) {
            $message = "✅ *اعلان ارسال شد*\n\n";
            $message .= $result['message'];
            
            editMessageText($token, $chat_id, $message_id, $message);
        } else if (function_exists('editMessageText')) {
            editMessageText($token, $chat_id, $message_id, "خطا: " . $result['message']);
        }
    }
    
    /**
     * پردازش کالبک دریافت دلتا کوین روزانه
     * @param string $data داده کالبک
     * @param int $user_id آیدی کاربر
     * @param int $chat_id آیدی چت
     * @param int $message_id آیدی پیام
     * @param string $token توکن تلگرام
     * @return void
     */
    private static function handleClaimDaily($data, $user_id, $chat_id, $message_id, $token)
    {
        // دریافت دلتا کوین روزانه
        $dailyCoinController = new DailyCoinController($user_id);
        $result = $dailyCoinController->claimDailyCoin();
        
        // ارسال پیام به کاربر
        if ($result['success'] && function_exists('editMessageText')) {
            $message = "🎁 *دریافت دلتا کوین روزانه*\n\n";
            $message .= "تبریک! مقدار {$result['amount']} دلتا کوین دریافت کردید.\n\n";
            $message .= "موجودی فعلی: {$result['new_balance']} دلتا کوین";
            
            editMessageText($token, $chat_id, $message_id, $message);
        } else if (function_exists('editMessageText')) {
            editMessageText($token, $chat_id, $message_id, $result['message']);
        }
    }
    
    /**
     * پردازش کالبک ری‌اکشن
     * @param string $data داده کالبک
     * @param int $user_id آیدی کاربر
     * @param int $chat_id آیدی چت
     * @param int $message_id آیدی پیام
     * @param string $token توکن تلگرام
     * @return void
     */
    private static function handleReaction($data, $user_id, $chat_id, $message_id, $token)
    {
        // جداسازی اطلاعات
        $parts = explode('_', $data);
        
        if (count($parts) < 3) {
            return;
        }
        
        $target_message_id = $parts[1];
        $emoji = str_replace('emoji:', '', $parts[2]);
        
        // ثبت ری‌اکشن
        $chatController = new ChatController($user_id);
        $result = $chatController->addReaction($target_message_id, $emoji);
        
        // نمایش اموجی به کاربر
        if ($result['success'] && function_exists('sendMessage')) {
            // ارسال یک پیام موقت
            sendMessage($token, $chat_id, "ری‌اکشن {$emoji} اضافه شد.");
        } else if (function_exists('answerCallbackQuery')) {
            // ارسال خطا
            answerCallbackQuery($token, $callback_id, $result['message']);
        }
    }
    
    /**
     * بررسی تکمیل پروفایل و اعطای پورسانت
     * @param int $user_id شناسه کاربر
     * @param string $token توکن تلگرام
     * @return void
     */
    private static function checkProfileCompletion($user_id, $token)
    {
        // دریافت اطلاعات پروفایل
        $profile = DB::table('user_profiles')
            ->where('user_id', $user_id)
            ->first();
            
        if (!$profile) {
            return;
        }
        
        // بررسی تکمیل پروفایل
        $isComplete = 
            $profile['photo'] && $profile['photo_approved'] &&
            $profile['name'] &&
            $profile['gender'] &&
            $profile['age'] &&
            $profile['bio'] && $profile['bio_approved'];
            
        if (!$isComplete) {
            return;
        }
        
        // دریافت اطلاعات کاربر
        $user = DB::table('users')
            ->where('id', $user_id)
            ->first();
            
        if (!$user) {
            return;
        }
        
        // بررسی و اعطای پورسانت
        require_once __DIR__ . '/../controllers/ProfileController.php';
        $profileController = new ProfileController($user['telegram_id']);
        
        // این متد باید در ProfileController پیاده‌سازی شود
        if (method_exists($profileController, 'updateReferralCommission')) {
            $profileController->updateReferralCommission('profile_completion');
        }
    }
}