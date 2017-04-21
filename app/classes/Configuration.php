<?php

class Configuration
{
    /** @var array Configuration cache */
    protected static $_cache = array();

    /**
     * @see ObjectModel::$definition
     */
    protected static $definition = array(
        'table' => 'configuration',
        'primary' => 'configuration_id',
    );

    /**
     * Load all configuration data in cache
     */
    public static function loadConfiguration()
    {
        $sql = 'SELECT c.`key`, c.`value`
                FROM `'.bqSQL(self::$definition['table']).'` c';
        $db = Db::getInstance();
        $result = $db->executeS($sql, false);
        while ($row = $db->nextRow($result))
            self::$_cache[$row['key']] = $row['value'];
    }

    /**
     * Get a single configuration value (in one language only)
     *
     * @param string $key Key wanted
     * @param int $id_lang Language ID
     * @return string Value
     */
    public static function get($key)
    {
        // If conf if not initialized, try manual query
        if (!self::$_cache) {
            Configuration::loadConfiguration();
            if (!self::$_cache) {
                return Db::getInstance()->getValue('SELECT `value` FROM `'.bqSQL(self::$definition['table']).'` WHERE `key` = "'.pSQL($key).'"');
            }
        }

        if (Configuration::hasKey($key))
            return self::$_cache[$key];

        return false;
    }

    /**
     * Return ID a configuration key
     *
     * @param string $key
     * @param int $id_shop_group
     * @param int $id_shop
     * @return int Configuration key ID
     */
    public static function getIdByName($key)
    {
        $sql = 'SELECT `'.bqSQL(self::$definition['primary']).'`
                FROM `'._DB_PREFIX_.bqSQL(self::$definition['table']).'`
                WHERE key = \''.pSQL($key).'\'';
        return (int)Db::getInstance()->getValue($sql);
    }

    /**
     * Set TEMPORARY a single configuration value
     *
     * @param string $key Key wanted
     * @param string $value.
     */
    public static function set($key, $value)
    {
        self::$_cache[$key] = $value;
    }

    /**
     * Check if key exists in configuration cache
     *
     * @param string $key
     * @param int $id_lang
     * @param int $id_shop_group
     * @param int $id_shop
     * @return bool
     */
    public static function hasKey($key)
    {
        if (!is_int($key) && !is_string($key)) {
            return false;
        }

        return isset(self::$_cache[$key]);
    }

    /**
     * Update configuration key and value into database (automatically insert if key does not exist)
     *
     * @param string $key Key
     * @param mixed $value $value a single string.
     * @param bool $html Specify if html is authorized in value
     */
    public static function updateValue($key, $value, $html = false)
    {
        if ($html)
            $value = Tools::purifyHTML($value);

        $result = true;

        $stored_value = Configuration::get($key);
        // if there isn't a $stored_value, we must insert $value
        if ((!is_numeric($value) && $value === $stored_value) || (is_numeric($value) && $value == $stored_value && Configuration::hasKey($key))) {
            return true;
        }

        // If key already exists, update value
        if (Configuration::hasKey($key)) {
            // Update config not linked to lang
            $result &= Db::getInstance()->update(self::$definition['table'], array(
                'value' => pSQL($value, $html),
            ), '`key` = \''.pSQL($key).'\'');
        }
        // If key does not exists, create it
        else {
            if (!$configID = Configuration::getIdByName($key)) {
                $now = date('Y-m-d H:i:s');
                $data = array(
                    'key'          => pSQL($key),
                    'value'         => pSQL($value, $html)
                );
                $result &= Db::getInstance()->insert(self::$definition['table'], $data, true);
                $configID = Db::getInstance()->Insert_ID();
            }
        }

        Configuration::set($key, $value);

        return $result;
    }
}