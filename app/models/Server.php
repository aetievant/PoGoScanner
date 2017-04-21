<?php

class Server extends ObjectModel
{
    const CACHE_KEY = 'Server::available_servers';

    /** @var string */
    public $server_address;

    /** @var boolean */
    public $is_secure;

    /** @var boolean */
    public $is_online;

    /** @var string */
    public $last_updated;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'server',
        'primary' => 'server_id',
        'fields' => array(
            'server_address'    => array('type' => self::TYPE_STRING),
            'is_secure'         => array('type' => self::TYPE_BOOL),
            'is_online'         => array('type' => self::TYPE_BOOL),
            'last_updated'      => array('type' => self::TYPE_DATE),
        ),
    );

    /**
     * @todo
     */
    protected static function checkAvailabilities() {

    }

    /**
     *
     * @return Collection
     */
    public static function getAvailableServers($refresh = false) {
        if ($refresh || !Cache::isStored(self::CACHE_KEY)) {
            self::checkAvailabilities();

            $servers = new Collection('Server');
            $servers->where('is_online', '=', '1');
            Cache::store(self::CACHE_KEY, $servers->getAll());
        }

        return Cache::retrieve(self::CACHE_KEY);
    }

    /**
     *
     * @return Server|boolean
     */
    public static function getFirstAvailable() {
        $servers = self::getAvailableServers();

        return $servers->count() ? $servers->getFirst() : false;
    }

    public function getRequestUri() {
        $protocol = $this->is_secure ? 'https://' : 'http://';
        $serverAddress = $this->server_address;

        return $protocol . $serverAddress . '/raw_data';
    }

}
