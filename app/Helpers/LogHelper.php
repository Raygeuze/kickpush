<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class LogHelper
{
    public static function info($message, $context = [])
    {
        Log::info($message, $context);
    }

    public static function warning($message, $context = [])
    {
        Log::warning($message, $context);
    }

    public static function error($message, $context = [])
    {
        Log::error($message, $context);
    }

    public static function debug($message, $context = [])
    {
        Log::debug($message, $context);
    }
}
