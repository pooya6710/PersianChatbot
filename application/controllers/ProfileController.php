<?php
namespace application\controllers;

require_once __DIR__ . '/../Model/DB.php';

use Application\Model\DB;

/**
 * کلاس مدیریت پروفایل کاربران
 */
class ProfileController
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
     * دریافت پروفایل کاربر
     * @return array
     */
    public function getProfile()
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
            
            // دریافت پروفایل کاربر
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            // دریافت اطلاعات تکمیلی کاربر
            $extra = DB::table('users_extra')
                ->where('user_id', $user['id'])
                ->first();
                
            // دریافت آمار بازی‌های کاربر
            $games_count = DB::table('matches')
                ->where('player1', $user['id'])
                ->orWhere('player2', $user['id'])
                ->count();
                
            $games_won = DB::table('matches')
                ->where('winner', $user['id'])
                ->count();
                
            // دریافت تعداد دوستان
            $friends_count = DB::table('friendships')
                ->where('user_id_1', $user['id'])
                ->orWhere('user_id_2', $user['id'])
                ->count();
                
            return [
                'success' => true,
                'message' => 'پروفایل با موفقیت دریافت شد.',
                'user' => [
                    'id' => $user['id'],
                    'telegram_id' => $user['telegram_id'],
                    'username' => $user['username'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'trophies' => $user['trophies'] ?? 0,
                    'created_at' => $user['created_at']
                ],
                'profile' => $profile ? [
                    'full_name' => $profile['full_name'] ?? null,
                    'gender' => $profile['gender'] ?? null,
                    'age' => $profile['age'] ?? null,
                    'bio' => $profile['bio'] ?? null,
                    'province' => $profile['province'] ?? null,
                    'city' => $profile['city'] ?? null,
                    'photo_url' => $profile['photo_url'] ?? null,
                    'photo_verified' => $profile['photo_verified'] ?? false,
                    'bio_verified' => $profile['bio_verified'] ?? false
                ] : null,
                'extra' => $extra ? [
                    'deltacoins' => $extra['deltacoins'] ?? 0,
                    'dozcoins' => $extra['dozcoins'] ?? 0
                ] : ['deltacoins' => 0, 'dozcoins' => 0],
                'stats' => [
                    'games_count' => $games_count,
                    'games_won' => $games_won,
                    'win_rate' => $games_count ? round(($games_won / $games_count) * 100, 1) : 0,
                    'friends_count' => $friends_count
                ]
            ];
        } catch (\Exception $e) {
            error_log("Error in getProfile: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در دریافت پروفایل: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * به‌روزرسانی نام کامل
     * @param string $full_name نام کامل
     * @return array
     */
    public function updateFullName($full_name)
    {
        try {
            // بررسی طول نام
            if (mb_strlen($full_name) > 50) {
                return [
                    'success' => false,
                    'message' => 'طول نام کامل باید حداکثر ۵۰ کاراکتر باشد.'
                ];
            }
            
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
            
            // بررسی وجود پروفایل
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            if ($profile) {
                // به‌روزرسانی نام کامل
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'full_name' => $full_name,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                // ایجاد پروفایل جدید
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'full_name' => $full_name,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'نام کامل با موفقیت به‌روزرسانی شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in updateFullName: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی نام کامل: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * به‌روزرسانی جنسیت
     * @param string $gender جنسیت (male یا female)
     * @return array
     */
    public function updateGender($gender)
    {
        try {
            // بررسی صحت جنسیت
            if ($gender !== 'male' && $gender !== 'female') {
                return [
                    'success' => false,
                    'message' => 'جنسیت باید «male» یا «female» باشد.'
                ];
            }
            
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
            
            // بررسی وجود پروفایل
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            if ($profile) {
                // به‌روزرسانی جنسیت
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'gender' => $gender,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                // ایجاد پروفایل جدید
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'gender' => $gender,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'جنسیت با موفقیت به‌روزرسانی شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in updateGender: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی جنسیت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * به‌روزرسانی سن
     * @param int $age سن
     * @return array
     */
    public function updateAge($age)
    {
        try {
            // بررسی صحت سن
            if (!is_numeric($age) || $age < 9 || $age > 70) {
                return [
                    'success' => false,
                    'message' => 'سن باید بین ۹ تا ۷۰ سال باشد.'
                ];
            }
            
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
            
            // بررسی وجود پروفایل
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            if ($profile) {
                // به‌روزرسانی سن
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'age' => $age,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                // ایجاد پروفایل جدید
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'age' => $age,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'سن با موفقیت به‌روزرسانی شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in updateAge: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی سن: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * به‌روزرسانی بیوگرافی
     * @param string $bio بیوگرافی
     * @return array
     */
    public function updateBio($bio)
    {
        try {
            // بررسی طول بیوگرافی
            if (mb_strlen($bio) > 200) {
                return [
                    'success' => false,
                    'message' => 'طول بیوگرافی باید حداکثر ۲۰۰ کاراکتر باشد.'
                ];
            }
            
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
            
            // بررسی وجود پروفایل
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            if ($profile) {
                // به‌روزرسانی بیوگرافی
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'bio' => $bio,
                        'bio_verified' => false, // نیاز به تأیید مجدد
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                // ایجاد پروفایل جدید
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'bio' => $bio,
                    'bio_verified' => false,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // ارسال بیوگرافی به کانال ادمین برای تأیید
            $this->sendBioForVerification($user, $bio);
            
            return [
                'success' => true,
                'message' => 'بیوگرافی با موفقیت به‌روزرسانی شد و در انتظار تأیید می‌باشد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in updateBio: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی بیوگرافی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * به‌روزرسانی استان
     * @param string $province استان
     * @return array
     */
    public function updateProvince($province)
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
            
            // بررسی وجود پروفایل
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            if ($profile) {
                // به‌روزرسانی استان
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'province' => $province,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                // ایجاد پروفایل جدید
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'province' => $province,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'استان با موفقیت به‌روزرسانی شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in updateProvince: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی استان: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * به‌روزرسانی شهر
     * @param string $city شهر
     * @return array
     */
    public function updateCity($city)
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
            
            // بررسی وجود پروفایل
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            if ($profile) {
                // به‌روزرسانی شهر
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'city' => $city,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                // ایجاد پروفایل جدید
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'city' => $city,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'شهر با موفقیت به‌روزرسانی شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in updateCity: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی شهر: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * آپلود عکس پروفایل
     * @param string $photo_url آدرس عکس
     * @return array
     */
    
    /**
     * پردازش مرحله‌ای تکمیل پروفایل 
     * @param array $message پیام دریافتی
     * @param string $step مرحله فعلی
     * @return array وضعیت و مرحله بعدی
     */
    public function handleProfileStep($message, $step) 
    {
        // import TelegramClass
        require_once __DIR__ . '/TelegramClass.php';
        
        switch ($step) {
            case 'waiting_for_photo':
                return $this->handleProfilePhotoStep($message);
            case 'waiting_for_fullname':
                return $this->handleProfileFullnameStep($message);
            case 'waiting_for_gender':
                return $this->handleProfileGenderStep($message);
            case 'waiting_for_age':
                return $this->handleProfileAgeStep($message);
            case 'waiting_for_bio':
                return $this->handleProfileBioStep($message);
            case 'waiting_for_province':
                return $this->handleProfileProvinceStep($message);
            case 'waiting_for_city':
                return $this->handleProfileCityStep($message);
            default:
                return [
                    'status' => 'error',
                    'next_state' => 'main_menu',
                    'next_step' => null
                ];
        }
    }
    
    /**
     * پردازش آپلود عکس پروفایل
     * @param array $message پیام دریافتی 
     * @return array وضعیت و مرحله بعدی
     */
    public function handleProfilePhotoStep($message)
    {
        $telegramClass = new TelegramClass();
        $telegramClass->setChatId($this->user_id);
        
        // بررسی وجود عکس
        if (!isset($message['photo'])) {
            // اگر کاربر متن ارسال کرده، بررسی کنیم «لغو» نباشد
            if (isset($message['text']) && $message['text'] === 'لغو ❌') {
                $telegramClass->sendMessage("عملیات ویرایش پروفایل لغو شد.");
                $telegramClass->showMainMenu();
                return [
                    'status' => 'canceled',
                    'next_state' => 'main_menu'
                ];
            }
            
            $telegramClass->sendMessage("⚠️ لطفاً یک تصویر ارسال کنید یا برای لغو، دکمه «لغو» را بزنید.");
            return [
                'status' => 'continue',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_photo'
            ];
        }
        
        // دریافت آخرین عکس با بالاترین کیفیت
        $photos = $message['photo'];
        $photo = end($photos);
        $file_id = $photo['file_id'];
        
        try {
            // دریافت اطلاعات فایل
            $file_info = $telegramClass->getFile($file_id);
            if (!$file_info['ok']) {
                throw new \Exception("خطا در دریافت اطلاعات فایل: " . $file_info['description']);
            }
            
            $file_path = $file_info['result']['file_path'];
            $download_url = $telegramClass->generateFileUrl($file_path);
            
            // ذخیره عکس در پروفایل کاربر
            $userData = DB::table('users')->where('telegram_id', $this->user_id)->first();
            if (!$userData) {
                throw new \Exception("کاربر در پایگاه داده یافت نشد!");
            }
            
            // بررسی وجود پروفایل
            $profile = DB::table('user_profiles')->where('user_id', $userData['id'])->first();
            
            if ($profile) {
                // به‌روزرسانی پروفایل موجود
                DB::table('user_profiles')
                    ->where('user_id', $userData['id'])
                    ->update([
                        'photo_url' => $download_url,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                // ایجاد پروفایل جدید
                DB::table('user_profiles')->insert([
                    'user_id' => $userData['id'],
                    'photo_url' => $download_url,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // ارسال پیام تأیید
            $telegramClass->sendMessage("✅ عکس پروفایل شما با موفقیت ذخیره شد.\n\nبرای ادامه تکمیل پروفایل، روی دکمه «تکمیل پروفایل» کلیک کنید.");
            $telegramClass->showMainMenu();
            
            return [
                'status' => 'success',
                'next_state' => 'main_menu',
                'next_step' => null
            ];
            
        } catch (\Exception $e) {
            $telegramClass->sendMessage("⚠️ خطا در ذخیره عکس پروفایل: " . $e->getMessage());
            return [
                'status' => 'error',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_photo'
            ];
        }
    }
    
    /**
     * پردازش تکمیل نام کامل 
     * @param array $message پیام دریافتی
     * @return array وضعیت و مرحله بعدی
     */
    public function handleProfileFullnameStep($message)
    {
        $telegramClass = new TelegramClass();
        $telegramClass->setChatId($this->user_id);
        
        // بررسی وجود متن
        if (!isset($message['text'])) {
            $telegramClass->sendMessage("⚠️ لطفاً نام کامل خود را به صورت متنی وارد کنید.");
            return [
                'status' => 'continue',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_fullname'
            ];
        }
        
        $fullname = trim($message['text']);
        
        // بررسی لغو عملیات
        if ($fullname === 'لغو ❌') {
            $telegramClass->sendMessage("عملیات ویرایش پروفایل لغو شد.");
            $telegramClass->showMainMenu();
            return [
                'status' => 'canceled',
                'next_state' => 'main_menu'
            ];
        }
        
        // بررسی طول نام
        if (mb_strlen($fullname) < 3) {
            $telegramClass->sendMessage("⚠️ نام کامل باید حداقل ۳ حرف داشته باشد.");
            return [
                'status' => 'continue',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_fullname'
            ];
        }
        
        if (mb_strlen($fullname) > 50) {
            $telegramClass->sendMessage("⚠️ نام کامل باید حداکثر ۵۰ حرف داشته باشد.");
            return [
                'status' => 'continue',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_fullname'
            ];
        }
        
        try {
            // ذخیره نام کامل
            $result = $this->updateFullName($fullname);
            
            if (!$result['success']) {
                throw new \Exception($result['message']);
            }
            
            // ارسال پیام تأیید
            $telegramClass->sendMessage("✅ نام کامل شما با موفقیت ثبت شد.\n\nبرای ادامه تکمیل پروفایل، روی دکمه «تکمیل پروفایل» کلیک کنید.");
            $telegramClass->showMainMenu();
            
            return [
                'status' => 'success',
                'next_state' => 'main_menu',
                'next_step' => null
            ];
            
        } catch (\Exception $e) {
            $telegramClass->sendMessage("⚠️ خطا در ثبت نام کامل: " . $e->getMessage());
            return [
                'status' => 'error',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_fullname'
            ];
        }
    }
    
    /**
     * پردازش انتخاب جنسیت
     * @param array $message پیام دریافتی
     * @return array وضعیت و مرحله بعدی
     */
    public function handleProfileGenderStep($message)
    {
        require_once __DIR__ . '/TelegramClass.php';
        $telegramClass = new TelegramClass();
        $telegramClass->setChatId($this->user_id);
        
        // برای جنسیت، باید از کالبک استفاده شود
        // اینجا کد اضافی نیاز نیست چون در بخش پردازش کالبک‌ها انجام می‌شود
        $telegramClass->sendMessage("⚠️ لطفاً از دکمه‌های نمایش داده شده برای انتخاب جنسیت استفاده کنید.");
        return [
            'status' => 'continue',
            'next_state' => 'profile_completion',
            'next_step' => 'waiting_for_gender'
        ];
    }
    
    /**
     * پردازش انتخاب سن
     * @param array $message پیام دریافتی
     * @return array وضعیت و مرحله بعدی
     */
    public function handleProfileAgeStep($message)
    {
        require_once __DIR__ . '/TelegramClass.php';
        $telegramClass = new TelegramClass();
        $telegramClass->setChatId($this->user_id);
        
        // برای سن، باید از کالبک استفاده شود
        // اینجا کد اضافی نیاز نیست چون در بخش پردازش کالبک‌ها انجام می‌شود
        $telegramClass->sendMessage("⚠️ لطفاً از دکمه‌های نمایش داده شده برای انتخاب سن استفاده کنید.");
        return [
            'status' => 'continue',
            'next_state' => 'profile_completion',
            'next_step' => 'waiting_for_age'
        ];
    }
    
    /**
     * پردازش بیوگرافی کاربر
     * @param array $message پیام دریافتی
     * @return array وضعیت و مرحله بعدی
     */
    public function handleProfileBioStep($message)
    {
        require_once __DIR__ . '/TelegramClass.php';
        $telegramClass = new TelegramClass();
        $telegramClass->setChatId($this->user_id);
        
        // بررسی وجود متن
        if (!isset($message['text'])) {
            $telegramClass->sendMessage("⚠️ لطفاً بیوگرافی خود را به صورت متنی وارد کنید.");
            return [
                'status' => 'continue',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_bio'
            ];
        }
        
        $bio = trim($message['text']);
        
        // بررسی لغو عملیات
        if ($bio === 'لغو ❌') {
            $telegramClass->sendMessage("عملیات ویرایش پروفایل لغو شد.");
            $telegramClass->showMainMenu();
            return [
                'status' => 'canceled',
                'next_state' => 'main_menu'
            ];
        }
        
        // بررسی طول بیوگرافی
        if (mb_strlen($bio) > 200) {
            $telegramClass->sendMessage("⚠️ بیوگرافی باید حداکثر ۲۰۰ حرف داشته باشد.");
            return [
                'status' => 'continue',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_bio'
            ];
        }
        
        try {
            // ذخیره بیوگرافی
            $result = $this->updateBio($bio);
            
            if (!$result['success']) {
                throw new \Exception($result['message']);
            }
            
            // ارسال پیام تأیید
            $telegramClass->sendMessage("✅ بیوگرافی شما با موفقیت ثبت شد و در انتظار تأیید می‌باشد.\n\nبرای ادامه تکمیل پروفایل، روی دکمه «تکمیل پروفایل» کلیک کنید.");
            $telegramClass->showMainMenu();
            
            return [
                'status' => 'success',
                'next_state' => 'main_menu',
                'next_step' => null
            ];
            
        } catch (\Exception $e) {
            $telegramClass->sendMessage("⚠️ خطا در ثبت بیوگرافی: " . $e->getMessage());
            return [
                'status' => 'error',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_bio'
            ];
        }
    }
    
    /**
     * پردازش انتخاب استان
     * @param array $message پیام دریافتی
     * @return array وضعیت و مرحله بعدی
     */
    public function handleProfileProvinceStep($message)
    {
        require_once __DIR__ . '/TelegramClass.php';
        $telegramClass = new TelegramClass();
        $telegramClass->setChatId($this->user_id);
        
        // بررسی وجود متن
        if (!isset($message['text'])) {
            $telegramClass->sendMessage("⚠️ لطفاً نام استان خود را وارد کنید.");
            return [
                'status' => 'continue',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_province'
            ];
        }
        
        $province = trim($message['text']);
        
        // بررسی لغو عملیات
        if ($province === 'لغو ❌') {
            $telegramClass->sendMessage("عملیات ویرایش پروفایل لغو شد.");
            $telegramClass->showMainMenu();
            return [
                'status' => 'canceled',
                'next_state' => 'main_menu'
            ];
        }
        
        try {
            // ذخیره استان
            $result = $this->updateProvince($province);
            
            if (!$result['success']) {
                throw new \Exception($result['message']);
            }
            
            // ارسال پیام تأیید
            $telegramClass->sendMessage("✅ استان شما با موفقیت ثبت شد.\n\nبرای ادامه تکمیل پروفایل، روی دکمه «تکمیل پروفایل» کلیک کنید.");
            $telegramClass->showMainMenu();
            
            return [
                'status' => 'success',
                'next_state' => 'main_menu',
                'next_step' => null
            ];
            
        } catch (\Exception $e) {
            $telegramClass->sendMessage("⚠️ خطا در ثبت استان: " . $e->getMessage());
            return [
                'status' => 'error',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_province'
            ];
        }
    }
    
    /**
     * پردازش انتخاب شهر
     * @param array $message پیام دریافتی
     * @return array وضعیت و مرحله بعدی
     */
    public function handleProfileCityStep($message)
    {
        require_once __DIR__ . '/TelegramClass.php';
        $telegramClass = new TelegramClass();
        $telegramClass->setChatId($this->user_id);
        
        // بررسی وجود متن
        if (!isset($message['text'])) {
            $telegramClass->sendMessage("⚠️ لطفاً نام شهر خود را وارد کنید.");
            return [
                'status' => 'continue',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_city'
            ];
        }
        
        $city = trim($message['text']);
        
        // بررسی لغو عملیات
        if ($city === 'لغو ❌') {
            $telegramClass->sendMessage("عملیات ویرایش پروفایل لغو شد.");
            $telegramClass->showMainMenu();
            return [
                'status' => 'canceled',
                'next_state' => 'main_menu'
            ];
        }
        
        try {
            // ذخیره شهر
            $result = $this->updateCity($city);
            
            if (!$result['success']) {
                throw new \Exception($result['message']);
            }
            
            // ارسال پیام تأیید
            $telegramClass->sendMessage("✅ شهر شما با موفقیت ثبت شد.\n\nبرای ادامه تکمیل پروفایل، روی دکمه «تکمیل پروفایل» کلیک کنید.");
            $telegramClass->showMainMenu();
            
            return [
                'status' => 'success',
                'next_state' => 'main_menu',
                'next_step' => null
            ];
            
        } catch (\Exception $e) {
            $telegramClass->sendMessage("⚠️ خطا در ثبت شهر: " . $e->getMessage());
            return [
                'status' => 'error',
                'next_state' => 'profile_completion',
                'next_step' => 'waiting_for_city'
            ];
        }
    }
    
    /**
     * آپلود عکس پروفایل
     * @param string $photo_url آدرس عکس
     * @return array
     */
    public function updatePhoto($photo_url)
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
            
            // بررسی وجود پروفایل
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            if ($profile) {
                // به‌روزرسانی عکس
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'photo_url' => $photo_url,
                        'photo_verified' => false, // نیاز به تأیید مجدد
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                // ایجاد پروفایل جدید
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'photo_url' => $photo_url,
                    'photo_verified' => false,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // ارسال عکس به کانال ادمین برای تأیید
            $this->sendPhotoForVerification($user, $photo_url);
            
            return [
                'success' => true,
                'message' => 'عکس پروفایل با موفقیت به‌روزرسانی شد و در انتظار تأیید می‌باشد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in updatePhoto: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی عکس پروفایل: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * تغییر نام کاربری
     * @param string $username نام کاربری جدید
     * @return array
     */
    public function changeUsername($username)
    {
        try {
            // بررسی هزینه تغییر نام کاربری
            $cost = 10; // هزینه ثابت ۱۰ دلتاکوین
            
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
            
            // بررسی موجودی دلتاکوین
            $extra = DB::table('users_extra')
                ->where('user_id', $user['id'])
                ->first();
                
            $deltacoins = $extra ? ($extra['deltacoins'] ?? 0) : 0;
            
            if ($deltacoins < $cost) {
                return [
                    'success' => false,
                    'message' => "موجودی شما {$deltacoins} دلتاکوین می‌باشد. مقدار دلتاکوین موردنیاز جهت تغییر نام کاربری {$cost} عدد می‌باشد!"
                ];
            }
            
            // حذف @ از ابتدای نام کاربری
            $username = ltrim($username, '@');
            
            // بررسی وجود نام کاربری
            $exists = DB::table('users')
                ->where('username', $username)
                ->where('id', '!=', $user['id'])
                ->exists();
                
            if ($exists) {
                return [
                    'success' => false,
                    'message' => 'این نام کاربری قبلاً توسط کاربر دیگری انتخاب شده است. لطفاً نام کاربری دیگری وارد کنید.'
                ];
            }
            
            // به‌روزرسانی نام کاربری
            DB::table('users')
                ->where('id', $user['id'])
                ->update([
                    'username' => $username,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            // کسر هزینه
            if ($extra) {
                DB::table('users_extra')
                    ->where('user_id', $user['id'])
                    ->update([
                        'deltacoins' => $deltacoins - $cost,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                DB::table('users_extra')->insert([
                    'user_id' => $user['id'],
                    'deltacoins' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return [
                'success' => true,
                'message' => "نام کاربری با موفقیت به {$username}@ تغییر یافت و {$cost} دلتاکوین از حساب شما کسر شد.",
                'username' => $username
            ];
        } catch (\Exception $e) {
            error_log("Error in changeUsername: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در تغییر نام کاربری: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * بررسی تکمیل پروفایل
     * @return bool
     */
    public function isProfileComplete()
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->user_id)
                ->first();
                
            if (!$user) {
                return false;
            }
            
            // دریافت پروفایل کاربر
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            if (!$profile) {
                return false;
            }
            
            // بررسی تکمیل فیلدهای اجباری
            $required_fields = ['full_name', 'gender', 'age', 'bio', 'province'];
            
            foreach ($required_fields as $field) {
                if (!isset($profile[$field]) || empty($profile[$field])) {
                    return false;
                }
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Error in isProfileComplete: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ارسال عکس به کانال ادمین برای تأیید
     * @param array $user اطلاعات کاربر
     * @param string $photo_url آدرس عکس
     */
    private function sendPhotoForVerification($user, $photo_url)
    {
        try {
            // دریافت آیدی کانال ادمین از تنظیمات
            $option = DB::table('options')
                ->where('option_name', 'admin_channel_id')
                ->first();
                
            if (!$option || empty($option['option_value'])) {
                return;
            }
            
            $admin_channel_id = $option['option_value'];
            
            // متن پیام
            $message = "📸 *درخواست تأیید عکس پروفایل*\n\n";
            $message .= "کاربر: " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . "\n";
            $message .= "آیدی تلگرام: {$user['telegram_id']}\n";
            $message .= "آیدی کاربر: {$user['id']}\n\n";
            $message .= "لطفاً عکس را بررسی و تأیید یا رد کنید.";
            
            // ساخت دکمه‌ها
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ تأیید', 'callback_data' => "verify_photo_{$user['id']}_1"],
                        ['text' => '❌ رد', 'callback_data' => "verify_photo_{$user['id']}_0"]
                    ]
                ]
            ]);
            
            // ارسال عکس
            if (function_exists('sendPhoto')) {
                sendPhoto($_ENV['TELEGRAM_TOKEN'], $admin_channel_id, $photo_url, $message, 'Markdown', $reply_markup);
            }
        } catch (\Exception $e) {
            error_log("Error in sendPhotoForVerification: " . $e->getMessage());
        }
    }
    
    /**
     * ارسال بیوگرافی به کانال ادمین برای تأیید
     * @param array $user اطلاعات کاربر
     * @param string $bio بیوگرافی
     */
    private function sendBioForVerification($user, $bio)
    {
        try {
            // دریافت آیدی کانال ادمین از تنظیمات
            $option = DB::table('options')
                ->where('option_name', 'admin_channel_id')
                ->first();
                
            if (!$option || empty($option['option_value'])) {
                return;
            }
            
            $admin_channel_id = $option['option_value'];
            
            // متن پیام
            $message = "📝 *درخواست تأیید بیوگرافی*\n\n";
            $message .= "کاربر: " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . "\n";
            $message .= "آیدی تلگرام: {$user['telegram_id']}\n";
            $message .= "آیدی کاربر: {$user['id']}\n\n";
            $message .= "بیوگرافی:\n{$bio}\n\n";
            $message .= "لطفاً بیوگرافی را بررسی و تأیید یا رد کنید.";
            
            // ساخت دکمه‌ها
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ تأیید', 'callback_data' => "verify_bio_{$user['id']}_1"],
                        ['text' => '❌ رد', 'callback_data' => "verify_bio_{$user['id']}_0"]
                    ]
                ]
            ]);
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($_ENV['TELEGRAM_TOKEN'], $admin_channel_id, $message, 'Markdown', $reply_markup);
            }
        } catch (\Exception $e) {
            error_log("Error in sendBioForVerification: " . $e->getMessage());
        }
    }
}