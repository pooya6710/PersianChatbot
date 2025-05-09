<?php
/**
 * ربات ساده Long Polling فقط با قابلیت لغو بازی
 */
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
include(__DIR__ . "/system/Loader.php");
date_default_timezone_set('Asia/Tehran');

// تعریف توابع ارسال پیام تلگرام
// تابع ارسال پیام با کیبورد Inline
/**
 * دریافت اطلاعات ربات از API تلگرام
 * 
 * @param string $token توکن ربات
 * @return array اطلاعات ربات
 */
function getBotInfo($token) {
    $url = "https://api.telegram.org/bot$token/getMe";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        return ['username' => 'your_bot']; // مقدار پیش‌فرض در صورت خطا
    }
    
    curl_close($ch);
    $result = json_decode($response, true);
    
    if ($result['ok'] && isset($result['result'])) {
        return $result['result'];
    }
    
    return ['username' => 'your_bot']; // مقدار پیش‌فرض در صورت خطا
}

function sendMessageWithInlineKeyboard($token, $chat_id, $message, $keyboard, $parse_mode = 'Markdown') {
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => $parse_mode,
        'reply_markup' => $keyboard
    ];
    
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $options = [
        'http' => [
            'header' => "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data)
        ]
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return json_decode($result, true);
}

// تنظیم آخرین آپدیت دریافت شده
$lastUpdateIdFile = __DIR__ . '/last_update_id.txt';
if (file_exists($lastUpdateIdFile)) {
    $lastUpdateId = (int)file_get_contents($lastUpdateIdFile);
} else {
    $lastUpdateId = 0;
}

// ذخیره آخرین شناسه پردازش شده جدید
if (!file_exists($lastUpdateIdFile)) {
    file_put_contents($lastUpdateIdFile, "603369409");
    $lastUpdateId = 603369409; // آخرین آپدیت شناسایی شده فعلی
}

echo "ربات تلگرام اصلی (نسخه کمینه) در حال اجرا با روش Long Polling...\n";
echo "آخرین آپدیت شروع: {$lastUpdateId}\n";
echo "برای توقف، کلید Ctrl+C را فشار دهید.\n\n";

// حلقه اصلی برای دریافت پیام‌ها
while (true) {
    // دریافت آپدیت‌ها از تلگرام
    $updates = getUpdatesViaFopen($_ENV['TELEGRAM_TOKEN'], $lastUpdateId);
    
    if (!$updates || !isset($updates['result']) || empty($updates['result'])) {
        // اگر آپدیتی نبود، کمی صبر کن و دوباره تلاش کن
        sleep(1);
        echo ".";
        continue;
    }
    
    // پردازش هر آپدیت
    foreach ($updates['result'] as $update) {
        // به‌روزرسانی آخرین آی‌دی آپدیت و ذخیره در فایل
        $lastUpdateId = $update['update_id'] + 1;
        file_put_contents($lastUpdateIdFile, $lastUpdateId);
        
        echo "\nآپدیت جدید (ID: {$update['update_id']})\n";
        
        // پردازش callback query (دکمه‌های inline)
        if (isset($update['callback_query'])) {
            $callback_query = $update['callback_query'];
            $callback_data = $callback_query['data'];
            $chat_id = $callback_query['message']['chat']['id'];
            $message_id = $callback_query['message']['message_id'];
            $user_id = $callback_query['from']['id'];
            
            echo "کالبک کوئری دریافت شد: {$callback_data}\n";
            
            // پردازش عملیات مدیریت ادمین‌ها
            if (strpos($callback_data, 'admin_action:') === 0) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin()) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    // استخراج عملیات مورد نظر
                    $action = substr($callback_data, strlen('admin_action:'));
                    
                    // انجام عملیات بر اساس نوع آن
                    switch ($action) {
                        case 'add':
                            // درخواست آیدی کاربر جدید
                            $message = "👤 *افزودن ادمین جدید*\n\n";
                            $message .= "لطفاً آیدی عددی تلگرام یا نام کاربری شخص مورد نظر را وارد کنید:";
                            
                            // کیبورد لغو
                            $cancel_keyboard = json_encode([
                                'keyboard' => [
                                    [['text' => 'لغو ❌']]
                                ],
                                'resize_keyboard' => true
                            ]);
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $cancel_keyboard);
                            
                            // ذخیره وضعیت ادمین
                            $userState = [
                                'state' => 'admin_panel',
                                'step' => 'waiting_for_new_admin_id'
                            ];
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                                
                            echo "درخواست افزودن ادمین جدید دریافت شد\n";
                            break;
                            
                        case 'remove':
                            // درخواست آیدی کاربر برای حذف دسترسی
                            $message = "❌ *حذف ادمین*\n\n";
                            $message .= "لطفاً آیدی عددی تلگرام یا نام کاربری شخص مورد نظر را وارد کنید:";
                            
                            // کیبورد لغو
                            $cancel_keyboard = json_encode([
                                'keyboard' => [
                                    [['text' => 'لغو ❌']]
                                ],
                                'resize_keyboard' => true
                            ]);
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $cancel_keyboard);
                            
                            // ذخیره وضعیت ادمین
                            $userState = [
                                'state' => 'admin_panel',
                                'step' => 'waiting_for_admin_to_remove'
                            ];
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                                
                            echo "درخواست حذف ادمین دریافت شد\n";
                            break;
                            
                        case 'list':
                            // نمایش لیست ادمین‌ها
                            $result = $adminController->getAdminsList();
                            
                            if ($result['success']) {
                                $admins = $result['admins'];
                                $message = "📋 *لیست ادمین‌های ربات*\n\n";
                                
                                if (empty($admins)) {
                                    $message .= "هیچ ادمینی یافت نشد!";
                                } else {
                                    $i = 1;
                                    foreach ($admins as $admin) {
                                        $username = !empty($admin['username']) ? "@" . $admin['username'] : "-";
                                        $name = !empty($admin['name']) ? $admin['name'] : "بدون نام";
                                        $type = $admin['is_owner'] ? "👑 مالک" : "👮‍♂️ ادمین";
                                        
                                        $message .= "{$i}. *{$name}* ({$type})\n";
                                        $message .= "  • آیدی تلگرام: `{$admin['telegram_id']}`\n";
                                        $message .= "  • نام کاربری: {$username}\n";
                                        
                                        // نمایش دسترسی‌ها اگر وجود داشته باشند
                                        if (!empty($admin['permissions'])) {
                                            $perms = [];
                                            if (!empty($admin['permissions']['can_send_broadcasts']) && $admin['permissions']['can_send_broadcasts']) {
                                                $perms[] = "ارسال پیام همگانی ✅";
                                            }
                                            if (!empty($admin['permissions']['can_manage_admins']) && $admin['permissions']['can_manage_admins']) {
                                                $perms[] = "مدیریت ادمین‌ها ✅";
                                            }
                                            if (!empty($admin['permissions']['can_manage_users']) && $admin['permissions']['can_manage_users']) {
                                                $perms[] = "مدیریت کاربران ✅";
                                            }
                                            if (!empty($admin['permissions']['can_view_statistics']) && $admin['permissions']['can_view_statistics']) {
                                                $perms[] = "مشاهده آمار ✅";
                                            }
                                            
                                            if (!empty($perms)) {
                                                $message .= "  • دسترسی‌ها: " . implode(", ", $perms) . "\n";
                                            }
                                        }
                                        
                                        $message .= "\n";
                                        $i++;
                                    }
                                }
                                
                                // کیبورد بازگشت
                                $back_keyboard = json_encode([
                                    'inline_keyboard' => [
                                        [
                                            ['text' => '🔙 بازگشت به منوی مدیریت ادمین‌ها', 'callback_data' => 'admin:manage_admins']
                                        ]
                                    ]
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $back_keyboard);
                                echo "لیست ادمین‌ها ارسال شد\n";
                            } else {
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ خطا در پردازش عملیات مدیریت ادمین‌ها: " . $result['message']);
                                echo "خطا در پردازش عملیات مدیریت ادمین‌ها: " . $result['message'] . "\n";
                            }
                            break;
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش عملیات مدیریت ادمین‌ها: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // پردازش درخواست دوستی
            else if (strpos($callback_data, 'friend_request:') === 0) {
                try {
                    // استخراج آیدی کاربر هدف
                    $target_user_id = substr($callback_data, strlen('friend_request:'));
                    
                    // بررسی اینکه آیا کاربر قبلاً در دیتابیس ثبت شده است
                    $user = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$user) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: شما هنوز در سیستم ثبت نشده‌اید!");
                        echo "خطا: کاربر درخواست دهنده در دیتابیس یافت نشد\n";
                        continue;
                    }
                    
                    // بررسی اینکه آیا کاربر هدف در دیتابیس ثبت شده است
                    $target_user = \Application\Model\DB::table('users')->where('id', $target_user_id)->first();
                    if (!$target_user) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: کاربر مورد نظر یافت نشد!");
                        echo "خطا: کاربر هدف در دیتابیس یافت نشد\n";
                        continue;
                    }
                    
                    // بررسی اینکه کاربر به خودش درخواست دوستی نفرستد
                    if ($user['id'] == $target_user_id) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ شما نمی‌توانید به خودتان درخواست دوستی ارسال کنید!");
                        echo "خطا: درخواست دوستی به خود\n";
                        continue;
                    }
                    
                    // بررسی اینکه آیا کاربر قبلاً درخواست دوستی ارسال کرده است
                    $existing_request = \Application\Model\DB::table('friend_requests')
                        ->where('sender_id', $user['id'])
                        ->where('receiver_id', $target_user_id)
                        ->where('status', 'pending')
                        ->first();
                        
                    if ($existing_request) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ شما قبلاً به این کاربر درخواست دوستی ارسال کرده‌اید!");
                        echo "خطا: درخواست دوستی تکراری\n";
                        continue;
                    }
                    
                    // بررسی اینکه آیا دو کاربر قبلاً دوست هستند
                    $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $user['id'])->first();
                    if ($userExtra && isset($userExtra['friends'])) {
                        $friends = json_decode($userExtra['friends'], true);
                        if (is_array($friends) && in_array($target_user_id, $friends)) {
                            answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ شما و این کاربر در حال حاضر دوست هستید!");
                            echo "خطا: کاربران قبلاً دوست هستند\n";
                            continue;
                        }
                    }
                    
                    // ثبت درخواست دوستی در جدول friend_requests
                    \Application\Model\DB::table('friend_requests')->insert([
                        'sender_id' => $user['id'],
                        'receiver_id' => $target_user_id,
                        'status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // پاسخ به کاربر
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ درخواست دوستی با موفقیت ارسال شد!");
                    echo "درخواست دوستی از کاربر {$user['id']} به کاربر {$target_user_id} ثبت شد\n";
                    
                    // اطلاع‌رسانی به کاربر هدف
                    if (isset($target_user['telegram_id'])) {
                        $message = "🔔 شما یک درخواست دوستی جدید دارید!\n\nکاربر {$user['username']} شما را به عنوان دوست اضافه کرده است.\n\nبرای مشاهده درخواست‌های دوستی، به منوی دوستان > درخواست‌های دوستی بروید.";
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $target_user['telegram_id'], $message);
                        echo "اطلاع‌رسانی به کاربر هدف انجام شد\n";
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش درخواست دوستی: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در پردازش درخواست دوستی: " . $e->getMessage());
                }
            }
            
            // پردازش دکمه‌های شیشه‌ای پروفایل
            else if (strpos($callback_data, 'profile:') === 0) {
                try {
                    // استخراج عملیات موردنظر
                    $action = substr($callback_data, strlen('profile:'));
                    
                    // دریافت اطلاعات کاربر
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$userData) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات کاربری یافت نشد!");
                        continue;
                    }
                    
                    switch ($action) {
                        case 'edit_photo':
                            // ارسال درخواست عکس پروفایل
                            $message = "📝 *تکمیل پروفایل*\n\n";
                            $message .= "لطفاً یک عکس برای پروفایل خود ارسال کنید.";
                            
                            // ایجاد دکمه لغو
                            $cancel_keyboard = [
                                'keyboard' => [
                                    [
                                        ['text' => 'لغو ❌']
                                    ]
                                ],
                                'resize_keyboard' => true
                            ];
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, json_encode($cancel_keyboard));
                            
                            // ذخیره وضعیت کاربر
                            $userState = [
                                'state' => 'profile_completion',
                                'step' => 'waiting_for_photo'
                            ];
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                            
                            break;
                            
                        case 'edit_fullname':
                            // ارسال درخواست نام کامل
                            $message = "📝 *تکمیل پروفایل*\n\n";
                            $message .= "لطفاً نام کامل خود را وارد کنید.";
                            
                            $cancel_keyboard = [
                                'keyboard' => [
                                    [
                                        ['text' => 'لغو ❌']
                                    ]
                                ],
                                'resize_keyboard' => true
                            ];
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, json_encode($cancel_keyboard));
                            
                            // ذخیره وضعیت کاربر
                            $userState = [
                                'state' => 'profile_completion',
                                'step' => 'waiting_for_fullname'
                            ];
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                            
                            break;
                            
                        case 'edit_gender':
                            // ارسال درخواست انتخاب جنسیت
                            $message = "📝 *تکمیل پروفایل*\n\n";
                            $message .= "لطفاً جنسیت خود را انتخاب کنید.";
                            
                            $gender_keyboard = [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'مرد 👨', 'callback_data' => 'select_gender:male'],
                                        ['text' => 'زن 👩', 'callback_data' => 'select_gender:female']
                                    ],
                                    [
                                        ['text' => 'لغو ❌', 'callback_data' => 'profile:back']
                                    ]
                                ]
                            ];
                            
                            sendMessageWithInlineKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, json_encode($gender_keyboard));
                            
                            // ذخیره وضعیت کاربر
                            $userState = [
                                'state' => 'profile_completion',
                                'step' => 'waiting_for_gender'
                            ];
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                            
                            break;
                            
                        case 'edit_age':
                            // ارسال درخواست انتخاب سن
                            $message = "📝 *تکمیل پروفایل*\n\n";
                            $message .= "لطفاً سن خود را وارد کنید (بین 9 تا 70 سال).";
                            
                            // ساخت دکمه‌های عددی
                            $age_keyboard = ['inline_keyboard' => []];
                            $row = [];
                            for ($i = 9; $i <= 70; $i++) {
                                $row[] = ['text' => "$i", 'callback_data' => "select_age:$i"];
                                
                                if (count($row) == 5 || $i == 70) {
                                    $age_keyboard['inline_keyboard'][] = $row;
                                    $row = [];
                                }
                            }
                            $age_keyboard['inline_keyboard'][] = [['text' => 'لغو ❌', 'callback_data' => 'profile:back']];
                            
                            sendMessageWithInlineKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, json_encode($age_keyboard));
                            
                            // ذخیره وضعیت کاربر
                            $userState = [
                                'state' => 'profile_completion',
                                'step' => 'waiting_for_age'
                            ];
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                            
                            break;
                            
                        case 'back':
                            // بازگشت به منوی اصلی
                            $main_keyboard = json_encode([
                                'keyboard' => [
                                    [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                                    [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                                    [['text' => '👤 حساب کاربری'], ['text' => '🏆نفرات برتر•']],
                                    [['text' => '👨‍👦‍👦 وضعیت زیرمجموعه‌ها'], ['text' => '💰 دلتا کوین روزانه']],
                                    [['text' => '• پشتیبانی👨‍💻'], ['text' => '⁉️راهنما •']]
                                ],
                                'resize_keyboard' => true
                            ]);
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, "🔙 به منوی اصلی بازگشتید.", $main_keyboard);
                            
                            // حذف وضعیت کاربر
                            $userState = [
                                'state' => 'main_menu',
                                'step' => null
                            ];
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                            
                            break;
                            
                        default:
                            // برای سایر بخش‌های پروفایل (بیو، استان، شهر و...)
                            answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "این بخش در حال پیاده‌سازی است...");
                            
                            break;
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش دکمه‌های پروفایل: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در پردازش درخواست.");
                }
            }
            
            // پردازش انتخاب جنسیت
            else if (strpos($callback_data, 'select_gender:') === 0) {
                try {
                    $gender = substr($callback_data, strlen('select_gender:'));
                    
                    // دریافت اطلاعات کاربر
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$userData) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات کاربری یافت نشد!");
                        continue;
                    }
                    
                    // ذخیره جنسیت در پروفایل
                    $profile = \Application\Model\DB::table('user_profiles')->where('user_id', $userData['id'])->first();
                    
                    if ($profile) {
                        \Application\Model\DB::table('user_profiles')
                            ->where('user_id', $userData['id'])
                            ->update(['gender' => $gender]);
                    } else {
                        \Application\Model\DB::table('user_profiles')
                            ->insert([
                                'user_id' => $userData['id'],
                                'gender' => $gender,
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                    }
                    
                    // ارسال پیام تأیید
                    $gender_text = ($gender == 'male') ? 'مرد' : 'زن';
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ جنسیت شما {$gender_text} ثبت شد.");
                    
                    // بازگشت به منوی پروفایل
                    $message = "✅ جنسیت شما با موفقیت ثبت شد.\n\n";
                    $message .= "برای ادامه تکمیل پروفایل، روی دکمه «تکمیل پروفایل» کلیک کنید.";
                    
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                    
                } catch (Exception $e) {
                    echo "خطا در ثبت جنسیت: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در ثبت جنسیت.");
                }
            }
            
            // پردازش انتخاب سن
            else if (strpos($callback_data, 'select_age:') === 0) {
                try {
                    $age = (int)substr($callback_data, strlen('select_age:'));
                    
                    // دریافت اطلاعات کاربر
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$userData) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات کاربری یافت نشد!");
                        continue;
                    }
                    
                    // ذخیره سن در پروفایل
                    $profile = \Application\Model\DB::table('user_profiles')->where('user_id', $userData['id'])->first();
                    
                    if ($profile) {
                        \Application\Model\DB::table('user_profiles')
                            ->where('user_id', $userData['id'])
                            ->update(['age' => $age]);
                    } else {
                        \Application\Model\DB::table('user_profiles')
                            ->insert([
                                'user_id' => $userData['id'],
                                'age' => $age,
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                    }
                    
                    // ارسال پیام تأیید
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ سن شما {$age} سال ثبت شد.");
                    
                    // بازگشت به منوی پروفایل
                    $message = "✅ سن شما با موفقیت ثبت شد.\n\n";
                    $message .= "برای ادامه تکمیل پروفایل، روی دکمه «تکمیل پروفایل» کلیک کنید.";
                    
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                    
                } catch (Exception $e) {
                    echo "خطا در ثبت سن: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در ثبت سن.");
                }
            }
            
            // پردازش دکمه صدا زدن کاربر در بازی
            else if (strpos($callback_data, 'notify_opponent:') === 0) {
                try {
                    // استخراج آیدی بازی
                    $match_id = substr($callback_data, strlen('notify_opponent:'));
                    
                    // دریافت اطلاعات بازی
                    $match = \Application\Model\DB::table('matches')->where('id', $match_id)->first();
                    if (!$match) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: بازی مورد نظر یافت نشد!");
                        echo "خطا: بازی {$match_id} در دیتابیس یافت نشد\n";
                        continue;
                    }
                    
                    // تعیین حریف کاربر فعلی
                    $opponent_id = ($match['player1'] == $user_id) ? $match['player2'] : $match['player1'];
                    
                    if (!$opponent_id) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات حریف کامل نیست!");
                        echo "خطا: اطلاعات حریف در بازی {$match_id} کامل نیست\n";
                        continue;
                    }
                    
                    // به‌روزرسانی زمان آخرین کنش در بازی
                    \Application\Model\DB::table('matches')
                        ->where('id', $match_id)
                        ->update(['last_action_time' => date('Y-m-d H:i:s')]);
                    
                    // اطلاع‌رسانی به حریف
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $opponent_id, "🔔 نوبت توعه! بازی کن.");
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ به حریف شما اطلاع داده شد!");
                    echo "اطلاع‌رسانی به حریف با آیدی {$opponent_id} انجام شد\n";
                    
                } catch (Exception $e) {
                    echo "خطا در اطلاع‌رسانی به حریف: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در اطلاع‌رسانی به حریف: " . $e->getMessage());
                }
            }
            
            // پاسخ به نظرسنجی پایان بازی
            else if (strpos($callback_data, 'end_chat:') === 0) {
                try {
                    $parts = explode(':', $callback_data);
                    $match_id = $parts[1];
                    $action = $parts[2]; // extend یا end
                    
                    // دریافت اطلاعات بازی
                    $match = \Application\Model\DB::table('matches')->where('id', $match_id)->first();
                    if (!$match) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: بازی مورد نظر یافت نشد!");
                        echo "خطا: بازی {$match_id} در دیتابیس یافت نشد\n";
                        continue;
                    }
                    
                    if ($action === 'extend') {
                        // بررسی وجود ستون chat_end_time
                        try {
                            // افزایش زمان چت به 5 دقیقه
                            \Application\Model\DB::table('matches')
                                ->where('id', $match_id)
                                ->update(['chat_end_time' => date('Y-m-d H:i:s', strtotime('+5 minutes'))]);
                        } catch (Exception $e) {
                            // اگر ستون وجود نداشت، خطا را نادیده بگیر و تنها در لاگ ثبت کن
                            echo "خطا در به‌روزرسانی chat_end_time: " . $e->getMessage() . "\n";
                        }
                        
                        // اطلاع‌رسانی به هر دو بازیکن
                        $message = "مقدار زمان چتِ بعد از بازی شما به 5 دقیقه افزایش یافت";
                        
                        // ارسال به هر دو بازیکن
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $match['player1'], $message);
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $match['player2'], $message);
                        
                        // تنظیم تایمر برای اطلاع‌رسانی 30 ثانیه آخر
                        // در یک سیستم واقعی، این کار باید با کرون جاب انجام شود
                        
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ زمان چت به 5 دقیقه افزایش یافت.");
                        echo "زمان چت برای بازی {$match_id} به 5 دقیقه افزایش یافت\n";
                        
                        // ویرایش پیام نظرسنجی برای جلوگیری از انتخاب مجدد
                        $new_text = "زمان چت به 5 دقیقه افزایش یافت. ✅";
                        editMessageText($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $new_text);
                    } 
                    else if ($action === 'end') {
                        // درخواست تأیید برای قطع چت
                        $confirm_message = "آیا مطمئنید میخواهید قابلیت چت را غیرفعال کنید؟\nبا این اقدام دیگر در این بازی پیامی ارسال یا دریافت نخواهد شد!";
                        
                        $confirm_keyboard = json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => 'غیرفعال شود', 'callback_data' => "confirm_end_chat:{$match_id}:yes"],
                                    ['text' => 'فعال بماند', 'callback_data' => "confirm_end_chat:{$match_id}:no"]
                                ]
                            ]
                        ]);
                        
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $confirm_message, $confirm_keyboard);
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "درخواست تأیید برای غیرفعال کردن چت ارسال شد.");
                        
                        // ویرایش پیام نظرسنجی قبلی
                        $new_text = "در انتظار تأیید برای قطع چت...";
                        editMessageText($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $new_text);
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش نظرسنجی پایان بازی: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // پاسخ به تأیید تغییر نام کاربری
            else if (strpos($callback_data, 'confirm_username_change:') === 0) {
                try {
                    $parts = explode(':', $callback_data);
                    $new_username = $parts[1];
                    $response = $parts[2]; // yes یا no
                    
                    // دریافت اطلاعات کاربر
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$userData) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات کاربری یافت نشد!");
                        continue;
                    }
                    
                    // حذف فایل وضعیت کاربر
                    $user_state_file = __DIR__ . "/user_states/{$user_id}.json";
                    if (file_exists($user_state_file)) {
                        unlink($user_state_file);
                    }
                    
                    if ($response === 'yes') {
                        // دریافت اطلاعات اضافی کاربر برای کسر هزینه
                        $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $userData['id'])->first();
                        if (!$userExtra) {
                            answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات اضافی کاربر یافت نشد!");
                            continue;
                        }
                        
                        // بررسی کافی بودن موجودی
                        $delta_coins = isset($userExtra['delta_coins']) ? $userExtra['delta_coins'] : 0;
                        if ($delta_coins < 10) {
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ موجودی شما {$delta_coins} دلتاکوین میباشد. مقدار دلتاکوین موردنیاز جهت تغییر نام کاربری 10 عدد میباشد!");
                            continue;
                        }
                        
                        // به روزرسانی نام کاربری
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['username' => $new_username]);
                        
                        // کسر هزینه تغییر نام کاربری
                        \Application\Model\DB::table('users_extra')
                            ->where('user_id', $userData['id'])
                            ->update(['delta_coins' => $delta_coins - 10]);
                        
                        // ارسال پیام موفقیت
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ نام کاربری شما با موفقیت به «{$new_username}» تغییر یافت و 10 دلتاکوین از حساب شما کسر شد.");
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ نام کاربری با موفقیت تغییر یافت");
                    } else {
                        // لغو تغییر نام کاربری
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ تغییر نام کاربری لغو شد.");
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "❌ تغییر نام کاربری لغو شد");
                    }
                    
                    // ویرایش پیام کالبک
                    $new_text = $response === 'yes' 
                        ? "✅ نام کاربری به {$new_username} تغییر یافت."
                        : "❌ تغییر نام کاربری لغو شد.";
                    editMessageText($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $new_text);
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش تغییر نام کاربری: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // ری‌اکشن به پیام
            else if (strpos($callback_data, 'reaction:') === 0) {
                try {
                    $parts = explode(':', $callback_data);
                    $message_id = $parts[1];
                    $reaction = $parts[2];
                    
                    // لیست ایموجی‌های iPhone-style
                    $reactions = [
                        'like' => '👍',
                        'dislike' => '👎',
                        'love' => '❤️',
                        'laugh' => '😂',
                        'wow' => '😮',
                        'sad' => '😢',
                        'angry' => '😡',
                        'clap' => '👏',
                        'fire' => '🔥',
                        'party' => '🎉'
                    ];
                    
                    if (!isset($reactions[$reaction])) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: ری‌اکشن نامعتبر!");
                        continue;
                    }
                    
                    // ارسال ری‌اکشن (در تلگرام واقعی باید از متد reaction استفاده شود)
                    // اینجا فقط یک پیام نمایش می‌دهیم
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], $reactions[$reaction], true);
                    
                    echo "ری‌اکشن {$reactions[$reaction]} به پیام {$message_id} اضافه شد\n";
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش ری‌اکشن: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // درخواست فعال‌سازی مجدد چت بعد از غیرفعال شدن
            else if (strpos($callback_data, 'request_chat:') === 0) {
                try {
                    $match_id = substr($callback_data, strlen('request_chat:'));
                    
                    // دریافت اطلاعات بازی
                    $match = \Application\Model\DB::table('matches')->where('id', $match_id)->first();
                    if (!$match) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: بازی مورد نظر یافت نشد!");
                        echo "خطا: بازی {$match_id} در دیتابیس یافت نشد\n";
                        continue;
                    }
                    
                    // بررسی اینکه آیا قبلاً درخواست فعال کردن چت داده شده است
                    try {
                        $has_pending_request = \Application\Model\DB::table('matches')
                            ->where('id', $match_id)
                            ->where('chat_request_pending', true)
                            ->exists();
                            
                        if ($has_pending_request) {
                            answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "درخواست چت قبلا ارسال شده منتظر پاسخ باشید");
                            echo "خطا: درخواست فعال‌سازی چت قبلاً ارسال شده است\n";
                            continue;
                        }
                    } catch (Exception $e) {
                        // اگر ستون وجود نداشت، نادیده بگیر
                        echo "خطا در بررسی وضعیت درخواست چت: " . $e->getMessage() . "\n";
                    }
                    
                    // تعیین کاربر درخواست کننده و حریف
                    $requester_id = $user_id;
                    $opponent_id = ($match['player1'] == $requester_id) ? $match['player2'] : $match['player1'];
                    
                    if (!$opponent_id) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات حریف کامل نیست!");
                        echo "خطا: اطلاعات حریف در بازی {$match_id} کامل نیست\n";
                        continue;
                    }
                    
                    // ثبت درخواست در دیتابیس
                    try {
                        \Application\Model\DB::table('matches')
                            ->where('id', $match_id)
                            ->update(['chat_request_pending' => true]);
                    } catch (Exception $e) {
                        // اگر ستون وجود نداشت، نادیده بگیر
                        echo "خطا در به‌روزرسانی وضعیت درخواست چت: " . $e->getMessage() . "\n";
                    }
                    
                    // اطلاع به درخواست کننده
                    $requester_message = "درخواست فعال شدن چت برای حریف ارسال شد منتظر پاسخ باشید";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $requester_id, $requester_message);
                    
                    // ارسال درخواست به حریف
                    $opponent_message = "حریف از شما درخواست فعال کردن چت را دارد\nبا قبول این درخواست شما میتوانید به یکدیگر پیام ارسال کنید!";
                    $opponent_keyboard = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => 'فعال شود', 'callback_data' => "chat_response:{$match_id}:accept"],
                                ['text' => 'غیرفعال بماند', 'callback_data' => "chat_response:{$match_id}:reject"]
                            ]
                        ]
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $opponent_id, $opponent_message, $opponent_keyboard);
                    
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ درخواست فعال‌سازی چت ارسال شد.");
                    echo "درخواست فعال‌سازی چت از کاربر {$requester_id} به کاربر {$opponent_id} ارسال شد\n";
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش درخواست فعال‌سازی چت: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // پاسخ به درخواست فعال‌سازی چت
            else if (strpos($callback_data, 'chat_response:') === 0) {
                try {
                    $parts = explode(':', $callback_data);
                    $match_id = $parts[1];
                    $response = $parts[2]; // accept یا reject
                    
                    // دریافت اطلاعات بازی
                    $match = \Application\Model\DB::table('matches')->where('id', $match_id)->first();
                    if (!$match) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: بازی مورد نظر یافت نشد!");
                        echo "خطا: بازی {$match_id} در دیتابیس یافت نشد\n";
                        continue;
                    }
                    
                    // تعیین کاربر پاسخ دهنده و درخواست کننده
                    $responder_id = $user_id;
                    $requester_id = ($match['player1'] == $responder_id) ? $match['player2'] : $match['player1'];
                    
                    if ($response === 'accept') {
                        // فعال کردن چت
                        try {
                            \Application\Model\DB::table('matches')
                                ->where('id', $match_id)
                                ->update([
                                    'chat_enabled' => true,
                                    'chat_request_pending' => false
                                ]);
                        } catch (Exception $e) {
                            // اگر ستون وجود نداشت، نادیده بگیر
                            echo "خطا در به‌روزرسانی وضعیت چت: " . $e->getMessage() . "\n";
                        }
                        
                        // اعلام به هر دو کاربر
                        $notification = "✅ قابلیت چت فعال شد. اکنون می‌توانید با حریف خود چت کنید.";
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $requester_id, $notification);
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $responder_id, $notification);
                        
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ قابلیت چت فعال شد.");
                        echo "چت برای بازی {$match_id} فعال شد\n";
                    }
                    else if ($response === 'reject') {
                        // رد کردن درخواست
                        try {
                            \Application\Model\DB::table('matches')
                                ->where('id', $match_id)
                                ->update(['chat_request_pending' => false]);
                        } catch (Exception $e) {
                            // اگر ستون وجود نداشت، نادیده بگیر
                            echo "خطا در به‌روزرسانی وضعیت درخواست چت: " . $e->getMessage() . "\n";
                        }
                        
                        // اعلام به هر دو کاربر
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $requester_id, "❌ درخواست فعال کردن چت رد شد.");
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $responder_id, "❌ شما درخواست فعال کردن چت را رد کردید.");
                        
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "❌ درخواست فعال کردن چت رد شد.");
                        echo "درخواست فعال‌سازی چت برای بازی {$match_id} رد شد\n";
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش پاسخ به درخواست فعال‌سازی چت: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در پردازش پاسخ: " . $e->getMessage());
                }
            }
            
            // تأیید یا رد درخواست قطع چت
            else if (strpos($callback_data, 'confirm_end_chat:') === 0) {
                try {
                    $parts = explode(':', $callback_data);
                    $match_id = $parts[1];
                    $response = $parts[2]; // yes یا no
                    
                    // پردازش مستقیم پاسخ
                    if ($response === 'yes') {
                        // کاربر تأیید کرده که چت قطع شود
                        $message = "بسیار خب. بازی شما به اتمام رسید چه کاری میتونم برات انجام بدم؟";
                        
                        try {
                            // به‌روزرسانی وضعیت چت در دیتابیس
                            \Application\Model\DB::table('matches')
                                ->where('id', $match_id)
                                ->update(['chat_enabled' => false]);
                        } catch (Exception $e) {
                            // اگر ستون وجود نداشت، نادیده بگیر
                            echo "خطا در به‌روزرسانی وضعیت چت: " . $e->getMessage() . "\n";
                        }
                        
                        // دریافت اطلاعات بازی
                        $match = \Application\Model\DB::table('matches')->where('id', $match_id)->first();
                        if (!$match) {
                            answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: بازی مورد نظر یافت نشد!");
                            echo "خطا: بازی {$match_id} در دیتابیس یافت نشد\n";
                            continue;
                        }
                        
                        // ارسال منوی اصلی به هر دو بازیکن
                        $keyboard = json_encode([
                            'keyboard' => [
                                [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                                [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                                [['text' => '👤 حساب کاربری'], ['text' => '🏆نفرات برتر•']],
                                [['text' => '👨‍👦‍👦 وضعیت زیرمجموعه‌ها'], ['text' => '💰 دلتا کوین روزانه']],
                                [['text' => '• پشتیبانی👨‍💻'], ['text' => '⁉️راهنما •']]
                            ],
                            'resize_keyboard' => true
                        ]);
                        
                        // ارسال پیام اعلان به هر دو بازیکن
                        $notification = "قابلیت چت غیرفعال شد. برای فعال کردن مجدد از دکمه زیر استفاده کنید:";
                        $reactivate_keyboard = json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '🔄 فعال کردن مجدد چت', 'callback_data' => "request_chat:{$match_id}"]
                                ]
                            ]
                        ]);
                        
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $match['player1'], $notification, $reactivate_keyboard);
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $match['player2'], $notification, $reactivate_keyboard);
                        
                        // ارسال منوی اصلی
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $match['player1'], $message, $keyboard);
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $match['player2'], $message, $keyboard);
                        
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ چت پایان یافت و به منوی اصلی بازگشتید.");
                        echo "چت برای بازی {$match_id} پایان یافت\n";
                        
                        // ویرایش پیام نظرسنجی
                        $new_text = "چت پایان یافت. ✅";
                        editMessageText($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $new_text);
                    } else {
                        // کاربر درخواست قطع چت را لغو کرده
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ درخواست قطع چت لغو شد.");
                        
                        // ویرایش پیام تأیید
                        $new_text = "درخواست قطع چت لغو شد. چت همچنان فعال است.";
                        editMessageText($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $new_text);
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش تأیید قطع چت: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // در اینجا می‌توان سایر انواع callback_query را پردازش کرد
            
            // پردازش نمایش اطلاعات زیرمجموعه
            if (strpos($callback_data, 'view_referral:') === 0) {
                try {
                    // استخراج آیدی ارجاع
                    $referral_id = substr($callback_data, strlen('view_referral:'));
                    
                    // دریافت اطلاعات ارجاع
                    $referral = \Application\Model\DB::table('referrals')->where('id', $referral_id)->first();
                    if (!$referral) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات زیرمجموعه یافت نشد!");
                        continue;
                    }
                    
                    // دریافت اطلاعات کاربر دعوت کننده و دعوت شونده
                    $referrer = \Application\Model\DB::table('users')->where('id', $referral['referrer_id'])->first();
                    $referee = \Application\Model\DB::table('users')->where('id', $referral['referee_id'])->first();
                    
                    if (!$referrer || !$referee) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات کاربران یافت نشد!");
                        continue;
                    }
                    
                    // بررسی اینکه آیا کاربر درخواست کننده همان فرد دعوت کننده است
                    if ($referrer['telegram_id'] != $user_id) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: شما دسترسی به این اطلاعات را ندارید!");
                        continue;
                    }
                    
                    // محاسبه پورسانت
                    $user_reward = 0;
                    if ($referral['started_rewarded']) $user_reward += 0.5;
                    if ($referral['first_win_rewarded']) $user_reward += 1.5;
                    if ($referral['profile_completed_rewarded']) $user_reward += 3;
                    if ($referral['thirty_wins_rewarded']) $user_reward += 5;
                    
                    // دریافت آمار بازی‌های کاربر زیرمجموعه
                    $stats = \Application\Model\DB::table('users_extra')->where('user_id', $referee['id'])->first();
                    $total_games = 0;
                    $wins = 0;
                    if ($stats) {
                        $total_games = $stats['played_games'] ?? 0;
                        $wins = $stats['wins'] ?? 0;
                    }
                    
                    // نمایش وضعیت پروفایل
                    $profile_status = "تکمیل نشده ❌";
                    $profile = \Application\Model\DB::table('user_profiles')->where('user_id', $referee['id'])->first();
                    if ($profile) {
                        // بررسی تکمیل بودن پروفایل
                        $required_fields = ['full_name', 'gender', 'age', 'bio', 'province'];
                        $complete = true;
                        foreach ($required_fields as $field) {
                            if (!isset($profile[$field]) || empty($profile[$field])) {
                                $complete = false;
                                break;
                            }
                        }
                        $profile_status = $complete ? "تکمیل شده ✅" : "ناقص ⚠️";
                    }
                    
                    // ساخت پیام اطلاعات زیرمجموعه
                    $message = "📊 *اطلاعات زیرمجموعه*\n\n";
                    $message .= "👤 *کاربر:* {$referee['username']}\n";
                    $message .= "📅 *تاریخ عضویت:* " . date('Y-m-d H:i:s', strtotime($referral['created_at'])) . "\n";
                    $message .= "🎮 *تعداد بازی‌ها:* {$total_games}\n";
                    $message .= "🏆 *تعداد بردها:* {$wins}\n";
                    $message .= "👤 *وضعیت پروفایل:* {$profile_status}\n\n";
                    
                    $message .= "💰 *وضعیت پورسانت‌ها:*\n";
                    $message .= "• شروع بازی: " . ($referral['started_rewarded'] ? "دریافت شده ✅" : "دریافت نشده ❌") . " (0.5 دلتا کوین)\n";
                    $message .= "• اولین برد: " . ($referral['first_win_rewarded'] ? "دریافت شده ✅" : "دریافت نشده ❌") . " (1.5 دلتا کوین)\n";
                    $message .= "• تکمیل پروفایل: " . ($referral['profile_completed_rewarded'] ? "دریافت شده ✅" : "دریافت نشده ❌") . " (3 دلتا کوین)\n";
                    $message .= "• 30 بازی موفق: " . ($referral['thirty_wins_rewarded'] ? "دریافت شده ✅" : "دریافت نشده ❌") . " (5 دلتا کوین)\n\n";
                    
                    $message .= "💵 *مجموع پورسانت:* {$user_reward} دلتا کوین";
                    
                    // دکمه بازگشت
                    $back_keyboard = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '🔙 بازگشت به لیست', 'callback_data' => "list_referrals"]
                            ]
                        ]
                    ]);
                    
                    // ویرایش پیام قبلی
                    editMessageTextWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $message, $back_keyboard);
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id']);
                    
                } catch (Exception $e) {
                    echo "خطا در نمایش اطلاعات زیرمجموعه: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // پردازش درخواست دریافت لینک رفرال
            else if ($callback_data === 'get_referral_link') {
                try {
                    // دریافت اطلاعات کاربر
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$userData) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات کاربری یافت نشد!");
                        continue;
                    }
                    
                    // ساخت پیام حاوی لینک رفرال
                    $message = "🔗 *لینک رفرال اختصاصی شما*\n\n";
                    $message .= "از لینک زیر برای دعوت از دوستان خود استفاده کنید:\n\n";
                    
                    // دریافت اطلاعات ربات برای ساخت لینک رفرال
                    $botInfo = getBotInfo($_ENV['TELEGRAM_TOKEN']);
                    $botUsername = isset($botInfo['username']) ? $botInfo['username'] : 'your_bot';
                    
                    $message .= "`https://t.me/" . $botUsername . "?start=" . $userData['id'] . "`\n\n";
                    $message .= "💰 *سیستم پاداش دهی رفرال:*\n";
                    $message .= "• عضویت اولیه: 0.5 دلتا کوین\n";
                    $message .= "• اولین برد: 1.5 دلتا کوین\n";
                    $message .= "• تکمیل پروفایل: 3 دلتا کوین\n";
                    $message .= "• 30 بازی موفق: 5 دلتا کوین\n\n";
                    $message .= "مجموع: 10 دلتا کوین به ازای هر زیرمجموعه فعال";
                    
                    // ساخت دکمه برای مشاهده وضعیت زیرمجموعه‌ها
                    $keyboard = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '📊 وضعیت زیرمجموعه‌های من', 'callback_data' => 'list_referrals']
                            ],
                            [
                                ['text' => '⬅️ منوی اصلی', 'callback_data' => 'return_to_main_menu']
                            ]
                        ]
                    ]);
                    
                    // ارسال پیام به کاربر
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ لینک رفرال شما با موفقیت ساخته شد");
                    
                    echo "لینک رفرال برای کاربر {$user_id} ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در ساخت لینک رفرال: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در ساخت لینک رفرال: " . $e->getMessage());
                }
            }
            
            // پردازش لیست زیرمجموعه‌ها (برای دکمه بازگشت)
            else if ($callback_data === 'list_referrals') {
                try {
                    // دریافت اطلاعات کاربر
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$userData) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در دریافت اطلاعات کاربری");
                        continue;
                    }
                    
                    // دریافت لیست زیرمجموعه‌ها
                    $referrals = \Application\Model\DB::table('referrals')
                        ->where('referrer_id', $userData['id'])
                        ->get();
                    
                    if (empty($referrals)) {
                        $message = "📊 *وضعیت زیرمجموعه‌ها*\n\n";
                        $message .= "⚠️ شما هنوز هیچ زیرمجموعه‌ای ندارید!\n\n";
                        $message .= "برای دعوت از دوستان، لینک اختصاصی خود را به آنها ارسال کنید:\n";
                        
// دریافت اطلاعات ربات
$botInfo = getBotInfo($_ENV['TELEGRAM_TOKEN']);
$botUsername = isset($botInfo['username']) ? $botInfo['username'] : 'your_bot';
$message .= "https://t.me/" . $botUsername . "?start=" . $userData['id'];
                        
                        editMessageText($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $message);
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id']);
                        continue;
                    }
                    
                    // نمایش لیست زیرمجموعه‌ها
                    $message = "📊 *وضعیت زیرمجموعه‌ها*\n\n";
                    $message .= "لینک اختصاصی شما برای دعوت از دوستان:\n";
