<?php
namespace application\controllers;

require_once __DIR__ . '/../Model/DB.php';

use Application\Model\DB;

/**
 * کلاس مدیریت زمان انقضای درخواست‌ها
 */
class RequestTimeoutController
{
    /**
     * بررسی زمان انقضای درخواست بازی
     * @param int $sender_id شناسه فرستنده
     * @param int $receiver_id شناسه گیرنده
     * @return array
     */
    public static function checkGameRequest($sender_id, $receiver_id)
    {
        try {
            // دریافت آخرین درخواست بازی
            $request = DB::table('game_requests')
                ->where('sender_id', $sender_id)
                ->where('receiver_id', $receiver_id)
                ->where('status', 'pending')
                ->orderBy('created_at', 'DESC')
                ->first();
                
            if (!$request) {
                return [
                    'can_send' => true,
                    'request' => null
                ];
            }
            
            // بررسی زمان انقضا
            $requestTime = strtotime($request['created_at']);
            $currentTime = time();
            $timeoutSeconds = 600; // 10 دقیقه
            
            if (($currentTime - $requestTime) > $timeoutSeconds) {
                // درخواست منقضی شده، می‌تواند درخواست جدید ارسال کند
                return [
                    'can_send' => true,
                    'request' => $request
                ];
            }
            
            // محاسبه زمان باقی‌مانده
            $remainingSeconds = $timeoutSeconds - ($currentTime - $requestTime);
            $remainingMinutes = ceil($remainingSeconds / 60);
            
            return [
                'can_send' => false,
                'request' => $request,
                'remaining_seconds' => $remainingSeconds,
                'remaining_minutes' => $remainingMinutes
            ];
        } catch (\Exception $e) {
            error_log("Error in checkGameRequest: " . $e->getMessage());
            return [
                'can_send' => true, // در صورت خطا اجازه ارسال می‌دهیم
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * بررسی زمان انقضای درخواست دوستی
     * @param int $sender_id شناسه فرستنده
     * @param int $receiver_id شناسه گیرنده
     * @return array
     */
    public static function checkFriendRequest($sender_id, $receiver_id)
    {
        try {
            // دریافت آخرین درخواست دوستی
            $request = DB::table('friend_requests')
                ->where('sender_id', $sender_id)
                ->where('receiver_id', $receiver_id)
                ->where('status', 'pending')
                ->orderBy('created_at', 'DESC')
                ->first();
                
            if (!$request) {
                return [
                    'can_send' => true,
                    'request' => null
                ];
            }
            
            // بررسی زمان انقضا
            $requestTime = strtotime($request['created_at']);
            $currentTime = time();
            $timeoutSeconds = 86400; // 24 ساعت
            
            if (($currentTime - $requestTime) > $timeoutSeconds) {
                // درخواست منقضی شده، می‌تواند درخواست جدید ارسال کند
                return [
                    'can_send' => true,
                    'request' => $request
                ];
            }
            
            // محاسبه زمان باقی‌مانده
            $remainingSeconds = $timeoutSeconds - ($currentTime - $requestTime);
            $remainingHours = ceil($remainingSeconds / 3600);
            
            return [
                'can_send' => false,
                'request' => $request,
                'remaining_seconds' => $remainingSeconds,
                'remaining_hours' => $remainingHours
            ];
        } catch (\Exception $e) {
            error_log("Error in checkFriendRequest: " . $e->getMessage());
            return [
                'can_send' => true, // در صورت خطا اجازه ارسال می‌دهیم
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * ایجاد درخواست بازی
     * @param int $sender_id شناسه فرستنده
     * @param int $receiver_id شناسه گیرنده
     * @return int|bool شناسه درخواست یا false در صورت خطا
     */
    public static function createGameRequest($sender_id, $receiver_id)
    {
        try {
            // بررسی وجود درخواست قبلی
            $checkResult = self::checkGameRequest($sender_id, $receiver_id);
            
            if (!$checkResult['can_send']) {
                return false;
            }
            
            // ایجاد درخواست بازی
            $request_id = DB::table('game_requests')->insert([
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            return $request_id;
        } catch (\Exception $e) {
            error_log("Error in createGameRequest: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ایجاد درخواست دوستی
     * @param int $sender_id شناسه فرستنده
     * @param int $receiver_id شناسه گیرنده
     * @return int|bool شناسه درخواست یا false در صورت خطا
     */
    public static function createFriendRequest($sender_id, $receiver_id)
    {
        try {
            // بررسی وجود درخواست قبلی
            $checkResult = self::checkFriendRequest($sender_id, $receiver_id);
            
            if (!$checkResult['can_send']) {
                return false;
            }
            
            // ایجاد درخواست دوستی
            $request_id = DB::table('friend_requests')->insert([
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            return $request_id;
        } catch (\Exception $e) {
            error_log("Error in createFriendRequest: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف درخواست‌های منقضی شده
     * @return array تعداد درخواست‌های منقضی شده
     */
    public static function cleanupExpiredRequests()
    {
        try {
            $gameRequestsCount = 0;
            $friendRequestsCount = 0;
            
            // حذف درخواست‌های بازی منقضی شده
            $gameRequestsTimeout = 600; // 10 دقیقه
            $expiredGameRequestsTime = date('Y-m-d H:i:s', time() - $gameRequestsTimeout);
            
            $gameRequestsCount = DB::table('game_requests')
                ->where('status', 'pending')
                ->where('created_at', '<', $expiredGameRequestsTime)
                ->update([
                    'status' => 'expired',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            // حذف درخواست‌های دوستی منقضی شده
            $friendRequestsTimeout = 86400; // 24 ساعت
            $expiredFriendRequestsTime = date('Y-m-d H:i:s', time() - $friendRequestsTimeout);
            
            $friendRequestsCount = DB::table('friend_requests')
                ->where('status', 'pending')
                ->where('created_at', '<', $expiredFriendRequestsTime)
                ->update([
                    'status' => 'expired',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            return [
                'success' => true,
                'game_requests_expired' => $gameRequestsCount,
                'friend_requests_expired' => $friendRequestsCount
            ];
        } catch (\Exception $e) {
            error_log("Error in cleanupExpiredRequests: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در حذف درخواست‌های منقضی شده: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * پذیرش درخواست بازی
     * @param int $request_id شناسه درخواست
     * @return array
     */
    public static function acceptGameRequest($request_id)
    {
        try {
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
            
            // بررسی وضعیت درخواست
            if ($request['status'] !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'این درخواست بازی قبلاً پذیرفته، رد یا منقضی شده است.'
                ];
            }
            
            // به‌روزرسانی وضعیت درخواست
            $result = DB::table('game_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'accepted',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'خطا در پذیرش درخواست بازی.'
                ];
            }
            
            // دریافت اطلاعات فرستنده و گیرنده
            $sender = DB::table('users')
                ->where('id', $request['sender_id'])
                ->first();
                
            $receiver = DB::table('users')
                ->where('id', $request['receiver_id'])
                ->first();
                
            if (!$sender || !$receiver) {
                return [
                    'success' => false,
                    'message' => 'خطا در دریافت اطلاعات بازیکنان.'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'درخواست بازی با موفقیت پذیرفته شد.',
                'sender' => $sender,
                'receiver' => $receiver,
                'request' => $request
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
    public static function rejectGameRequest($request_id)
    {
        try {
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
            
            // بررسی وضعیت درخواست
            if ($request['status'] !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'این درخواست بازی قبلاً پذیرفته، رد یا منقضی شده است.'
                ];
            }
            
            // به‌روزرسانی وضعیت درخواست
            $result = DB::table('game_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'rejected',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'خطا در رد درخواست بازی.'
                ];
            }
            
            // دریافت اطلاعات فرستنده و گیرنده
            $sender = DB::table('users')
                ->where('id', $request['sender_id'])
                ->first();
                
            $receiver = DB::table('users')
                ->where('id', $request['receiver_id'])
                ->first();
                
            if (!$sender || !$receiver) {
                return [
                    'success' => false,
                    'message' => 'خطا در دریافت اطلاعات بازیکنان.'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'درخواست بازی با موفقیت رد شد.',
                'sender' => $sender,
                'receiver' => $receiver,
                'request' => $request
            ];
        } catch (\Exception $e) {
            error_log("Error in rejectGameRequest: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در رد درخواست بازی: ' . $e->getMessage()
            ];
        }
    }
}