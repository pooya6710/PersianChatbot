<?php
namespace application\controllers;

require_once __DIR__ . '/../Model/DB.php';

use Application\Model\DB;

/**
 * کلاس مدیریت دوستی
 */
class FriendshipController
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
     * ارسال درخواست دوستی
     * @param string $username نام کاربری یا آیدی تلگرام
     * @return array
     */
    public function sendFriendRequest($username)
    {
        try {
            // دریافت اطلاعات کاربر فرستنده
            $user = DB::table('users')
                ->where('telegram_id', $this->user_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // دریافت اطلاعات کاربر هدف
            $target_user = null;
            
            // بررسی آیا نام کاربری است یا آیدی تلگرام
            if (is_numeric($username)) {
                $target_user = DB::table('users')
                    ->where('telegram_id', $username)
                    ->first();
            } else {
                $target_user = DB::table('users')
                    ->where('username', ltrim($username, '@'))
                    ->first();
            }
            
            if (!$target_user) {
                return [
                    'success' => false,
                    'message' => 'کاربر مورد نظر یافت نشد.'
                ];
            }
            
            // بررسی عدم ارسال درخواست به خود
            if ($user['id'] === $target_user['id']) {
                return [
                    'success' => false,
                    'message' => 'شما نمی‌توانید به خودتان درخواست دوستی ارسال کنید.'
                ];
            }
            
            // بررسی وجود دوستی قبلی
            $existingFriendship = DB::table('friendships')
                ->where(function ($query) use ($user, $target_user) {
                    $query->where('user_id_1', $user['id'])
                        ->where('user_id_2', $target_user['id']);
                })
                ->orWhere(function ($query) use ($user, $target_user) {
                    $query->where('user_id_1', $target_user['id'])
                        ->where('user_id_2', $user['id']);
                })
                ->first();
                
            if ($existingFriendship) {
                return [
                    'success' => false,
                    'message' => 'شما و این کاربر قبلاً دوست هستید.'
                ];
            }
            
            // بررسی وجود درخواست قبلی از سمت کاربر
            $existingRequest = DB::table('friend_requests')
                ->where('sender_id', $user['id'])
                ->where('receiver_id', $target_user['id'])
                ->where('status', 'pending')
                ->first();
                
            if ($existingRequest) {
                return [
                    'success' => false,
                    'message' => 'شما قبلاً به این کاربر درخواست دوستی ارسال کرده‌اید که هنوز پاسخ داده نشده است.'
                ];
            }
            
            // بررسی وجود درخواست قبلی از سمت کاربر هدف
            $existingRequest = DB::table('friend_requests')
                ->where('sender_id', $target_user['id'])
                ->where('receiver_id', $user['id'])
                ->where('status', 'pending')
                ->first();
                
            if ($existingRequest) {
                // پذیرش خودکار درخواست
                $result = $this->acceptFriendRequest($existingRequest['id']);
                
                if ($result['success']) {
                    return [
                        'success' => true,
                        'message' => 'این کاربر قبلاً به شما درخواست دوستی ارسال کرده بود که به طور خودکار پذیرفته شد.',
                        'auto_accepted' => true,
                        'friend' => $target_user
                    ];
                }
            }
            
            // ایجاد درخواست دوستی
            $request_id = DB::table('friend_requests')->insert([
                'sender_id' => $user['id'],
                'receiver_id' => $target_user['id'],
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$request_id) {
                return [
                    'success' => false,
                    'message' => 'خطا در ارسال درخواست دوستی.'
                ];
            }
            
            // ارسال اعلان به کاربر هدف
            $this->sendFriendRequestNotification($user, $target_user, $request_id);
            
            return [
                'success' => true,
                'message' => 'درخواست دوستی با موفقیت ارسال شد. پس از پذیرش به شما اطلاع داده خواهد شد.',
                'auto_accepted' => false,
                'friend' => $target_user
            ];
        } catch (\Exception $e) {
            error_log("Error in sendFriendRequest: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ارسال درخواست دوستی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * پذیرش درخواست دوستی
     * @param int $request_id شناسه درخواست
     * @return array
     */
    public function acceptFriendRequest($request_id)
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
            $request = DB::table('friend_requests')
                ->where('id', $request_id)
                ->first();
                
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'درخواست دوستی یافت نشد.'
                ];
            }
            
            // بررسی اینکه درخواست برای کاربر فعلی باشد
            if ($request['receiver_id'] != $user['id'] && $request['sender_id'] != $user['id']) {
                return [
                    'success' => false,
                    'message' => 'شما مجاز به پذیرش این درخواست دوستی نیستید.'
                ];
            }
            
            // بررسی وضعیت درخواست
            if ($request['status'] !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'این درخواست دوستی قبلاً پذیرفته یا رد شده است.'
                ];
            }
            
            // به‌روزرسانی وضعیت درخواست
            $result = DB::table('friend_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'accepted',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'خطا در پذیرش درخواست دوستی.'
                ];
            }
            
            // دریافت اطلاعات کاربر دیگر
            $friend_id = ($request['sender_id'] == $user['id']) ? $request['receiver_id'] : $request['sender_id'];
            $friend = DB::table('users')
                ->where('id', $friend_id)
                ->first();
                
            if (!$friend) {
                return [
                    'success' => false,
                    'message' => 'کاربر دوست یافت نشد.'
                ];
            }
            
            // ایجاد دوستی
            DB::table('friendships')->insert([
                'user_id_1' => min($user['id'], $friend['id']),
                'user_id_2' => max($user['id'], $friend['id']),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // ارسال اعلان به کاربر دیگر
            $this->sendFriendAcceptedNotification($user, $friend);
            
            return [
                'success' => true,
                'message' => 'درخواست دوستی با موفقیت پذیرفته شد.',
                'friend' => $friend
            ];
        } catch (\Exception $e) {
            error_log("Error in acceptFriendRequest: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در پذیرش درخواست دوستی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * رد درخواست دوستی
     * @param int $request_id شناسه درخواست
     * @return array
     */
    public function rejectFriendRequest($request_id)
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
            $request = DB::table('friend_requests')
                ->where('id', $request_id)
                ->first();
                
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'درخواست دوستی یافت نشد.'
                ];
            }
            
            // بررسی اینکه درخواست برای کاربر فعلی باشد
            if ($request['receiver_id'] != $user['id']) {
                return [
                    'success' => false,
                    'message' => 'شما مجاز به رد این درخواست دوستی نیستید.'
                ];
            }
            
            // بررسی وضعیت درخواست
            if ($request['status'] !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'این درخواست دوستی قبلاً پذیرفته یا رد شده است.'
                ];
            }
            
            // به‌روزرسانی وضعیت درخواست
            $result = DB::table('friend_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'rejected',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'خطا در رد درخواست دوستی.'
                ];
            }
            
            // دریافت اطلاعات فرستنده درخواست
            $sender = DB::table('users')
                ->where('id', $request['sender_id'])
                ->first();
                
            if ($sender) {
                // ارسال اعلان به فرستنده
                $this->sendFriendRejectedNotification($user, $sender);
            }
            
            return [
                'success' => true,
                'message' => 'درخواست دوستی با موفقیت رد شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in rejectFriendRequest: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در رد درخواست دوستی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * حذف دوستی
     * @param int $friendship_id شناسه دوستی
     * @return array
     */
    public function removeFriend($friend_id)
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
            
            // حذف دوستی
            $result = DB::table('friendships')
                ->where('id', $friendship['id'])
                ->delete();
                
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'خطا در حذف دوستی.'
                ];
            }
            
            // ارسال اعلان به دوست
            $this->sendFriendRemovedNotification($user, $friend);
            
            return [
                'success' => true,
                'message' => 'دوستی با موفقیت حذف شد.'
            ];
        } catch (\Exception $e) {
            error_log("Error in removeFriend: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در حذف دوستی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * بررسی دوستی
     * @param int $friend_id شناسه دوست
     * @return array
     */
    public function checkFriendship($friend_id)
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
                
            // بررسی وجود درخواست معلق
            $pendingRequest = null;
            
            if (!$friendship) {
                $pendingRequest = DB::table('friend_requests')
                    ->where(function ($query) use ($user, $friend) {
                        $query->where('sender_id', $user['id'])
                            ->where('receiver_id', $friend['id']);
                    })
                    ->orWhere(function ($query) use ($user, $friend) {
                        $query->where('sender_id', $friend['id'])
                            ->where('receiver_id', $user['id']);
                    })
                    ->where('status', 'pending')
                    ->first();
            }
            
            return [
                'success' => true,
                'is_friend' => !is_null($friendship),
                'pending_request' => $pendingRequest,
                'friendship' => $friendship
            ];
        } catch (\Exception $e) {
            error_log("Error in checkFriendship: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در بررسی دوستی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * دریافت لیست دوستان
     * @param int $page شماره صفحه
     * @param int $limit تعداد در هر صفحه
     * @return array
     */
    public function getFriendsList($page = 1, $limit = 10)
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
            
            // محاسبه آفست
            $offset = ($page - 1) * $limit;
            
            // دریافت تعداد کل دوستان
            $total = DB::query(
                "SELECT COUNT(*) as count FROM 
                (
                    SELECT CASE
                        WHEN user_id_1 = ? THEN user_id_2
                        WHEN user_id_2 = ? THEN user_id_1
                    END as friend_id
                    FROM friendships
                    WHERE user_id_1 = ? OR user_id_2 = ?
                ) as friends",
                [$user['id'], $user['id'], $user['id'], $user['id']]
            )->fetch();
            
            // دریافت دوستان
            $friends = DB::query(
                "SELECT u.*, ue.trophies, ue.wins, ue.total_games
                FROM users u
                LEFT JOIN users_extra ue ON u.id = ue.user_id
                WHERE u.id IN (
                    SELECT CASE
                        WHEN f.user_id_1 = ? THEN f.user_id_2
                        WHEN f.user_id_2 = ? THEN f.user_id_1
                    END as friend_id
                    FROM friendships f
                    WHERE f.user_id_1 = ? OR f.user_id_2 = ?
                )
                ORDER BY u.username, u.first_name
                LIMIT ? OFFSET ?",
                [$user['id'], $user['id'], $user['id'], $user['id'], $limit, $offset]
            )->fetchAll();
            
            // محاسبه تعداد صفحات
            $total_pages = ceil($total['count'] / $limit);
            
            return [
                'success' => true,
                'friends' => $friends,
                'total' => $total['count'],
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $total_pages
            ];
        } catch (\Exception $e) {
            error_log("Error in getFriendsList: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در دریافت لیست دوستان: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * دریافت لیست درخواست‌های دوستی
     * @param string $type نوع درخواست‌ها (received, sent, all)
     * @param int $page شماره صفحه
     * @param int $limit تعداد در هر صفحه
     * @return array
     */
    public function getFriendRequests($type = 'received', $page = 1, $limit = 10)
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
            
            // محاسبه آفست
            $offset = ($page - 1) * $limit;
            
            // شرط مربوط به نوع درخواست‌ها
            $condition = '';
            $params = [];
            
            switch ($type) {
                case 'received':
                    $condition = 'fr.receiver_id = ? AND fr.status = "pending"';
                    $params = [$user['id'], $limit, $offset];
                    break;
                    
                case 'sent':
                    $condition = 'fr.sender_id = ? AND fr.status = "pending"';
                    $params = [$user['id'], $limit, $offset];
                    break;
                    
                case 'all':
                default:
                    $condition = '(fr.sender_id = ? OR fr.receiver_id = ?) AND fr.status = "pending"';
                    $params = [$user['id'], $user['id'], $limit, $offset];
                    break;
            }
            
            // دریافت تعداد کل درخواست‌ها
            $query = "SELECT COUNT(*) as count FROM friend_requests fr WHERE " . $condition;
            $total = DB::query($query, array_slice($params, 0, -2))->fetch();
            
            // دریافت درخواست‌ها
            $query = "
                SELECT fr.*, 
                    CASE 
                        WHEN fr.sender_id = ? THEN 'sent'
                        ELSE 'received'
                    END as direction,
                    s.id as sender_id, s.telegram_id as sender_telegram_id, s.username as sender_username, s.first_name as sender_first_name, s.last_name as sender_last_name,
                    r.id as receiver_id, r.telegram_id as receiver_telegram_id, r.username as receiver_username, r.first_name as receiver_first_name, r.last_name as receiver_last_name
                FROM friend_requests fr
                JOIN users s ON fr.sender_id = s.id
                JOIN users r ON fr.receiver_id = r.id
                WHERE {$condition}
                ORDER BY fr.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $requests = DB::query($query, array_merge([$user['id']], $params))->fetchAll();
            
            // محاسبه تعداد صفحات
            $total_pages = ceil($total['count'] / $limit);
            
            return [
                'success' => true,
                'requests' => $requests,
                'total' => $total['count'],
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $total_pages,
                'type' => $type
            ];
        } catch (\Exception $e) {
            error_log("Error in getFriendRequests: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در دریافت لیست درخواست‌های دوستی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * مشاهده پروفایل کاربر
     * @param string $username نام کاربری یا آیدی تلگرام
     * @return array
     */
    public function viewUserProfile($username)
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
            
            // دریافت اطلاعات کاربر هدف
            $target_user = null;
            
            // بررسی آیا نام کاربری است یا آیدی تلگرام
            if (is_numeric($username)) {
                $target_user = DB::table('users')
                    ->where('telegram_id', $username)
                    ->first();
            } else {
                $target_user = DB::table('users')
                    ->where('username', ltrim($username, '@'))
                    ->first();
            }
            
            if (!$target_user) {
                return [
                    'success' => false,
                    'message' => 'کاربر مورد نظر یافت نشد.'
                ];
            }
            
            // دریافت اطلاعات اضافی
            $targetExtra = DB::table('users_extra')
                ->where('user_id', $target_user['id'])
                ->first();
                
            // دریافت پروفایل
            $targetProfile = DB::table('user_profiles')
                ->where('user_id', $target_user['id'])
                ->first();
                
            // بررسی وضعیت دوستی
            $friendshipStatus = $this->checkFriendship($target_user['id']);
            
            // ساخت خروجی
            $result = [
                'success' => true,
                'user_id' => $target_user['id'],
                'telegram_id' => $target_user['telegram_id'],
                'username' => $target_user['username'],
                'first_name' => $target_user['first_name'],
                'last_name' => $target_user['last_name'],
                'name' => ($targetProfile && $targetProfile['name']) ? $targetProfile['name'] : $target_user['first_name'] . ' ' . $target_user['last_name'],
                'trophies' => $targetExtra ? $targetExtra['trophies'] : 0,
                'wins' => $targetExtra ? $targetExtra['wins'] : 0,
                'total_games' => $targetExtra ? $targetExtra['total_games'] : 0,
                'win_ratio' => $targetExtra && $targetExtra['total_games'] > 0 ? round(($targetExtra['wins'] / $targetExtra['total_games']) * 100) : 0,
                'is_online' => false, // TODO: پیاده‌سازی منطق آنلاین بودن
                'is_friend' => $friendshipStatus['is_friend'],
                'has_pending_request' => !is_null($friendshipStatus['pending_request']),
                'pending_request_direction' => (!is_null($friendshipStatus['pending_request']) && $friendshipStatus['pending_request']['sender_id'] == $user['id']) ? 'sent' : 'received'
            ];
            
            // افزودن اطلاعات پروفایل
            if ($targetProfile) {
                $result['profile'] = [
                    'name' => $targetProfile['name'],
                    'gender' => $targetProfile['gender'],
                    'age' => $targetProfile['age'],
                    'province' => $targetProfile['province'],
                    'city' => $targetProfile['city'],
                    'bio' => $targetProfile['bio_approved'] ? $targetProfile['bio'] : null,
                    'photo' => $targetProfile['photo_approved'] ? $targetProfile['photo'] : null
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log("Error in viewUserProfile: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در مشاهده پروفایل کاربر: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * جستجوی کاربر
     * @param string $query عبارت جستجو
     * @param int $page شماره صفحه
     * @param int $limit تعداد در هر صفحه
     * @return array
     */
    public function searchUsers($query, $page = 1, $limit = 10)
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
            
            // پاکسازی عبارت جستجو
            $query = trim($query);
            
            // بررسی طول عبارت جستجو
            if (mb_strlen($query, 'UTF-8') < 3) {
                return [
                    'success' => false,
                    'message' => 'عبارت جستجو باید حداقل 3 کاراکتر باشد.'
                ];
            }
            
            // محاسبه آفست
            $offset = ($page - 1) * $limit;
            
            // ساخت شرط جستجو
            $search_condition = "
                u.id != ? AND (
                    u.username LIKE ? OR
                    u.first_name LIKE ? OR
                    u.last_name LIKE ? OR
                    CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR
                    up.name LIKE ?
                )
            ";
            
            $search_params = [
                $user['id'],
                "%{$query}%",
                "%{$query}%",
                "%{$query}%",
                "%{$query}%",
                "%{$query}%"
            ];
            
            // دریافت تعداد کل نتایج
            $total = DB::query(
                "SELECT COUNT(*) as count
                FROM users u
                LEFT JOIN user_profiles up ON u.id = up.user_id
                WHERE {$search_condition}",
                $search_params
            )->fetch();
            
            // دریافت کاربران
            $users = DB::query(
                "SELECT u.*, ue.trophies, ue.wins, ue.total_games,
                    CASE
                        WHEN f.id IS NOT NULL THEN TRUE
                        ELSE FALSE
                    END as is_friend,
                    CASE
                        WHEN fr.id IS NOT NULL AND fr.status = 'pending' THEN
                            CASE
                                WHEN fr.sender_id = ? THEN 'sent'
                                ELSE 'received'
                            END
                        ELSE NULL
                    END as pending_request_direction
                FROM users u
                LEFT JOIN users_extra ue ON u.id = ue.user_id
                LEFT JOIN user_profiles up ON u.id = up.user_id
                LEFT JOIN (
                    SELECT f.id, f.user_id_1, f.user_id_2
                    FROM friendships f
                    WHERE f.user_id_1 = ? OR f.user_id_2 = ?
                ) f ON (f.user_id_1 = u.id AND f.user_id_2 = ?) OR (f.user_id_1 = ? AND f.user_id_2 = u.id)
                LEFT JOIN (
                    SELECT fr.id, fr.sender_id, fr.receiver_id, fr.status
                    FROM friend_requests fr
                    WHERE (fr.sender_id = ? AND fr.status = 'pending') OR (fr.receiver_id = ? AND fr.status = 'pending')
                ) fr ON (fr.sender_id = u.id AND fr.receiver_id = ?) OR (fr.sender_id = ? AND fr.receiver_id = u.id)
                WHERE {$search_condition}
                ORDER BY is_friend DESC, u.username, u.first_name
                LIMIT ? OFFSET ?",
                array_merge(
                    [$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id']],
                    $search_params,
                    [$limit, $offset]
                )
            )->fetchAll();
            
            // محاسبه تعداد صفحات
            $total_pages = ceil($total['count'] / $limit);
            
            return [
                'success' => true,
                'users' => $users,
                'total' => $total['count'],
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $total_pages,
                'query' => $query
            ];
        } catch (\Exception $e) {
            error_log("Error in searchUsers: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در جستجوی کاربران: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ارسال اعلان درخواست دوستی
     * @param array $sender فرستنده
     * @param array $receiver گیرنده
     * @param int $request_id شناسه درخواست
     * @return void
     */
    private function sendFriendRequestNotification($sender, $receiver, $request_id)
    {
        try {
            // متن پیام
            $message = "👋 *درخواست دوستی جدید*\n\n";
            $message .= "کاربر " . ($sender['username'] ? '@' . $sender['username'] : $sender['first_name'] . ' ' . $sender['last_name']) . " برای شما درخواست دوستی ارسال کرده است.\n\n";
            $message .= "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
            
            // ساخت دکمه‌ها
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ قبول', 'callback_data' => "accept_friend_{$request_id}"],
                        ['text' => '❌ رد', 'callback_data' => "reject_friend_{$request_id}"]
                    ],
                    [
                        ['text' => '👤 مشاهده پروفایل', 'callback_data' => "view_profile_{$sender['id']}"]
                    ]
                ]
            ]);
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($GLOBALS['token'], $receiver['telegram_id'], $message, 'Markdown', $reply_markup);
            }
        } catch (\Exception $e) {
            error_log("Error in sendFriendRequestNotification: " . $e->getMessage());
        }
    }
    
    /**
     * ارسال اعلان پذیرش دوستی
     * @param array $user کاربر
     * @param array $friend دوست
     * @return void
     */
    private function sendFriendAcceptedNotification($user, $friend)
    {
        try {
            // متن پیام
            $message = "✅ *درخواست دوستی پذیرفته شد*\n\n";
            $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " درخواست دوستی شما را پذیرفت.\n\n";
            $message .= "اکنون می‌توانید با این کاربر بازی کنید.";
            
            // ساخت دکمه‌ها
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '🎮 شروع بازی', 'callback_data' => "start_game_{$user['id']}"]
                    ],
                    [
                        ['text' => '👤 مشاهده پروفایل', 'callback_data' => "view_profile_{$user['id']}"]
                    ]
                ]
            ]);
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($GLOBALS['token'], $friend['telegram_id'], $message, 'Markdown', $reply_markup);
            }
        } catch (\Exception $e) {
            error_log("Error in sendFriendAcceptedNotification: " . $e->getMessage());
        }
    }
    
    /**
     * ارسال اعلان رد دوستی
     * @param array $user کاربر
     * @param array $friend دوست
     * @return void
     */
    private function sendFriendRejectedNotification($user, $friend)
    {
        try {
            // متن پیام
            $message = "❌ *درخواست دوستی رد شد*\n\n";
            $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " درخواست دوستی شما را رد کرد.";
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($GLOBALS['token'], $friend['telegram_id'], $message, 'Markdown');
            }
        } catch (\Exception $e) {
            error_log("Error in sendFriendRejectedNotification: " . $e->getMessage());
        }
    }
    
    /**
     * ارسال اعلان حذف دوستی
     * @param array $user کاربر
     * @param array $friend دوست
     * @return void
     */
    private function sendFriendRemovedNotification($user, $friend)
    {
        try {
            // متن پیام
            $message = "🔴 *دوستی حذف شد*\n\n";
            $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " شما را از لیست دوستان خود حذف کرد.";
            
            // ارسال پیام
            if (function_exists('sendMessage')) {
                sendMessage($GLOBALS['token'], $friend['telegram_id'], $message, 'Markdown');
            }
        } catch (\Exception $e) {
            error_log("Error in sendFriendRemovedNotification: " . $e->getMessage());
        }
    }
}