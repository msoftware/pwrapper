<?php

class PW_Logger extends PW_Module
{
    static $file = false;

    static function init()
    {
        self::$file = WP_CONTENT_DIR ."/pwrapper.log";
    }

    static function log($message, $type='timed')
    {
        if (!is_array($message))
            $message = [$message];

        $logging = array();
        switch ($type)
        {
            case 'header':
                $logging[] = "###############################";
                $logging[] = "### ". date('Y-m-d H:i:s');
                foreach ($message as $msg)
                    $logging[] = "### $msg";
                $logging[] = "###############################";
                break;
            case 'timed':
                $logging[] = "[". date('Y-m-d H:i:s') ."] ". $message[0];
                foreach (array_slice($message, 1) as $msg)
                    $logging[] = "\t\t$msg";
                break;
            default:
                foreach ($message as $msg)
                    $logging[] = "$msg";
                break;
        }

        file_put_contents(
            self::$file,
            implode("\n", $logging) ."\n",
            FILE_APPEND
        );        
    }

    static function print_r($var)
    {
        echo '<pre>', print_r($var, true), '</pre>';
    }
}