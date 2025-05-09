<?php
namespace application\controllers;

use application\controllers\TelegramClass;
class HelperController
{
    public function getUserTelegramID($update)
    {
        if (isset($update->message)) {
            return $update->message->from->id;
        }
        elseif (isset($update->inline_query)) {
            return $update->inline_query->from->id;
        }
        elseif (isset($update->callback_query)) {
            return $update->callback_query->from->id;
        }else
            return false;
    }

    public function Hash($length = 128)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        // Loop to generate a random string based on the defined length
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }
}