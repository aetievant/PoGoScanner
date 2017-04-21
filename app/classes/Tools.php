<?php

class Tools
{
    const SERVER_MEMORY_LIMIT = '256M';
	const SERVER_MAXIMUM_EXECUTION_TIME = 120; // 2 minutes

    public static function strtoupper($str)
    {
        if (is_array($str)) {
            return false;
        }
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($str, 'utf-8');
        }
        return strtoupper($str);
    }

    public static function strtolower($str)
    {
        if (is_array($str)) {
            return false;
        }
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($str, 'utf-8');
        }
        return strtolower($str);
    }

    /**
     *
     * @return bool true if php-cli is used
     */
    public static function isPHPCLI()
    {
        return (defined('STDIN') || (Tools::strtolower(php_sapi_name()) == 'cli' && (!isset($_SERVER['REMOTE_ADDR']) || empty($_SERVER['REMOTE_ADDR']))));
    }

    public static function expandServerLimitations() {
        if (!$timeLimit = Configuration::get('maximum_execution_time'))
            $timeLimit = self::SERVER_MAXIMUM_EXECUTION_TIME;


        if (!$memoryLimit = Configuration::get('memory_limit'))
            $memoryLimit = self::SERVER_MEMORY_LIMIT;

        set_time_limit($timeLimit);
		ini_set('memory_limit', $memoryLimit);
    }

    public static function argvToGET($argc, $argv)
    {
        if ($argc <= 1) {
            return;
        }

        // get the first argument and parse it like a query string
        parse_str($argv[1], $args);
        if (!is_array($args) || !count($args)) {
            return;
        }
        $_GET = array_merge($args, $_GET);
        $_SERVER['QUERY_STRING'] = $argv[1];
    }

   /**
    * Sanitize a string
    *
    * @param string $string String to sanitize
    * @param bool $full String contains HTML or not (optional)
    * @return string Sanitized string
    */
    public static function safeOutput($string, $html = false)
    {
        if (!$html) {
            $string = strip_tags($string);
        }
        return @Tools::htmlentitiesUTF8($string, ENT_QUOTES);
    }

    public static function htmlentitiesUTF8($string, $type = ENT_QUOTES)
    {
        if (is_array($string)) {
            return array_map(array('Tools', 'htmlentitiesUTF8'), $string);
        }

        return htmlentities((string)$string, $type, 'utf-8');
    }

    /**
    * Get a value from $_POST / $_GET
    * if unavailable, take a default value
    *
    * @param string $key Value key
    * @param mixed $default_value (optional)
    * @return mixed Value
    */
    public static function getValue($key, $default_value = false)
    {
        if (!isset($key) || empty($key) || !is_string($key)) {
            return false;
        }

        $ret = (isset($_POST[$key]) ? $_POST[$key] : (isset($_GET[$key]) ? $_GET[$key] : $default_value));

        if (is_string($ret)) {
            return stripslashes(urldecode(preg_replace('/((\%5C0+)|(\%00+))/i', '', urlencode($ret))));
        }

        return $ret;
    }

    /**
     * Parse a HTTP request
     *
     * @param type $urlRequest
     * @return array
     */
    public static function parseUrlRequest($urlRequest) {
        return parse_str($urlRequest);
    }

    public static function getMySQLDatetime($timestamp = null, $fromMsTimestamp = true) {
        if ($timestamp && $fromMsTimestamp)
            $timestamp /= 1000;

        return date('Y-m-d H:i:s', $timestamp ? $timestamp : time());
    }

    public static function getTimestamp($inMilliseconds = true) {
        if (!$inMilliseconds)
            return time();

        $mt = explode(' ', microtime());
        return ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000));
    }

    /**
     * Convert \n and \r\n and \r to <br />
     *
     * @param string $string String to transform
     * @return string New string
     */
    public static function nl2br($str)
    {
        return str_replace(array("\r\n", "\r", "\n"), '<br />', $str);
    }

    /**
     * Get IV rate from IV notes.
     *
     * @param int $ivAttack
     * @param int $ivDefense
     * @param int $ivStamina
     * @return float
     */
    public static function calculateIvRate($ivAttack, $ivDefense, $ivStamina) {
        return round(($ivAttack + $ivDefense + $ivStamina) / .45, 1);
    }

    /**
    * Display a readable date regarding to language we speak
    *
    * @param string $date Date to display format UNIX
    * @return string Date
    */
    public static function getHumanReadableDate($date)
    {
        if (!$date || !($time = strtotime($date))) {
            return $date;
        }

        if ($date == '0000-00-00 00:00:00' || $date == '0000-00-00') {
            return '';
        }

        $date_format = Configuration::get('human_readable_date_format');
        return date($date_format, $time);
    }

    /**
     *
     * @return bool
     */
    public static function isCurrentlyRunning() {
        return (bool) Configuration::get('is_currently_running');
    }

    /**
     *
     * @param bool $isRunning
     * @return bool
     */
    public static function setIsRunning($isRunning) {
        return Configuration::updateValue('is_currently_running', (int) $isRunning);
    }
}
