<?php
namespace application\handlers;

require_once __DIR__ . '/../Model/DB.php';
require_once __DIR__ . '/../controllers/ProfileController.php';
require_once __DIR__ . '/../controllers/FriendshipController.php';
require_once __DIR__ . '/../controllers/ChatController.php';
require_once __DIR__ . '/../controllers/DailyCoinController.php';
require_once __DIR__ . '/../controllers/WithdrawalController.php';

use Application\Model\DB;
use application\controllers\ProfileController;
use application\controllers\FriendshipController;
use application\controllers\ChatController;
use application\controllers\DailyCoinController;
use application\controllers\WithdrawalController;

/**
 * کلاس مدیریت وضعیت‌های کاربر
 */
class StateHandler
{
    /**
     * پردازش دلتا کوین روزانه
     * @param int $user_id آیدی کاربر
     * @param int $chat_id آیدی چت
     * @param string $text متن پیام
     * @param string $token توکن تلگرام
     * @return bool
     */
    public static function processDailyDeltaCoin($user_id, $chat_id, $text, $token)
    {
        // بررسی دلتا کوین روزانه
        $dailyCoinController = new DailyCoinController($user_id);
        $checkResult = $dailyCoinController->checkDailyCoin();
        
        if (!$checkResult['success']) {
            // اگر عضو کانال‌ها نیست
            if (isset($checkResult['channels']) && !empty($checkResult['channels'])) {
                $message = "📣 *دلتا کوین روزانه*\n\n";
                $message .= "برای دریافت دلتا کوین رایگانِ امروزتان در چنل‌های اسپانسری زیر عضو شده سپس روی دکمه «دریافت دلتا کوین» کلیک کنید.\n\n";
                
                // لیست کانال‌ها
                foreach ($checkResult['channels'] as $index => $channel) {
                    $message .= ($index + 1) . "- " . $channel['title'] . (isset($channel['link']) ? (" » [عضویت](" . $channel['link'] . ")") : "") . "\n";
                }
                
                $reply_markup = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ دریافت دلتا کوین', 'callback_data' => 'claim_daily_coin']
                        ]
                    ]
                ]);
                
                if (function_exists('sendMessage')) {
                    sendMessage($token, $chat_id, $message, 'Markdown', $reply_markup);
                }
                
                return true;
            }
            
            // اگر قبلاً دریافت کرده
            if (isset($checkResult['already_claimed']) && $checkResult['already_claimed']) {
                $message = "⏳ *دلتا کوین روزانه*\n\n";
                $message .= $checkResult['message'];
                
                if (function_exists('sendMessage')) {
                    sendMessage($token, $chat_id, $message);
                }
                
                return true;
            }
            
            // سایر خطاها
            if (function_exists('sendMessage')) {
                sendMessage($token, $chat_id, $checkResult['message']);
            }
            
            return true;
        }
        
        // اگر می‌تواند دریافت کند
        if (isset($checkResult['can_claim']) && $checkResult['can_claim']) {
            // دریافت دلتا کوین
            $claimResult = $dailyCoinController->claimDailyCoin();
            
            if ($claimResult['success']) {
                $message = "🎁 *دریافت دلتا کوین روزانه*\n\n";
                $message .= "تبریک! مقدار {$claimResult['amount']} دلتا کوین دریافت کردید.\n\n";
                $message .= "موجودی فعلی: {$claimResult['new_balance']} دلتا کوین";
            } else {
                $message = "❌ *خطا*\n\n";
                $message .= $claimResult['message'];
            }
            
            if (function_exists('sendMessage')) {
                sendMessage($token, $chat_id, $message);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * پردازش پروفایل کاربر
     * @param int $user_id آیدی کاربر
     * @param int $chat_id آیدی چت
     * @param array $user_state وضعیت کاربر
     * @param string $text متن پیام
     * @param array $message داده‌های پیام
     * @param string $token توکن تلگرام
     * @return bool
     */
    public static function processProfile($user_id, $chat_id, $user_state, $text, $message, $token)
    {
        if (!isset($user_state['state']) || $user_state['state'] !== 'profile') {
            return false;
        }
        
        $profileController = new ProfileController($user_id);
        $step = $user_state['step'] ?? '';
        
        switch ($step) {
            case 'main_menu':
                // منوی اصلی پروفایل
                if (strpos($text, 'عکس') !== false || strpos($text, 'پروفایل') !== false) {
                    // اطلاعات راهنمایی آپلود عکس
                    $message = "🖼️ *آپلود عکس پروفایل*\n\n";
                    $message .= "لطفاً عکس پروفایل خود را ارسال کنید.\n";
                    $message .= "توجه: عکس شما پس از بررسی و تایید توسط ادمین در پروفایل قرار خواهد گرفت.";
                    
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '🔙 بازگشت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    // تغییر وضعیت کاربر
                    $user_state['step'] = 'upload_photo';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    return true;
                }
                else if (strpos($text, 'نام') !== false) {
                    // درخواست نام
                    $message = "👤 *نام*\n\n";
                    $message .= "لطفاً نام خود را وارد کنید:";
                    
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '🔙 بازگشت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    // تغییر وضعیت کاربر
                    $user_state['step'] = 'enter_name';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    return true;
                }
                else if (strpos($text, 'جنسیت') !== false) {
                    // انتخاب جنسیت
                    $message = "👫 *جنسیت*\n\n";
                    $message .= "لطفاً جنسیت خود را انتخاب کنید:";
                    
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '👨 مرد'], ['text' => '👩 زن']],
                            [['text' => '🔙 بازگشت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    // تغییر وضعیت کاربر
                    $user_state['step'] = 'select_gender';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    return true;
                }
                else if (strpos($text, 'سن') !== false) {
                    // انتخاب سن
                    $message = "🔢 *سن*\n\n";
                    $message .= "لطفاً سن خود را انتخاب کنید:";
                    
                    // ساخت دکمه‌های سن
                    $keyboard = [
                        'keyboard' => [],
                        'resize_keyboard' => true
                    ];
                    
                    $row = [];
                    for ($age = 9; $age <= 70; $age++) {
                        $row[] = ['text' => (string)$age];
                        
                        // هر 5 عدد در یک ردیف
                        if (count($row) === 5) {
                            $keyboard['keyboard'][] = $row;
                            $row = [];
                        }
                    }
                    
                    // اضافه کردن باقیمانده اعداد
                    if (!empty($row)) {
                        $keyboard['keyboard'][] = $row;
                    }
                    
                    // دکمه بازگشت
                    $keyboard['keyboard'][] = [['text' => '🔙 بازگشت']];
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, json_encode($keyboard));
                    }
                    
                    // تغییر وضعیت کاربر
                    $user_state['step'] = 'select_age';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    return true;
                }
                else if (strpos($text, 'بیوگرافی') !== false) {
                    // بیوگرافی
                    $message = "📝 *بیوگرافی*\n\n";
                    $message .= "لطفاً متن بیوگرافی خود را ارسال کنید:";
                    
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '🔙 بازگشت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    // تغییر وضعیت کاربر
                    $user_state['step'] = 'enter_bio';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    return true;
                }
                else if (strpos($text, 'استان') !== false) {
                    // انتخاب استان
                    $message = "🌍 *استان*\n\n";
                    $message .= "لطفاً استان خود را انتخاب کنید:";
                    
                    // لیست استان‌های ایران
                    $provinces = [
                        'تهران', 'اصفهان', 'فارس', 'خراسان رضوی', 'آذربایجان شرقی', 'آذربایجان غربی',
                        'اردبیل', 'البرز', 'ایلام', 'بوشهر', 'چهارمحال و بختیاری', 'خراسان جنوبی',
                        'خراسان شمالی', 'خوزستان', 'زنجان', 'سمنان', 'سیستان و بلوچستان', 'قزوین',
                        'قم', 'کردستان', 'کرمان', 'کرمانشاه', 'کهگیلویه و بویراحمد', 'گلستان',
                        'گیلان', 'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد'
                    ];
                    
                    // ساخت دکمه‌های استان
                    $keyboard = [
                        'keyboard' => [],
                        'resize_keyboard' => true
                    ];
                    
                    foreach ($provinces as $province) {
                        $keyboard['keyboard'][] = [['text' => $province]];
                    }
                    
                    // دکمه ترجیح ندادن و بازگشت
                    $keyboard['keyboard'][] = [['text' => 'ترجیح میدهم نگویم']];
                    $keyboard['keyboard'][] = [['text' => '🔙 بازگشت']];
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, json_encode($keyboard));
                    }
                    
                    // تغییر وضعیت کاربر
                    $user_state['step'] = 'select_province';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    return true;
                }
                else if (strpos($text, 'شهر') !== false) {
                    // اطلاعات پروفایل کاربر
                    $profile = $profileController->getProfile();
                    
                    if (!$profile || !$profile['profile'] || !isset($profile['profile']['province']) || !$profile['profile']['province']) {
                        $message = "⚠️ *خطا*\n\n";
                        $message .= "برای انتخاب شهر ابتدا باید استان خود را انتخاب کنید.";
                        
                        if (function_exists('sendMessage')) {
                            sendMessage($token, $chat_id, $message);
                        }
                        
                        return true;
                    }
                    
                    // انتخاب شهر
                    $province = $profile['profile']['province'];
                    $message = "🏙️ *شهر*\n\n";
                    $message .= "لطفاً شهر خود در استان {$province} را انتخاب کنید:";
                    
                    // دریافت لیست شهرهای استان
                    $cities = self::getCitiesForProvince($province);
                    
                    // ساخت دکمه‌های شهر
                    $keyboard = [
                        'keyboard' => [],
                        'resize_keyboard' => true
                    ];
                    
                    foreach ($cities as $city) {
                        $keyboard['keyboard'][] = [['text' => $city]];
                    }
                    
                    // دکمه ترجیح ندادن و بازگشت
                    $keyboard['keyboard'][] = [['text' => 'ترجیح میدهم نگویم']];
                    $keyboard['keyboard'][] = [['text' => '🔙 بازگشت']];
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, json_encode($keyboard));
                    }
                    
                    // تغییر وضعیت کاربر
                    $user_state['step'] = 'select_city';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    return true;
                }
                else if (strpos($text, 'موقعیت مکانی') !== false) {
                    // درخواست موقعیت مکانی
                    $message = "📍 *موقعیت مکانی*\n\n";
                    $message .= "لطفاً موقعیت مکانی خود را ارسال کنید:";
                    
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '📍 ارسال موقعیت مکانی', 'request_location' => true]],
                            [['text' => 'ترجیح میدهم نگویم']],
                            [['text' => '🔙 بازگشت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    // تغییر وضعیت کاربر
                    $user_state['step'] = 'send_location';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    return true;
                }
                else if (strpos($text, 'شماره تلفن') !== false) {
                    // درخواست شماره تلفن
                    $message = "📱 *شماره تلفن*\n\n";
                    $message .= "لطفاً شماره تلفن خود را ارسال کنید:";
                    
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '📱 ارسال شماره تلفن', 'request_contact' => true]],
                            [['text' => 'ترجیح میدهم نگویم']],
                            [['text' => '🔙 بازگشت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    // تغییر وضعیت کاربر
                    $user_state['step'] = 'send_phone';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    return true;
                }
                else if (strpos($text, 'تغییر نام کاربری') !== false) {
                    // درخواست تغییر نام کاربری
                    
                    // دریافت اطلاعات کاربر
                    $user = DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->first();
                        
                    // دریافت اطلاعات اضافی کاربر
                    $userExtra = DB::table('users_extra')
                        ->where('user_id', $user['id'])
                        ->first();
                        
                    // بررسی کافی بودن دلتا کوین
                    if (!$userExtra || $userExtra['delta_coins'] < 10) {
                        $delta_coins = $userExtra ? $userExtra['delta_coins'] : 0;
                        
                        $message = "⚠️ *تغییر نام کاربری*\n\n";
                        $message .= "موجودی شما {$delta_coins} دلتا کوین است.\n";
                        $message .= "برای تغییر نام کاربری نیاز به حداقل ۱۰ دلتا کوین دارید!";
                        
                        if (function_exists('sendMessage')) {
                            sendMessage($token, $chat_id, $message);
                        }
                        
                        return true;
                    }
                    
                    // درخواست نام کاربری جدید
                    $message = "🔄 *تغییر نام کاربری*\n\n";
                    $message .= "شما میتوانید با 10 دلتاکوین نام کاربری خود را عوض کنید.\n";
                    $message .= "چنانچه قصد تغییر آن را دارید، نام کاربری جدیدتان را ارسال کنید.\n\n";
                    $message .= "نام کاربری فعلی: /" . ($user['username'] ?? 'بدون نام کاربری');
                    
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '🔙 بازگشت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    // تغییر وضعیت کاربر
                    $user_state['step'] = 'change_username';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    return true;
                }
                else if (strpos($text, 'بازگشت') !== false) {
                    // بازگشت به منوی اصلی
                    $mainMenu = [
                        'state' => 'main_menu',
                        'step' => ''
                    ];
                    
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($mainMenu)]);
                        
                    // ارسال منوی اصلی
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                            [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                            [['text' => '👤 حساب کاربری'], ['text' => '❓ راهنما']],
                            [['text' => '⚙️ پنل مدیریت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    $message = "به منوی اصلی بازگشتید. لطفاً یکی از گزینه‌ها را انتخاب کنید:";
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    return true;
                }
                break;
                
            case 'upload_photo':
                // آپلود عکس پروفایل
                if (isset($message['photo'])) {
                    // دریافت آیدی فایل بزرگترین نسخه عکس
                    $photo = end($message['photo']);
                    $file_id = $photo['file_id'];
                    
                    // ذخیره آیدی فایل و منتظر تایید ادمین
                    $result = $profileController->uploadProfilePhoto($file_id);
                    
                    if ($result['success']) {
                        $message = "✅ *آپلود عکس*\n\n";
                        $message .= "عکس پروفایل شما با موفقیت آپلود شد و در انتظار تایید ادمین است.";
                    } else {
                        $message = "❌ *خطا*\n\n";
                        $message .= $result['message'];
                    }
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                } else if (strpos($text, 'بازگشت') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                } else {
                    // پیام خطا
                    $message = "⚠️ لطفاً یک عکس ارسال کنید یا برای بازگشت به منوی پروفایل، دکمه «بازگشت» را بزنید.";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                break;
                
            case 'enter_name':
                // ثبت نام
                if (strpos($text, 'بازگشت') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // ثبت نام
                $result = $profileController->setName($text);
                
                if ($result['success']) {
                    $message = "✅ *نام*\n\n";
                    $message .= "نام شما با موفقیت ثبت شد: " . $result['name'];
                } else {
                    $message = "❌ *خطا*\n\n";
                    $message .= $result['message'];
                }
                
                if (function_exists('sendMessage')) {
                    sendMessage($token, $chat_id, $message);
                }
                
                // بازگشت به منوی پروفایل
                $user_state['step'] = 'main_menu';
                DB::table('users')
                    ->where('telegram_id', $user_id)
                    ->update(['state' => json_encode($user_state)]);
                    
                // نمایش منوی پروفایل
                self::showProfileMenu($token, $chat_id);
                
                return true;
                break;
                
            case 'select_gender':
                // انتخاب جنسیت
                if (strpos($text, 'بازگشت') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                $gender = '';
                if (strpos($text, 'مرد') !== false) {
                    $gender = 'male';
                } else if (strpos($text, 'زن') !== false) {
                    $gender = 'female';
                } else {
                    // پیام خطا
                    $message = "⚠️ لطفاً از دکمه‌های ارائه شده استفاده کنید یا برای بازگشت به منوی پروفایل، دکمه «بازگشت» را بزنید.";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                
                // ثبت جنسیت
                $result = $profileController->setGender($gender);
                
                if ($result['success']) {
                    $message = "✅ *جنسیت*\n\n";
                    $message .= "جنسیت شما با موفقیت ثبت شد: " . $result['gender_text'];
                } else {
                    $message = "❌ *خطا*\n\n";
                    $message .= $result['message'];
                }
                
                if (function_exists('sendMessage')) {
                    sendMessage($token, $chat_id, $message);
                }
                
                // بازگشت به منوی پروفایل
                $user_state['step'] = 'main_menu';
                DB::table('users')
                    ->where('telegram_id', $user_id)
                    ->update(['state' => json_encode($user_state)]);
                    
                // نمایش منوی پروفایل
                self::showProfileMenu($token, $chat_id);
                
                return true;
                break;
                
            case 'select_age':
                // انتخاب سن
                if (strpos($text, 'بازگشت') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // بررسی عدد بودن و محدوده
                if (!is_numeric($text) || intval($text) < 9 || intval($text) > 70) {
                    // پیام خطا
                    $message = "⚠️ لطفاً از دکمه‌های ارائه شده استفاده کنید یا برای بازگشت به منوی پروفایل، دکمه «بازگشت» را بزنید.";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                
                // ثبت سن
                $result = $profileController->setAge(intval($text));
                
                if ($result['success']) {
                    $message = "✅ *سن*\n\n";
                    $message .= "سن شما با موفقیت ثبت شد: " . $result['age'];
                } else {
                    $message = "❌ *خطا*\n\n";
                    $message .= $result['message'];
                }
                
                if (function_exists('sendMessage')) {
                    sendMessage($token, $chat_id, $message);
                }
                
                // بازگشت به منوی پروفایل
                $user_state['step'] = 'main_menu';
                DB::table('users')
                    ->where('telegram_id', $user_id)
                    ->update(['state' => json_encode($user_state)]);
                    
                // نمایش منوی پروفایل
                self::showProfileMenu($token, $chat_id);
                
                return true;
                break;
                
            case 'enter_bio':
                // ثبت بیوگرافی
                if (strpos($text, 'بازگشت') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // ثبت بیوگرافی
                $result = $profileController->setBio($text);
                
                if ($result['success']) {
                    $message = "✅ *بیوگرافی*\n\n";
                    $message .= "بیوگرافی شما با موفقیت ثبت شد و در انتظار تایید ادمین است.";
                } else {
                    $message = "❌ *خطا*\n\n";
                    $message .= $result['message'];
                }
                
                if (function_exists('sendMessage')) {
                    sendMessage($token, $chat_id, $message);
                }
                
                // بازگشت به منوی پروفایل
                $user_state['step'] = 'main_menu';
                DB::table('users')
                    ->where('telegram_id', $user_id)
                    ->update(['state' => json_encode($user_state)]);
                    
                // نمایش منوی پروفایل
                self::showProfileMenu($token, $chat_id);
                
                return true;
                break;
                
            case 'select_province':
                // انتخاب استان
                if (strpos($text, 'بازگشت') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // ترجیح ندادن
                if (strpos($text, 'ترجیح میدهم نگویم') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // لیست استان‌های ایران
                $provinces = [
                    'تهران', 'اصفهان', 'فارس', 'خراسان رضوی', 'آذربایجان شرقی', 'آذربایجان غربی',
                    'اردبیل', 'البرز', 'ایلام', 'بوشهر', 'چهارمحال و بختیاری', 'خراسان جنوبی',
                    'خراسان شمالی', 'خوزستان', 'زنجان', 'سمنان', 'سیستان و بلوچستان', 'قزوین',
                    'قم', 'کردستان', 'کرمان', 'کرمانشاه', 'کهگیلویه و بویراحمد', 'گلستان',
                    'گیلان', 'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد'
                ];
                
                // بررسی اعتبار استان
                if (!in_array($text, $provinces)) {
                    // پیام خطا
                    $message = "⚠️ لطفاً از دکمه‌های ارائه شده استفاده کنید یا برای بازگشت به منوی پروفایل، دکمه «بازگشت» را بزنید.";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                
                // ثبت استان
                $result = $profileController->setProvince($text);
                
                if ($result['success']) {
                    $message = "✅ *استان*\n\n";
                    $message .= "استان شما با موفقیت ثبت شد: " . $result['province'];
                    
                    // درخواست انتخاب شهر
                    $message .= "\n\nلطفاً شهر خود را انتخاب کنید:";
                    
                    // دریافت لیست شهرهای استان
                    $cities = self::getCitiesForProvince($text);
                    
                    // ساخت دکمه‌های شهر
                    $keyboard = [
                        'keyboard' => [],
                        'resize_keyboard' => true
                    ];
                    
                    foreach ($cities as $city) {
                        $keyboard['keyboard'][] = [['text' => $city]];
                    }
                    
                    // دکمه ترجیح ندادن و بازگشت
                    $keyboard['keyboard'][] = [['text' => 'ترجیح میدهم نگویم']];
                    $keyboard['keyboard'][] = [['text' => '🔙 بازگشت']];
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, json_encode($keyboard));
                    }
                    
                    // تغییر وضعیت کاربر
                    $user_state['step'] = 'select_city';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                } else {
                    $message = "❌ *خطا*\n\n";
                    $message .= $result['message'];
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                }
                
                return true;
                break;
                
            case 'select_city':
                // انتخاب شهر
                if (strpos($text, 'بازگشت') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // ترجیح ندادن
                if (strpos($text, 'ترجیح میدهم نگویم') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // دریافت اطلاعات پروفایل کاربر
                $profile = $profileController->getProfile();
                
                if (!$profile || !$profile['profile'] || !isset($profile['profile']['province']) || !$profile['profile']['province']) {
                    $message = "⚠️ *خطا*\n\n";
                    $message .= "برای انتخاب شهر ابتدا باید استان خود را انتخاب کنید.";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // دریافت لیست شهرهای استان
                $province = $profile['profile']['province'];
                $cities = self::getCitiesForProvince($province);
                
                // بررسی اعتبار شهر
                if (!in_array($text, $cities)) {
                    // پیام خطا
                    $message = "⚠️ لطفاً از دکمه‌های ارائه شده استفاده کنید یا برای بازگشت به منوی پروفایل، دکمه «بازگشت» را بزنید.";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                
                // ثبت شهر
                $result = $profileController->setCity($text);
                
                if ($result['success']) {
                    $message = "✅ *شهر*\n\n";
                    $message .= "شهر شما با موفقیت ثبت شد: " . $result['city'];
                } else {
                    $message = "❌ *خطا*\n\n";
                    $message .= $result['message'];
                }
                
                if (function_exists('sendMessage')) {
                    sendMessage($token, $chat_id, $message);
                }
                
                // بازگشت به منوی پروفایل
                $user_state['step'] = 'main_menu';
                DB::table('users')
                    ->where('telegram_id', $user_id)
                    ->update(['state' => json_encode($user_state)]);
                    
                // نمایش منوی پروفایل
                self::showProfileMenu($token, $chat_id);
                
                return true;
                break;
                
            case 'send_location':
                // ارسال موقعیت مکانی
                if (strpos($text, 'بازگشت') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // ترجیح ندادن
                if (strpos($text, 'ترجیح میدهم نگویم') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // دریافت موقعیت مکانی
                if (isset($message['location'])) {
                    $latitude = $message['location']['latitude'];
                    $longitude = $message['location']['longitude'];
                    
                    // ثبت موقعیت مکانی
                    $result = $profileController->setLocation($latitude, $longitude);
                    
                    if ($result['success']) {
                        $message = "✅ *موقعیت مکانی*\n\n";
                        $message .= "موقعیت مکانی شما با موفقیت ثبت شد.";
                    } else {
                        $message = "❌ *خطا*\n\n";
                        $message .= $result['message'];
                    }
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                } else {
                    // پیام خطا
                    $message = "⚠️ لطفاً از دکمه‌های ارائه شده استفاده کنید یا برای بازگشت به منوی پروفایل، دکمه «بازگشت» را بزنید.";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                break;
                
            case 'send_phone':
                // ارسال شماره تلفن
                if (strpos($text, 'بازگشت') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // ترجیح ندادن
                if (strpos($text, 'ترجیح میدهم نگویم') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // دریافت شماره تلفن
                if (isset($message['contact'])) {
                    $phone = $message['contact']['phone_number'];
                    
                    // ثبت شماره تلفن
                    $result = $profileController->setPhone($phone);
                    
                    if ($result['success']) {
                        $message = "✅ *شماره تلفن*\n\n";
                        $message .= "شماره تلفن شما با موفقیت ثبت شد.";
                    } else {
                        $message = "❌ *خطا*\n\n";
                        $message .= $result['message'];
                    }
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                } else if (is_string($text) && (preg_match('/^(?:\+|00)?98\d{10}$/', $text) || preg_match('/^0\d{10}$/', $text))) {
                    // ثبت شماره تلفن
                    $result = $profileController->setPhone($text);
                    
                    if ($result['success']) {
                        $message = "✅ *شماره تلفن*\n\n";
                        $message .= "شماره تلفن شما با موفقیت ثبت شد.";
                    } else {
                        $message = "❌ *خطا*\n\n";
                        $message .= $result['message'];
                    }
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                } else {
                    // پیام خطا
                    $message = "⚠️ لطفاً از دکمه‌های ارائه شده استفاده کنید یا یک شماره تلفن ایرانی معتبر وارد کنید.";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                break;
                
            case 'change_username':
                // تغییر نام کاربری
                if (strpos($text, 'بازگشت') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                // پاکسازی نام کاربری
                $username = trim($text);
                $username = ltrim($username, '@/');
                
                // بررسی نام کاربری
                if (!preg_match('/^[a-zA-Z0-9_]{5,32}$/', $username)) {
                    $message = "⚠️ *خطا*\n\n";
                    $message .= "نام کاربری باید بین ۵ تا ۳۲ کاراکتر و شامل حروف انگلیسی، اعداد و زیرخط باشد.";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                
                // درخواست تایید
                $message = "❓ *تغییر نام کاربری*\n\n";
                $message .= "آیا مطمئنید میخواهید {$username} را برای نام کاربری خود استفاده کنید؟\n";
                $message .= "با این کار ۱۰ دلتا کوین از حساب شما کسر خواهد شد.";
                
                $keyboard = json_encode([
                    'keyboard' => [
                        [['text' => '✅ بله']],
                        [['text' => '❌ خیر']]
                    ],
                    'resize_keyboard' => true
                ]);
                
                if (function_exists('sendMessageWithKeyboard')) {
                    sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                }
                
                // تغییر وضعیت کاربر
                $user_state['step'] = 'confirm_username_change';
                $user_state['username'] = $username;
                DB::table('users')
                    ->where('telegram_id', $user_id)
                    ->update(['state' => json_encode($user_state)]);
                    
                return true;
                break;
                
            case 'confirm_username_change':
                // تایید تغییر نام کاربری
                if (strpos($text, 'خیر') !== false) {
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                
                if (strpos($text, 'بله') !== false) {
                    // تغییر نام کاربری
                    $username = $user_state['username'] ?? '';
                    
                    if (empty($username)) {
                        $message = "⚠️ *خطا*\n\n";
                        $message .= "نام کاربری نامعتبر است. لطفاً مجدد تلاش کنید.";
                        
                        if (function_exists('sendMessage')) {
                            sendMessage($token, $chat_id, $message);
                        }
                        
                        // بازگشت به منوی پروفایل
                        $user_state['step'] = 'main_menu';
                        DB::table('users')
                            ->where('telegram_id', $user_id)
                            ->update(['state' => json_encode($user_state)]);
                            
                        // نمایش منوی پروفایل
                        self::showProfileMenu($token, $chat_id);
                        
                        return true;
                    }
                    
                    // تغییر نام کاربری
                    $result = $profileController->changeUsername($username);
                    
                    if ($result['success']) {
                        $message = "✅ *تغییر نام کاربری*\n\n";
                        $message .= $result['message'];
                    } else {
                        $message = "❌ *خطا*\n\n";
                        $message .= $result['message'];
                    }
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    // بازگشت به منوی پروفایل
                    $user_state['step'] = 'main_menu';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // نمایش منوی پروفایل
                    self::showProfileMenu($token, $chat_id);
                    
                    return true;
                }
                break;
        }
        
        return false;
    }
    
    /**
     * پردازش برداشت دلتا کوین
     * @param int $user_id آیدی کاربر
     * @param int $chat_id آیدی چت
     * @param array $user_state وضعیت کاربر
     * @param string $text متن پیام
     * @param array $message داده‌های پیام
     * @param string $token توکن تلگرام
     * @return bool
     */
    public static function processWithdrawal($user_id, $chat_id, $user_state, $text, $message, $token)
    {
        if (!isset($user_state['state']) || $user_state['state'] !== 'withdrawal') {
            return false;
        }
        
        $withdrawalController = new WithdrawalController($user_id);
        $step = $user_state['step'] ?? '';
        
        switch ($step) {
            case 'enter_amount':
                // ورود مقدار برداشت
                if (strpos($text, 'بازگشت') !== false || strpos($text, 'لغو') !== false) {
                    // بازگشت به منوی اصلی
                    $mainMenu = [
                        'state' => 'main_menu',
                        'step' => ''
                    ];
                    
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($mainMenu)]);
                        
                    // ارسال منوی اصلی
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                            [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                            [['text' => '👤 حساب کاربری'], ['text' => '❓ راهنما']],
                            [['text' => '⚙️ پنل مدیریت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    $message = "به منوی اصلی بازگشتید. لطفاً یکی از گزینه‌ها را انتخاب کنید:";
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    return true;
                }
                
                // بررسی ورودی
                if (!is_numeric($text)) {
                    $message = "⚠️ *خطا*\n\n";
                    $message .= "فقط از اعداد انگلیسی یا فارسی استفاده کنید!";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                
                // تبدیل به عدد
                $amount = intval($text);
                
                // دریافت حداقل مقدار برداشت
                $minWithdrawalAmount = $withdrawalController->getMinWithdrawalAmount();
                
                // بررسی حداقل مقدار
                if ($amount < $minWithdrawalAmount) {
                    $message = "⚠️ *خطا*\n\n";
                    $message .= "حداقل برداشت دلتا کوین {$minWithdrawalAmount} عدد میباشد!";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                
                // بررسی مضرب 10 بودن
                $step = $withdrawalController->getWithdrawalStep();
                if ($amount % $step !== 0) {
                    // گرد کردن به نزدیکترین مضرب
                    $amount = floor($amount / $step) * $step;
                    
                    $message = "⚠️ *خطا*\n\n";
                    $message .= "مقدار برداشت باید مضربی از {$step} باشد. مقدار درخواستی شما به {$amount} تغییر یافت.";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                }
                
                // بررسی موجودی
                $user = DB::table('users')
                    ->where('telegram_id', $user_id)
                    ->first();
                    
                $userExtra = DB::table('users_extra')
                    ->where('user_id', $user['id'])
                    ->first();
                    
                if (!$userExtra || $userExtra['delta_coins'] < $amount) {
                    $delta_coins = $userExtra ? $userExtra['delta_coins'] : 0;
                    
                    $message = "⚠️ *خطا*\n\n";
                    $message .= "موجودی شما {$delta_coins} دلتا کوین میباشد. مقدار وارد شده بیشتر از موجودی میباشد!";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                
                // ذخیره مقدار برداشت
                $user_state['amount'] = $amount;
                
                // درخواست نوع برداشت
                $message = "💰 *نوع برداشت*\n\n";
                $message .= "نوع برداشت به چه صورت باشد؟\n\n";
                $message .= "برداشت ترونی(TRX): واریز کمتر از 5 دقیقه\n";
                $message .= "برداشت بانکی: واریز نیم ساعت الی 6 ساعت\n\n";
                $message .= "از دکمه‌های زیر انتخاب کنید:";
                
                $keyboard = json_encode([
                    'keyboard' => [
                        [['text' => '💎 برداشت ترونی'], ['text' => '🏦 برداشت بانکی']],
                        [['text' => '🔙 بازگشت']]
                    ],
                    'resize_keyboard' => true
                ]);
                
                if (function_exists('sendMessageWithKeyboard')) {
                    sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                }
                
                // تغییر وضعیت کاربر
                $user_state['step'] = 'select_withdrawal_type';
                DB::table('users')
                    ->where('telegram_id', $user_id)
                    ->update(['state' => json_encode($user_state)]);
                    
                return true;
                break;
                
            case 'select_withdrawal_type':
                // انتخاب نوع برداشت
                if (strpos($text, 'بازگشت') !== false) {
                    // برگشت به مرحله قبل
                    $user_state['step'] = 'enter_amount';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // درخواست مقدار برداشت
                    $user = DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->first();
                        
                    $userExtra = DB::table('users_extra')
                        ->where('user_id', $user['id'])
                        ->first();
                        
                    $delta_coins = $userExtra ? $userExtra['delta_coins'] : 0;
                    
                    $message = "💸 *برداشت موجودی*\n\n";
                    $message .= "دوست عزیز لطفا مقدارِ دلتا کوین که میخواهید به ریال یا ارز دیگر تبدیل کنید را وارد کنید.\n\n";
                    $message .= "این مقدار نباید کمتر از 50 عدد باشد و باید مضربی از 10 باشد مانند: 50، 60، 100 و ...\n\n";
                    $message .= "موجودی دلتا کوین شما: {$delta_coins}";
                    
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '🔙 بازگشت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    return true;
                }
                
                $type = '';
                if (strpos($text, 'ترونی') !== false) {
                    $type = 'trx';
                } else if (strpos($text, 'بانکی') !== false) {
                    $type = 'bank';
                } else {
                    // پیام خطا
                    $message = "⚠️ لطفاً از دکمه‌های ارائه شده استفاده کنید.";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                
                // ذخیره نوع برداشت
                $user_state['type'] = $type;
                
                // درخواست آدرس کیف پول یا شماره کارت
                if ($type === 'trx') {
                    $message = "💎 *برداشت ترونی*\n\n";
                    $message .= "آدرس کیف پول ترون مورد نظر را جهت واریز ارسال کنید.\n\n";
                    $message .= "توجه: ارز پرداختی قابل بازگشت نیست. لطفا از صحیح بودن آدرس کیف پول ارسالی اطمینان حاصل کنید!";
                } else {
                    $message = "🏦 *برداشت بانکی*\n\n";
                    $message .= "شماره کارت بانکی یا شماره شبای بانکی را جهت واریز ارسال کنید.\n\n";
                    $message .= "توجه: از صحیح بودن اطلاعات ارسالی اطمینان حاصل کنید!";
                }
                
                $keyboard = json_encode([
                    'keyboard' => [
                        [['text' => '🔙 بازگشت']]
                    ],
                    'resize_keyboard' => true
                ]);
                
                if (function_exists('sendMessageWithKeyboard')) {
                    sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                }
                
                // تغییر وضعیت کاربر
                $user_state['step'] = 'enter_wallet';
                DB::table('users')
                    ->where('telegram_id', $user_id)
                    ->update(['state' => json_encode($user_state)]);
                    
                return true;
                break;
                
            case 'enter_wallet':
                // ورود آدرس کیف پول یا شماره کارت
                if (strpos($text, 'بازگشت') !== false) {
                    // برگشت به مرحله قبل
                    $user_state['step'] = 'select_withdrawal_type';
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // درخواست نوع برداشت
                    $message = "💰 *نوع برداشت*\n\n";
                    $message .= "نوع برداشت به چه صورت باشد؟\n\n";
                    $message .= "برداشت ترونی(TRX): واریز کمتر از 5 دقیقه\n";
                    $message .= "برداشت بانکی: واریز نیم ساعت الی 6 ساعت\n\n";
                    $message .= "از دکمه‌های زیر انتخاب کنید:";
                    
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '💎 برداشت ترونی'], ['text' => '🏦 برداشت بانکی']],
                            [['text' => '🔙 بازگشت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    return true;
                }
                
                // ذخیره آدرس کیف پول یا شماره کارت
                $user_state['wallet'] = $text;
                
                // نمایش اطلاعات برای تایید
                $amount = $user_state['amount'];
                $type = $user_state['type'];
                $wallet = $user_state['wallet'];
                
                // محاسبه مبلغ به تومان
                $delta_coin_price = $withdrawalController->getDeltaCoinPrice();
                $amount_toman = $amount * $delta_coin_price;
                
                $message = "📋 *تایید اطلاعات*\n\n";
                $message .= "مقدار برداشتی: {$amount} دلتا کوین\n";
                
                if ($type === 'bank') {
                    $message .= "مقدار برداشتی به تومان: " . number_format($amount_toman) . " تومان\n";
                    $message .= "شماره کارت/شبای ارسالی: {$wallet}\n";
                } else {
                    $message .= "مقدار برداشتی به ترون: " . number_format($amount_toman / 100000) . " TRX\n";
                    $message .= "آدرس کیف پول ارسالی: {$wallet}\n";
                }
                
                $message .= "\nآیا اطلاعات ارسال شده صحیح میباشد؟";
                
                $keyboard = json_encode([
                    'keyboard' => [
                        [['text' => '✅ بله']],
                        [['text' => '❌ خیر']]
                    ],
                    'resize_keyboard' => true
                ]);
                
                if (function_exists('sendMessageWithKeyboard')) {
                    sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                }
                
                // تغییر وضعیت کاربر
                $user_state['step'] = 'confirm_withdrawal';
                DB::table('users')
                    ->where('telegram_id', $user_id)
                    ->update(['state' => json_encode($user_state)]);
                    
                return true;
                break;
                
            case 'confirm_withdrawal':
                // تایید برداشت
                if (strpos($text, 'خیر') !== false) {
                    // برگشت به مرحله اول
                    $user_state['step'] = 'enter_amount';
                    
                    // پاک کردن اطلاعات قبلی
                    unset($user_state['amount']);
                    unset($user_state['type']);
                    unset($user_state['wallet']);
                    
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($user_state)]);
                        
                    // درخواست مقدار برداشت
                    $user = DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->first();
                        
                    $userExtra = DB::table('users_extra')
                        ->where('user_id', $user['id'])
                        ->first();
                        
                    $delta_coins = $userExtra ? $userExtra['delta_coins'] : 0;
                    
                    $message = "💸 *برداشت موجودی*\n\n";
                    $message .= "دوست عزیز لطفا مقدارِ دلتا کوین که میخواهید به ریال یا ارز دیگر تبدیل کنید را وارد کنید.\n\n";
                    $message .= "این مقدار نباید کمتر از 50 عدد باشد و باید مضربی از 10 باشد مانند: 50، 60، 100 و ...\n\n";
                    $message .= "موجودی دلتا کوین شما: {$delta_coins}";
                    
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '🔙 بازگشت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    return true;
                }
                
                if (strpos($text, 'بله') !== false) {
                    // ثبت درخواست برداشت
                    $amount = $user_state['amount'];
                    $type = $user_state['type'];
                    $wallet = $user_state['wallet'];
                    
                    $result = $withdrawalController->createWithdrawalRequest($amount, $type, $wallet);
                    
                    if ($result['success']) {
                        $message = "✅ *برداشت موجودی*\n\n";
                        
                        if ($type === 'bank') {
                            $message .= "وجه شما نیم ساعت الی 6 ساعت دیگر واریز خواهد شد.";
                        } else {
                            $message .= "مقدار ارز شما کمتر از 10 دقیقه واریز خواهد شد.";
                        }
                    } else {
                        $message = "❌ *خطا*\n\n";
                        $message .= $result['message'];
                    }
                    
                    // منوی اصلی
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                            [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                            [['text' => '👤 حساب کاربری'], ['text' => '❓ راهنما']],
                            [['text' => '⚙️ پنل مدیریت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    if (function_exists('sendMessageWithKeyboard')) {
                        sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
                    }
                    
                    // بازگشت به منوی اصلی
                    $mainMenu = [
                        'state' => 'main_menu',
                        'step' => ''
                    ];
                    
                    DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($mainMenu)]);
                        
                    return true;
                }
                
                // پیام خطا
                $message = "⚠️ لطفاً از دکمه‌های ارائه شده استفاده کنید.";
                
                if (function_exists('sendMessage')) {
                    sendMessage($token, $chat_id, $message);
                }
                
                return true;
                break;
        }
        
        return false;
    }
    
    /**
     * پردازش چت پس از بازی
     * @param int $user_id آیدی کاربر
     * @param int $chat_id آیدی چت
     * @param array $user_state وضعیت کاربر
     * @param string $text متن پیام
     * @param array $message داده‌های پیام
     * @param string $token توکن تلگرام
     * @return bool
     */
    public static function processPostGameChat($user_id, $chat_id, $user_state, $text, $message, $token)
    {
        if (!isset($user_state['state']) || $user_state['state'] !== 'post_game_chat') {
            return false;
        }
        
        $chatController = new ChatController($user_id);
        $step = $user_state['step'] ?? '';
        $match_id = $user_state['match_id'] ?? 0;
        $opponent_id = $user_state['opponent_id'] ?? 0;
        
        // بررسی وضعیت چت
        $chatStatus = $chatController->getChatStatus($match_id);
        
        if (!$chatStatus['success']) {
            // بازگشت به منوی اصلی
            $mainMenu = [
                'state' => 'main_menu',
                'step' => ''
            ];
            
            DB::table('users')
                ->where('telegram_id', $user_id)
                ->update(['state' => json_encode($mainMenu)]);
                
            // ارسال منوی اصلی
            $keyboard = json_encode([
                'keyboard' => [
                    [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                    [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                    [['text' => '👤 حساب کاربری'], ['text' => '❓ راهنما']],
                    [['text' => '⚙️ پنل مدیریت']]
                ],
                'resize_keyboard' => true
            ]);
            
            $message = "بسیار خب. بازی شما به اتمام رسید. چه کاری میتونم برات انجام بدم؟";
            
            if (function_exists('sendMessageWithKeyboard')) {
                sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
            }
            
            return true;
        }
        
        // بررسی انقضای چت
        if ($chatStatus['is_expired']) {
            // بازگشت به منوی اصلی
            $mainMenu = [
                'state' => 'main_menu',
                'step' => ''
            ];
            
            DB::table('users')
                ->where('telegram_id', $user_id)
                ->update(['state' => json_encode($mainMenu)]);
                
            // ارسال منوی اصلی
            $keyboard = json_encode([
                'keyboard' => [
                    [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                    [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                    [['text' => '👤 حساب کاربری'], ['text' => '❓ راهنما']],
                    [['text' => '⚙️ پنل مدیریت']]
                ],
                'resize_keyboard' => true
            ]);
            
            $message = "زمان چت به پایان رسید. بسیار خب. بازی شما به اتمام رسید. چه کاری میتونم برات انجام بدم؟";
            
            if (function_exists('sendMessageWithKeyboard')) {
                sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
            }
            
            return true;
        }
        
        // بررسی فعال بودن چت
        if (!$chatStatus['is_active']) {
            // ارسال پیام خطا
            $message = "⚠️ قابلیت چت غیرفعال میباشد. پیام شما ارسال نشد!";
            
            if (function_exists('sendMessage')) {
                sendMessage($token, $chat_id, $message);
            }
            
            return true;
        }
        
        // بررسی فعال بودن چت حریف
        if (!$chatStatus['opponent_active']) {
            // ارسال پیام خطا
            $message = "⚠️ حریف شما چت را غیرفعال کرده است. پیام شما ارسال نشد!";
            
            $inline_keyboard = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '🔄 درخواست فعال‌سازی مجدد چت', 'callback_data' => "request_reactivate_chat_{$match_id}"]
                    ]
                ]
            ]);
            
            if (function_exists('sendMessage')) {
                sendMessage($token, $chat_id, $message, 'Markdown', $inline_keyboard);
            }
            
            return true;
        }
        
        switch ($step) {
            case 'chatting':
                // پردازش پیام
                // بررسی نوع پیام
                $allowedTypes = ['text'];
                $messageType = 'unknown';
                
                if (isset($message['text'])) {
                    $messageType = 'text';
                } else if (isset($message['sticker'])) {
                    $messageType = 'sticker';
                } else if (isset($message['photo'])) {
                    $messageType = 'photo';
                } else if (isset($message['voice'])) {
                    $messageType = 'voice';
                } else if (isset($message['video'])) {
                    $messageType = 'video';
                } else if (isset($message['document'])) {
                    $messageType = 'document';
                } else if (isset($message['animation'])) {
                    $messageType = 'animation';
                }
                
                // بررسی مجاز بودن نوع پیام
                if (!in_array($messageType, $allowedTypes)) {
                    // ارسال پیام خطا
                    $message = "⚠️ شما تنها مجاز به ارسال پیام بصورت متنی میباشید. پیام شما ارسال نشد!";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                
                // بررسی وجود لینک در متن
                if ($messageType === 'text' && preg_match('/(https?:\/\/[^\s]+)|(www\.[^\s]+)|([^\s]+\.(com|org|net|ir|io|me))/i', $text)) {
                    // ارسال پیام خطا
                    $message = "⚠️ ارسال لینک ممنوع میباشد! پیام شما ارسال نشد!";
                    
                    if (function_exists('sendMessage')) {
                        sendMessage($token, $chat_id, $message);
                    }
                    
                    return true;
                }
                
                // ارسال پیام به حریف
                $sent = false;
                
                if ($messageType === 'text') {
                    // ارسال متن
                    if (function_exists('sendMessage')) {
                        $user = DB::table('users')
                            ->where('telegram_id', $user_id)
                            ->first();
                            
                        $username = $user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name'];
                        
                        sendMessage($token, $opponent_id, "{$username}: {$text}");
                        $sent = true;
                    }
                }
                
                // ارسال منوی ری‌اکشن
                if ($sent) {
                    // دریافت همه ری‌اکشن‌های فعال
                    $reactions = $chatController->getAllReactions();
                    
                    if (isset($reactions['success']) && $reactions['success'] && isset($reactions['reactions']) && !empty($reactions['reactions'])) {
                        // ساخت دکمه‌های ری‌اکشن
                        $inline_keyboard = [[]];
                        $row = 0;
                        $col = 0;
                        
                        foreach ($reactions['reactions'] as $reaction) {
                            $inline_keyboard[$row][] = [
                                'text' => $reaction['emoji'],
                                'callback_data' => "reaction_{$user_id}_{$reaction['emoji']}"
                            ];
                            
                            $col++;
                            
                            // هر 5 ری‌اکشن در یک ردیف
                            if ($col >= 5) {
                                $row++;
                                $col = 0;
                                $inline_keyboard[$row] = [];
                            }
                        }
                        
                        // حذف ردیف خالی
                        if (empty($inline_keyboard[count($inline_keyboard) - 1])) {
                            array_pop($inline_keyboard);
                        }
                        
                        $reply_markup = json_encode([
                            'inline_keyboard' => $inline_keyboard
                        ]);
                        
                        if (function_exists('sendMessage')) {
                            sendMessage($token, $opponent_id, "ری‌اکشن به پیام ⬆️", 'Markdown', $reply_markup);
                        }
                    }
                }
                
                return true;
                break;
        }
        
        return false;
    }
    
    /**
     * نمایش منوی پروفایل
     * @param string $token توکن تلگرام
     * @param int $chat_id آیدی چت
     * @return void
     */
    private static function showProfileMenu($token, $chat_id)
    {
        $message = "👤 *حساب کاربری*\n\n";
        $message .= "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
        
        $keyboard = json_encode([
            'keyboard' => [
                [['text' => '🖼️ عکس پروفایل'], ['text' => '📝 نام']],
                [['text' => '👫 جنسیت'], ['text' => '🔢 سن']],
                [['text' => '📄 بیوگرافی'], ['text' => '🌏 استان']],
                [['text' => '🏙️ شهر'], ['text' => '📍 موقعیت مکانی']],
                [['text' => '📱 شماره تلفن'], ['text' => '🔄 تغییر نام کاربری']],
                [['text' => '🔙 بازگشت']]
            ],
            'resize_keyboard' => true
        ]);
        
        if (function_exists('sendMessageWithKeyboard')) {
            sendMessageWithKeyboard($token, $chat_id, $message, $keyboard);
        }
    }
    
    /**
     * دریافت لیست شهرهای استان
     * @param string $province نام استان
     * @return array
     */
    private static function getCitiesForProvince($province)
    {
        // لیست شهرهای استان‌های ایران (نمونه)
        $cities = [
            'تهران' => ['تهران', 'شهریار', 'اسلامشهر', 'ری', 'ورامین', 'دماوند', 'پردیس', 'پرند', 'پاکدشت', 'رباط کریم'],
            'اصفهان' => ['اصفهان', 'کاشان', 'نجف آباد', 'خمینی شهر', 'شاهین شهر', 'فولادشهر', 'مبارکه', 'بهارستان', 'گلپایگان', 'نطنز'],
            'فارس' => ['شیراز', 'مرودشت', 'جهرم', 'فسا', 'کازرون', 'لار', 'آباده', 'اقلید', 'داراب', 'استهبان'],
            'خراسان رضوی' => ['مشهد', 'نیشابور', 'سبزوار', 'تربت حیدریه', 'قوچان', 'کاشمر', 'گناباد', 'تربت جام', 'تایباد', 'چناران'],
            'آذربایجان شرقی' => ['تبریز', 'مراغه', 'مرند', 'سراب', 'اهر', 'بناب', 'میانه', 'هریس', 'آذرشهر', 'جلفا'],
            'آذربایجان غربی' => ['ارومیه', 'خوی', 'میاندوآب', 'بوکان', 'مهاباد', 'سلماس', 'پیرانشهر', 'نقده', 'سردشت', 'تکاب'],
            'اردبیل' => ['اردبیل', 'پارس آباد', 'مشگین شهر', 'خلخال', 'گرمی', 'نمین', 'نیر', 'بیله سوار', 'کوثر', 'سرعین'],
            'البرز' => ['کرج', 'فردیس', 'هشتگرد', 'نظرآباد', 'محمدشهر', 'مشکین دشت', 'کمالشهر', 'ماهدشت', 'اشتهارد', 'گرمدره'],
        ];
        
        // اگر استان در لیست نباشد
        if (!isset($cities[$province])) {
            // لیست عمومی
            return ['مرکز استان', 'شهر 1', 'شهر 2', 'شهر 3', 'شهر 4', 'شهر 5'];
        }
        
        return $cities[$province];
    }
}