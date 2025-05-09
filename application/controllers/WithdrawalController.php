<?php
namespace application\controllers;

require_once __DIR__ . '/../Model/DB.php';

use Application\Model\DB;

/**
 * کلاس مدیریت برداشت دلتا کوین
 */
class WithdrawalController
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
     * ایجاد درخواست برداشت
     * @param int $amount مقدار برداشت
     * @param string $type نوع برداشت (bank یا trx)
     * @param string $wallet آدرس کیف پول یا شماره کارت
     * @return array
     */
    public function createWithdrawalRequest($amount, $type, $wallet)
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
            
            // دریافت اطلاعات اضافی کاربر
            $userExtra = DB::table('users_extra')
                ->where('user_id', $user['id'])
                ->first();
                
            if (!$userExtra) {
                return [
                    'success' => false,
                    'message' => 'اطلاعات اضافی کاربر یافت نشد.'
                ];
            }
            
            // بررسی موجودی
            if ($userExtra['delta_coins'] < $amount) {
                return [
                    'success' => false,
                    'message' => "موجودی شما {$userExtra['delta_coins']} دلتا کوین میباشد. مقدار وارد شده بیشتر از موجودی میباشد!"
                ];
            }
            
            // بررسی حداقل مقدار برداشت
            $minWithdrawalAmount = $this->getMinWithdrawalAmount();
            
            if ($amount < $minWithdrawalAmount) {
                return [
                    'success' => false,
                    'message' => "حداقل برداشت دلتا کوین {$minWithdrawalAmount} عدد میباشد!"
                ];
            }
            
            // بررسی مضرب بودن
            $step = $this->getWithdrawalStep();
            
            if ($amount % $step !== 0) {
                return [
                    'success' => false,
                    'message' => "مقدار برداشت باید مضربی از {$step} باشد."
                ];
            }
            
            // اعتبارسنجی نوع برداشت
            if (!in_array($type, ['bank', 'trx'])) {
                return [
                    'success' => false,
                    'message' => 'نوع برداشت نامعتبر است.'
                ];
            }
            
            // اعتبارسنجی کیف پول یا شماره کارت
            if ($type === 'bank') {
                // بررسی شماره کارت
                $wallet = preg_replace('/[^0-9]/', '', $wallet);
                
                if (strlen($wallet) !== 16) {
                    return [
                        'success' => false,
                        'message' => 'شماره کارت باید 16 رقم باشد.'
                    ];
                }
            } else { // trx
                // بررسی آدرس ترون
                if (strlen($wallet) < 30 || strpos($wallet, 'T') !== 0) {
                    return [
                        'success' => false,
                        'message' => 'آدرس کیف پول ترون نامعتبر است.'
                    ];
                }
            }
            
            // بررسی وجود درخواست معلق قبلی
            $pendingRequest = DB::table('withdrawal_requests')
                ->where('user_id', $user['id'])
                ->where('status', 'pending')
                ->first();
                
            if ($pendingRequest) {
                return [
                    'success' => false,
                    'message' => 'شما یک درخواست برداشت معلق دارید. لطفاً تا تکمیل آن صبر کنید.'
                ];
            }
            
            // کاهش موجودی کاربر
            $result = DB::table('users_extra')
                ->where('user_id', $user['id'])
                ->update([
                    'delta_coins' => $userExtra['delta_coins'] - $amount,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'خطا در کاهش موجودی.'
                ];
            }
            
            // ایجاد درخواست برداشت
            $request_id = DB::table('withdrawal_requests')->insert([
                'user_id' => $user['id'],
                'amount' => $amount,
                'type' => $type,
                'bank_card_number' => $type === 'bank' ? $wallet : null,
                'wallet_address' => $type === 'trx' ? $wallet : null,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$request_id) {
                // برگرداندن موجودی
                DB::table('users_extra')
                    ->where('user_id', $user['id'])
                    ->update([
                        'delta_coins' => $userExtra['delta_coins'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    
                return [
                    'success' => false,
                    'message' => 'خطا در ثبت درخواست برداشت.'
                ];
            }
            
            // ثبت تراکنش
            DB::table('transactions')->insert([
                'user_id' => $user['id'],
                'amount' => -$amount,
                'description' => 'درخواست برداشت ' . ($type === 'bank' ? 'بانکی' : 'ترون'),
                'transaction_type' => 'withdrawal_request',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // ارسال اعلان به ادمین‌ها
            $this->sendWithdrawalNotificationToAdmins($user, $request_id, $amount, $type, $wallet);
            
            return [
                'success' => true,
                'message' => 'درخواست برداشت با موفقیت ثبت شد و پس از بررسی، به حساب شما واریز خواهد شد.',
                'request_id' => $request_id
            ];
        } catch (\Exception $e) {
            error_log("Error in createWithdrawalRequest: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ایجاد درخواست برداشت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * تایید درخواست برداشت
     * @param int $request_id شناسه درخواست
     * @param int $admin_id شناسه ادمین
     * @return bool
     */
    public function approveWithdrawalRequest($request_id, $admin_id)
    {
        try {
            // دریافت اطلاعات درخواست
            $request = DB::table('withdrawal_requests')
                ->where('id', $request_id)
                ->first();
                
            if (!$request) {
                return false;
            }
            
            // بررسی وضعیت درخواست
            if ($request['status'] !== 'pending') {
                return false;
            }
            
            // به‌روزرسانی وضعیت درخواست
            $result = DB::table('withdrawal_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'approved',
                    'admin_id' => $admin_id,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            if (!$result) {
                return false;
            }
            
            // ثبت تراکنش
            DB::table('transactions')->insert([
                'user_id' => $request['user_id'],
                'amount' => 0, // بدون تأثیر در موجودی
                'description' => 'تایید برداشت ' . ($request['type'] === 'bank' ? 'بانکی' : 'ترون'),
                'transaction_type' => 'withdrawal_approved',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return true;
        } catch (\Exception $e) {
            error_log("Error in approveWithdrawalRequest: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * رد درخواست برداشت
     * @param int $request_id شناسه درخواست
     * @param int $admin_id شناسه ادمین
     * @return bool
     */
    public function rejectWithdrawalRequest($request_id, $admin_id)
    {
        try {
            // دریافت اطلاعات درخواست
            $request = DB::table('withdrawal_requests')
                ->where('id', $request_id)
                ->first();
                
            if (!$request) {
                return false;
            }
            
            // بررسی وضعیت درخواست
            if ($request['status'] !== 'pending') {
                return false;
            }
            
            // به‌روزرسانی وضعیت درخواست
            $result = DB::table('withdrawal_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'rejected',
                    'admin_id' => $admin_id,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            if (!$result) {
                return false;
            }
            
            // بازگرداندن موجودی به کاربر
            $userExtra = DB::table('users_extra')
                ->where('user_id', $request['user_id'])
                ->first();
                
            if ($userExtra) {
                DB::table('users_extra')
                    ->where('user_id', $request['user_id'])
                    ->update([
                        'delta_coins' => $userExtra['delta_coins'] + $request['amount'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    
                // ثبت تراکنش
                DB::table('transactions')->insert([
                    'user_id' => $request['user_id'],
                    'amount' => $request['amount'],
                    'description' => 'برگشت مبلغ برداشت ' . ($request['type'] === 'bank' ? 'بانکی' : 'ترون') . ' به دلیل رد درخواست',
                    'transaction_type' => 'withdrawal_rejected',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Error in rejectWithdrawalRequest: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت لیست درخواست‌های برداشت
     * @param string $status وضعیت درخواست (pending, approved, rejected, all)
     * @param int $page شماره صفحه
     * @param int $limit تعداد در هر صفحه
     * @return array
     */
    public function getWithdrawalRequests($status = 'pending', $page = 1, $limit = 10)
    {
        try {
            // محاسبه آفست
            $offset = ($page - 1) * $limit;
            
            // ساخت کوئری
            $query = DB::table('withdrawal_requests')
                ->join('users', 'withdrawal_requests.user_id', '=', 'users.id')
                ->leftJoin('users as admin', 'withdrawal_requests.admin_id', '=', 'admin.id');
                
            // اعمال فیلتر وضعیت
            if ($status !== 'all') {
                $query->where('withdrawal_requests.status', $status);
            }
            
            // دریافت تعداد کل
            $total = $query->count();
            
            // دریافت درخواست‌ها
            $requests = $query
                ->select([
                    'withdrawal_requests.*',
                    'users.username as user_username',
                    'users.first_name as user_first_name',
                    'users.last_name as user_last_name',
                    'users.telegram_id as user_telegram_id',
                    'admin.username as admin_username',
                    'admin.first_name as admin_first_name',
                    'admin.last_name as admin_last_name'
                ])
                ->orderBy('withdrawal_requests.created_at', 'DESC')
                ->limit($limit)
                ->offset($offset)
                ->get();
                
            // محاسبه تعداد صفحات
            $total_pages = ceil($total / $limit);
            
            // انتخاب قیمت دلتا کوین
            $delta_coin_price = $this->getDeltaCoinPrice();
            
            // افزودن مبلغ به تومان
            foreach ($requests as &$request) {
                $request['amount_toman'] = $request['amount'] * $delta_coin_price;
            }
            
            return [
                'success' => true,
                'requests' => $requests,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $total_pages,
                'delta_coin_price' => $delta_coin_price
            ];
        } catch (\Exception $e) {
            error_log("Error in getWithdrawalRequests: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در دریافت لیست درخواست‌های برداشت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * دریافت قیمت دلتا کوین
     * @return int
     */
    public function getDeltaCoinPrice()
    {
        try {
            // دریافت قیمت از تنظیمات
            $setting = DB::table('settings')
                ->where('key', 'delta_coin_price')
                ->first();
                
            if (!$setting) {
                return 500; // مقدار پیش‌فرض
            }
            
            return (int) $setting['value'];
        } catch (\Exception $e) {
            error_log("Error in getDeltaCoinPrice: " . $e->getMessage());
            return 500; // مقدار پیش‌فرض
        }
    }
    
    /**
     * دریافت حداقل مقدار برداشت
     * @return int
     */
    public function getMinWithdrawalAmount()
    {
        try {
            // دریافت حداقل مقدار از تنظیمات
            $setting = DB::table('settings')
                ->where('key', 'min_withdrawal_amount')
                ->first();
                
            if (!$setting) {
                return 50; // مقدار پیش‌فرض
            }
            
            return (int) $setting['value'];
        } catch (\Exception $e) {
            error_log("Error in getMinWithdrawalAmount: " . $e->getMessage());
            return 50; // مقدار پیش‌فرض
        }
    }
    
    /**
     * دریافت مضرب برداشت
     * @return int
     */
    public function getWithdrawalStep()
    {
        try {
            // دریافت مضرب از تنظیمات
            $setting = DB::table('settings')
                ->where('key', 'withdrawal_step')
                ->first();
                
            if (!$setting) {
                return 10; // مقدار پیش‌فرض
            }
            
            return (int) $setting['value'];
        } catch (\Exception $e) {
            error_log("Error in getWithdrawalStep: " . $e->getMessage());
            return 10; // مقدار پیش‌فرض
        }
    }
    
    /**
     * دریافت درصد پورسانت معرف
     * @return int
     */
    public function getReferralCommissionPercent()
    {
        try {
            // دریافت درصد پورسانت از تنظیمات
            $setting = DB::table('settings')
                ->where('key', 'referral_commission_percent')
                ->first();
                
            if (!$setting) {
                return 5; // مقدار پیش‌فرض
            }
            
            return (int) $setting['value'];
        } catch (\Exception $e) {
            error_log("Error in getReferralCommissionPercent: " . $e->getMessage());
            return 5; // مقدار پیش‌فرض
        }
    }
    
    /**
     * ارسال اعلان درخواست برداشت به ادمین‌ها
     * @param array $user کاربر
     * @param int $request_id شناسه درخواست
     * @param int $amount مقدار
     * @param string $type نوع
     * @param string $wallet آدرس کیف پول یا شماره کارت
     * @return void
     */
    private function sendWithdrawalNotificationToAdmins($user, $request_id, $amount, $type, $wallet)
    {
        try {
            // دریافت ادمین‌های با دسترسی مدیریت برداشت
            $admins = DB::table('admin_permissions')
                ->join('users', 'admin_permissions.user_id', '=', 'users.id')
                ->where('admin_permissions.can_manage_withdrawals', true)
                ->select('users.telegram_id')
                ->get();
                
            if (empty($admins)) {
                return;
            }
            
            // محاسبه مبلغ به تومان
            $delta_coin_price = $this->getDeltaCoinPrice();
            $amount_toman = $amount * $delta_coin_price;
            
            // متن پیام
            $message = "💸 *درخواست برداشت جدید*\n\n";
            $message .= "کاربر: " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . "\n";
            $message .= "مقدار: " . $amount . " دلتا کوین\n";
            $message .= "مبلغ: " . number_format($amount_toman) . " تومان\n";
            $message .= "نوع: " . ($type === 'bank' ? "بانکی 🏦" : "ترون 💎") . "\n";
            
            if ($type === 'bank') {
                $message .= "شماره کارت: " . $wallet . "\n";
            } else {
                $message .= "آدرس کیف پول: " . $wallet . "\n";
            }
            
            $message .= "تاریخ درخواست: " . date('Y-m-d H:i:s');
            
            // ساخت دکمه‌ها
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ تایید و واریز', 'callback_data' => "approve_withdrawal_{$request_id}"],
                        ['text' => '❌ رد درخواست', 'callback_data' => "reject_withdrawal_{$request_id}"]
                    ],
                    [
                        ['text' => '🔍 جزئیات بیشتر', 'callback_data' => "withdrawal_{$request_id}"]
                    ]
                ]
            ]);
            
            // ارسال پیام به ادمین‌ها
            if (function_exists('sendMessage')) {
                foreach ($admins as $admin) {
                    sendMessage($GLOBALS['token'], $admin['telegram_id'], $message, 'Markdown', $reply_markup);
                }
            }
        } catch (\Exception $e) {
            error_log("Error in sendWithdrawalNotificationToAdmins: " . $e->getMessage());
        }
    }
}