// دریافت اطلاعات ربات
$botInfo = getBotInfo($_ENV['TELEGRAM_TOKEN']);
$botUsername = isset($botInfo['username']) ? $botInfo['username'] : 'your_bot';

$message .= "https://t.me/" . $botUsername . "?start=" . $userData['id'] . "\n\n";
                    $message .= "📋 *لیست زیرمجموعه‌های شما:*\n";
                    
                    $total_rewards = 0;
                    $i = 1;
                    
                    // کیبورد برای نمایش اطلاعات بیشتر درباره هر زیرمجموعه
                    $inline_keyboard = [];
                    
                    foreach ($referrals as $referral) {
                        // دریافت اطلاعات کاربر زیرمجموعه
                        $referredUser = \Application\Model\DB::table('users')
                            ->where('id', $referral['referee_id'])
                            ->first();
                            
                        if ($referredUser) {
                            $row = [['text' => "{$i}. {$referredUser['username']} ➡️", 'callback_data' => "view_referral:{$referral['id']}"]];
                            $inline_keyboard[] = $row;
                            
                            // محاسبه پورسانت
                            $user_reward = 0;
                            if ($referral['started_rewarded']) $user_reward += 0.5;
                            if ($referral['first_win_rewarded']) $user_reward += 1.5;
                            if ($referral['profile_completed_rewarded']) $user_reward += 3;
                            if ($referral['thirty_wins_rewarded']) $user_reward += 5;
                            
                            $total_rewards += $user_reward;
                            $i++;
                        }
                    }
                    
                    $message .= "\nتعداد زیرمجموعه‌ها: " . count($referrals) . "\n";
                    $message .= "مجموع پورسانت دریافتی: " . $total_rewards . " دلتا کوین\n\n";
                    $message .= "🔍 برای مشاهده جزئیات هر زیرمجموعه، روی نام آن کلیک کنید.";
                    
                    // کیبورد برای لیست
                    $keyboard = json_encode([
                        'inline_keyboard' => $inline_keyboard
                    ]);
                    
                    // ویرایش پیام قبلی
                    editMessageTextWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $message, $keyboard);
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id']);
                    
                } catch (Exception $e) {
                    echo "خطا در نمایش لیست زیرمجموعه‌ها: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // پردازش دریافت دلتا کوین روزانه
            else if ($callback_data === 'claim_daily_coin') {
                try {
                    // دریافت اطلاعات کاربر
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$userData) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در دریافت اطلاعات کاربری");
                        continue;
                    }
                    
                    // بررسی آیا کاربر قبلاً امروز دلتا کوین دریافت کرده است
                    $today = date('Y-m-d');
                    $daily_claim = \Application\Model\DB::table('daily_delta_coins')
                        ->where('user_id', $userData['id'])
                        ->where('claim_date', $today)
                        ->first();
                    
                    if ($daily_claim) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ شما امروز دلتا کوین روزانه خود را دریافت کرده‌اید!");
                        continue;
                    }
                    
                    // بررسی عضویت در کانال‌های اسپانسر
                    // [توجه] برای بررسی عضویت در کانال‌های اسپانسر، باید از متد getChatMember استفاده کرد
                    // به دلیل محدودیت‌ها، این بخش به صورت نمونه پیاده‌سازی شده و عضویت را تأیید شده فرض می‌کند
                    
                    // تولید مقدار تصادفی دلتا کوین (بین 1 تا 5)
                    $coin_amount = rand(1, 5);
                    
                    // افزایش دلتا کوین کاربر
                    $delta_coins = \Application\Model\DB::table('users_extra')
                        ->where('user_id', $userData['id'])
                        ->value('delta_coins') ?? 0;
                    
                    \Application\Model\DB::table('users_extra')
                        ->where('user_id', $userData['id'])
                        ->update(['delta_coins' => $delta_coins + $coin_amount]);
                    
                    // ثبت دریافت دلتا کوین روزانه
                    \Application\Model\DB::table('daily_delta_coins')->insert([
                        'user_id' => $userData['id'],
                        'amount' => $coin_amount,
                        'claim_date' => $today
                    ]);
                    
                    // پیام تأیید
                    $message = "✅ *تبریک!*\n\n";
                    $message .= "شما {$coin_amount} دلتا کوین روزانه دریافت کردید!\n";
                    $message .= "موجودی فعلی شما: " . ($delta_coins + $coin_amount) . " دلتا کوین\n\n";
                    $message .= "فردا دوباره برگردید تا دلتا کوین رایگان جدید دریافت کنید.";
                    
                    // ویرایش پیام قبلی
                    editMessageText($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $message);
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ {$coin_amount} دلتا کوین به حساب شما اضافه شد!");
                    
                } catch (Exception $e) {
                    echo "خطا در دریافت دلتا کوین روزانه: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            continue;
        }
        
        // پردازش عکس (برای آپلود عکس پروفایل)
        if (isset($update['message']) && isset($update['message']['photo'])) {
            $chat_id = $update['message']['chat']['id'];
            $user_id = $update['message']['from']['id'];
            
            try {
                // بررسی آیا کاربر در حالت تکمیل پروفایل است
                $user_state_file = __DIR__ . "/user_states/{$user_id}.json";
                if (file_exists($user_state_file)) {
                    $userState = json_decode(file_get_contents($user_state_file), true);
                    
                    // اگر کاربر در مرحله آپلود عکس پروفایل است
                    if (isset($userState['state']) && $userState['state'] === 'profile_completion' && 
                        isset($userState['step']) && $userState['step'] === 'waiting_for_photo') {
                        
                        // دریافت اطلاعات کاربر از دیتابیس
                        $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                        
                        if (!$userData) {
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                            echo "خطا: کاربر در دیتابیس یافت نشد\n";
                            unlink($user_state_file); // حذف فایل وضعیت
                            continue;
                        }
                        
                        // دریافت بهترین کیفیت عکس
                        $photo = end($update['message']['photo']);
                        $file_id = $photo['file_id'];
                        
                        // ذخیره شناسه فایل عکس در پروفایل کاربر
                        $profileExists = \Application\Model\DB::table('user_profiles')
                            ->where('user_id', $userData['id'])
                            ->exists();
                            
                        if ($profileExists) {
                            \Application\Model\DB::table('user_profiles')
                                ->where('user_id', $userData['id'])
                                ->update(['photo_file_id' => $file_id, 'photo_approved' => false]);
                        } else {
                            \Application\Model\DB::table('user_profiles')->insert([
                                'user_id' => $userData['id'],
                                'photo_file_id' => $file_id,
                                'photo_approved' => false
                            ]);
                        }
                        
                        // ارسال عکس به کانال ادمین برای تأیید
                        $admin_channel_id = "-100123456789"; // آیدی کانال ادمین را قرار دهید
                        try {
                            $admin_message = "✅ درخواست تأیید عکس پروفایل:\n\nکاربر: {$userData['username']}\nآیدی: {$userData['telegram_id']}";
                            
                            $admin_keyboard = json_encode([
                                'inline_keyboard' => [
                                    [
                                        ['text' => '✅ تأیید', 'callback_data' => "approve_photo:{$userData['id']}"],
                                        ['text' => '❌ رد', 'callback_data' => "reject_photo:{$userData['id']}"]
                                    ]
                                ]
                            ]);
                            
                            // تابع ارسال عکس به کانال ادمین
                            // sendPhoto($_ENV['TELEGRAM_TOKEN'], $admin_channel_id, $file_id, $admin_message, $admin_keyboard);
                            echo "عکس به کانال ادمین ارسال شد\n";
                        } catch (Exception $e) {
                            echo "خطا در ارسال عکس به کانال ادمین: " . $e->getMessage() . "\n";
                        }
                        
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ عکس پروفایل شما با موفقیت ارسال شد و در انتظار تأیید ادمین است.");
                        
                        // به روز رسانی وضعیت کاربر به مرحله بعدی
                        $userState['step'] = 'waiting_for_name';
                        file_put_contents($user_state_file, json_encode($userState));
                        
                        // مرحله بعدی - درخواست نام
                        $message = "📝 *تکمیل پروفایل*\n\n";
                        $message .= "مرحله 2/7: لطفاً نام کامل خود را وارد کنید.";
                        
                        // ایجاد دکمه لغو
                        $cancel_keyboard = [
                            'keyboard' => [
                                [
                                    ['text' => 'لغو ❌']
                                ]
                            ],
                            'resize_keyboard' => true
                        ];
                        
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, json_encode($cancel_keyboard));
                    }
                }
                
            } catch (Exception $e) {
                echo "خطا در پردازش عکس: " . $e->getMessage() . "\n";
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش عکس: " . $e->getMessage());
            }
        }
        
        // پردازش موقعیت مکانی
        if (isset($update['message']) && isset($update['message']['location'])) {
            $chat_id = $update['message']['chat']['id'];
            $user_id = $update['message']['from']['id'];
            
            // دریافت اطلاعات کاربر و وضعیت فعلی
            try {
                $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                
                if (!$userData || !isset($userData['state']) || empty($userData['state'])) {
                    // اگر وضعیتی برای کاربر تعریف نشده، موقعیت را نادیده می‌گیریم
                    continue;
                }
                
                $userState = json_decode($userData['state'], true);
                
                // پردازش وضعیت‌های پنل مدیریت
                if ($userState['state'] === 'admin_panel') {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin()) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    // پردازش مراحل مختلف پنل مدیریت
                    switch ($userState['step']) {
                        // منتظر دریافت پیام برای فوروارد همگانی
                        case 'waiting_for_forward_message':
                            // بررسی آیا کاربر درخواست لغو کرده است
                            if (strpos($text, 'لغو') !== false) {
                                // بازگشت به منوی پنل مدیریت
                                $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                                $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '⚙️ تنظیمات ربات']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "درخواست فوروارد همگانی لغو شد\n";
                                continue 2;
                            }
                            
                            // دریافت پیام دریافتی (متن یا مدیا)
                            // بررسی نوع پیام دریافتی
                            $message_type = 'text';
                            $message_content = $text;
                            
                            // اگر پیام، متن نباشد و حاوی رسانه باشد
                            if (isset($update['message']['photo'])) {
                                $message_type = 'photo';
                                // آخرین آیتم آرایه photo دارای بالاترین کیفیت است
                                $photos = $update['message']['photo'];
                                $photo = end($photos);
                                $message_content = $photo['file_id'];
                                // اگر caption داشته باشد
                                if (isset($update['message']['caption'])) {
                                    $caption = $update['message']['caption'];
                                } else {
                                    $caption = '';
                                }
                            } elseif (isset($update['message']['video'])) {
                                $message_type = 'video';
                                $message_content = $update['message']['video']['file_id'];
                                // اگر caption داشته باشد
                                if (isset($update['message']['caption'])) {
                                    $caption = $update['message']['caption'];
                                } else {
                                    $caption = '';
                                }
                            } elseif (isset($update['message']['audio'])) {
                                $message_type = 'audio';
                                $message_content = $update['message']['audio']['file_id'];
                                // اگر caption داشته باشد
                                if (isset($update['message']['caption'])) {
                                    $caption = $update['message']['caption'];
                                } else {
                                    $caption = '';
                                }
                            } elseif (isset($update['message']['document'])) {
                                $message_type = 'document';
                                $message_content = $update['message']['document']['file_id'];
                                // اگر caption داشته باشد
                                if (isset($update['message']['caption'])) {
                                    $caption = $update['message']['caption'];
                                } else {
                                    $caption = '';
                                }
                            } elseif (isset($update['message']['voice'])) {
                                $message_type = 'voice';
                                $message_content = $update['message']['voice']['file_id'];
                                // اگر caption داشته باشد
                                if (isset($update['message']['caption'])) {
                                    $caption = $update['message']['caption'];
                                } else {
                                    $caption = '';
                                }
                            }
                            
                            // ذخیره اطلاعات پیام در وضعیت کاربر
                            $userState['forward_message_type'] = $message_type;
                            $userState['forward_message_content'] = $message_content;
                            if (isset($caption)) {
                                $userState['forward_message_caption'] = $caption;
                            }
                            
                            // تغییر وضعیت کاربر
                            $userState['step'] = 'confirm_forward';
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                            
                            // پیام تأیید
                            $message = "📤 *تأیید فوروارد همگانی*\n\n";
                            $message .= "پیام شما برای فوروارد همگانی آماده است. برای تأیید و ارسال به همه کاربران، دکمه «ارسال» را بزنید.";
                            
                            // کیبورد تأیید
                            $confirm_keyboard = json_encode([
                                'keyboard' => [
                                    [['text' => '✅ ارسال به همه کاربران']],
                                    [['text' => '❌ لغو ارسال']]
                                ],
                                'resize_keyboard' => true
                            ]);
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $confirm_keyboard);
                            echo "پیام فوروارد همگانی دریافت شد\n";
                            break;
                            
                        // تأیید فوروارد همگانی
                        case 'confirm_forward':
                            // بررسی آیا کاربر درخواست لغو کرده است
                            if (strpos($text, 'لغو') !== false) {
                                // بازگشت به منوی پنل مدیریت
                                $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                                $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '⚙️ تنظیمات ربات']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "فوروارد همگانی لغو شد\n";
                                continue 2;
                            }
                            
                            // اگر کاربر تأیید کرده است
                            if (strpos($text, 'ارسال به همه کاربران') !== false) {
                                // شروع ارسال پیام
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "🕒 در حال ارسال پیام به کاربران، لطفاً صبر کنید...");
                                
                                // بررسی آیا این یک فوروارد عادی است یا متد fprwardMessageToAll
                                if (isset($userState['forwarded_from_chat_id']) && isset($userState['forwarded_message_id'])) {
                                    // استفاده از کلاس AdminController برای فوروارد پیام
                                    require_once __DIR__ . '/application/controllers/AdminController.php';
                                    $adminController = new \application\controllers\AdminController($user_id);
                                    
                                    // فوروارد پیام با کلاس AdminController
                                    $result = $adminController->forwardMessageToAll(
                                        $userState['forwarded_from_chat_id'],
                                        $userState['forwarded_message_id']
                                    );
                                    
                                    if ($result['success']) {
                                        $sent = $result['sent_count'];
                                        $failed = $result['failed_count'];
                                        $total = $sent + $failed;
                                        
                                        // گزارش نهایی
                                        $message = "✅ *گزارش فوروارد همگانی*\n\n";
                                        $message .= "• تعداد کل کاربران: {$total}\n";
                                        $message .= "• ارسال موفق: {$sent}\n";
                                        $message .= "• ارسال ناموفق: {$failed}\n";
                                    } else {
                                        // خطا در فوروارد
                                        $message = "❌ *خطا در فوروارد همگانی*\n\n";
                                        $message .= $result['message'];
                                    }
                                    
                                } else {
                                    // دریافت لیست کاربران
                                    $users = \Application\Model\DB::table('users')->select('telegram_id')->get();
                                    
                                    if (empty($users)) {
                                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ هیچ کاربری در دیتابیس یافت نشد!");
                                        echo "لیست کاربران خالی است\n";
                                        continue 2;
                                    }
                                    
                                    $total = count($users);
                                    $sent = 0;
                                    $failed = 0;
                                    $start_time = time();
                                    
                                    // ارسال پیام به کاربران
                                    foreach ($users as $u) {
                                        // اگر بیش از 30 دقیقه از شروع گذشته باشد، خاتمه دهیم
                                        if (time() - $start_time > 1800) {
                                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ ارسال پیام به دلیل طولانی شدن زمان متوقف شد. {$sent} پیام از {$total} ارسال شد و {$failed} پیام ناموفق بود.");
                                            echo "ارسال پیام به دلیل طولانی شدن زمان متوقف شد\n";
                                            break;
                                        }
                                        
                                        try {
                                            // چک کردن آیدی تلگرام
                                            if (empty($u['telegram_id'])) {
                                                $failed++;
                                                continue;
                                            }
                                            
                                            // ارسال پیام براساس نوع آن
                                            switch ($userState['forward_message_type']) {
                                                case 'text':
                                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $u['telegram_id'], $userState['forward_message_content']);
                                                    break;
                                                case 'photo':
                                                    sendPhoto($_ENV['TELEGRAM_TOKEN'], $u['telegram_id'], $userState['forward_message_content'], $userState['forward_message_caption'] ?? '');
                                                    break;
                                                case 'video':
                                                    sendVideo($_ENV['TELEGRAM_TOKEN'], $u['telegram_id'], $userState['forward_message_content'], $userState['forward_message_caption'] ?? '');
                                                    break;
                                                case 'audio':
                                                    sendAudio($_ENV['TELEGRAM_TOKEN'], $u['telegram_id'], $userState['forward_message_content'], $userState['forward_message_caption'] ?? '');
                                                    break;
                                                case 'document':
                                                    sendDocument($_ENV['TELEGRAM_TOKEN'], $u['telegram_id'], $userState['forward_message_content'], $userState['forward_message_caption'] ?? '');
                                                    break;
                                                case 'voice':
                                                    sendVoice($_ENV['TELEGRAM_TOKEN'], $u['telegram_id'], $userState['forward_message_content'], $userState['forward_message_caption'] ?? '');
                                                    break;
                                            }
                                            
                                            $sent++;
                                            
                                            // هر 50 ارسال، اطلاع‌رسانی کنیم
                                            if ($sent % 50 === 0) {
                                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "📤 در حال ارسال... {$sent}/{$total} ارسال شده...");
                                            }
                                            
                                            // تأخیر 0.5 ثانیه برای جلوگیری از محدودیت تلگرام
                                            usleep(500000);
                                        } catch (\Exception $e) {
                                            $failed++;
                                            echo "خطا در ارسال پیام به کاربر {$u['telegram_id']}: " . $e->getMessage() . "\n";
                                        }
                                    }
                                    
                                    // گزارش نهایی
                                    $total_time = time() - $start_time;
                                    $minutes = floor($total_time / 60);
                                    $seconds = $total_time % 60;
                                    
                                    $message = "✅ *گزارش ارسال همگانی*\n\n";
                                    $message .= "• تعداد کل کاربران: {$total}\n";
                                    $message .= "• ارسال موفق: {$sent}\n";
                                    $message .= "• ارسال ناموفق: {$failed}\n";
                                    $message .= "• زمان کل: {$minutes} دقیقه و {$seconds} ثانیه";
                                }
                                
                                // بازگشت به منوی مدیریت
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '⚙️ تنظیمات ربات']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, "🛠️ بازگشت به پنل مدیریت:", $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "فوروارد همگانی به {$sent} کاربر انجام شد\n";
                            }
                            break;
                    
                        // منتظر تغییر وضعیت روشن/خاموش بودن ربات
                        case 'bot_status_menu':
                            // بررسی آیا کاربر درخواست لغو کرده است
                            if (strpos($text, 'بازگشت به پنل مدیریت') !== false) {
                                // بازگشت به منوی پنل مدیریت
                                $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                                $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '⚙️ تنظیمات ربات']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "بازگشت به منوی اصلی پنل مدیریت\n";
                                continue 2;
                            }
                            
                            // فعال کردن ربات
                            else if (strpos($text, 'فعال کردن ربات') !== false) {
                                $result = $adminController->setBotStatus(true);
                                
                                if ($result) {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ ربات با موفقیت فعال شد.");
                                } else {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در فعال‌سازی ربات. لطفاً مجدداً تلاش کنید.");
                                }
                                
                                // بازگشت به منوی تنظیمات
                                $settings_menu = "⚙️ *تنظیمات ربات*\n\n";
                                $settings_menu .= "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد تنظیمات
                                $settings_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '🔄 روشن/خاموش کردن ربات']],
                                        [['text' => '💰 تنظیم قیمت دلتا'], ['text' => '💸 تنظیم پورسانت']],
                                        [['text' => '🔙 بازگشت به پنل مدیریت']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $settings_menu, $settings_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'settings_menu';
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "ربات فعال شد\n";
                                continue 2;
                            }
                            
                            // غیرفعال کردن ربات
                            else if (strpos($text, 'غیرفعال کردن ربات') !== false) {
                                $result = $adminController->setBotStatus(false);
                                
                                if ($result) {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ ربات با موفقیت غیرفعال شد.");
                                } else {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در غیرفعال‌سازی ربات. لطفاً مجدداً تلاش کنید.");
                                }
                                
                                // بازگشت به منوی تنظیمات
                                $settings_menu = "⚙️ *تنظیمات ربات*\n\n";
                                $settings_menu .= "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد تنظیمات
                                $settings_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '🔄 روشن/خاموش کردن ربات']],
                                        [['text' => '💰 تنظیم قیمت دلتا'], ['text' => '💸 تنظیم پورسانت']],
                                        [['text' => '🔙 بازگشت به پنل مدیریت']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $settings_menu, $settings_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'settings_menu';
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "ربات غیرفعال شد\n";
                                continue 2;
                            }
                            break;
                            
                        // منتظر دریافت پیام همگانی
                        case 'waiting_for_broadcast_message':
                            // بررسی آیا کاربر درخواست لغو کرده است
                            if (strpos($text, 'لغو') !== false) {
                                // بازگشت به منوی پنل مدیریت
                                $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                                $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '🔗 قفل گروه/کانال']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "درخواست پیام همگانی لغو شد\n";
                                continue 2;
                            }
                            
                            // دریافت پیام همگانی
                            $message = "📢 *تأییدیه ارسال پیام همگانی*\n\n";
                            $message .= "پیام شما برای ارسال همگانی آماده است. برای تأیید و ارسال، دکمه «ارسال» را بزنید.\n\n";
                            $message .= "📝 *متن پیام:*\n\n";
                            $message .= $text;
                            
                            // کیبورد تأیید یا لغو
                            $confirm_keyboard = json_encode([
                                'keyboard' => [
                                    [['text' => '✅ ارسال پیام به همه کاربران']],
                                    [['text' => '❌ لغو ارسال']]
                                ],
                                'resize_keyboard' => true
                            ]);
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $confirm_keyboard);
                            
                            // ذخیره متن پیام در وضعیت
                            $userState['broadcast_message'] = $text;
                            $userState['step'] = 'confirm_broadcast';
                            
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                                
                            echo "پیام همگانی دریافت شد\n";
                            break;
                        
                        // تأیید یا لغو ارسال پیام همگانی
                        case 'confirm_broadcast':
                            if (strpos($text, 'ارسال پیام به همه کاربران') !== false) {
                                // دریافت لیست کاربران از دیتابیس
                                $users = \Application\Model\DB::table('users')->select('*')->get();
                                
                                // شروع ارسال پیام همگانی
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "🕒 در حال ارسال پیام به کاربران...");
                                
                                // ثبت اطلاعات پیام در دیتابیس
                                $broadcast_message = [
                                    'admin_id' => $userData['id'],
                                    'message_type' => 'text',
                                    'message_text' => $userState['broadcast_message'],
                                    'status' => 'processing'
                                ];
                                
                                $broadcast_id = \Application\Model\DB::table('broadcast_messages')->insert($broadcast_message);
                                
                                // ارسال پیام به تمام کاربران
                                $sent_count = 0;
                                $failed_count = 0;
                                
                                // استفاده از کلاس AdminController برای ارسال پیام همگانی
                                require_once __DIR__ . '/application/controllers/AdminController.php';
                                $adminController = new \application\controllers\AdminController($user_id);
                                
                                // بررسی آیا پیام نیاز به نمایش آمار دارد
                                $include_stats = isset($userState['include_stats']) && $userState['include_stats'] === true;
                                
                                // استفاده از متد broadcastMessage کلاس AdminController
                                $broadcast_result = $adminController->broadcastMessage($userState['broadcast_message'], $include_stats);
                                
                                if ($broadcast_result['success']) {
                                    $sent_count = $broadcast_result['sent_count'];
                                    $failed_count = count($users) - $sent_count;
                                } else {
                                    // در صورت خطا، ارسال به روش قدیمی انجام شود
                                    foreach ($users as $user) {
                                        try {
                                            sendMessage($_ENV['TELEGRAM_TOKEN'], $user['telegram_id'], $userState['broadcast_message']);
                                            $sent_count++;
                                            
                                            // به روز رسانی هر 10 کاربر یک بار
                                            if ($sent_count % 10 === 0) {
                                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "📨 در حال ارسال پیام... {$sent_count} پیام ارسال شده");
                                            }
                                            
                                            // کمی تأخیر برای جلوگیری از محدودیت‌های تلگرام
                                            usleep(200000); // 0.2 ثانیه تأخیر
                                        } catch (Exception $e) {
                                            $failed_count++;
                                            echo "خطا در ارسال پیام به کاربر {$user['telegram_id']}: " . $e->getMessage() . "\n";
                                        }
                                    }
                                }
                                
                                // به روز رسانی وضعیت پیام در دیتابیس
                                \Application\Model\DB::table('broadcast_messages')
                                    ->where('id', $broadcast_id)
                                    ->update([
                                        'status' => 'completed',
                                        'total_sent' => $sent_count,
                                        'total_failed' => $failed_count,
                                        'completed_at' => date('Y-m-d H:i:s')
                                    ]);
                                
                                // ارسال گزارش نهایی
                                $summary = "✅ *پیام همگانی ارسال شد*\n\n";
                                $summary .= "📊 آمار ارسال:\n";
                                $summary .= "• تعداد کاربران: " . count($users) . "\n";
                                $summary .= "• ارسال موفق: {$sent_count}\n";
                                $summary .= "• ارسال ناموفق: {$failed_count}\n";
                                $summary .= "• زمان اتمام: " . date('Y-m-d H:i:s');
                                
                                // بازگشت به منوی مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '🔗 قفل گروه/کانال']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $summary, $admin_keyboard);
                                
                                // به روز رسانی وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                unset($userState['broadcast_message']);
                                
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "پیام همگانی با موفقیت ارسال شد\n";
                            } else if (strpos($text, 'لغو ارسال') !== false) {
                                // بازگشت به منوی پنل مدیریت
                                $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                                $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '🔗 قفل گروه/کانال']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ ارسال پیام همگانی لغو شد.", $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                unset($userState['broadcast_message']);
                                
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "ارسال پیام همگانی لغو شد\n";
                            }
                            break;
                            
                        // منتظر دریافت آیدی ادمین جدید برای افزودن (از طریق منوی جدید)
                        case 'waiting_for_new_admin_id':
                            // بررسی آیا کاربر درخواست لغو کرده است
                            if (strpos($text, 'لغو') !== false) {
                                // بازگشت به منوی پنل مدیریت
                                $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                                $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '🔗 قفل گروه/کانال']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "درخواست افزودن ادمین لغو شد\n";
                                continue 2;
                            }
                            
                            // جستجوی کاربر با آیدی یا نام کاربری
                            $searchQuery = $text;
                            
                            // استفاده از AdminController برای افزودن ادمین
                            require_once __DIR__ . '/application/controllers/AdminController.php';
                            $adminController = new \application\controllers\AdminController($user_id);
                            
                            // افزودن ادمین با دسترسی‌های پایه
                            $result = $adminController->addAdmin($searchQuery, [
                                'can_send_broadcasts' => true,
                                'can_manage_users' => true,
                                'can_view_statistics' => true
                            ]);
                            
                            if ($result['success']) {
                                // اطلاع‌رسانی موفقیت به ادمین
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ " . $result['message']);
                                
                                // اطلاع‌رسانی به کاربر جدید
                                if (isset($result['user']['telegram_id'])) {
                                    $new_admin_message = "🎖 *تبریک!*\n\n";
                                    $new_admin_message .= "شما به عنوان ادمین ربات انتخاب شده‌اید و می‌توانید با ارسال دستور /admin به پنل مدیریت دسترسی داشته باشید.";
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $result['user']['telegram_id'], $new_admin_message);
                                }
                            } else {
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ " . $result['message']);
                            }
                            
                            // بازگشت به منوی پنل مدیریت
                            $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                            $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                            
                            // کیبورد مدیریت
                            $admin_keyboard = json_encode([
                                'keyboard' => [
                                    [['text' => '📊 آمار ربات']],
                                    [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                    [['text' => '👥 مدیریت ادمین‌ها']],
                                    [['text' => '🔗 قفل گروه/کانال']],
                                    [['text' => '🔙 بازگشت به منوی اصلی']]
                                ],
                                'resize_keyboard' => true
                            ]);
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                            
                            // تغییر وضعیت کاربر
                            $userState['step'] = 'main_menu';
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                            break;
                            
                        // منتظر دریافت آیدی ادمین برای حذف
                        case 'waiting_for_admin_to_remove':
                            // بررسی آیا کاربر درخواست لغو کرده است
                            if (strpos($text, 'لغو') !== false) {
                                // بازگشت به منوی پنل مدیریت
                                $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                                $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '🔗 قفل گروه/کانال']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "درخواست حذف ادمین لغو شد\n";
                                continue 2;
                            }
                            
                            // جستجوی کاربر با آیدی یا نام کاربری
                            $searchQuery = $text;
                            
                            // استفاده از AdminController برای حذف ادمین
                            require_once __DIR__ . '/application/controllers/AdminController.php';
                            $adminController = new \application\controllers\AdminController($user_id);
                            
                            // حذف دسترسی ادمین
                            $result = $adminController->removeAdmin($searchQuery);
                            
                            if ($result['success']) {
                                // اطلاع‌رسانی موفقیت به ادمین
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ " . $result['message']);
                                
                                // اطلاع‌رسانی به کاربر
                                if (isset($result['user']['telegram_id'])) {
                                    $removed_admin_message = "⚠️ *اطلاعیه*\n\n";
                                    $removed_admin_message .= "دسترسی‌های ادمین شما در ربات حذف شده است.";
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $result['user']['telegram_id'], $removed_admin_message);
                                }
                            } else {
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ " . $result['message']);
                            }
                            
                            // بازگشت به منوی پنل مدیریت
                            $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                            $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                            
                            // کیبورد مدیریت
                            $admin_keyboard = json_encode([
                                'keyboard' => [
                                    [['text' => '📊 آمار ربات']],
                                    [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                    [['text' => '👥 مدیریت ادمین‌ها']],
                                    [['text' => '🔗 قفل گروه/کانال']],
                                    [['text' => '🔙 بازگشت به منوی اصلی']]
                                ],
                                'resize_keyboard' => true
                            ]);
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                            
                            // تغییر وضعیت کاربر
                            $userState['step'] = 'main_menu';
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                            break;
                        
                        // منتظر دریافت آیدی ادمین برای مدیریت (روش قدیمی)
                        case 'waiting_for_admin_id':
                            // بررسی آیا کاربر درخواست لغو کرده است
                            if (strpos($text, 'لغو') !== false) {
                                // بازگشت به منوی پنل مدیریت
                                $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                                $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '🔗 قفل گروه/کانال']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "درخواست مدیریت ادمین لغو شد\n";
                                continue 2;
                            }
                            
                            // جستجوی کاربر با آیدی یا نام کاربری
                            $searchQuery = $text;
                            
                            // بررسی آیا ورودی یک عدد (آیدی تلگرام) است
                            if (is_numeric($searchQuery)) {
                                $targetUser = \Application\Model\DB::table('users')
                                    ->where('telegram_id', $searchQuery)
                                    ->first();
                            } else {
                                // جستجو بر اساس نام کاربری
                                $targetUser = \Application\Model\DB::table('users')
                                    ->where('username', 'LIKE', '%' . $searchQuery . '%')
                                    ->first();
                            }
                            
                            if (!$targetUser) {
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ کاربر مورد نظر یافت نشد. لطفاً دوباره تلاش کنید یا برای لغو، دکمه «لغو» را بزنید.");
                                echo "کاربر مورد نظر برای مدیریت ادمین یافت نشد\n";
                                continue 2;
                            }
                            
                            // ذخیره اطلاعات کاربر هدف در وضعیت
                            $userState['target_user_id'] = $targetUser['id'];
                            $userState['target_telegram_id'] = $targetUser['telegram_id'];
                            $userState['step'] = 'select_admin_permissions';
                            
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                                
                            // نمایش اطلاعات کاربر و درخواست انتخاب دسترسی‌ها
                            $user_info = "👤 *اطلاعات کاربر*\n\n";
                            $user_info .= "• نام کاربری: {$targetUser['username']}\n";
                            $user_info .= "• آیدی تلگرام: {$targetUser['telegram_id']}\n";
                            $user_info .= "• نوع کاربر: {$targetUser['type']}\n\n";
                            $user_info .= "لطفاً دسترسی‌های مورد نظر را انتخاب کنید:";
                            
                            // کیبورد انتخاب دسترسی‌ها
                            $permissions_keyboard = json_encode([
                                'keyboard' => [
                                    [['text' => '✅ تبدیل به ادمین'], ['text' => '❌ حذف دسترسی ادمین']],
                                    [['text' => '✅ ارسال پیام همگانی'], ['text' => '❌ بدون ارسال پیام همگانی']],
                                    [['text' => '✅ مدیریت ادمین‌ها'], ['text' => '❌ بدون مدیریت ادمین‌ها']],
                                    [['text' => '✅ مدیریت بازی‌ها'], ['text' => '❌ بدون مدیریت بازی‌ها']],
                                    [['text' => '✅ مدیریت کاربران'], ['text' => '❌ بدون مدیریت کاربران']],
                                    [['text' => '💾 ذخیره تغییرات'], ['text' => 'لغو ❌']]
                                ],
                                'resize_keyboard' => true
                            ]);
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $user_info, $permissions_keyboard);
                            
                            // ذخیره وضعیت پیش‌فرض دسترسی‌ها
                            $userState['permissions'] = [
                                'is_admin' => false,
                                'can_send_broadcasts' => false,
                                'can_manage_admins' => false,
                                'can_manage_games' => false,
                                'can_manage_users' => false
                            ];
                            
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                                
                            echo "فرم مدیریت دسترسی‌های ادمین ارسال شد\n";
                            break;
                            
                        // انتخاب دسترسی‌های ادمین
                        case 'select_admin_permissions':
                            // بررسی آیا کاربر درخواست لغو کرده است
                            if (strpos($text, 'لغو') !== false) {
                                // بازگشت به منوی پنل مدیریت
                                $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                                $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '🔗 قفل گروه/کانال']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                unset($userState['target_user_id']);
                                unset($userState['target_telegram_id']);
                                unset($userState['permissions']);
                                
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "انتخاب دسترسی‌های ادمین لغو شد\n";
                                continue 2;
                            }
                            
                            // بررسی و به روز رسانی دسترسی‌ها بر اساس انتخاب کاربر
                            if (strpos($text, 'تبدیل به ادمین') !== false) {
                                $userState['permissions']['is_admin'] = true;
                            } else if (strpos($text, 'حذف دسترسی ادمین') !== false) {
                                $userState['permissions']['is_admin'] = false;
                            } else if (strpos($text, 'ارسال پیام همگانی') !== false) {
                                $userState['permissions']['can_send_broadcasts'] = true;
                            } else if (strpos($text, 'بدون ارسال پیام همگانی') !== false) {
                                $userState['permissions']['can_send_broadcasts'] = false;
                            } else if (strpos($text, 'مدیریت ادمین‌ها') !== false && strpos($text, 'بدون') === false) {
                                $userState['permissions']['can_manage_admins'] = true;
                            } else if (strpos($text, 'بدون مدیریت ادمین‌ها') !== false) {
                                $userState['permissions']['can_manage_admins'] = false;
                            } else if (strpos($text, 'مدیریت بازی‌ها') !== false && strpos($text, 'بدون') === false) {
                                $userState['permissions']['can_manage_games'] = true;
                            } else if (strpos($text, 'بدون مدیریت بازی‌ها') !== false) {
                                $userState['permissions']['can_manage_games'] = false;
                            } else if (strpos($text, 'مدیریت کاربران') !== false && strpos($text, 'بدون') === false) {
                                $userState['permissions']['can_manage_users'] = true;
                            } else if (strpos($text, 'بدون مدیریت کاربران') !== false) {
                                $userState['permissions']['can_manage_users'] = false;
                            } else if (strpos($text, 'ذخیره تغییرات') !== false) {
                                // اعمال تغییرات به دیتابیس
                                if ($userState['permissions']['is_admin']) {
                                    // تغییر نوع کاربر به ادمین
                                    \Application\Model\DB::table('users')
                                        ->where('id', $userState['target_user_id'])
                                        ->update(['type' => 'admin']);
                                        
                                    // حذف دسترسی‌های قبلی
                                    \Application\Model\DB::table('admin_permissions')
                                        ->where('user_id', $userState['target_user_id'])
                                        ->delete();
                                        
                                    // اضافه کردن دسترسی‌های جدید
                                    $permissionsData = [
                                        'user_id' => $userState['target_user_id'],
                                        'role' => 'admin',
                                        'can_send_broadcasts' => $userState['permissions']['can_send_broadcasts'],
                                        'can_manage_admins' => $userState['permissions']['can_manage_admins'],
                                        'can_manage_games' => $userState['permissions']['can_manage_games'],
                                        'can_manage_users' => $userState['permissions']['can_manage_users'],
                                        'can_view_statistics' => true
                                    ];
                                    
                                    \Application\Model\DB::table('admin_permissions')->insert($permissionsData);
                                    
                                    // ارسال پیام تأیید
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ دسترسی‌های ادمین با موفقیت ثبت شد.");
                                    
                                    // ارسال پیام به کاربر مورد نظر
                                    $notification = "🎖 *اطلاعیه ارتقاء سطح دسترسی*\n\n";
                                    $notification .= "شما به عنوان ادمین ربات انتخاب شده‌اید!\n";
                                    $notification .= "برای دسترسی به پنل مدیریت، می‌توانید از دکمه «⚙️ پنل مدیریت» در منوی اصلی استفاده کنید.";
                                    
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $userState['target_telegram_id'], $notification);
                                } else {
                                    // حذف دسترسی‌های ادمین
                                    \Application\Model\DB::table('users')
                                        ->where('id', $userState['target_user_id'])
                                        ->update(['type' => 'user']);
                                        
                                    \Application\Model\DB::table('admin_permissions')
                                        ->where('user_id', $userState['target_user_id'])
                                        ->delete();
                                        
                                    // ارسال پیام تأیید
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ دسترسی‌های ادمین با موفقیت حذف شد.");
                                    
                                    // ارسال پیام به کاربر مورد نظر
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $userState['target_telegram_id'], "⚠️ *اطلاعیه تغییر سطح دسترسی*\n\nدسترسی ادمین شما در ربات لغو شده است.");
                                }
                                
                                // بازگشت به منوی پنل مدیریت
                                $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                                $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '🔗 قفل گروه/کانال']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                unset($userState['target_user_id']);
                                unset($userState['target_telegram_id']);
                                unset($userState['permissions']);
                                
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "دسترسی‌های ادمین ذخیره شد\n";
                                continue 2;
                            }
                            
                            // به روز رسانی وضعیت کاربر برای ذخیره دسترسی‌ها
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                                
                            // ارسال پیام وضعیت دسترسی‌ها
                            $status = "🔄 *وضعیت دسترسی‌ها:*\n\n";
                            $status .= "• ادمین: " . ($userState['permissions']['is_admin'] ? "✅" : "❌") . "\n";
                            $status .= "• ارسال پیام همگانی: " . ($userState['permissions']['can_send_broadcasts'] ? "✅" : "❌") . "\n";
                            $status .= "• مدیریت ادمین‌ها: " . ($userState['permissions']['can_manage_admins'] ? "✅" : "❌") . "\n";
                            $status .= "• مدیریت بازی‌ها: " . ($userState['permissions']['can_manage_games'] ? "✅" : "❌") . "\n";
                            $status .= "• مدیریت کاربران: " . ($userState['permissions']['can_manage_users'] ? "✅" : "❌") . "\n\n";
                            $status .= "لطفاً دسترسی‌های دیگر را انتخاب کنید یا برای ذخیره، دکمه «ذخیره تغییرات» را بزنید.";
                            
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $status);
                            echo "وضعیت دسترسی‌های ادمین به روز شد\n";
                            break;
                            
                        // منتظر دریافت آیدی کانال/گروه
                        case 'waiting_for_channel_id':
                            // بررسی آیا کاربر درخواست لغو کرده است
                            if (strpos($text, 'لغو') !== false) {
                                // بازگشت به منوی پنل مدیریت
                                $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                                $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '🔗 قفل گروه/کانال']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "درخواست قفل گروه/کانال لغو شد\n";
                                continue 2;
                            }
                            
                            // ذخیره آیدی کانال/گروه
                            $channel_id = $text;
                            $userState['channel_id'] = $channel_id;
                            $userState['step'] = 'waiting_for_channel_name';
                            
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                                
                            // درخواست نام کانال/گروه
                            $message = "📝 *نام گروه/کانال*\n\n";
                            $message .= "لطفاً نام گروه/کانال را وارد کنید:";
                            
                            // کیبورد لغو
                            $cancel_keyboard = json_encode([
                                'keyboard' => [
                                    [['text' => 'لغو ❌']]
                                ],
                                'resize_keyboard' => true
                            ]);
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $cancel_keyboard);
                            echo "آیدی کانال/گروه دریافت شد\n";
                            break;
                            
                        // منتظر دریافت نام کانال/گروه
                        case 'waiting_for_channel_name':
                            // بررسی آیا کاربر درخواست لغو کرده است
                            if (strpos($text, 'لغو') !== false) {
                                // بازگشت به منوی پنل مدیریت
                                $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                                $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                                
                                // کیبورد مدیریت
                                $admin_keyboard = json_encode([
                                    'keyboard' => [
                                        [['text' => '📊 آمار ربات']],
                                        [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                        [['text' => '👥 مدیریت ادمین‌ها']],
                                        [['text' => '🔗 قفل گروه/کانال']],
                                        [['text' => '🔙 بازگشت به منوی اصلی']]
                                    ],
                                    'resize_keyboard' => true
                                ]);
                                
                                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                                
                                // تغییر وضعیت کاربر
                                $userState['step'] = 'main_menu';
                                unset($userState['channel_id']);
                                
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($userState)]);
                                    
                                echo "درخواست قفل گروه/کانال لغو شد\n";
                                continue 2;
                            }
                            
                            // ذخیره نام کانال/گروه
                            $channel_name = $text;
                            $userState['channel_name'] = $channel_name;
                            
                            // تولید توکن تصادفی
                            $token = substr(md5(uniqid(rand(), true)), 0, 10);
                            
                            // ذخیره اطلاعات کانال/گروه در دیتابیس
                            $channelData = [
                                'channel_id' => $userState['channel_id'],
                                'channel_name' => $channel_name,
                                'channel_type' => strpos($userState['channel_id'], '-100') === 0 ? 'channel' : 'group',
                                'token' => $token,
                                'is_active' => true
                            ];
                            
                            $channel_id = \Application\Model\DB::table('channel_locks')->insert($channelData);
                            
                            // ارسال پیام تأیید
                            $message = "✅ *گروه/کانال ثبت شد*\n\n";
                            $message .= "• شناسه: {$userState['channel_id']}\n";
                            $message .= "• نام: {$channel_name}\n";
                            $message .= "• توکن: `{$token}`\n\n";
                            $message .= "این توکن را باید در کانال/گروه خود به صورت پین شده قرار دهید تا کاربران بتوانند از ربات استفاده کنند.";
                            
                            // بازگشت به منوی پنل مدیریت
                            $admin_keyboard = json_encode([
                                'keyboard' => [
                                    [['text' => '📊 آمار ربات']],
                                    [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                                    [['text' => '👥 مدیریت ادمین‌ها']],
                                    [['text' => '🔗 قفل گروه/کانال']],
                                    [['text' => '🔙 بازگشت به منوی اصلی']]
                                ],
                                'resize_keyboard' => true
                            ]);
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $admin_keyboard);
                            
                            // تغییر وضعیت کاربر
                            $userState['step'] = 'main_menu';
                            unset($userState['channel_id']);
                            unset($userState['channel_name']);
                            
                            \Application\Model\DB::table('users')
                                ->where('telegram_id', $user_id)
                                ->update(['state' => json_encode($userState)]);
                                
                            echo "گروه/کانال با موفقیت ثبت شد\n";
                            break;
                    }
                    
                    continue;
                }
                
                // اگر کاربر در حال ارسال موقعیت مکانی است
                if ($userState['state'] === 'profile' && $userState['step'] === 'location') {
                    $latitude = $update['message']['location']['latitude'];
                    $longitude = $update['message']['location']['longitude'];
                    $location_json = json_encode(['lat' => $latitude, 'lng' => $longitude]);
                    
                    // ذخیره موقعیت مکانی در پروفایل کاربر
                    $profileExists = \Application\Model\DB::table('user_profiles')
                        ->where('user_id', $userData['id'])
                        ->exists();
                    
                    if ($profileExists) {
                        \Application\Model\DB::table('user_profiles')
                            ->where('user_id', $userData['id'])
                            ->update(['location' => $location_json]);
                    } else {
                        \Application\Model\DB::table('user_profiles')->insert([
                            'user_id' => $userData['id'],
                            'location' => $location_json
                        ]);
                    }
                    
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ موقعیت مکانی شما با موفقیت ثبت شد.");
                    
                    // بازگشت به منوی پروفایل
                    $userState = [
                        'state' => 'profile',
                        'step' => 'menu'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('id', $userData['id'])
                        ->update(['state' => json_encode($userState)]);
                    
                    // فراخوانی مجدد منوی پروفایل
                    $text = "📝 پروفایل";
                    $update['message']['text'] = $text;
                }
                
            } catch (Exception $e) {
                echo "خطا در پردازش موقعیت مکانی: " . $e->getMessage() . "\n";
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش موقعیت مکانی: " . $e->getMessage());
            }
        }
        
        // پردازش شماره تماس
        if (isset($update['message']) && isset($update['message']['contact'])) {
            $chat_id = $update['message']['chat']['id'];
            $user_id = $update['message']['from']['id'];
            
            // دریافت اطلاعات کاربر و وضعیت فعلی
            try {
                $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                
                if (!$userData || !isset($userData['state']) || empty($userData['state'])) {
                    // اگر وضعیتی برای کاربر تعریف نشده، شماره را نادیده می‌گیریم
                    continue;
                }
                
                $userState = json_decode($userData['state'], true);
                
                // اگر کاربر در حال ارسال شماره تماس است
                if ($userState['state'] === 'profile' && $userState['step'] === 'phone') {
                    $phone_number = $update['message']['contact']['phone_number'];
                    
                    // بررسی اینکه آیا شماره تلفن ایرانی است (شروع با +98)
                    $is_iranian = (strpos($phone_number, '+98') === 0);
                    
                    // ذخیره شماره تماس در پروفایل کاربر
                    $profileExists = \Application\Model\DB::table('user_profiles')
                        ->where('user_id', $userData['id'])
                        ->exists();
                    
                    if ($profileExists) {
                        \Application\Model\DB::table('user_profiles')
                            ->where('user_id', $userData['id'])
                            ->update(['phone' => $phone_number]);
                    } else {
                        \Application\Model\DB::table('user_profiles')->insert([
                            'user_id' => $userData['id'],
                            'phone' => $phone_number
                        ]);
                    }
                    
                    $message = "✅ شماره تلفن شما با موفقیت ثبت شد.";
                    if ($is_iranian) {
                        $message .= "\n\n✅ شماره شما ایرانی است و مشمول دریافت پورسانت می‌باشد.";
                    } else {
                        $message .= "\n\n❌ شماره شما ایرانی نیست و مشمول دریافت پورسانت نمی‌باشد.";
                    }
                    
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                    
                    // بازگشت به منوی پروفایل
                    $userState = [
                        'state' => 'profile',
                        'step' => 'menu'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('id', $userData['id'])
                        ->update(['state' => json_encode($userState)]);
                    
                    // فراخوانی مجدد منوی پروفایل
                    $text = "📝 پروفایل";
                    $update['message']['text'] = $text;
                }
                
            } catch (Exception $e) {
                echo "خطا در پردازش شماره تماس: " . $e->getMessage() . "\n";
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش شماره تماس: " . $e->getMessage());
            }
        }
        
        // پردازش پیام‌های متنی
        if (isset($update['message']) && isset($update['message']['text'])) {
            $text = $update['message']['text'];
            $chat_id = $update['message']['chat']['id'];
            $user_id = $update['message']['from']['id'];
            $username = isset($update['message']['from']['username']) ? 
                        $update['message']['from']['username'] : 'بدون نام کاربری';
            
            echo "پیام از {$username}: {$text} - Telegram ID: {$user_id}\n";
            
            // بررسی وضعیت کاربر برای تغییر نام کاربری و سایر حالت‌های ویژه
            try {
                // بررسی آیا کاربر در حالتی خاص است
                $user_state_file = __DIR__ . "/user_states/{$user_id}.json";
                if (file_exists($user_state_file)) {
                    $userState = json_decode(file_get_contents($user_state_file), true);
                    
                    // پردازش حالت تغییر نام کاربری
                    if (isset($userState['state']) && $userState['state'] === 'change_username') {
                        // دریافت اطلاعات کاربر از دیتابیس
                        $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                        
                        if (!$userData) {
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                            echo "خطا: کاربر در دیتابیس یافت نشد\n";
                            unlink($user_state_file); // حذف فایل وضعیت
                            continue;
                        }
                        
                        // دریافت اطلاعات اضافی کاربر
                        $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $userData['id'])->select('*')->first();
                        if (!$userExtra) {
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات اضافی کاربر");
                            echo "خطا: اطلاعات اضافی کاربر یافت نشد\n";
                            unlink($user_state_file); // حذف فایل وضعیت
                            continue;
                        }
                        
                        if ($userState['step'] === 'waiting_for_username') {
                            // بررسی نام کاربری جدید
                            $new_username = trim($text);
                            
                            // بررسی وجود کاربر دیگر با همین نام کاربری
                            // جایگزین با rawQuery برای استفاده از عملگر !=
                            $existingUser = \Application\Model\DB::rawQuery(
                                "SELECT * FROM users WHERE username = ? AND id != ? LIMIT 1", 
                                [$new_username, $userData['id']]
                            );
                            $existingUser = !empty($existingUser) ? $existingUser[0] : null;
                            
                            if ($existingUser) {
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ این نام کاربری قبلاً توسط کاربر دیگری انتخاب شده است. لطفاً نام کاربری دیگری انتخاب کنید.");
                                continue;
                            }
                            
                            // تایید نام کاربری
                            $confirm_message = "آیا مطمئنید میخواهید {$new_username} را برای نام کاربری خود استفاده کنید؟";
                            $confirm_keyboard = json_encode([
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'بله', 'callback_data' => "confirm_username_change:{$new_username}:yes"],
                                        ['text' => 'خیر', 'callback_data' => "confirm_username_change:{$new_username}:no"]
                                    ]
                                ]
                            ]);
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $confirm_message, $confirm_keyboard);
                            
                            // آپدیت وضعیت کاربر به مرحله تایید
                            $userState['step'] = 'waiting_for_confirmation';
                            $userState['new_username'] = $new_username;
                            file_put_contents($user_state_file, json_encode($userState));
                            
                            continue;
                        }
                    }
                    // پردازش حالت تکمیل پروفایل
                    else if (isset($userState['state']) && $userState['state'] === 'profile_completion') {
                        // پردازش مراحل مختلف پروفایل
                        require_once __DIR__ . '/application/controllers/ProfileController.php';
                        $profileController = new \application\controllers\ProfileController($user_id);
                        
                        // بررسی مرحله فعلی
                        if (isset($userState['step'])) {
                            // پردازش مرحله‌ای بر اساس وضعیت کاربر
                            $step = $userState['step'];
                            
                            // استفاده از متد جدید برای پردازش مراحل مختلف
                            $result = $profileController->handleProfileStep($update['message'], $step);
                            
                            // بررسی وضعیت و مرحله بعدی
                            if ($result) {
                                // به‌روزرسانی وضعیت کاربر
                                $newState = [
                                    'state' => $result['next_state'] ?? $userState['state'],
                                    'step' => $result['next_step'] ?? null
                                ];
                                
                                // ذخیره وضعیت جدید
                                \Application\Model\DB::table('users')
                                    ->where('telegram_id', $user_id)
                                    ->update(['state' => json_encode($newState)]);
                                
                                echo "پردازش مرحله $step با وضعیت " . ($result['status'] ?? 'نامشخص') . " انجام شد\n";
                            } else {
                                echo "خطا در پردازش مرحله $step\n";
                            }
                            continue;
                        } else {
                            echo "اطلاعات مرحله مشخص نشده\n";
                            // دریافت اطلاعات کاربر از دیتابیس
                            $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                            
                            if (!$userData) {
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                                echo "خطا: کاربر در دیتابیس یافت نشد\n";
                                continue;
                            }
                        }
                        
                        // پردازش مراحل مختلف تکمیل پروفایل
                        if ($userState['step'] === 'waiting_for_name') {
                            // ثبت نام
                            $full_name = trim($text);
                            
                            if (strlen($full_name) > 100) {
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ نام وارد شده خیلی طولانی است. لطفاً نام کوتاه‌تری وارد کنید.");
                                continue;
                            }
                            
                            // ذخیره نام در پروفایل کاربر
                            $profileExists = \Application\Model\DB::table('user_profiles')
                                ->where('user_id', $userData['id'])
                                ->exists();
                                
                            if ($profileExists) {
                                \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->update(['full_name' => $full_name]);
                            } else {
                                \Application\Model\DB::table('user_profiles')->insert([
                                    'user_id' => $userData['id'],
                                    'full_name' => $full_name
                                ]);
                            }
                            
                            // به روز رسانی وضعیت کاربر به مرحله بعدی - جنسیت
                            $userState['step'] = 'waiting_for_gender';
                            file_put_contents($user_state_file, json_encode($userState));
                            
                            // مرحله بعدی - انتخاب جنسیت
                            $message = "📝 *تکمیل پروفایل*\n\n";
                            $message .= "مرحله 3/7: لطفاً جنسیت خود را انتخاب کنید.";
                            
                            // ایجاد دکمه انتخاب جنسیت
                            $gender_keyboard = [
                                'keyboard' => [
                                    [
                                        ['text' => '👨 پسر'], ['text' => '👩 دختر']
                                    ],
                                    [
                                        ['text' => 'لغو ❌']
                                    ]
                                ],
                                'resize_keyboard' => true
                            ];
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, json_encode($gender_keyboard));
                            continue;
                        }
                        else if ($userState['step'] === 'waiting_for_gender') {
                            // پردازش انتخاب جنسیت
                            $gender = '';
                            if (strpos($text, 'پسر') !== false) {
                                $gender = 'male';
                            } else if (strpos($text, 'دختر') !== false) {
                                $gender = 'female';
                            } else {
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ لطفاً یکی از گزینه‌های نمایش داده شده را انتخاب کنید.");
                                continue;
                            }
                            
                            // ذخیره جنسیت در پروفایل کاربر
                            $profileExists = \Application\Model\DB::table('user_profiles')
                                ->where('user_id', $userData['id'])
                                ->exists();
                                
                            if ($profileExists) {
                                \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->update(['gender' => $gender]);
                            } else {
                                \Application\Model\DB::table('user_profiles')->insert([
                                    'user_id' => $userData['id'],
                                    'gender' => $gender
                                ]);
                            }
                            
                            // به روز رسانی وضعیت کاربر به مرحله بعدی - سن
                            $userState['step'] = 'waiting_for_age';
                            file_put_contents($user_state_file, json_encode($userState));
                            
                            // ایجاد دکمه های سن
                            $age_keyboard = ['keyboard' => [], 'resize_keyboard' => true];
                            $row = [];
                            for ($age = 9; $age <= 70; $age++) {
                                $row[] = ['text' => $age];
                                if (count($row) == 5 || $age == 70) { // 5 عدد در هر ردیف
                                    $age_keyboard['keyboard'][] = $row;
                                    $row = [];
                                }
                            }
                            $age_keyboard['keyboard'][] = [['text' => 'لغو ❌']];
                            
                            // مرحله بعدی - انتخاب سن
                            $message = "📝 *تکمیل پروفایل*\n\n";
                            $message .= "مرحله 4/7: لطفاً سن خود را انتخاب کنید.";
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, json_encode($age_keyboard));
                            continue;
                        }
                        else if ($userState['step'] === 'waiting_for_age') {
                            // پردازش انتخاب سن
                            $age = intval($text);
                            if ($age < 9 || $age > 70) {
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ لطفاً سن بین 9 تا 70 سال انتخاب کنید.");
                                continue;
                            }
                            
                            // ذخیره سن در پروفایل کاربر
                            $profileExists = \Application\Model\DB::table('user_profiles')
                                ->where('user_id', $userData['id'])
                                ->exists();
                                
                            if ($profileExists) {
                                \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->update(['age' => $age]);
                            } else {
                                \Application\Model\DB::table('user_profiles')->insert([
                                    'user_id' => $userData['id'],
                                    'age' => $age
                                ]);
                            }
                            
                            // به روز رسانی وضعیت کاربر به مرحله بعدی - بیوگرافی
                            $userState['step'] = 'waiting_for_bio';
                            file_put_contents($user_state_file, json_encode($userState));
                            
                            // مرحله بعدی - ارسال بیوگرافی
                            $message = "📝 *تکمیل پروفایل*\n\n";
                            $message .= "مرحله 5/7: لطفاً بیوگرافی کوتاهی درباره خود بنویسید.";
                            
                            $cancel_keyboard = [
                                'keyboard' => [
                                    [
                                        ['text' => 'لغو ❌']
                                    ]
                                ],
                                'resize_keyboard' => true
                            ];
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, json_encode($cancel_keyboard));
                            continue;
                        }
                        else if ($userState['step'] === 'waiting_for_bio') {
                            // ثبت بیوگرافی
                            $bio = trim($text);
                            
                            if (strlen($bio) > 300) {
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ بیوگرافی وارد شده خیلی طولانی است. لطفاً متن کوتاه‌تری وارد کنید.");
                                continue;
                            }
                            
                            // ذخیره بیوگرافی در پروفایل کاربر
                            $profileExists = \Application\Model\DB::table('user_profiles')
                                ->where('user_id', $userData['id'])
                                ->exists();
                                
                            if ($profileExists) {
                                \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->update(['bio' => $bio]);
                            } else {
                                \Application\Model\DB::table('user_profiles')->insert([
                                    'user_id' => $userData['id'],
                                    'bio' => $bio
                                ]);
                            }
                            
                            // ارسال بیوگرافی به کانال ادمین
                            $admin_channel_id = "-100123456789"; // آیدی کانال ادمین را قرار دهید
                            try {
                                $admin_message = "✅ درخواست تأیید بیوگرافی:\n\nکاربر: {$userData['username']}\nآیدی: {$userData['telegram_id']}\n\nبیوگرافی:\n{$bio}";
                                
                                $admin_keyboard = json_encode([
                                    'inline_keyboard' => [
                                        [
                                            ['text' => '✅ تأیید', 'callback_data' => "approve_bio:{$userData['id']}"],
                                            ['text' => '❌ رد', 'callback_data' => "reject_bio:{$userData['id']}"]
                                        ]
                                    ]
                                ]);
                                
                                // sendMessage($_ENV['TELEGRAM_TOKEN'], $admin_channel_id, $admin_message, $admin_keyboard);
                                echo "بیوگرافی به کانال ادمین ارسال شد\n";
                            } catch (Exception $e) {
                                echo "خطا در ارسال بیوگرافی به کانال ادمین: " . $e->getMessage() . "\n";
                            }
                            
                            // به روز رسانی وضعیت کاربر به مرحله بعدی - استان
                            $userState['step'] = 'waiting_for_province';
                            file_put_contents($user_state_file, json_encode($userState));
                            
                            // لیست استان‌های ایران
                            $provinces = [
                                "آذربایجان شرقی", "آذربایجان غربی", "اردبیل", "اصفهان", "البرز",
                                "ایلام", "بوشهر", "تهران", "چهارمحال و بختیاری", "خراسان جنوبی",
                                "خراسان رضوی", "خراسان شمالی", "خوزستان", "زنجان", "سمنان",
                                "سیستان و بلوچستان", "فارس", "قزوین", "قم", "کردستان",
                                "کرمان", "کرمانشاه", "کهگیلویه و بویراحمد", "گلستان", "گیلان",
                                "لرستان", "مازندران", "مرکزی", "هرمزگان", "همدان", "یزد"
                            ];
                            
                            // ایجاد کیبورد استان‌ها
                            $province_keyboard = ['keyboard' => [], 'resize_keyboard' => true];
                            $row = [];
                            foreach ($provinces as $province) {
                                $row[] = ['text' => $province];
                                if (count($row) == 2) {
                                    $province_keyboard['keyboard'][] = $row;
                                    $row = [];
                                }
                            }
                            if (!empty($row)) {
                                $province_keyboard['keyboard'][] = $row;
                            }
                            $province_keyboard['keyboard'][] = [['text' => 'ترجیح میدهم نگویم']];
                            $province_keyboard['keyboard'][] = [['text' => 'لغو ❌']];
                            
                            // مرحله بعدی - انتخاب استان
                            $message = "📝 *تکمیل پروفایل*\n\n";
                            $message .= "مرحله 6/7: لطفاً استان خود را انتخاب کنید.";
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, json_encode($province_keyboard));
                            continue;
                        }
                    }
                }
            } catch (Exception $e) {
                echo "خطا در پردازش وضعیت کاربر: " . $e->getMessage() . "\n";
            }
            
            // بررسی پیام چت بازی
            $active_match = getActiveMatchForUser($user_id);
            if ($active_match && $text[0] !== '/') {
                // تعیین گیرنده پیام (بازیکن دیگر)
                $recipient_id = ($active_match['player1'] == $user_id) ? $active_match['player2'] : $active_match['player1'];
                
                // بررسی امکان ارسال پیام
                $chat_enabled = true;
                try {
                    // بررسی وضعیت فعال بودن چت
                    $match_data = \Application\Model\DB::table('matches')
                        ->where('id', $active_match['id'])
                        ->select('chat_enabled')
                        ->first();
                    
                    if ($match_data && isset($match_data['chat_enabled']) && $match_data['chat_enabled'] === false) {
                        $chat_enabled = false;
                    }
                } catch (Exception $e) {
                    // اگر ستون وجود نداشت، فرض کنید چت فعال است
                    echo "خطا در بررسی وضعیت چت: " . $e->getMessage() . "\n";
                }
                
                if (!$chat_enabled) {
                    // چت غیرفعال است
                    $response = "قابلیت چت غیرفعال میباشد پیام شما ارسال نشد!";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $user_id, $response);
                    
                    // نمایش دکمه درخواست فعال کردن چت
                    $reactivate_keyboard = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '🔄 فعال کردن مجدد چت', 'callback_data' => "request_chat:{$active_match['id']}"]
                            ]
                        ]
                    ]);
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $user_id, "برای درخواست فعال کردن چت از دکمه زیر استفاده کنید:", $reactivate_keyboard);
                    continue;
                }
                
                // بررسی نوع پیام ارسالی
                if (isset($update['message']['sticker']) || 
                    isset($update['message']['animation']) || 
                    isset($update['message']['photo']) || 
                    isset($update['message']['video']) || 
                    isset($update['message']['voice']) || 
                    isset($update['message']['audio']) || 
                    isset($update['message']['document'])) {
                    // پیام غیر متنی است
                    $response = "شما تنها مجاز به ارسال پیام بصورت متنی میباشید\nپیام شما ارسال نشد";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $user_id, $response);
                    continue;
                }
                
                // بررسی وجود لینک در پیام
                if (preg_match('/(https?:\/\/[^\s]+)/i', $text) || 
                    preg_match('/(www\.[^\s]+)/i', $text) || 
                    preg_match('/(@[^\s]+)/i', $text) || 
                    preg_match('/(t\.me\/[^\s]+)/i', $text)) {
                    // پیام حاوی لینک است
                    $response = "ارسال لینک ممنوع میباشد!\nپیام شما ارسال نشد";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $user_id, $response);
                    continue;
                }
                

                
                // ارسال پیام به بازیکن دیگر
                $sender_name = isset($update['message']['from']['first_name']) ? $update['message']['from']['first_name'] : 'بازیکن';
                $forward_text = "👤 {$sender_name}: {$text}";
                
                // دکمه‌های واکنش
                $reaction_keyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '👍', 'callback_data' => "reaction:{$update['message']['message_id']}:like"],
                            ['text' => '👎', 'callback_data' => "reaction:{$update['message']['message_id']}:dislike"],
                            ['text' => '❤️', 'callback_data' => "reaction:{$update['message']['message_id']}:love"],
                            ['text' => '😂', 'callback_data' => "reaction:{$update['message']['message_id']}:laugh"],
                            ['text' => '😮', 'callback_data' => "reaction:{$update['message']['message_id']}:wow"]
                        ],
                        [
                            ['text' => '😢', 'callback_data' => "reaction:{$update['message']['message_id']}:sad"],
                            ['text' => '😡', 'callback_data' => "reaction:{$update['message']['message_id']}:angry"],
                            ['text' => '👏', 'callback_data' => "reaction:{$update['message']['message_id']}:clap"],
                            ['text' => '🔥', 'callback_data' => "reaction:{$update['message']['message_id']}:fire"],
                            ['text' => '🎉', 'callback_data' => "reaction:{$update['message']['message_id']}:party"]
                        ]
                    ]
                ]);
                
                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $recipient_id, $forward_text, $reaction_keyboard);
                echo "پیام از کاربر {$user_id} به کاربر {$recipient_id} ارسال شد\n";
                continue;
            }
            
            // پردازش دستور /cancel
            if ($text === '/cancel') {
                echo "دستور cancel دریافت شد - در حال حذف بازی‌های در انتظار...\n";
                
                // حذف بازی‌های در انتظار
                try {
                    // روش اصلاح شده برای حذف بازی‌های در انتظار
                    $deleted = \Application\Model\DB::table('matches')
                        ->where('player1', $user_id)
                        ->where('status', 'pending')
                        ->delete();
                    
                    $response_text = "✅ جستجوی بازیکن لغو شد.";
                    
                    // ارسال پاسخ
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $response_text);
                    echo "پاسخ ارسال شد: {$response_text}\n";
                } catch (Exception $e) {
                    echo "خطا: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در لغو جستجو: " . $e->getMessage());
                }
            }
            
            // پاسخ به دکمه بازی با ناشناس
            else if (strpos($text, 'بازی با ناشناس') !== false) {
                try {
                    // ارسال پیام در حال یافتن بازیکن - دقیقاً متن اصلی
                    $response_text = "در حال یافتن بازیکن 🕔\n\nبرای لغو جستجو، دستور /cancel را ارسال کنید.";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $response_text);
                    echo "پاسخ ارسال شد: {$response_text}\n";
                    
                    // ثبت در پایگاه داده بازی جدید در وضعیت pending
                    $helper = new application\controllers\HelperController();
                    $current_time = date('Y-m-d H:i:s');
                    \Application\Model\DB::table('matches')->insert([
                        'player1' => $user_id, 
                        'player1_hash' => $helper->Hash(), 
                        'type' => 'anonymous',
                        'created_at' => $current_time,
                        'last_action_time' => $current_time
                    ]);
                    
                    echo "بازی جدید در وضعیت pending ایجاد شد\n";
                } catch (Exception $e) {
                    echo "خطا در ایجاد بازی: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در ایجاد بازی: " . $e->getMessage());
                }
            }
            
            // شرکت در مسابقه
            else if (strpos($text, 'شرکت در مسابقه') !== false) {
                $response_text = "cooming soon ..."; // عینا از متن اصلی
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $response_text);
                echo "پاسخ ارسال شد: {$response_text}\n";
            }
            
            // حساب کاربری
            else if (strpos($text, 'حساب کاربری') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $userData['id'])->select('*')->first();
                    if (!$userExtra) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات اضافی کاربر");
                        echo "خطا: اطلاعات اضافی کاربر یافت نشد\n";
                        return;
                    }
                    
                    // محاسبه رتبه کاربر - ساده‌سازی شده
                    $match_rank = 1; // فرض
                    $winRate_rank = 1; // فرض
                    
                    // بررسی دوستان (با در نظر گرفتن مقادیر خالی)
                    $friends = isset($userExtra['friends']) ? json_decode($userExtra['friends'], true) : null;
                    $friends_count = is_array($friends) ? count($friends) : 0;
                    
                    // اطمینان از وجود سایر مقادیر
                    $matches = isset($userExtra['matches']) ? $userExtra['matches'] : 0;
                    $win_rate = isset($userExtra['win_rate']) ? strval(number_format($userExtra['win_rate'], 2)) . "%" : "0%";
                    $cups = isset($userExtra['cups']) ? $userExtra['cups'] : 0;
                    $doz_coin = isset($userExtra['doz_coin']) ? $userExtra['doz_coin'] : 0;
                    
                    // ساخت متن پاسخ
                    $message = "
