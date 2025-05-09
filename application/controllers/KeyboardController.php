<?php
namespace application\controllers;

use application\controllers\LocaleController;

class KeyboardController extends LocaleController
{
    public function get($main ,$vars = null)
    {
        $base_url = dirname(__DIR__ , 2) . "/keyboard/";
        $path = explode("." , $main);
        $index_name = end($path);
        array_pop($path);
        $folder = implode('/', $path);
        $path = $base_url . $folder . ".php";
        if (file_exists($path)){
            $values = require($path);
            if (!isset($values[$index_name])) {
                return $this->trans('error.default.text_not_found');
            }
            if ($vars != null) {
                array_walk_recursive($values[$index_name], function (&$value) use ($vars) {
                    foreach ($vars as $key => $replacement) {
                        $placeholder = '%' . $key . '%';
                        if (strpos($value, $placeholder) !== false) {
                            $value = str_replace($placeholder, $replacement, $value);
                        }
                    }
                });
            }
            return $values[$index_name];
        }else{
            return $this->trans('error.default.file_not_found');
        }
    }
}