<?php
namespace application\helpers;

/**
 * کلاس کمکی برای ارسال پیام‌های تلگرام
 */
class TelegramHelper
{
    /**
     * ارسال پیام به کاربر از طریق API تلگرام
     * 
     * @param int $chat_id شناسه کاربر
     * @param string $message متن پیام
     * @param string $parse_mode حالت پارس متن (HTML یا Markdown)
     * @param array|null $reply_markup دکمه‌های اضافی 
     * @return bool|array نتیجه ارسال پیام
     */
    public static function sendMessage($chat_id, $message, $parse_mode = 'Markdown', $reply_markup = null)
    {
        try {
            $token = $_ENV['TELEGRAM_TOKEN'];
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            
            $params = [
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => $parse_mode
            ];
            
            if ($reply_markup) {
                $params['reply_markup'] = $reply_markup;
            }
            
            // ارسال درخواست
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            
            if (curl_errno($ch)) {
                curl_close($ch);
                return false;
            }
            
            curl_close($ch);
            return json_decode($response, true);
        } catch (\Exception $e) {
            error_log("Error in TelegramHelper::sendMessage: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ساخت لینک رفرال
     * 
     * @param int $user_id شناسه کاربر
     * @return string لینک رفرال
     */
    public static function generateReferralLink($user_id)
    {
        $token = $_ENV['TELEGRAM_TOKEN'];
        $url = "https://api.telegram.org/bot{$token}/getMe";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        
        $botUsername = 'your_bot'; // مقدار پیش‌فرض
        
        if (!curl_errno($ch)) {
            $result = json_decode($response, true);
            if ($result['ok'] && isset($result['result']['username'])) {
                $botUsername = $result['result']['username'];
            }
        }
        
        curl_close($ch);
        
        // ساخت لینک با پیشوند ref_ برای سازگاری با سیستم رفرال
        return "https://t.me/{$botUsername}?start=ref_{$user_id}";
    }
    
    /**
     * دریافت اطلاعات ربات از API تلگرام
     * 
     * @return array اطلاعات ربات
     */
    public static function getBotInfo()
    {
        $token = $_ENV['TELEGRAM_TOKEN'];
        $url = "https://api.telegram.org/bot{$token}/getMe";
        
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
        
        if (isset($result['ok']) && $result['ok'] && isset($result['result'])) {
            return $result['result'];
        }
        
        return ['username' => 'your_bot']; // مقدار پیش‌فرض در صورت خطا
    }
}
