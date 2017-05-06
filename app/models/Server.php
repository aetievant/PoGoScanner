<?php

class Server extends ObjectModel
{
    const CACHE_KEY = 'Server::available_servers';
    const SESSION_LIFETIME = '3600'; // 1 hour

    /** @var string */
    public $server_address;

    /** @var boolean */
    public $is_secure;

    /** @var string */
    public $scan_request_uri;

    /** @var string */
    public $control_request_uri;

    /** @var boolean */
    public $is_online;

    /** @var string */
    public $last_request_date;

    /** @var string */
    public $last_session_params;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'server',
        'primary' => 'server_id',
        'fields' => array(
            'server_address'            => array('type' => self::TYPE_STRING),
            'is_secure'                 => array('type' => self::TYPE_BOOL),
            'scan_request_uri'          => array('type' => self::TYPE_STRING),
            'control_request_uri'       => array('type' => self::TYPE_STRING),
            'is_online'                 => array('type' => self::TYPE_BOOL),
            'last_request_date'         => array('type' => self::TYPE_DATE),
            'last_session_params'    => array('type' => self::TYPE_NOTHING),
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

    public function getHttpServerAddress() {
        $protocol = $this->is_secure ? 'https://' : 'http://';
        $serverAddress = $this->server_address;

        return $protocol . $serverAddress . '/';
    }

    public function getLastSessionParams() {
        return unserialize($this->last_session_params);
    }

    public function isSessionExpired() {
        if (!$this->isLoaded())
            return true;

        $now = Tools::getTimestamp(false);
        $expirationTime = strtotime($this->last_request_date) + self::SESSION_LIFETIME;

        return $now > $expirationTime;
    }

    public function setSessionParams(array $sessionParams) {
        $this->last_session_params = serialize($sessionParams);

        return $this;
    }

    /**
     * Updates and saves session parameters.
     *
     * @param array $sesionParams
     * @return boolean true on success
     */
    public function updateSessionParams(array $sesionParams) {
        if (!$this->isLoaded())
            return false;

        $this->setSessionParams($sesionParams);
        $this->last_request_date = Tools::getMySQLDatetime();

        return $this->save();
    }

    /**
     *
     * Get control request URL for that server, if exists.
     *
     * @return string|boolean
     */
    public function getControlRequestUrl() {
         return $this->control_request_uri ? $this->getHttpServerAddress() . $this->control_request_uri : false;
    }

    /**
     *
     * Get scan request URL for that server.
     *
     * @return string
     */
    public function getScanRequestUrl() {
        return $this->getHttpServerAddress() . $this->scan_request_uri;
    }
}
