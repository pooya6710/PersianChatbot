<?php

namespace application\controllers;


use application\controllers\KeyboardController;
use application\controllers\LocaleController;


class TelegramClass extends HelperController
{
    private static $api_url;
    public $update;
    private $method = "";
    public $parameters = [];
    public $message_id;
    public $from_id;
    public $chat_id;
    public $text;
    public function __construct($update)
    {
        self::$api_url = 'https://api.telegram.org/bot' . ($_ENV['TELEGRAM_TOKEN'] ?? '') . "/";
        $this->update = $update;
        if ($this->getUserTelegramID($update))
            $this->from_id = $this->getUserTelegramID($update);
        // else will be here
        if (isset($update->message)) {
            $this->message_id = $update->message->message_id ?? null;
            $this->chat_id = $update->message->chat->id ?? null;
            $this->text = $update->message->text ?? null;
        }
        elseif (isset($update->callback_query)) {
            $this->message_id = $update->callback_query->message->message_id ?? null;
            // اگر chat->id در callback_query->message وجود داشته باشد، آن را تنظیم کنید
            if (isset($update->callback_query->message) && isset($update->callback_query->message->chat)) {
                $this->chat_id = $update->callback_query->message->chat->id;
            }
        }
    }

    public static function api($method , $parameters): bool|string
    {
        if (!$parameters) {
            $parameters = array();
        }
        $parameters["method"] = $method;
        $handle = curl_init(self::$api_url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($handle, CURLOPT_TIMEOUT, 60);
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
        curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        return curl_exec($handle);
    }

    public function sendMessage($text  , $link_preview_options = true): static
    {
        $this->method = 'sendMessage';
        if (str_starts_with($text, '%') && str_ends_with($text, '%')) {
        $locale = new LocaleController();
            if (str_contains($text, '[') && str_contains($text, ']')) {
                preg_match('/\[(.*?)\]/', $text, $matches);
                if (isset($matches[1])) {
                    $textInsideBrackets = $matches[1];
                    $result = [];
                    $pairs = explode(',', $textInsideBrackets);
                    foreach ($pairs as $pair) {
                        list($key, $value) = explode(':', $pair);
                        $result[$key] = $value;
                    }
                    $text = str_replace('%', '', $text);
                    $text = str_replace($matches[0], '', $text);
                    $text = $locale->trans($text , $result);
                }
            } else {
                $text = str_replace('%', '', $text);
                $text = $locale->trans($text);
            }
        }
        $this->parameters =
            [
                'text' => $text ,
                'link_preview_options' => ['is_disabled' => $link_preview_options]
            ];
        return $this;
    }

    public function sendPhoto($photo , $caption = ""): static
    {
        $this->method = 'sendMessage';
        $this->parameters =
            [
                'photo' => $photo ,
                'caption' => $caption
            ];
        return $this;

    }

    public function spoiler($spoiler = true): static
    {
        $this->parameters =
            [
                'has_spoiler' => $spoiler
            ];
        return $this;

    }

    public function parse_mode($parse_mode): static
    {
        $this->parameters = [
            'parse_mode' => $parse_mode,
        ];
        return $this;
    }

    public function protect_content($protect_content = true): static
    {
        $this->parameters = [
            'protect_content' => $protect_content,
        ];
        return $this;
    }

    public function disable_notification($disable_notification = true): static
    {
        $this->parameters = [
            'disable_notification' => $disable_notification
        ];
        return $this;
    }

    public function keyboard($keyboard , $resize_keyboard = true , $input_field_placeholder = "" , $is_persistent = false , $one_time_keyboard = false): static
    {
        if (is_string($keyboard)) {

            $ob_keyboard = new KeyboardController();

            if (str_contains($keyboard, '[') && str_contains($keyboard, ']')) {

                preg_match('/\[(.*?)\]/', $keyboard, $matches);
                if (isset($matches[1])) {
                    $textInsideBrackets = $matches[1];
                    $result = [];
                    $pairs = explode(',', $textInsideBrackets);
                    foreach ($pairs as $pair) {
                        list($key, $value) = explode(':', $pair);
                        $result[$key] = $value;
                    }
                    $keyboard = str_replace($matches[0], '', $keyboard);
                    $keyboard = $ob_keyboard->get($keyboard , $result);
                }
            } else {
                $keyboard = $ob_keyboard->get($keyboard);
            }
        }
        $this->parameters['reply_markup'] = [
            'keyboard' => $keyboard,
            'resize_keyboard' => $resize_keyboard,
            'input_field_placeholder' => $input_field_placeholder,
            'is_persistent' => $is_persistent,
            'one_time_keyboard' => $one_time_keyboard
        ];
        return $this;

    }

    public function inline_keyboard($keyboard)
    {
        $this->parameters['reply_markup'] = [
            'inline_keyboard' => $keyboard
        ];
        return $this;
    }

    public function replay($message_id = ""): static
    {
        if ($message_id == ""){
            $message_id = $this->message_id;
        }
        elseif(str_starts_with($message_id , '-')){
            $message_id = intval($this->message_id) - str_replace("-", " ", $message_id); ;
        }
        elseif(str_starts_with($message_id , '+')){
            $message_id = intval($this->message_id) + str_replace("+", " ", $message_id); ;
        }
        $this->parameters['reply_to_message_id'] = $message_id;
        return $this;
    }

    public function deleteMessage($message_id = null, $chat_id = null )
    {
        $this->method = 'deleteMessage';

        if (isset($message_id)) {
            $this->parameters['message_id'] = $message_id;
        }else{
            $this->parameters['message_id'] = $this->message_id;
        }
        if (isset($chat_id)) {
            $this->parameters['chat_id'] = $chat_id;
        }
        else{
            $this->parameters['chat_id'] = $this->from_id;
        }
        $this::api($this->method, $this->parameters);
        return true;

    }

    public function get()
    {
        return $this->update;
    }
    
    public function send($chat_id = ""): true
    {
        if ($this->method == 'sendMessage') {
            if ($chat_id == ""){
                $this->parameters['chat_id'] = $this->from_id;
            }else{
                $this->parameters['chat_id'] = $chat_id;
            }
        }

        self::api($this->method, $this->parameters);
        $this->method = "";
        $this->parameters = [] ;
        return true;
    }

    public function getChatMember($channel_id, $user_id = "")
    {
        $this->method = 'getChatMember';
        $this->parameters['chat_id'] = $channel_id;
        if ($user_id == ""){
            $this->parameters['user_id'] = $this->from_id;
        }else{
            $this->parameters['user_id'] = $user_id;
        }
        $is_join = self::api($this->method, $this->parameters);
        $this->method = "";
        $this->parameters = [];
        $is_join = json_decode($is_join, true);
        if ($is_join['ok']) {
            return $is_join['result']['status'];
        }else{
            return false;
        }
    }

    public function getChat($chat_id)
    {
        $this->method = 'getChat';
        $this->parameters['chat_id'] = $chat_id;
        $res = json_decode(self::api($this->method, $this->parameters) , 1);
        $this->method = "";
        $this->parameters = [];
        return $res;

    }

    public function answerInlineQuery($cache_time = 0)
    {
        $this->method = 'answerInlineQuery';
        if (isset($this->get()->inline_query->id)) {
            $this->parameters['inline_query_id'] = $this->get()->inline_query->id;
            $this->parameters['cache_time'] = $cache_time;
            return $this;
        }
        return $this;
    }

    public function article($id , $title , $input_message_content , $replay_markup = null)
    {
        if (isset($replay_markup)){
            $this->parameters['results'] = array(array("type" => "article", "id" => "$id", 'title' => $title, 'input_message_content' => $input_message_content ,
                "reply_markup" => $replay_markup
            ));
        }
        else{
            $this->parameters['results'] = array(array("type" => "article", "id" => "$id", 'title' => $title, 'input_message_content' => $input_message_content
            ));
        }

        $x = self::api($this->method, $this->parameters);
        $this->method = "";
        $this->parameters = [] ;
        return $x;
    }

    public function answerCallbackQuery($text , $show_alert = false , $url = "" , $cache_time = 0)
    {
        $this->method = 'answerCallbackQuery';
        if (isset($this->get()->callback_query->id)) {
            $this->parameters['callback_query_id'] = $this->get()->callback_query->id;
            if (str_starts_with($text, '%') && str_ends_with($text, '%')) {
                $locale = new LocaleController();
                if (str_contains($text, '[') && str_contains($text, ']')) {
                    preg_match('/\[(.*?)\]/', $text, $matches);
                    if (isset($matches[1])) {
                        $textInsideBrackets = $matches[1];
                        $result = [];
                        $pairs = explode(',', $textInsideBrackets);
                        foreach ($pairs as $pair) {
                            list($key, $value) = explode(':', $pair);
                            $result[$key] = $value;
                        }
                        $text = str_replace('%', '', $text);
                        $text = str_replace($matches[0], '', $text);
                        $text = $locale->trans($text , $result);
                    }
                } else {
                    $text = str_replace('%', '', $text);
                    $text = $locale->trans($text);
                }
            }
            $this->parameters['text'] = $text;
            $this->parameters['show_alert'] = $show_alert;
            $this->parameters['url'] = $url;
            $this->parameters['cache_time'] = $cache_time;
            $x = self::api($this->method, $this->parameters);
            $this->method = "";
            $this->parameters = [] ;
            return $x;
        }
        return false;
    }

    public function editMessage($inline_message_id , $text)
    {
        $this->method = 'editMessageText';
        $this->parameters['inline_message_id'] = $inline_message_id;
        $this->parameters['text'] = $text;
        return $this;
    }
    
    // متد کمکی برای تشخیص متن کلید با عبارات کلیدی
    public function matchesKeyword($text, $keywords) 
    {
        if (empty($text)) return false;
        
        $text = trim($text);
        
        // اگر آرایه کلیدواژه‌ها نباشد، به آرایه تبدیل می‌کنیم
        if (!is_array($keywords)) {
            $keywords = [$keywords];
        }
        
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            // مقایسه دقیق
            if ($text === $keyword) return true;
            
            // بررسی شامل بودن با پشتیبانی از متن فارسی
            if (mb_stripos($text, $keyword, 0, 'UTF-8') !== false) return true;
        }
        
        return false;
    }
}