🪪 حساب کاربری شما به شرح زیر میباشد :

 🆔 نام کاربری :      /{$userData['username']}
🔢 آیدی عددی :      {$userData['telegram_id']}

🎮 تعداد بازیهای انجام شده:      {$matches}
🔆 رتبه تعداد بازی بین کاربران:     {$match_rank}

➗ درصد برد در کل بازیها:     {$win_rate}
〽️ رتبه درصد برد بین کاربران:     {$winRate_rank}

🥇 تعداد قهرمانی در مسابقه: coming soon
🎊 رتبه قهرمانی در مسابقه: coming soon

🏆 موجودی جام:     {$cups}
 💎 موجودی دلتاکوین:     {$doz_coin}

👥 تعداد دوستان:     {$friends_count}
⏰ تاریخ و ساعت ورود:     {$userData['created_at']}
";
                    
                    // ایجاد کیبورد مخصوص حساب کاربری
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '📝 پروفایل'], ['text' => '🏆 وضعیت زیرمجموعه ها']],
                            [['text' => '📝 تغییر نام کاربری']],
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    echo "اطلاعات حساب کاربری ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در دریافت اطلاعات کاربر: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات: " . $e->getMessage());
                }
            }
            
            // نفرات برتر
            else if (strpos($text, 'نفرات برتر') !== false) {
                $keyboard = json_encode([
                    'keyboard' => [
                        [['text' => 'نفرات برتر در درصد برد'], ['text' => 'نفرات برتر در تعداد جام']],
                        [['text' => 'نفرات برتر در تعداد بازی'], ['text' => 'نفرات برتر مسابقات هفتگی']],
                        [['text' => 'لغو ❌']]
                    ],
                    'resize_keyboard' => true
                ]);
                
                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, "🏆 لیست نفرات برتر برحسب کدام دسته بندی ارسال شود ؟", $keyboard);
                echo "منوی نفرات برتر ارسال شد\n";
            }
            
            // دوستان
            else if (strpos($text, 'دوستان') !== false) {
                $keyboard = json_encode([
                    'keyboard' => [
                        [['text' => 'لیست دوستان'], ['text' => 'افزودن دوست']],
                        [['text' => 'درخواست های دوستی'], ['text' => 'لغو ❌']]
                    ],
                    'resize_keyboard' => true
                ]);
                
                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, "با استفاده از دکمه های زیر بخش مورد نظر را انتخاب کنید👇", $keyboard);
                echo "منوی دوستان ارسال شد\n";
            }
            
            // نمایش درخواست‌های دوستی
            else if ($text === 'درخواست های دوستی') {
                try {
                    require_once __DIR__ . '/application/controllers/FriendshipController.php';
                    $friendshipController = new \application\controllers\FriendshipController($telegram_id);
                    $result = $friendshipController->getFriendRequests('received');
                    
                    if ($result['success']) {
                        if (count($result['requests']) > 0) {
                            $message = "📨 درخواست‌های دوستی دریافتی شما:\n\n";
                            $inlineKeyboard = [];
                            
                            foreach ($result['requests'] as $request) {
                                $senderName = $request['sender_username'] ? '@' . $request['sender_username'] : $request['sender_first_name'] . ' ' . $request['sender_last_name'];
                                $message .= "👤 {$senderName}\n";
                                
                                // دکمه‌های قبول یا رد درخواست برای هر کاربر
                                $inlineKeyboard[] = [
                                    ['text' => "✅ قبول {$senderName}", 'callback_data' => "accept_friend:{$request['id']}"],
                                    ['text' => "❌ رد {$senderName}", 'callback_data' => "reject_friend:{$request['id']}"]
                                ];
                            }
                            
                            $keyboard = [
                                'inline_keyboard' => $inlineKeyboard
                            ];
                            
                            sendMessageWithInlineKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, json_encode($keyboard));
                        } else {
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "📭 شما هیچ درخواست دوستی دریافت نشده‌ای ندارید.");
                        }
                    } else {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ خطا در دریافت درخواست‌های دوستی: " . $result['message']);
                    }
                } catch (\Exception $e) {
                    error_log("Error in showing friend requests: " . $e->getMessage());
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ خطایی در نمایش درخواست‌های دوستی رخ داده است.");
                }
                
                echo "درخواست‌های دوستی نمایش داده شد\n";
            }
            
            // کسب درآمد
            else if (strpos($text, 'کسب درآمد') !== false) {
                $message = "شما میتوانید با ربات ما کسب درآمد کنید ، حالا چطوری ⁉️

💸 روش های کسب درآمد در ربات : 

1️⃣ ساده ترین روش کسب درآمد بازی کردن در ربات است . شما در قسمت بازی با ناشناس میتوانید به ازای هر بُرد 0.2 دلتا کوین دریافت کنید، توجه داشته باشید که به ازای هر باخت در این قسمت 0.1 دلتا کوین از دست میدهید. 
2️⃣ این روش از طریق زیرمجموعه گیری ممکن است. در این روش با کلیک بر روی دکمه زیرمجموعه گیری بنر و لینک اختصاصی خود را دریافت میکنید و به دوستانتان ارسال میکنید، به ازای هر دعوت از طریق لینک شما 2 دلتا کوین دریافت میکنید.
3️⃣ روش سوم هنوز در ربات اعمال نشده است. در این روش از طریق شرکت در مسابقات ربات که در قسمت تورنومنت ها، جوایز بُرد هر بازی مشخص شده است ، میتوانید به جوایز ارزنده ای دست یابید.

‼️ توجه : ارزش هر دلتا کوین ، هزار تومن میباشد
1 دلتا کوین = 1000 تومن
0.1 دلتا کوین = 100 تومن";
                
                // کیبورد برای دکمه لینک رفرال
                $referral_keyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🔗 دریافت لینک رفرال', 'callback_data' => 'get_referral_link']
                        ]
                    ]
                ]);
                
                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $referral_keyboard);
                echo "اطلاعات کسب درآمد ارسال شد\n";
            }
            
            // پشتیبانی
            else if (strpos($text, 'پشتیبانی') !== false) {
                $message = "• به بخش پشتیبانی ربات خوشومدی(: 🤍

• سعی بخش پشتیبانی بر این است که تمامی پیام های دریافتی در کمتر از ۱۲ ساعت پاسخ داده شوند، بنابراین تا زمان دریافت پاسخ صبور باشید

• لطفا پیام، سوال، پیشنهاد و یا انتقاد خود را در قالب یک پیام واحد و بدون احوالپرسی و ... ارسال کنید 👇🏻

👨‍💻 @Doz_Sup";
                
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                echo "اطلاعات پشتیبانی ارسال شد\n";
            }
            
            // راهنما
            else if (strpos($text, 'راهنما') !== false) {
                $message = "🎮 نحوه بازی : 
1️⃣ با انتخاب هر دکمه ( 1 تا 7 ) یک مهره داخل ستون مربوطه می افتد و در پایین ترین محل خالی قرار میگیرد. 

2️⃣ دو نفر به نوبت بازی میکنند و به یک بازیکن رنگ 🔵 و بازیکن دیگر رنگ 🔴 اختصاص داده میشود.

3️⃣ بازیکنان باید تلاش کنند تا 4 مهره از رنگ خود را به صورت عمودی، افقی یا مایل مانند شکل زیر ردیف کنند.

به 3 مثال زیر توجه کنید :

1- برنده : آبی    روش: افقی
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️🔴⚪️⚪️⚪️
⚪️🔵🔵🔵🔵⚪️⚪️
⚪️🔴🔴🔴🔵⚪️⚪️
1️⃣2️⃣3️⃣4️⃣5️⃣6️⃣7️⃣

2- برنده : قرمز     روش: مایل
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️⚪️⚪️⚪️🔴
⚪️⚪️⚪️⚪️⚪️🔴🔵
⚪️⚪️⚪️⚪️🔴🔵🔴
🔴⚪️🔵🔴🔵🔵🔵
1️⃣2️⃣3️⃣4️⃣5️⃣6️⃣7️⃣

3- برنده : آبی      روش: عمودی
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️🔵⚪️⚪️⚪️
⚪️⚪️⚪️🔵🔴⚪️⚪️
⚪️⚪️⚪️🔵🔴⚪️⚪️
⚪️⚪️⚪️🔵🔴⚪️⚪️
1️⃣2️⃣3️⃣4️⃣5️⃣6️⃣7️⃣

دو سه بار بازی کنی قلق کار دستت میاد ❤️‍🔥
بازی خوبی داشته باشی 🫂";
                
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                echo "اطلاعات راهنما ارسال شد\n";
            }
            
            // پاسخ به دکمه وضعیت زیرمجموعه‌ها
            else if (strpos($text, 'وضعیت زیرمجموعه‌ها') !== false) {
                try {
                    // دریافت اطلاعات کاربر
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        continue;
                    }
                    
                    // دریافت لیست زیرمجموعه‌ها
                    $referrals = \Application\Model\DB::table('referrals')
                        ->where('referrer_id', $userData['id'])
                        ->get();
                    
                    if (empty($referrals)) {
                        $message = "📊 *وضعیت زیرمجموعه‌ها*\n\n";
                        $message .= "⚠️ شما هنوز هیچ زیرمجموعه‌ای ندارید!\n\n";
                        $message .= "برای دعوت از دوستان، لینک اختصاصی خود را به آنها ارسال کنید:\n";
// دریافت اطلاعات ربات
$botInfo = getBotInfo($_ENV['TELEGRAM_TOKEN']);
$botUsername = isset($botInfo['username']) ? $botInfo['username'] : 'your_bot';
$message .= "https://t.me/" . $botUsername . "?start=" . $userData['id'];
                        
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                        continue;
                    }
                    
                    // نمایش لیست زیرمجموعه‌ها
                    $message = "📊 *وضعیت زیرمجموعه‌ها*\n\n";
                    $message .= "لینک اختصاصی شما برای دعوت از دوستان:\n";
// دریافت اطلاعات ربات
$botInfo = getBotInfo($_ENV['TELEGRAM_TOKEN']);
$botUsername = isset($botInfo['username']) ? $botInfo['username'] : 'your_bot';

$message .= "https://t.me/" . $botUsername . "?start=" . $userData['id'] . "\n\n";
                    $message .= "📋 *لیست زیرمجموعه‌های شما:*\n";
                    
                    $total_rewards = 0;
                    $i = 1;
                    
                    // کیبورد برای نمایش اطلاعات بیشتر درباره هر زیرمجموعه
                    $inline_keyboard = [];
                    
                    foreach ($referrals as $referral) {
                        // دریافت اطلاعات کاربر زیرمجموعه
                        $referredUser = \Application\Model\DB::table('users')
                            ->where('id', $referral['referee_id'])
                            ->first();
                            
                        if ($referredUser) {
                            $row = [['text' => "{$i}. {$referredUser['username']} ➡️", 'callback_data' => "view_referral:{$referral['id']}"]];
                            $inline_keyboard[] = $row;
                            
                            // محاسبه پورسانت
                            $user_reward = 0;
                            if ($referral['started_rewarded']) $user_reward += 0.5;
                            if ($referral['first_win_rewarded']) $user_reward += 1.5;
                            if ($referral['profile_completed_rewarded']) $user_reward += 3;
                            if ($referral['thirty_wins_rewarded']) $user_reward += 5;
                            
                            $total_rewards += $user_reward;
                            $i++;
                        }
                    }
                    
                    $message .= "\nتعداد زیرمجموعه‌ها: " . count($referrals) . "\n";
                    $message .= "مجموع پورسانت دریافتی: " . $total_rewards . " دلتا کوین\n\n";
                    $message .= "🔍 برای مشاهده جزئیات هر زیرمجموعه، روی نام آن کلیک کنید.";
                    
                    // ارسال پیام با کیبورد
                    $keyboard = json_encode([
                        'inline_keyboard' => $inline_keyboard
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                } catch (Exception $e) {
                    echo "خطا در نمایش وضعیت زیرمجموعه‌ها: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در نمایش وضعیت زیرمجموعه‌ها: " . $e->getMessage());
                }
            }
            
            // پاسخ به دکمه دلتا کوین روزانه
            else if (strpos($text, 'دلتا کوین روزانه') !== false) {
                try {
                    // دریافت اطلاعات کاربر
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        continue;
                    }
                    
                    // قسمت توضیحات و کانال‌های اسپانسر
                    $message = "💰 *دلتا کوین روزانه*\n\n";
                    $message .= "برای دریافت دلتا کوین رایگانِ امروزتان در چنل(های) اسپانسری زیر عضو شده سپس روی «دریافت دلتا کوین» کلیک کنید.\n\n";
                    $message .= "📣 چنل‌های اسپانسر:\n";
                    $message .= "📌 [چنل 1](https://t.me/channel1)\n";
                    $message .= "📌 [چنل 2](https://t.me/channel2)\n";
                    
                    // کیبورد برای دریافت دلتا کوین
                    $keyboard = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '💰 دریافت دلتا کوین', 'callback_data' => "claim_daily_coin"]
                            ]
                        ]
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                } catch (Exception $e) {
                    echo "خطا در نمایش دلتا کوین روزانه: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در نمایش دلتا کوین روزانه: " . $e->getMessage());
                }
            }

            // پاسخ به دکمه پروفایل
            else if ($text === '📝 پروفایل') {
                try {
                    // دریافت اطلاعات کاربر
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        continue;
                    }
                    
                    $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $userData['id'])->first();
                    if (!$userExtra) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات اضافی کاربر");
                        continue;
                    }
                    
                    // پروفایل کاربر
                    $profile = "👤 *اطلاعات پروفایل شما*\n\n";
                    $profile .= "📛 *نام کاربری:* " . ($userData['username'] ?? 'تنظیم نشده') . "\n";
                    
                    // اطلاعات تکمیلی پروفایل (اگر ثبت شده باشد)
                    $userProfile = \Application\Model\DB::table('user_profiles')->where('user_id', $userData['id'])->first();
                    
                    if ($userProfile) {
                        if (isset($userProfile['full_name']) && !empty($userProfile['full_name'])) {
                            $profile .= "👤 *نام:* " . $userProfile['full_name'] . "\n";
                        }
                        if (isset($userProfile['gender'])) {
                            $gender_text = $userProfile['gender'] === 'male' ? 'پسر' : 'دختر';
                            $profile .= "👫 *جنسیت:* " . $gender_text . "\n";
                        }
                        if (isset($userProfile['age']) && $userProfile['age'] > 0) {
                            $profile .= "🎂 *سن:* " . $userProfile['age'] . "\n";
                        }
                        if (isset($userProfile['bio']) && !empty($userProfile['bio'])) {
                            $profile .= "📝 *بیوگرافی:* " . $userProfile['bio'] . "\n";
                        }
                        if (isset($userProfile['province']) && !empty($userProfile['province'])) {
                            $profile .= "🏠 *استان:* " . $userProfile['province'] . "\n";
                        }
                        if (isset($userProfile['city']) && !empty($userProfile['city'])) {
                            $profile .= "🏙️ *شهر:* " . $userProfile['city'] . "\n";
                        }
                    } else {
                        $profile .= "\n⚠️ پروفایل شما کامل نیست. برای کامل کردن پروفایل روی دکمه «تکمیل پروفایل» کلیک کنید.";
                    }
                    
                    // ایجاد دکمه‌های پروفایل
                    $keyboard = [
                        'keyboard' => [
                            [
                                ['text' => '👤 تکمیل پروفایل']
                            ],
                            [
                                ['text' => '🔙 بازگشت به منوی اصلی']
                            ]
                        ],
                        'resize_keyboard' => true
                    ];
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $profile, json_encode($keyboard));
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش پروفایل: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در نمایش پروفایل: " . $e->getMessage());
                }
            }
            
            // پاسخ به دکمه تکمیل پروفایل
            else if ($text === '👤 تکمیل پروفایل') {
                try {
                    // دریافت اطلاعات کاربر
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        continue;
                    }
                    
                    // نمایش منوی تکمیل پروفایل با دکمه‌های شیشه‌ای
                    $message = "📝 *تکمیل پروفایل*\n\n";
                    $message .= "با کلیک روی هر یک از گزینه‌های زیر، آن بخش از پروفایل خود را ویرایش کنید:\n";
                    
                    // دریافت اطلاعات پروفایل موجود
                    $profile = \Application\Model\DB::table('user_profiles')->where('user_id', $userData['id'])->first();
                    
                    // تعیین وضعیت هر بخش
                    $photo_status = isset($profile['photo_url']) && !empty($profile['photo_url']) ? "✅" : "❌";
                    $fullname_status = isset($profile['full_name']) && !empty($profile['full_name']) ? "✅" : "❌";
                    $gender_status = isset($profile['gender']) && !empty($profile['gender']) ? "✅" : "❌";
                    $age_status = isset($profile['age']) && $profile['age'] > 0 ? "✅" : "❌";
                    $bio_status = isset($profile['bio']) && !empty($profile['bio']) ? "✅" : "❌";
                    $province_status = isset($profile['province']) && !empty($profile['province']) ? "✅" : "❌";
                    $city_status = isset($profile['city']) && !empty($profile['city']) ? "✅" : "❌";
                    
                    // ایجاد کیبورد شیشه‌ای
                    $inline_keyboard = [
                        [['text' => "🖼 عکس پروفایل {$photo_status}", 'callback_data' => 'profile:edit_photo']],
                        [['text' => "👤 نام کامل {$fullname_status}", 'callback_data' => 'profile:edit_fullname']],
                        [['text' => "👫 جنسیت {$gender_status}", 'callback_data' => 'profile:edit_gender']],
                        [['text' => "🔢 سن {$age_status}", 'callback_data' => 'profile:edit_age']],
                        [['text' => "📝 بیوگرافی {$bio_status}", 'callback_data' => 'profile:edit_bio']],
                        [['text' => "🏙 استان {$province_status}", 'callback_data' => 'profile:edit_province']],
                        [['text' => "🏢 شهر {$city_status}", 'callback_data' => 'profile:edit_city']],
                        [['text' => "📍 اشتراک‌گذاری موقعیت", 'callback_data' => 'profile:edit_location']],
                        [['text' => "📱 شماره تلگرام", 'callback_data' => 'profile:edit_phone']],
                        [['text' => "🔙 بازگشت", 'callback_data' => 'profile:back']]
                    ];
                    
                    $keyboard = [
                        'inline_keyboard' => $inline_keyboard
                    ];
                    
                    sendMessageWithInlineKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, json_encode($keyboard));
                    
                    // ذخیره وضعیت کاربر
                    $userState = [
                        'state' => 'profile_completion',
                        'step' => 'menu'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($userState)]);
                    
                    echo "منوی تکمیل پروفایل با دکمه‌های شیشه‌ای نمایش داده شد\n";
                    
                } catch (Exception $e) {
                    echo "خطا در شروع تکمیل پروفایل: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در شروع تکمیل پروفایل: " . $e->getMessage());
                }
            }
            
            // تغییر نام کاربری
            else if (strpos($text, 'تغییر نام کاربری') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // دریافت اطلاعات اضافی کاربر
                    $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $userData['id'])->select('*')->first();
                    if (!$userExtra) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات اضافی کاربر");
                        echo "خطا: اطلاعات اضافی کاربر یافت نشد\n";
                        return;
                    }
                    
                    // بررسی موجودی دلتا کوین
                    $delta_coins = isset($userExtra['delta_coins']) ? $userExtra['delta_coins'] : 0;
                    
                    // ارسال پیام درخواست تغییر نام کاربری
                    $message = "چنانچه قصد تغییر آن را دارید، نام کاربری جدیدتان را ارسال کنید\n";
                    $message .= "نام کاربری فعلی: /{$userData['username']}\n";
                    
                    if ($delta_coins < 10) {
                        $message .= "\nموجودی شما {$delta_coins} دلتاکوین میباشد. مقدار دلتاکوین موردنیاز جهت تغییر نام کاربری 10 عدد میباشد!";
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                        return;
                    }
                    
                    // ایجاد دکمه لغو
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                    // ذخیره وضعیت کاربر در حالت تغییر نام کاربری
                    try {
                        $userState = [
                            'state' => 'change_username',
                            'step' => 'waiting_for_username'
                        ];
                        
                        // ذخیره وضعیت در دیتابیس یا فایل
                        // فعلاً به صورت ساده پیاده‌سازی می‌کنیم
                        file_put_contents(__DIR__ . "/user_states/{$user_id}.json", json_encode($userState));
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                    
                    echo "درخواست تغییر نام کاربری برای کاربر {$user_id} ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش درخواست تغییر نام کاربری: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // پنل مدیریت - دستور /admin یا کلمه پنل مدیریت
            else if (strpos($text, 'پنل مدیریت') !== false || $text === '/admin') {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin()) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    // منوی پنل مدیریت
                    $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                    $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                    
                    // کیبورد مدیریت
                    $admin_keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '📊 آمار ربات']],
                            [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                            [['text' => '👥 مدیریت ادمین‌ها'], ['text' => '👤 مدیریت کاربران']],
                            [['text' => '🔗 قفل گروه/کانال'], ['text' => '🔒 قفل آیدی']],
                            [['text' => '⚙️ تنظیمات ربات'], ['text' => '📱 وضعیت سرور']],
                            [['text' => '💰 تنظیم قیمت دلتا'], ['text' => '💸 تنظیم پورسانت']],
                            [['text' => '🔄 روشن/خاموش ربات'], ['text' => '📝 لیست برداشت ها']],
                            [['text' => '🔙 بازگشت به منوی اصلی']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                    
                    // ذخیره وضعیت ادمین
                    $userState = [
                        'state' => 'admin_panel',
                        'step' => 'main_menu'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($userState)]);
                        
                    echo "منوی پنل مدیریت ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش پنل مدیریت: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // آمار ربات
            else if (strpos($text, 'آمار ربات') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin()) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    // دریافت آمار از دیتابیس
                    $stats_result = $adminController->getBotStats();
                    
                    if (!$stats_result['success']) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت آمار: " . $stats_result['message']);
                        continue;
                    }
                    
                    $stats = $stats_result['stats'];
                    
                    // ساخت متن آمار
                    $stats_message = "📊 *آمار ربات*\n\n";
                    $stats_message .= "👥 تعداد کل کاربران: " . ($stats['total_users'] ?? 0) . "\n";
                    $stats_message .= "🎮 تعداد کل بازی‌ها: " . ($stats['total_games'] ?? 0) . "\n";
                    $stats_message .= "🎲 بازی‌های فعال: " . ($stats['active_games'] ?? 0) . "\n";
                    $stats_message .= "🎯 بازی‌های امروز: " . ($stats['games_today'] ?? 0) . "\n";
                    $stats_message .= "💰 میانگین دلتا کوین‌ها: " . ($stats['avg_deltacoins'] ?? 0) . "\n";
                    $stats_message .= "🆕 کاربران جدید امروز: " . ($stats['new_users_today'] ?? 0) . "\n";
                    
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $stats_message);
                    echo "آمار ربات ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش آمار ربات: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // پیام همگانی
            else if (strpos($text, 'پیام همگانی') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin() || !$adminController->hasPermission('can_send_broadcasts')) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    $message = "📨 *ارسال پیام همگانی*\n\n";
                    $message .= "لطفاً پیامی که می‌خواهید به تمام کاربران ارسال شود را ارسال کنید.\n";
                    $message .= "پیام می‌تواند شامل متن، عکس، فایل صوتی، ویدئو یا فایل باشد.\n\n";
                    $message .= "⚠️ توجه: این پیام به تمام کاربران ربات ارسال خواهد شد.\n";
                    $message .= "برای لغو، دکمه «لغو» را بزنید.";
                    
                    // کیبورد لغو
                    $cancel_keyboard = json_encode([
                        'keyboard' => [
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $cancel_keyboard);
                    
                    // ذخیره وضعیت ادمین
                    $userState = [
                        'state' => 'admin_panel',
                        'step' => 'waiting_for_broadcast_message'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($userState)]);
                        
                    echo "درخواست ارسال پیام همگانی دریافت شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش پیام همگانی: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // فوروارد همگانی
            else if (strpos($text, 'فوروارد همگانی') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin() || !$adminController->hasPermission('can_send_broadcasts')) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    $message = "📤 *فوروارد همگانی*\n\n";
                    $message .= "لطفاً پیامی که می‌خواهید به تمام کاربران فوروارد شود را ارسال یا فوروارد کنید.\n";
                    $message .= "پیام می‌تواند شامل متن، عکس، فایل صوتی، ویدئو یا فایل باشد.\n\n";
                    $message .= "⚠️ توجه: این پیام به تمام کاربران ربات فوروارد خواهد شد.\n";
                    $message .= "برای لغو، دکمه «لغو» را بزنید.";
                    
                    // کیبورد لغو
                    $cancel_keyboard = json_encode([
                        'keyboard' => [
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $cancel_keyboard);
                    
                    // ذخیره وضعیت ادمین
                    $userState = [
                        'state' => 'admin_panel',
                        'step' => 'waiting_for_forward_message'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($userState)]);
                        
                    echo "درخواست فوروارد همگانی دریافت شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش فوروارد همگانی: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // مدیریت ادمین‌ها
            else if (strpos($text, 'مدیریت ادمین‌ها') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin()) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    $message = "👤 *مدیریت ادمین‌ها*\n\n";
                    $message .= "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                    
                    // دکمه‌های مدیریت ادمین 
                    $admin_keyboard = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '➕ افزودن ادمین جدید', 'callback_data' => 'admin_action:add']
                            ],
                            [
                                ['text' => '❌ حذف ادمین', 'callback_data' => 'admin_action:remove']
                            ],
                            [
                                ['text' => '📋 لیست ادمین‌ها', 'callback_data' => 'admin_action:list']
                            ]
                        ]
                    ]);
                    
                    sendMessageWithInlineKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $admin_keyboard);
                    
                    echo "منوی مدیریت ادمین‌ها ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش مدیریت ادمین‌ها: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // قفل گروه/کانال
            else if (strpos($text, 'قفل گروه/کانال') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin()) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    $message = "🔗 *قفل گروه/کانال*\n\n";
                    $message .= "لطفاً آیدی یا لینک گروه/کانال مورد نظر را وارد کنید:";
                    
                    // کیبورد لغو
                    $cancel_keyboard = json_encode([
                        'keyboard' => [
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $cancel_keyboard);
                    
                    // ذخیره وضعیت ادمین
                    $userState = [
                        'state' => 'admin_panel',
                        'step' => 'waiting_for_channel_id'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($userState)]);
                        
                    echo "درخواست قفل گروه/کانال دریافت شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش قفل گروه/کانال: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // قفل آیدی
            else if (strpos($text, 'قفل آیدی') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin() || !$adminController->hasPermission('can_lock_usernames')) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    $message = "🔒 *قفل آیدی*\n\n";
                    $message .= "لطفاً نام کاربری که می‌خواهید قفل کنید را وارد کنید (با یا بدون @):\n";
                    $message .= "این نام کاربری برای همه کاربران قفل خواهد شد و کسی نمی‌تواند آن را انتخاب کند.";
                    
                    // کیبورد لغو
                    $cancel_keyboard = json_encode([
                        'keyboard' => [
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $cancel_keyboard);
                    
                    // ذخیره وضعیت ادمین
                    $userState = [
                        'state' => 'admin_panel',
                        'step' => 'waiting_for_username_to_lock'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($userState)]);
                        
                    echo "درخواست قفل آیدی دریافت شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش قفل آیدی: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // مدیریت کاربران
            else if (strpos($text, 'مدیریت کاربران') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin() || !$adminController->hasPermission('can_manage_users')) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    $message = "👤 *مدیریت کاربران*\n\n";
                    $message .= "لطفاً آیدی عددی، نام کاربری یا شناسه کاربر مورد نظر را وارد کنید:";
                    
                    // کیبورد لغو
                    $cancel_keyboard = json_encode([
                        'keyboard' => [
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $cancel_keyboard);
                    
                    // ذخیره وضعیت ادمین
                    $userState = [
                        'state' => 'admin_panel',
                        'step' => 'waiting_for_user_to_manage'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($userState)]);
                        
                    echo "درخواست مدیریت کاربران دریافت شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش مدیریت کاربران: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // تنظیمات ربات
            else if (strpos($text, 'تنظیمات ربات') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin() || !$adminController->hasPermission('can_manage_settings')) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    $message = "⚙️ *تنظیمات ربات*\n\n";
                    $message .= "لطفاً یکی از تنظیمات زیر را انتخاب کنید:";
                    
                    // کیبورد تنظیمات
                    $settings_keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '💰 تنظیم قیمت دلتا کوین']],
                            [['text' => '💸 تنظیم پورسانت زیرمجموعه']],
                            [['text' => '🔄 روشن/خاموش کردن ربات']],
                            [['text' => '📝 تنظیم حداقل برداشت']],
                            [['text' => '🔙 بازگشت به پنل مدیریت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $settings_keyboard);
                    
                    // ذخیره وضعیت ادمین
                    $userState = [
                        'state' => 'admin_panel',
                        'step' => 'settings_menu'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($userState)]);
                        
                    echo "منوی تنظیمات ربات ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش تنظیمات ربات: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // وضعیت سرور
            else if (strpos($text, 'وضعیت سرور') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin()) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    // دریافت اطلاعات سرور
                    $load = sys_getloadavg();
                    $memory_usage = memory_get_usage();
                    $memory_peak = memory_get_peak_usage();
                    $free_disk = disk_free_space("/");
                    $total_disk = disk_total_space("/");
                    
                    // محاسبه مقادیر با واحد مناسب
                    $load_avg = round($load[0], 2);
                    $memory_usage_mb = round($memory_usage / 1024 / 1024, 2);
                    $memory_peak_mb = round($memory_peak / 1024 / 1024, 2);
                    $free_disk_gb = round($free_disk / 1024 / 1024 / 1024, 2);
                    $total_disk_gb = round($total_disk / 1024 / 1024 / 1024, 2);
                    $disk_usage_percent = round(100 - ($free_disk / $total_disk * 100), 2);
                    
                    // ساخت متن وضعیت سرور
                    $server_status = "📱 *وضعیت سرور*\n\n";
                    $server_status .= "🔄 میانگین بار (Load Average): {$load_avg}\n";
                    $server_status .= "🧠 مصرف حافظه: {$memory_usage_mb} MB\n";
                    $server_status .= "📊 اوج مصرف حافظه: {$memory_peak_mb} MB\n";
                    $server_status .= "💾 فضای خالی دیسک: {$free_disk_gb} GB\n";
                    $server_status .= "💿 فضای کل دیسک: {$total_disk_gb} GB\n";
                    $server_status .= "📈 درصد استفاده از دیسک: {$disk_usage_percent}%\n";
                    // استفاده از دستور uptime بدون پارامتر -p برای سازگاری بیشتر
                    $uptime = trim(shell_exec('uptime'));
                    $server_status .= "⏱️ زمان کارکرد سرور: " . (empty($uptime) ? 'نامشخص' : $uptime) . "\n";
                    $server_status .= "🕒 زمان سرور: " . date('Y-m-d H:i:s') . "\n";
                    
                    // دریافت نسخه PHP
                    $php_version = phpversion();
                    $server_status .= "🔧 نسخه PHP: {$php_version}\n";
                    
                    // دریافت اطلاعات پایگاه داده
                    $db_stats = \Application\Model\DB::rawQuery("SELECT pg_database_size(current_database()) as db_size");
                    $db_size_bytes = $db_stats[0]['db_size'] ?? 0;
                    $db_size_mb = round($db_size_bytes / 1024 / 1024, 2);
                    $server_status .= "🗄️ سایز پایگاه داده: {$db_size_mb} MB\n";
                    
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $server_status);
                    echo "وضعیت سرور ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش وضعیت سرور: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // تنظیم قیمت دلتا کوین
            else if (strpos($text, 'تنظیم قیمت دلتا') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin() || !$adminController->hasPermission('can_manage_settings')) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    // دریافت قیمت فعلی
                    $current_price = \Application\Model\DB::table('bot_settings')
                        ->where('name', 'delta_coin_price')
                        ->select('value')
                        ->first();
                        
                    $current_price_value = $current_price ? $current_price['value'] : '1000';
                    
                    $message = "💰 *تنظیم قیمت دلتا کوین*\n\n";
                    $message .= "قیمت فعلی هر دلتا کوین: {$current_price_value} تومان\n\n";
                    $message .= "لطفاً قیمت جدید را به تومان وارد کنید:";
                    
                    // کیبورد لغو
                    $cancel_keyboard = json_encode([
                        'keyboard' => [
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $cancel_keyboard);
                    
                    // ذخیره وضعیت ادمین
                    $userState = [
                        'state' => 'admin_panel',
                        'step' => 'waiting_for_delta_coin_price'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($userState)]);
                        
                    echo "درخواست تنظیم قیمت دلتا کوین دریافت شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش تنظیم قیمت دلتا کوین: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // تنظیم پورسانت زیرمجموعه
            else if (strpos($text, 'تنظیم پورسانت') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin() || !$adminController->hasPermission('can_manage_settings')) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    // دریافت پورسانت‌های فعلی
                    $initial = \Application\Model\DB::table('bot_settings')
                        ->where('name', 'referral_commission_initial')
                        ->select('value')
                        ->first();
                        
                    $first_win = \Application\Model\DB::table('bot_settings')
                        ->where('name', 'referral_commission_first_win')
                        ->select('value')
                        ->first();
                        
                    $profile_completion = \Application\Model\DB::table('bot_settings')
                        ->where('name', 'referral_commission_profile_completion')
                        ->select('value')
                        ->first();
                        
                    $thirty_wins = \Application\Model\DB::table('bot_settings')
                        ->where('name', 'referral_commission_thirty_wins')
                        ->select('value')
                        ->first();
                        
                    $initial_value = $initial ? $initial['value'] : '0.5';
                    $first_win_value = $first_win ? $first_win['value'] : '1.5';
                    $profile_completion_value = $profile_completion ? $profile_completion['value'] : '3';
                    $thirty_wins_value = $thirty_wins ? $thirty_wins['value'] : '5';
                    
                    $message = "💸 *تنظیم پورسانت زیرمجموعه*\n\n";
                    $message .= "پورسانت‌های فعلی:\n";
                    $message .= "• عضویت اولیه: {$initial_value} دلتا کوین\n";
                    $message .= "• اولین برد: {$first_win_value} دلتا کوین\n";
                    $message .= "• تکمیل پروفایل: {$profile_completion_value} دلتا کوین\n";
                    $message .= "• 30 بازی موفق: {$thirty_wins_value} دلتا کوین\n\n";
                    $message .= "لطفاً نوع پورسانت مورد نظر را انتخاب کنید:";
                    
                    // کیبورد پورسانت‌ها
                    $commission_keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '1️⃣ عضویت اولیه']],
                            [['text' => '2️⃣ اولین برد']],
                            [['text' => '3️⃣ تکمیل پروفایل']],
                            [['text' => '4️⃣ 30 بازی موفق']],
                            [['text' => '🔙 بازگشت به پنل مدیریت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $commission_keyboard);
                    
                    // ذخیره وضعیت ادمین
                    $userState = [
                        'state' => 'admin_panel',
                        'step' => 'commission_menu'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($userState)]);
                        
                    echo "منوی تنظیم پورسانت زیرمجموعه ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش تنظیم پورسانت زیرمجموعه: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // روشن/خاموش کردن ربات
            else if (strpos($text, 'روشن/خاموش ربات') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin() || !$adminController->hasPermission('can_manage_settings')) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    // دریافت وضعیت فعلی ربات
                    $is_active = $adminController->isBotActive();
                    $status_text = $is_active ? "فعال ✅" : "غیرفعال ❌";
                    
                    $message = "🔄 *روشن/خاموش کردن ربات*\n\n";
                    $message .= "وضعیت فعلی ربات: {$status_text}\n\n";
                    $message .= "اگر ربات را خاموش کنید، بازی‌های در جریان تا انتها ادامه می‌یابند، اما کاربران نمی‌توانند بازی جدیدی را شروع کنند.\n\n";
                    $message .= "لطفاً وضعیت جدید را انتخاب کنید:";
                    
                    // کیبورد وضعیت
                    $status_keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '✅ فعال کردن ربات'], ['text' => '❌ غیرفعال کردن ربات']],
                            [['text' => '🔙 بازگشت به پنل مدیریت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $status_keyboard);
                    
                    // ذخیره وضعیت ادمین
                    $userState = [
                        'state' => 'admin_panel',
                        'step' => 'bot_status_menu'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($userState)]);
                        
                    echo "منوی روشن/خاموش کردن ربات ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش روشن/خاموش کردن ربات: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // لیست برداشت‌ها
            else if (strpos($text, 'لیست برداشت ها') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin() || !$adminController->hasPermission('can_manage_withdrawals')) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    // بررسی وجود کنترلر Withdrawal
                    if (file_exists(__DIR__ . '/application/controllers/WithdrawalController.php')) {
                        require_once __DIR__ . '/application/controllers/WithdrawalController.php';
                        $withdrawalController = new \application\controllers\WithdrawalController($user_id);
                        
                        // تلاش برای استفاده از متد getWithdrawalRequests
                        try {
                            $pending_requests = $withdrawalController->getWithdrawalRequests('pending', 10);
                        } catch (\Exception $e) {
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت درخواست‌های برداشت: " . $e->getMessage());
                            echo "خطا در دریافت درخواست‌های برداشت: " . $e->getMessage() . "\n";
                            continue;
                        }
                    } else {
                        // اگر کنترلر موجود نیست، اطلاعات را مستقیماً از دیتابیس دریافت کنیم
                        try {
                            $pending_requests = \Application\Model\DB::rawQuery("
                                SELECT wr.*, u.username, u.telegram_id 
                                FROM withdrawal_requests wr
                                LEFT JOIN users u ON wr.user_id = u.id
                                WHERE wr.status = 'pending'
                                ORDER BY wr.created_at DESC
                                LIMIT 10
                            ");
                        } catch (\Exception $e) {
                            // نمایش پیغام خطا به صورت دقیق
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت درخواست‌های برداشت: " . $e->getMessage());
                            echo "خطا در دریافت درخواست‌های برداشت: " . $e->getMessage() . "\n";
                            continue;
                        }
                    }
                    
                    if (empty($pending_requests)) {
                        $message = "📝 *لیست برداشت‌ها*\n\n";
                        $message .= "هیچ درخواست برداشت در انتظار تأیید وجود ندارد.";
                        
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                        echo "لیست برداشت‌های خالی ارسال شد\n";
                        continue;
                    }
                    
                    $message = "📝 *لیست برداشت‌های در انتظار تأیید*\n\n";
                    $message .= "لطفاً یکی از درخواست‌های زیر را برای مدیریت انتخاب کنید:";
                    
                    // ساخت دکمه‌های اینلاین برای هر درخواست
                    $inline_keyboard = [];
                    foreach ($pending_requests as $request) {
                        // دریافت اطلاعات کاربر
                        $user = \Application\Model\DB::table('users')
                            ->where('id', $request['user_id'])
                            ->select('username')
                            ->first();
                            
                        $username = $user ? $user['username'] : '?';
                        
                        // محاسبه مبلغ به تومان
                        $delta_coin_price = $withdrawalController->getDeltaCoinPrice();
                        $amount_toman = $request['amount'] * $delta_coin_price;
                        
                        // تعیین نوع برداشت
                        $type_text = $request['type'] === 'bank' ? '🏦' : '💎';
                        
                        // اضافه کردن دکمه
                        $inline_keyboard[] = [
                            ['text' => "{$type_text} {$username} - {$request['amount']} دلتا کوین ({$amount_toman} تومان)", 'callback_data' => "withdrawal:{$request['id']}"]
                        ];
                    }
                    
                    // اضافه کردن دکمه بازگشت
                    $inline_keyboard[] = [
                        ['text' => '🔙 بازگشت به پنل مدیریت', 'callback_data' => 'admin_panel']
                    ];
                    
                    $keyboard = json_encode([
                        'inline_keyboard' => $inline_keyboard
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    echo "لیست برداشت‌ها ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش لیست برداشت‌ها: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // بازگشت به پنل مدیریت
            else if (strpos($text, 'بازگشت به پنل مدیریت') !== false) {
                try {
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    
                    // بررسی دسترسی ادمین
                    if (!$adminController->isAdmin()) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ شما دسترسی لازم برای این بخش را ندارید.");
                        continue;
                    }
                    
                    // منوی پنل مدیریت
                    $admin_menu = "🛠️ *پنل مدیریت*\n\n";
                    $admin_menu .= "به پنل مدیریت ربات خوش آمدید. لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                    
                    // کیبورد مدیریت
                    $admin_keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '📊 آمار ربات']],
                            [['text' => '📨 پیام همگانی'], ['text' => '📤 فوروارد همگانی']],
                            [['text' => '👥 مدیریت ادمین‌ها'], ['text' => '👤 مدیریت کاربران']],
                            [['text' => '🔗 قفل گروه/کانال'], ['text' => '🔒 قفل آیدی']],
                            [['text' => '⚙️ تنظیمات ربات'], ['text' => '📱 وضعیت سرور']],
                            [['text' => '💰 تنظیم قیمت دلتا'], ['text' => '💸 تنظیم پورسانت']],
                            [['text' => '🔄 روشن/خاموش ربات'], ['text' => '📝 لیست برداشت ها']],
                            [['text' => '🔙 بازگشت به منوی اصلی']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_menu, $admin_keyboard);
                    
                    // ذخیره وضعیت ادمین
                    $userState = [
                        'state' => 'admin_panel',
                        'step' => 'main_menu'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => json_encode($userState)]);
                        
                    echo "بازگشت به منوی پنل مدیریت\n";
                } catch (Exception $e) {
                    echo "خطا در بازگشت به پنل مدیریت: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // بازگشت به منوی اصلی از پنل مدیریت
            else if (strpos($text, 'بازگشت به منوی اصلی') !== false) {
                try {
                    // ارسال منوی اصلی
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                            [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                            [['text' => '👤 حساب کاربری'], ['text' => '🏆نفرات برتر•']],
                            [['text' => '👨‍👦‍👦 وضعیت زیرمجموعه‌ها'], ['text' => '💰 دلتا کوین روزانه']],
                            [['text' => '• پشتیبانی👨‍💻'], ['text' => '⁉️راهنما •']],
                            [['text' => '⚙️ پنل مدیریت']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, "🎮 منوی اصلی:", $keyboard);
                    
                    // حذف وضعیت کاربر
                    \Application\Model\DB::table('users')
                        ->where('telegram_id', $user_id)
                        ->update(['state' => null]);
                        
                    echo "بازگشت به منوی اصلی\n";
                } catch (Exception $e) {
                    echo "خطا در بازگشت به منوی اصلی: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // پروفایل کاربر
            else if (strpos($text, 'پروفایل') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // دریافت وضعیت تکمیل پروفایل با استفاده از کوئری خام
                    $profiles = \Application\Model\DB::rawQuery(
                        "SELECT * FROM user_profiles WHERE user_id = ?", 
                        [$userData['id']]
                    );
                    $userProfile = !empty($profiles) ? $profiles[0] : null;
                    
                    // پیام‌های راهنمای پروفایل
                    $message = "📝 برای تکمیل پروفایل خود، موارد زیر را تکمیل کنید:";
                    
                    // ساخت کیبورد مخصوص پروفایل
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '📷 ارسال عکس پروفایل']],
                            [['text' => '👤 نام'], ['text' => '⚧ جنسیت']],
                            [['text' => '🔢 سن'], ['text' => '✍️ بیوگرافی']],
                            [['text' => '🏙 انتخاب استان'], ['text' => '🏠 انتخاب شهر']],
                            [['text' => '📍 ارسال موقعیت مکانی']],
                            [['text' => '📱 ارسال شماره تلگرام']],
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    // نمایش وضعیت فعلی پروفایل
                    $status_message = "";
                    if ($userProfile) {
                        $status_message .= "✅ وضعیت تکمیل پروفایل شما:\n\n";
                        $status_message .= isset($userProfile['photo_id']) && !empty($userProfile['photo_id']) ? "✅ عکس پروفایل: ارسال شده\n" : "❌ عکس پروفایل: ارسال نشده\n";
                        $status_message .= isset($userProfile['name']) && !empty($userProfile['name']) ? "✅ نام: {$userProfile['name']}\n" : "❌ نام: تنظیم نشده\n";
                        $status_message .= isset($userProfile['gender']) && !empty($userProfile['gender']) ? "✅ جنسیت: {$userProfile['gender']}\n" : "❌ جنسیت: تنظیم نشده\n";
                        $status_message .= isset($userProfile['age']) && !empty($userProfile['age']) ? "✅ سن: {$userProfile['age']}\n" : "❌ سن: تنظیم نشده\n";
                        $status_message .= isset($userProfile['bio']) && !empty($userProfile['bio']) ? "✅ بیوگرافی: تنظیم شده\n" : "❌ بیوگرافی: تنظیم نشده\n";
                        $status_message .= isset($userProfile['province']) && !empty($userProfile['province']) ? "✅ استان: {$userProfile['province']}\n" : "❌ استان: تنظیم نشده\n";
                        $status_message .= isset($userProfile['city']) && !empty($userProfile['city']) ? "✅ شهر: {$userProfile['city']}\n" : "❌ شهر: تنظیم نشده\n";
                        $status_message .= isset($userProfile['location']) && !empty($userProfile['location']) ? "✅ موقعیت مکانی: ارسال شده\n" : "❌ موقعیت مکانی: ارسال نشده\n";
                        $status_message .= isset($userProfile['phone']) && !empty($userProfile['phone']) ? "✅ شماره تلفن: {$userProfile['phone']}\n" : "❌ شماره تلفن: ارسال نشده\n";
                    } else {
                        $status_message = "❌ شما هنوز پروفایل خود را تکمیل نکرده‌اید.\n\nبا تکمیل پروفایل خود، به بازیکنان دیگر اجازه می‌دهید بیشتر با شما آشنا شوند و همچنین 3 دلتا کوین دریافت می‌کنید!";
                    }
                    
                    // ارسال وضعیت و منوی پروفایل
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $status_message);
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    echo "منوی پروفایل کاربر ارسال شد\n";
                    
                    // ذخیره وضعیت کاربر در حالت پردازش پروفایل
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'menu'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش پروفایل کاربر: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات پروفایل: " . $e->getMessage());
                }
            }
            
            // وضعیت زیرمجموعه ها
            else if (strpos($text, 'وضعیت زیرمجموعه ها') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // دریافت زیرمجموعه‌ها با استفاده از کوئری خام
                    $referrals = \Application\Model\DB::rawQuery(
                        "SELECT * FROM referrals WHERE referee_id = ?", 
                        [$userData['id']]
                    );
                    
                    if (count($referrals) === 0) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ شما هنوز هیچ زیرمجموعه‌ای ندارید.\n\nبرای دعوت از دوستان خود، از بخش کسب درآمد استفاده کنید.");
                        echo "اطلاعات زیرمجموعه‌ها ارسال شد (بدون زیرمجموعه)\n";
                        return;
                    }
                    
                    // ساخت لیست زیرمجموعه‌ها با دکمه‌ها
                    $referral_buttons = [];
                    foreach ($referrals as $referral) {
                        $referral_buttons[] = [['text' => $referral['username']]];
                    }
                    
                    // اضافه کردن دکمه بازگشت
                    $referral_buttons[] = [['text' => 'لغو ❌']];
                    
                    $keyboard = json_encode([
                        'keyboard' => $referral_buttons,
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, "📊 لیست زیرمجموعه‌های شما: (روی هر کدام کلیک کنید تا وضعیت پاداش‌ها را ببینید)", $keyboard);
                    echo "لیست زیرمجموعه‌ها ارسال شد\n";
                    
                    // ذخیره وضعیت کاربر در حالت مشاهده زیرمجموعه‌ها
                    try {
                        $userState = [
                            'state' => 'referrals',
                            'step' => 'list'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش زیرمجموعه‌ها: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات زیرمجموعه‌ها: " . $e->getMessage());
                }
            }
            
            // بخش‌های مختلف پروفایل کاربر
            else if (strpos($text, 'ارسال عکس پروفایل') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    $message = "لطفاً عکس پروفایل خود را ارسال کنید. این عکس پس از تأیید توسط ادمین در پروفایل شما نمایش داده خواهد شد.";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'photo'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش عکس پروفایل: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // تنظیم نام
            else if (strpos($text, '👤 نام') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    $message = "لطفاً نام خود را وارد کنید. نام می‌تواند شامل حروف فارسی یا انگلیسی باشد و حداکثر 30 کاراکتر می‌تواند داشته باشد.";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'name'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش نام: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // تنظیم جنسیت
            else if (strpos($text, '⚧ جنسیت') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // ایجاد کیبورد انتخاب جنسیت
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '👨 پسر'], ['text' => '👧 دختر']],
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ]);
                    
                    $message = "لطفاً جنسیت خود را انتخاب کنید:";
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'gender'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش جنسیت: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // تنظیم سن
            else if (strpos($text, '🔢 سن') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // ایجاد کیبورد انتخاب سن (9 تا 70 سال)
                    $age_buttons = [];
                    $row = [];
                    for ($age = 9; $age <= 70; $age++) {
                        $row[] = ['text' => (string)$age];
                        if (count($row) === 5 || $age === 70) { // 5 تا در هر ردیف
                            $age_buttons[] = $row;
                            $row = [];
                        }
                    }
                    $age_buttons[] = [['text' => 'لغو ❌']];
                    
                    $keyboard = json_encode([
                        'keyboard' => $age_buttons,
                        'resize_keyboard' => true
                    ]);
                    
                    $message = "لطفاً سن خود را انتخاب کنید:";
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'age'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش سن: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // تنظیم بیوگرافی
            else if (strpos($text, '✍️ بیوگرافی') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    $message = "لطفاً متن بیوگرافی خود را وارد کنید. این متن می‌تواند به زبان فارسی یا انگلیسی باشد و حداکثر 200 کاراکتر می‌تواند داشته باشد. این متن نیاز به تأیید ادمین دارد.";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'bio'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش بیوگرافی: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // انتخاب استان
            else if (strpos($text, '🏙 انتخاب استان') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // لیست استان‌های ایران
                    $provinces = [
                        'آذربایجان شرقی', 'آذربایجان غربی', 'اردبیل', 'اصفهان', 'البرز',
                        'ایلام', 'بوشهر', 'تهران', 'چهارمحال و بختیاری', 'خراسان جنوبی',
                        'خراسان رضوی', 'خراسان شمالی', 'خوزستان', 'زنجان', 'سمنان',
                        'سیستان و بلوچستان', 'فارس', 'قزوین', 'قم', 'کردستان',
                        'کرمان', 'کرمانشاه', 'کهگیلویه و بویراحمد', 'گلستان', 'گیلان',
                        'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد'
                    ];
                    
                    // ایجاد کیبورد انتخاب استان
                    $province_buttons = [];
                    foreach ($provinces as $province) {
                        $province_buttons[] = [['text' => $province]];
                    }
                    $province_buttons[] = [['text' => 'ترجیح میدهم نگویم']];
                    $province_buttons[] = [['text' => 'لغو ❌']];
                    
                    $keyboard = json_encode([
                        'keyboard' => $province_buttons,
                        'resize_keyboard' => true
                    ]);
                    
                    $message = "لطفاً استان محل سکونت خود را انتخاب کنید:";
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'province'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش استان: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // ارسال موقعیت مکانی
            else if (strpos($text, '📍 ارسال موقعیت مکانی') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // ایجاد کیبورد با دکمه ارسال موقعیت
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '📍 ارسال موقعیت', 'request_location' => true]],
                            [['text' => 'ترجیح میدهم نگویم']],
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ]);
                    
                    $message = "لطفاً موقعیت مکانی خود را با کلیک بر روی دکمه زیر ارسال کنید یا اگر نمی‌خواهید این اطلاعات را ارائه دهید، گزینه «ترجیح میدهم نگویم» را انتخاب کنید:";
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'location'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش موقعیت مکانی: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // ارسال شماره تلفن
            else if (strpos($text, '📱 ارسال شماره تلگرام') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // ایجاد کیبورد با دکمه ارسال شماره تلفن
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '📱 ارسال شماره', 'request_contact' => true]],
                            [['text' => 'ترجیح میدهم نگویم']],
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ]);
                    
                    $message = "لطفاً شماره تلفن خود را با کلیک بر روی دکمه زیر ارسال کنید یا اگر نمی‌خواهید این اطلاعات را ارائه دهید، گزینه «ترجیح میدهم نگویم» را انتخاب کنید. توجه: فقط برای شماره‌های ایرانی پورسانت تعلق می‌گیرد.";
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'phone'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش شماره تلفن: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // پردازش ورودی‌های کاربر در حالت‌های مختلف
            else if (isset($update['message']) && 
                   (!isset($update['message']['entities']) || $update['message']['entities'][0]['type'] !== 'bot_command')) {
                try {
                    // اول بررسی شود آیا دکمه لغو زده شده است
                    if ($text === 'لغو ❌') {
                        // برگشت به منوی اصلی
                        $keyboard = json_encode([
                            'keyboard' => [
                                [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                                [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                                [['text' => '👤 حساب کاربری'], ['text' => '🏆نفرات برتر•']],
                                [['text' => '• پشتیبانی👨‍💻'], ['text' => '⁉️راهنما •']]
                            ],
                            'resize_keyboard' => true
                        ]);
                        
                        // پاک کردن وضعیت کاربر
                        $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                        if ($userData) {
                            \Application\Model\DB::rawQuery(
                                "UPDATE users SET state = ? WHERE id = ?", 
                                [json_encode(['state' => '', 'step' => '']), $userData['id']]
                            );
                        }
                        
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, "🎮 منوی اصلی:", $keyboard);
                        echo "برگشت به منوی اصلی\n";
                        continue;
                    }

                    // دریافت اطلاعات کاربر و وضعیت فعلی
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData || !isset($userData['state']) || empty($userData['state'])) {
                        // اگر وضعیتی برای کاربر تعریف نشده، به پیام پاسخ نمی‌دهیم
                        continue;
                    }
                    
                    $userState = json_decode($userData['state'], true);
                    
                    // پردازش ورودی بر اساس وضعیت کاربر
                    if ($userState['state'] === 'referrals' && $userState['step'] === 'list') {
                        // پردازش انتخاب زیرمجموعه از لیست
                        if ($text === 'لغو ❌') {
                            // بازگشت به منوی اصلی
                            $userState = [
                                'state' => '',
                                'step' => ''
                            ];
                            \Application\Model\DB::table('users')
                                ->where('id', $userData['id'])
                                ->update(['state' => json_encode($userState)]);
                            
                            // فراخوانی مجدد منوی اصلی
                            $text = "👤 حساب کاربری";
                            break;
                        }
                        
                        // جستجوی کاربر انتخاب شده در میان زیرمجموعه‌ها
                        $referral = \Application\Model\DB::rawQuery(
                            "SELECT r.*, u.username FROM referrals r JOIN users u ON r.referee_id = u.id WHERE u.username = ? AND r.referrer_id = ?", 
                            [$text, $userData['id']]
                        );
                        $referral = !empty($referral) ? $referral[0] : null;
                        
                        if (!$referral) {
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ کاربر انتخاب شده در میان زیرمجموعه‌های شما یافت نشد.");
                            continue;
                        }
                        
                        // دریافت اطلاعات وضعیت این زیرمجموعه
                        $referralStatus = \Application\Model\DB::table('referral_status')
                            ->where('user_id', $referral['id'])
                            ->first();
                        
                        // اگر اطلاعات وضعیت زیرمجموعه وجود نداشت، مقادیر پیش‌فرض را در نظر می‌گیریم
                        $started_bot = false;
                        $won_one_game = false;
                        $completed_profile = false;
                        $won_thirty_games = false;
                        
                        if ($referralStatus) {
                            $started_bot = $referralStatus['started_bot'] ?? false;
                            $won_one_game = $referralStatus['won_one_game'] ?? false;
                            $completed_profile = $referralStatus['completed_profile'] ?? false;
                            $won_thirty_games = $referralStatus['won_thirty_games'] ?? false;
                        }
                        
                        // شمارش تعداد بازی‌های برنده شده توسط زیرمجموعه
                        $wins = \Application\Model\DB::table('matches')
                            ->where(function($q) use ($referral) {
                                $q->where('player1', $referral['id'])
                                  ->where('winner', 1);
                            })
                            ->orWhere(function($q) use ($referral) {
                                $q->where('player2', $referral['id'])
                                  ->where('winner', 2);
                            })
                            ->count();
                        
                        // بررسی تکمیل پروفایل زیرمجموعه
                        $profile = \Application\Model\DB::table('user_profiles')
                            ->where('user_id', $referral['id'])
                            ->first();
                        
                        $profile_completed = false;
                        if ($profile) {
                            // بررسی تکمیل شدن فیلدهای اصلی پروفایل
                            $profile_completed = 
                                isset($profile['name']) && !empty($profile['name']) &&
                                isset($profile['gender']) && !empty($profile['gender']) &&
                                isset($profile['age']) && !empty($profile['age']) &&
                                isset($profile['bio']) && !empty($profile['bio']);
                        }
                        
                        // بروزرسانی وضعیت زیرمجموعه
                        if ($started_bot === false) {
                            $started_bot = true;
                            
                            // اگر رکورد وضعیت وجود نداشت، آن را ایجاد می‌کنیم
                            if (!$referralStatus) {
                                \Application\Model\DB::table('referral_status')->insert([
                                    'user_id' => $referral['id'],
                                    'referrer_id' => $userData['id'],
                                    'started_bot' => true,
                                    'won_one_game' => $wins >= 1,
                                    'completed_profile' => $profile_completed,
                                    'won_thirty_games' => $wins >= 30
                                ]);
                            } else {
                                \Application\Model\DB::table('referral_status')
                                    ->where('user_id', $referral['id'])
                                    ->update([
                                        'started_bot' => true,
                                        'won_one_game' => $wins >= 1,
                                        'completed_profile' => $profile_completed,
                                        'won_thirty_games' => $wins >= 30
                                    ]);
                            }
                            
                            // اضافه کردن پاداش 0.5 دلتا کوین به کاربر
                            \Application\Model\DB::table('users_extra')
                                ->where('user_id', $userData['id'])
                                ->increment('doz_coin', 0.5);
                        }
                        
                        // بروزرسانی برد یک بازی
                        if ($won_one_game === false && $wins >= 1) {
                            \Application\Model\DB::table('referral_status')
                                ->where('user_id', $referral['id'])
                                ->update(['won_one_game' => true]);
                            
                            // اضافه کردن پاداش 1.5 دلتا کوین به کاربر
                            \Application\Model\DB::table('users_extra')
                                ->where('user_id', $userData['id'])
                                ->increment('doz_coin', 1.5);
                        }
                        
                        // بروزرسانی تکمیل پروفایل
                        if ($completed_profile === false && $profile_completed) {
                            \Application\Model\DB::table('referral_status')
                                ->where('user_id', $referral['id'])
                                ->update(['completed_profile' => true]);
                            
                            // اضافه کردن پاداش 3 دلتا کوین به کاربر
                            \Application\Model\DB::table('users_extra')
                                ->where('user_id', $userData['id'])
                                ->increment('doz_coin', 3);
                        }
                        
                        // بروزرسانی برد 30 بازی
                        if ($won_thirty_games === false && $wins >= 30) {
                            \Application\Model\DB::table('referral_status')
                                ->where('user_id', $referral['id'])
                                ->update(['won_thirty_games' => true]);
                            
                            // اضافه کردن پاداش 5 دلتا کوین به کاربر
                            \Application\Model\DB::table('users_extra')
                                ->where('user_id', $userData['id'])
                                ->increment('doz_coin', 5);
                        }
                        
                        // ساخت متن وضعیت زیرمجموعه
                        $referral_status_text = "📊 وضعیت زیرمجموعه: {$referral['username']}\n\n";
                        $referral_status_text .= "وضعیت استارت ربات (0.5 دلتا کوین): " . ($started_bot ? "✅ انجام شده" : "❌ انجام نشده") . "\n";
                        $referral_status_text .= "وضعیت کسب 1 برد (1.5 دلتا کوین): " . ($won_one_game ? "✅ انجام شده" : "❌ انجام نشده") . "\n";
                        $referral_status_text .= "وضعیت تکمیل پروفایل (3 دلتا کوین): " . ($completed_profile ? "✅ انجام شده" : "❌ انجام نشده") . "\n";
                        $referral_status_text .= "وضعیت کسب 30 برد (5 دلتا کوین): " . ($won_thirty_games ? "✅ انجام شده" : "❌ انجام نشده") . "\n\n";
                        $referral_status_text .= "تعداد کل بردهای کاربر: {$wins}";
                        
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $referral_status_text);
                        continue;
                    }
                    else if ($userState['state'] === 'profile') {
                        switch ($userState['step']) {
                            case 'name':
                                if (strlen($text) > 30) {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ نام شما نباید بیشتر از 30 کاراکتر باشد. لطفاً دوباره تلاش کنید.");
                                    continue 2;
                                }
                                
                                // ذخیره نام در پروفایل کاربر
                                $profileExists = \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->exists();
                                
                                if ($profileExists) {
                                    \Application\Model\DB::table('user_profiles')
                                        ->where('user_id', $userData['id'])
                                        ->update(['name' => $text]);
                                } else {
                                    \Application\Model\DB::table('user_profiles')->insert([
                                        'user_id' => $userData['id'],
                                        'name' => $text
                                    ]);
                                }
                                
                                // بازگشت به منوی پروفایل
                                $userState = [
                                    'state' => 'profile',
                                    'step' => 'menu'
                                ];
                                \Application\Model\DB::table('users')
                                    ->where('id', $userData['id'])
                                    ->update(['state' => json_encode($userState)]);
                                
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ نام شما با موفقیت به «{$text}» تغییر یافت.");
                                // بازگرداندن به منوی پروفایل
                                $text = "📝 پروفایل";
                                break;
                                
                            case 'gender':
                                // پردازش انتخاب جنسیت (پسر/دختر)
                                $gender = '';
                                if (strpos($text, 'پسر') !== false) {
                                    $gender = 'male';
                                } else if (strpos($text, 'دختر') !== false) {
                                    $gender = 'female';
                                } else {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ لطفاً یکی از گزینه‌های موجود را انتخاب کنید.");
                                    continue 2;
                                }
                                
                                // ذخیره جنسیت در پروفایل کاربر
                                $profileExists = \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->exists();
                                
                                if ($profileExists) {
                                    \Application\Model\DB::table('user_profiles')
                                        ->where('user_id', $userData['id'])
                                        ->update(['gender' => $gender]);
                                } else {
                                    \Application\Model\DB::table('user_profiles')->insert([
                                        'user_id' => $userData['id'],
                                        'gender' => $gender
                                    ]);
                                }
                                
                                // بازگشت به منوی پروفایل
                                $userState = [
                                    'state' => 'profile',
                                    'step' => 'menu'
                                ];
                                \Application\Model\DB::table('users')
                                    ->where('id', $userData['id'])
                                    ->update(['state' => json_encode($userState)]);
                                
                                $gender_text = ($gender === 'male') ? 'پسر' : 'دختر';
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ جنسیت شما به «{$gender_text}» تنظیم شد.");
                                // بازگرداندن به منوی پروفایل
                                $text = "📝 پروفایل";
                                break;
                                
                            case 'age':
                                // پردازش انتخاب سن
                                $age = intval($text);
                                if ($age < 9 || $age > 70) {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ لطفاً سن خود را بین 9 تا 70 سال انتخاب کنید.");
                                    continue 2;
                                }
                                
                                // ذخیره سن در پروفایل کاربر
                                $profileExists = \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->exists();
                                
                                if ($profileExists) {
                                    \Application\Model\DB::table('user_profiles')
                                        ->where('user_id', $userData['id'])
                                        ->update(['age' => $age]);
                                } else {
                                    \Application\Model\DB::table('user_profiles')->insert([
                                        'user_id' => $userData['id'],
                                        'age' => $age
                                    ]);
                                }
                                
                                // بازگشت به منوی پروفایل
                                $userState = [
                                    'state' => 'profile',
                                    'step' => 'menu'
                                ];
                                \Application\Model\DB::table('users')
                                    ->where('id', $userData['id'])
                                    ->update(['state' => json_encode($userState)]);
                                
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ سن شما به {$age} سال تنظیم شد.");
                                // بازگرداندن به منوی پروفایل
                                $text = "📝 پروفایل";
                                break;
                                
                            case 'province':
                                // لیست استان‌های ایران
                                $provinces = [
                                    'آذربایجان شرقی', 'آذربایجان غربی', 'اردبیل', 'اصفهان', 'البرز',
                                    'ایلام', 'بوشهر', 'تهران', 'چهارمحال و بختیاری', 'خراسان جنوبی',
                                    'خراسان رضوی', 'خراسان شمالی', 'خوزستان', 'زنجان', 'سمنان',
                                    'سیستان و بلوچستان', 'فارس', 'قزوین', 'قم', 'کردستان',
                                    'کرمان', 'کرمانشاه', 'کهگیلویه و بویراحمد', 'گلستان', 'گیلان',
                                    'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد'
                                ];
                                
                                // بررسی معتبر بودن استان انتخاب شده
                                if (!in_array($text, $provinces) && $text !== 'ترجیح میدهم نگویم') {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ لطفاً یکی از استان‌های موجود در لیست را انتخاب کنید.");
                                    continue 2;
                                }
                                
                                // ذخیره استان در پروفایل کاربر
                                $profileExists = \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->exists();
                                
                                if ($profileExists) {
                                    \Application\Model\DB::table('user_profiles')
                                        ->where('user_id', $userData['id'])
                                        ->update(['province' => $text]);
                                } else {
                                    \Application\Model\DB::table('user_profiles')->insert([
                                        'user_id' => $userData['id'],
                                        'province' => $text
                                    ]);
                                }
                                
                                // اگر کاربر استان را انتخاب کرده، مرحله بعدی انتخاب شهر است
                                if ($text !== 'ترجیح میدهم نگویم') {
                                    // به کاربر نمایش میدهیم که استان ذخیره شده و حالا باید شهر را انتخاب کند
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ استان شما به «{$text}» تنظیم شد.");
                                    
                                    // ذخیره وضعیت کاربر برای انتخاب شهر
                                    $userState = [
                                        'state' => 'profile',
                                        'step' => 'city',
                                        'province' => $text
                                    ];
                                    \Application\Model\DB::table('users')
                                        ->where('id', $userData['id'])
                                        ->update(['state' => json_encode($userState)]);
                                    
                                    // بازگرداندن به منوی انتخاب شهر
                                    $text = "🏠 انتخاب شهر";
                                } else {
                                    // اگر کاربر نخواهد استان را مشخص کند، به منوی پروفایل برمی‌گردیم
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ انتخاب شما ثبت شد.");
                                    
                                    // بازگشت به منوی پروفایل
                                    $userState = [
                                        'state' => 'profile',
                                        'step' => 'menu'
                                    ];
                                    \Application\Model\DB::table('users')
                                        ->where('id', $userData['id'])
                                        ->update(['state' => json_encode($userState)]);
                                    
                                    // بازگرداندن به منوی پروفایل
                                    $text = "📝 پروفایل";
                                }
                                break;
                                
                            case 'city':
                                // ذخیره شهر در پروفایل کاربر
                                $profileExists = \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->exists();
                                
                                if ($profileExists) {
                                    \Application\Model\DB::table('user_profiles')
                                        ->where('user_id', $userData['id'])
                                        ->update(['city' => $text]);
                                } else {
                                    // این حالت نباید رخ دهد، زیرا پیش از این، استان را ذخیره کرده‌ایم
                                    \Application\Model\DB::table('user_profiles')->insert([
                                        'user_id' => $userData['id'],
                                        'city' => $text
                                    ]);
                                }
                                
                                // بازگشت به منوی پروفایل
                                $userState = [
                                    'state' => 'profile',
                                    'step' => 'menu'
                                ];
                                \Application\Model\DB::table('users')
                                    ->where('id', $userData['id'])
                                    ->update(['state' => json_encode($userState)]);
                                
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ شهر شما به «{$text}» تنظیم شد.");
                                // بازگرداندن به منوی پروفایل
                                $text = "📝 پروفایل";
                                break;
                                
                            case 'bio':
                                if (strlen($text) > 200) {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ بیوگرافی شما نباید بیشتر از 200 کاراکتر باشد. لطفاً دوباره تلاش کنید.");
                                    continue 2;
                                }
                                
                                // ذخیره بیوگرافی در پروفایل کاربر
                                $profileExists = \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->exists();
                                
                                if ($profileExists) {
                                    \Application\Model\DB::table('user_profiles')
                                        ->where('user_id', $userData['id'])
                                        ->update(['bio' => $text, 'bio_approved' => false]);
                                } else {
                                    \Application\Model\DB::table('user_profiles')->insert([
                                        'user_id' => $userData['id'],
                                        'bio' => $text,
                                        'bio_approved' => false
                                    ]);
                                }
                                
                                // ارسال بیوگرافی به کانال ادمین
                                $admin_channel_id = "-100123456789"; // آیدی کانال ادمین را قرار دهید
                                try {
                                    $admin_message = "✅ درخواست تأیید بیوگرافی:\n\nکاربر: {$userData['username']}\nآیدی: {$userData['telegram_id']}\n\nبیوگرافی:\n$text";
                                    
                                    $admin_keyboard = json_encode([
                                        'inline_keyboard' => [
                                            [
                                                ['text' => '✅ تأیید', 'callback_data' => "approve_bio:{$userData['id']}"],
                                                ['text' => '❌ رد', 'callback_data' => "reject_bio:{$userData['id']}"]
                                            ]
                                        ]
                                    ]);
                                    
                                    // sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $admin_channel_id, $admin_message, $admin_keyboard);
                                } catch (Exception $e) {
                                    echo "خطا در ارسال بیوگرافی به کانال ادمین: " . $e->getMessage() . "\n";
                                }
                                
                                // بازگشت به منوی پروفایل
                                $userState = [
                                    'state' => 'profile',
                                    'step' => 'menu'
                                ];
                                \Application\Model\DB::table('users')
                                    ->where('id', $userData['id'])
                                    ->update(['state' => json_encode($userState)]);
                                
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ بیوگرافی شما با موفقیت ثبت شد و در انتظار تأیید ادمین است.");
                                // بازگرداندن به منوی پروفایل
                                $text = "📝 پروفایل";
                                break;
                        }
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش ورودی کاربر: " . $e->getMessage() . "\n";
                }
            }
            
            // دکمه ترجیح میدهم نگویم
            else if ($text === 'ترجیح میدهم نگویم') {
                try {
                    // دریافت اطلاعات کاربر و وضعیت فعلی
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData || !isset($userData['state']) || empty($userData['state'])) {
                        continue;
                    }
                    
                    $userState = json_decode($userData['state'], true);
                    
                    // بررسی وضعیت کاربر
                    if ($userState['state'] === 'profile') {
                        $field = '';
                        $value = 'prefer_not_to_say';
                        
                        switch ($userState['step']) {
                            case 'province':
                                $field = 'province';
                                break;
                            case 'location':
                                $field = 'location';
                                break;
                            case 'phone':
                                $field = 'phone';
                                break;
                            default:
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "این گزینه در این مرحله قابل استفاده نیست.");
                                return;
                        }
                        
                        // ثبت ترجیح ندادن به ارائه اطلاعات
                        $profileExists = \Application\Model\DB::table('user_profiles')
                            ->where('user_id', $userData['id'])
                            ->exists();
                        
                        if ($profileExists) {
                            \Application\Model\DB::table('user_profiles')
                                ->where('user_id', $userData['id'])
                                ->update([$field => $value]);
                        } else {
                            \Application\Model\DB::table('user_profiles')->insert([
                                'user_id' => $userData['id'],
                                $field => $value
                            ]);
                        }
                        
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ انتخاب شما ثبت شد.");
                        
                        // بازگشت به منوی پروفایل
                        $userState = [
                            'state' => 'profile',
                            'step' => 'menu'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                        
                        // بازگرداندن به منوی پروفایل
                        $text = "📝 پروفایل";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش ترجیح ندادن به ارائه اطلاعات: " . $e->getMessage() . "\n";
                }
            }
            
            // دکمه لغو (قبلاً به بخش دیگری منتقل شده است)
            else if ($text === 'لغو ❌') {
                // این قسمت دیگر اجرا نمی‌شود و در ابتدای پردازش پیام‌ها قرار گرفته است
                echo "این قسمت دیگر استفاده نمی‌شود.\n";
            }
            
            // پاسخ به دستور /username (نمایش مشخصات کاربر)
            else if (strpos($text, '/') === 0 && $text !== '/start' && $text !== '/cancel') {
                try {
                    // حذف اسلش از ابتدای نام کاربری
                    $username = ltrim($text, '/');
                    
                    // جستجوی کاربر بر اساس نام کاربری
                    $userData = \Application\Model\DB::table('users')->where('username', $username)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ کاربری با این نام کاربری یافت نشد!");
                        echo "خطا: کاربر {$username} در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $userData['id'])->select('*')->first();
                    if (!$userExtra) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات اضافی کاربر");
                        echo "خطا: اطلاعات اضافی کاربر {$username} یافت نشد\n";
                        return;
                    }
                    
                    // آماده‌سازی اطلاعات کاربر برای نمایش
                    $win_rate = isset($userExtra['win_rate']) ? strval(number_format($userExtra['win_rate'], 2)) . "%" : "0%";
                    $cups = isset($userExtra['cups']) ? $userExtra['cups'] : 0;
                    $matches = isset($userExtra['matches']) ? $userExtra['matches'] : 0;
                    
                    // ساخت متن پاسخ
                    $message = "
🪪 اطلاعات کاربر {$userData['username']} :

🎮 تعداد بازی‌های انجام شده: {$matches}
➗ درصد برد: {$win_rate}
🏆 تعداد جام: {$cups}
                    ";
                    
                    // ایجاد دکمه درخواست دوستی
                    $inlineKeyboard = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '👥 درخواست دوستی', 'callback_data' => "friend_request:{$userData['id']}"]
                            ]
                        ]
                    ]);
                    
                    // ارسال پیام با دکمه درخواست دوستی
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $inlineKeyboard);
                    echo "اطلاعات کاربر {$username} ارسال شد\n";
                    
                } catch (Exception $e) {
                    echo "خطا در دریافت اطلاعات کاربر {$username}: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات: " . $e->getMessage());
                }
            }
            
            // پاسخ به دستور /start
            else if (strpos($text, '/start') === 0) {
                $first_name = isset($update['message']['from']['first_name']) ? $update['message']['from']['first_name'] : 'کاربر';
                
                // دقیقاً متن اصلی از فایل locale
                $response_text = "سلااام {$first_name} عزیززز به ربات بازی ما خوشومدییی❤️‍🔥

قراره اینجا کلی خوشبگذره بهت😼

با افراد ناشناس بازی کنی و دوست پیدا کنی 😁

تمرین کنی و قوی شی مسابقاتمون شرکت کنی و جایزه برنده شیی 😻

با رفیقات بازی کنی و ببینی کدومتون قوی و باهوش هستید 😹

همین حالا با استفاده از دکمه های زیر از ربات استفاده کن و لذت ببرر👇";
                
                // ارسال پاسخ
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $response_text);
                echo "پاسخ ارسال شد: {$response_text}\n";
                
                // ارسال مجدد منوی اصلی - اختیاری
                try {
                    // بررسی آیا کاربر ادمین است یا خیر
                    require_once __DIR__ . '/application/controllers/AdminController.php';
                    $adminController = new \application\controllers\AdminController($user_id);
                    $isAdmin = $adminController->isAdmin();
                    
                    // ایجاد کیبورد متناسب با دسترسی کاربر
                    $keyboard_buttons = [
                        [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                        [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                        [['text' => '👤 حساب کاربری'], ['text' => '🏆نفرات برتر•']],
                        [['text' => '👨‍👦‍👦 وضعیت زیرمجموعه‌ها'], ['text' => '💰 دلتا کوین روزانه']],
                        [['text' => '• پشتیبانی👨‍💻'], ['text' => '⁉️راهنما •']]
                    ];
                    
                    // اگر کاربر ادمین باشد، دکمه پنل مدیریت را اضافه می‌کنیم
                    if ($isAdmin) {
                        $keyboard_buttons[] = [['text' => '⚙️ پنل مدیریت']];
                    }
                    
                    $keyboard = json_encode([
                        'keyboard' => $keyboard_buttons,
                        'resize_keyboard' => true
                    ]);
                    
                    $url = "https://api.telegram.org/bot{$_ENV['TELEGRAM_TOKEN']}/sendMessage";
                    $params = [
                        'chat_id' => $chat_id,
                        'text' => '🎮 منوی اصلی:',
                        'reply_markup' => $keyboard
                    ];
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $result = curl_exec($ch);
                    curl_close($ch);
                    
                    echo "کیبورد ارسال شد!\n";
                } catch (Exception $e) {
                    echo "خطا در ارسال کیبورد: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}

/**
 * دریافت آپدیت‌ها از API تلگرام
 */
function getUpdatesViaFopen($token, $offset = 0) {
    $url = "https://api.telegram.org/bot{$token}/getUpdates";
    $params = [
        'offset' => $offset,
        'timeout' => 1,
        'limit' => 10,
        'allowed_updates' => json_encode(["message", "callback_query"])
    ];
    
    $url .= '?' . http_build_query($params);
    
    $response = @file_get_contents($url);
    if ($response === false) {
        return false;
    }
    
    return json_decode($response, true);
}

/**
 * ارسال پیام به کاربر
 */
function sendMessage($token, $chat_id, $text) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $params = [
        'chat_id' => $chat_id,
        'text' => $text
    ];
    
    $url .= '?' . http_build_query($params);
    return file_get_contents($url);
}

/**
 * ارسال پیام با کیبورد به کاربر
 */
function sendMessageWithKeyboard($token, $chat_id, $text, $keyboard) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'reply_markup' => $keyboard
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

/**
 * تابع برای ارسال عکس
 */
function sendPhoto($token, $chat_id, $photo, $caption = '') {
    $url = "https://api.telegram.org/bot{$token}/sendPhoto";
    $params = [
        'chat_id' => $chat_id,
        'photo' => $photo,
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * تابع برای ارسال ویدیو
 */
function sendVideo($token, $chat_id, $video, $caption = '') {
    $url = "https://api.telegram.org/bot{$token}/sendVideo";
    $params = [
        'chat_id' => $chat_id,
        'video' => $video,
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * تابع برای ارسال فایل صوتی
 */
function sendAudio($token, $chat_id, $audio, $caption = '') {
    $url = "https://api.telegram.org/bot{$token}/sendAudio";
    $params = [
        'chat_id' => $chat_id,
        'audio' => $audio,
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * تابع برای ارسال فایل
 */
function sendDocument($token, $chat_id, $document, $caption = '') {
    $url = "https://api.telegram.org/bot{$token}/sendDocument";
    $params = [
        'chat_id' => $chat_id,
        'document' => $document,
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * تابع برای ارسال پیام صوتی
 */
function sendVoice($token, $chat_id, $voice, $caption = '') {
    $url = "https://api.telegram.org/bot{$token}/sendVoice";
    $params = [
        'chat_id' => $chat_id,
        'voice' => $voice,
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * پاسخ به callback_query از دکمه‌های inline
 */
function answerCallbackQuery($token, $callback_query_id, $text = null, $show_alert = false) {
    $url = "https://api.telegram.org/bot{$token}/answerCallbackQuery";
    $params = [
        'callback_query_id' => $callback_query_id,
        'show_alert' => $show_alert
    ];
    
    if ($text !== null) {
        $params['text'] = $text;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

/**
 * ویرایش متن پیام
 */
function editMessageText($token, $chat_id, $message_id, $text, $reply_markup = null) {
    $url = "https://api.telegram.org/bot{$token}/editMessageText";
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text
    ];
    
    if ($reply_markup !== null) {
        $params['reply_markup'] = $reply_markup;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

/**
 * محاسبه و تولید متن تایمر برای بازیکن
 * این تایمر زیر نام کاربر نمایش داده می‌شود
 */
function generatePlayerTimer($last_action_time) {
    // اگر زمان آخرین کنش صفر یا خالی باشد
    if (empty($last_action_time)) {
        return "⏱️ زمان: 00:00";
    }
    
    // تبدیل به تایم‌استمپ
    $last_action_timestamp = strtotime($last_action_time);
    $current_timestamp = time();
    
    // محاسبه تفاوت زمانی (به ثانیه)
    $time_diff = $current_timestamp - $last_action_timestamp;
    
    // اگر تفاوت زمانی منفی باشد (که نباید باشد)
    if ($time_diff < 0) {
        $time_diff = 0;
    }
    
    // تبدیل به دقیقه و ثانیه
    $minutes = floor($time_diff / 60);
    $seconds = $time_diff % 60;
    
    // قالب‌بندی متن تایمر
    return sprintf("⏱️ زمان: %02d:%02d", $minutes, $seconds);
}

/**
 * یافتن بازی فعال برای کاربر
 * 
 * @param int $user_id شناسه کاربر
 * @return array|null اطلاعات بازی فعال یا null اگر بازی فعالی وجود نداشته باشد
 */
function getActiveMatchForUser($user_id) {
    try {
        // استفاده از متد rawQuery
        $results = \Application\Model\DB::rawQuery(
            "SELECT * FROM matches WHERE (player1 = ? OR player2 = ?) AND status = 'active' LIMIT 1", 
            [$user_id, $user_id]
        );
        
        // بررسی وجود نتیجه
        if (count($results) > 0) {
            return $results[0];
        }
        
        return null;
    } catch (Exception $e) {
        echo "خطا در یافتن بازی فعال: " . $e->getMessage() . "\n";
        return null;
    }
}

/**
 * ویرایش پیام قبلی با کیبورد
 * 
 * @param string $token توکن ربات
 * @param int $chat_id آیدی چت
 * @param int $message_id آیدی پیام
 * @param string $text متن جدید
 * @param string $keyboard کیبورد (به صورت json_encode شده)
 * @return mixed نتیجه درخواست
 */
function editMessageTextWithKeyboard($token, $chat_id, $message_id, $text, $keyboard) {
    return editMessageText($token, $chat_id, $message_id, $text, $keyboard);
}
?>