<?php

namespace application\controllers;
class LocaleController{
    public $lang;
    // Prevent infinite recursion
    private $recursion_depth = 0;
    private $max_recursion = 3;
    
    public function __construct($lang = ""){
        if ($lang == "") {
            $this->lang = $_ENV['DEFAULT_LANG'] ?? 'fa';
        }
        else{
            $this->lang = $lang;
        }
    }

    public function trans($path , $vars = [] , $lang = "")
    {
        // Prevent infinite recursion that causes memory exhaustion
        $this->recursion_depth++;
        if ($this->recursion_depth > $this->max_recursion) {
            $this->recursion_depth = 0;
            return "Error: Maximum translation recursion depth exceeded for path: $path";
        }
        
        $base_url = dirname(__DIR__, 2);
        $path = explode('.', $path);
        $index_name = end($path);
        array_pop($path);
        $folder = implode('/', $path);
        $path = $base_url . "/locale/" . $this->lang. "/" . $folder . ".php";
        
        if (file_exists($path)){
            $values = require($path);
            if (!isset($values[$index_name])){
                $result = "Translation key not found: $index_name";
                $this->recursion_depth--;
                return $result;
            }
            if ($vars){
                $values[$index_name] = json_encode($values[$index_name]);
                foreach ($vars as $key => $var) {
                    $values[$index_name] = str_replace('%' . $key . '%', $var, $values[$index_name]);
                }
                $values[$index_name] = json_decode($values[$index_name]);
            }
            $this->recursion_depth--;
            return $values[$index_name];
        }
        else{
            $result = "Translation file not found: $folder";
            $this->recursion_depth--;
            return $result;
        }
    }
    
    public function getLocale()
    {
        return $this->lang;
    }

}