<?php

use application\controllers\HelperController as Helper;
use application\controllers\TelegramClass as Telegram;
use application\controllers\LocaleController as Locale;
use application\controllers\KeyboardController as Keyboard;
use application\controllers\StepController as Step;
use application\controllers\UserController as User;
use application\controllers\OptionController as Option;
use Application\Model\DB;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/vendor/autoload.php');
$dotenv = \Dotenv\Dotenv::createImmutable((__DIR__));
$dotenv->safeLoad();
include(__DIR__ . "/system/Loader.php");
date_default_timezone_set('Asia/Tehran');

$content = file_get_contents("php://input");
$update = json_decode($content, false);
$telegram = new Telegram($update);
$locale = new Locale();
$keyboard = new Keyboard();
$option = new Option();
$helper = new Helper();
$first_name = $telegram->get()->message->from->first_name;

if (isset($telegram->get()->message->text) and str_starts_with($telegram->get()->message->text, "/start")) {
    $exploded_text = explode(" " , $telegram->get()->message->text);
    $exploded_text = explode("_" , $exploded_text[1]);
    if ($exploded_text[0] === "re"){
        $ref_user_id = DB::table('users')->where('username' , $exploded_text[1])->select('id')->first()['id'];
        $user = new User($update , $ref_user_id);
        if (!$ref_user_id){
            $telegram->sendMessage('%message.wrong_ref_link%')->send();
            exit();
        }
        if ($user->is_ref == 0){
            $telegram->sendMessage('%message.warning_joined_before%')->send();
            exit();
        }
        
        elseif ($user->is_ref == 1) {
            $ref_telegram_id = DB::table('users')->where('username' , $exploded_text[1])->select('telegram_id')->first()['telegram_id'];
            DB::rawQuery("UPDATE users_extra SET doz_coin = doz_coin + 0.5 WHERE user_id = $ref_user_id;");
            $doz_coin = DB::rawQuery("SELECT doz_coin FROM users_extra WHERE user_id = ( SELECT id FROM users WHERE telegram_id = $ref_telegram_id);")[0]['doz_coin'];

            $telegram->sendMessage("%message.ref_joined[doz_coin:$doz_coin]%")->send($ref_telegram_id);
        }

    }
}

$user = new User($update);
$step = new Step($update);

if ($option->forced_to_join and !(isset($telegram->get()->callback_query->data) and str_starts_with($telegram->get()->callback_query->data , "p1"))){
    $should_to_join = [];
    foreach ($option->channels as $channel){
        $status = $telegram->getChatMember($channel);
        if ($status == 'left' or $status == 'kicked'){
            $channel = $telegram->getChat($channel);
            if ($channel['ok']){
                $should_to_join[] = [['text' => $channel['result']['title'], 'url' => $channel['result']['invite_link']]];
            }
        }
    }
    if ($should_to_join){
        $should_to_join[] = [['text' => $locale->trans('message.i_join') , 'callback_data' => 'i_join']];
        if (isset($telegram->get()->callback_query) and $telegram->get()->callback_query->data == 'i_join'){
            $telegram->deleteMessage();
            $telegram->sendMessage('%message.force_rejoin%')->inline_keyboard($should_to_join)->send();
        }else{
            $telegram->sendMessage('%message.force_join%')->inline_keyboard($should_to_join)->send();
        }
        exit();
    }
}
//check if user set username
if ($step->get() == 'set_username') {
    if (preg_match('/^[a-zA-Z][a-zA-Z0-9]{4,11}$/', $telegram->text)){
        $is_username_exist = DB::table('users')->where('username', $telegram->text)->select('id')->first();
        if ($is_username_exist){
            $telegram->sendMessage("%message.username_exits%")->send();
            exit();
        }
        DB::table('users')->where('telegram_id' , $telegram->from_id)->update(['username' => $telegram->text]);
        $step->clear();
        $telegram->sendMessage("%message.username_ok[username:$telegram->text]%")->keyboard('main.home')->send();
        exit();
    }else{
        $telegram->sendMessage('%message.bad_username%')->send();
        exit();
    }
    exit();
}
if ($user->userData()['username'] == NULL){
    $telegram->sendMessage("%message.set_username[firstname:$first_name]%")->send();
    $step->set('set_username');
    exit();
}

if ($user->isAdmin()){
    include('handler/handle_admin.php');
}

if (isset($update->message)) {
    include('handler/handle_message.php');
}
elseif (isset($update->inline_query)) {
    include('handler/handle_inline_query.php');
}
elseif (isset($update->callback_query)) {
    include('handler/handle_callback_query.php');
}
