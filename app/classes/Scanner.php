<?php
// https://browse-tutorials.com/tutorial/php-memory-management
/*
 * Note: latest versions of Curl PHP class (>= 7.3.0) apparently fix memory leaks problems.
 * Do not forget to composer update the projet in order to get benefits from it.
 */

/**
 * Response example :
 *
 * object(stdClass)[22]
    public 'disappear_time' => int 1492203350000
    public 'encounter_id' => string 'MjA0MDE5ODI1NjY5NDMyMzg5MA==' (length=28)
    public 'gender' => int 1
    public 'height' => float 0.588756
    public 'individual_attack' => int 0
    public 'individual_defense' => int 9
    public 'individual_stamina' => int 9
    public 'last_modified' => int 1492201564000
    public 'latitude' => float 52.0524674352
    public 'longitude' => float 4.18647800706
    public 'move_1' => int 230
    public 'move_2' => int 57
    public 'pokemon_id' => int 158
    public 'pokemon_name' => string 'Kaiminus' (length=8)
    public 'pokemon_rarity' => string 'Peu Commun' (length=10)
    public 'pokemon_types' =>
      array (size=1)
        ...
    public 'spawnpoint_id' => string '47c5ae1fe25' (length=11)
    public 'weight' => float 8.62822
 *
 */
class Scanner
{
    /** @var Scanner */
    protected static $_instance;

    /** @var Server */
    protected $_currentServer;

    /** @var Curl\Curl */
    protected $_curl;

   /**
    * Get singleton
    *
    * @return Scanner
    */
   public static function getInstance() {

        if (is_null(self::$_instance)) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function __construct() {
        // Instantiate curl PHP class
        $this->_curl = new Curl\Curl;

        /** @todo: make that one dynamic. */
        $this->_currentServer = Server::getFirstAvailable();
    }

    public static function scanAllZones() {
        $zones = Zone::getZonesToScan();

        /* @var $zone Zone */
        foreach ($zones as $zone) {
            self::getInstance()->doScan($zone);

            gc_collect_cycles(); // Use that only if needed to force garbage collector.
            sleep(5); // Sleep for 5 sec to avoid spam requests
        }
    }

    protected function isCurrentServerAvailable() {
        return $this->_currentServer instanceof Server && $this->_currentServer->isLoaded();
    }

    protected function assignSessionParams() {
        if (!$this->isCurrentServerAvailable())
            return false;

        // Set user agent if available in configuration
        if ($userAgent = Configuration::get('request_user_agent'))
            $this->_curl->setUserAgent($userAgent);

        // Set request headers if available in configuration
        if ($requestHeaders = Configuration::get('request_headers')) {
            $requestHeaders = unserialize($requestHeaders);
            $this->_curl->setHeaders($requestHeaders);
            $this->_curl->setHeader('Referer', $this->_currentServer->getHttpServerAddress());
        }

        // configure accepted encoding responses
        $this->_curl->setOpt(CURLOPT_ENCODING, 'gzip, deflate, br');

        // Set cookies, including session cookies
        $sessionParams = $this->_currentServer->getLastSessionParams();

        if (!empty($sessionParams['cookies']))
            $this->_curl->setCookies($sessionParams['cookies']);
    }

    /**
     * Check control request for current server, if available.
     *
     * @return boolean
     */
    protected function doControlRequest() {
        if (!$this->isCurrentServerAvailable() || !$controlRequestUrl = $this->_currentServer->getControlRequestUrl())
            return true;

        $this->_curl->get($controlRequestUrl);

        if (!$this->_curl->error) {
            if (isset($this->_curl->response->status) && $this->_curl->response->status)
                return true;
        } else
            throw new PoGoScannerException('Error while doing control request: ' . $this->_curl->errorCode . ': ' . $this->_curl->errorMessage);
    }

    /**
     * Do a scan and fill spawn points for the specified zone
     *
     * @param Zone $zone
     */
    public function doScan(Zone $zone) {
        if (!$this->isCurrentServerAvailable())
            return false;

        // First check if there is an active session
        if ($this->_currentServer->isSessionExpired() || !$currentSessionParams = $this->_currentServer->getLastSessionParams())
            return false;

        $this->assignSessionParams($currentSessionParams);

        if (!$this->doControlRequest())
            return false;

        $this->_curl->get($this->_currentServer->getScanRequestUrl(), $this->getRequestParams($zone));
//        var_dump($this->_curl->url, $this->_curl->requestHeaders, $this->_curl->responseHeaders, $this->_curl->responseCookies, $this->_curl->response);

        if (!$this->_curl->error) {
            if (isset($this->_curl->response->pokemons)) {
                foreach ($this->_curl->response->pokemons as $pokemonEncounter) {
//                    var_dump($pokemonEncounter);
                    if (!SpawnPoint::isExpired($pokemonEncounter->disappear_time) && !SpawnPoint::getByEncounterId($pokemonEncounter->encounter_id)) {
                        $pokemonId = $pokemonEncounter->pokemon_id;

                        // Checks if Pokémon has been disallowed in notifiers
                        if (!Notifier::isPokemonAllowed($pokemonId))
                            continue;

                        $shouldNotify = Notifier::isPokemonNotified($pokemonId);
                        // $encounterIvRate = Tools::calculateIvRate($pokemonEncounter->individual_attack, $pokemonEncounter->individual_defense, $pokemonEncounter->individual_stamina);
                        // $minAlertIvRate = Notifier::getMinimalIvForPokemon($pokemonId);
                        // if ($encounterIvRate >= $minAlertIvRate) { // Create SpawnPoint if criterias are met
                        if ($shouldNotify) { // Create SpawnPoint if criterias are met
                            $spawnPoint = new SpawnPoint();
                            $spawnPoint->pokemon_id = $pokemonId;
                            $spawnPoint->encounter_id = $pokemonEncounter->encounter_id;
                            $spawnPoint->zone_id = $zone->id;
                            $spawnPoint->latitude = $pokemonEncounter->latitude;
                            $spawnPoint->longitude = $pokemonEncounter->longitude;
                            $spawnPoint->iv_attack = $pokemonEncounter->individual_attack;
                            $spawnPoint->iv_defense = $pokemonEncounter->individual_defense;
                            $spawnPoint->iv_stamina = $pokemonEncounter->individual_stamina;
                            $spawnPoint->spawn_date = Tools::getMySQLDatetime($pokemonEncounter->last_modified);
                            $spawnPoint->expiration_date = Tools::getMySQLDatetime($pokemonEncounter->disappear_time);
                            $spawnPoint->pokemon_move_id_1 = $pokemonEncounter->move_1;
                            $spawnPoint->pokemon_move_id_2 = $pokemonEncounter->move_2;
                            $spawnPoint->gender = $pokemonEncounter->gender;
                            $spawnPoint->spawnpoint_id = $pokemonEncounter->spawnpoint_id;
                            $spawnPoint->add(true, true);
                        }
                    }
                }
                $zone->updateScanDate(true);
            } else
                $zone->updateScanDate();

            // Update session params in all success cases, wether we got good Pokémon or not.
            $this->updateCurrentServerSessionParams();
        } else {
            throw new PoGoScannerException('Error while doing scan: ' . $this->_curl->errorCode . ': ' . $this->_curl->errorMessage);
        }
    }

    protected function getRequestParams(Zone $zone) {
        if ($this->isCurrentServerAvailable() && $sessionParams = $this->_currentServer->getLastSessionParams()) {
            if (!empty($sessionParams['last_request']))
                $lastRequestParams = $sessionParams['last_request'];
        }

        $now = Tools::getTimestamp(); // Current timestamp in ms

        $params = array();

        // nth request context: use last request timestamp if avaiable, current time otherwise
        if (isset($lastRequestParams))
            $params['timestamp'] = isset($lastRequestParams['timestamp']) ? $lastRequestParams['timestamp'] : $now;

        $params['pokemon'] = 'true';

        if (isset($lastRequestParams)) // nth request context
            $params['lastpokemon'] = 'true'; // Get the differential

        $params += array(
            'pokestops'     => 'false',
            'luredonly'     => 'false',
            'gyms'          => 'false',
            'scanned'       => 'false',
            'spawnpoints'   => 'false',
            'swLat'         => Tools::randomizeGpsCoordinate($zone->sw_latitude),
            'swLng'         => Tools::randomizeGpsCoordinate($zone->sw_longitude),
            'neLat'         => Tools::randomizeGpsCoordinate($zone->ne_latitude),
            'neLng'         => Tools::randomizeGpsCoordinate($zone->ne_longitude),
        );

        if (isset($lastRequestParams)) { // nth request context
            $params += array(
                'oSwLat'        => $lastRequestParams['oSwLat'], // "o" means "original"
                'oSwLng'        => $lastRequestParams['oSwLng'],
                'oNeLat'        => $lastRequestParams['oNeLat'],
                'oNeLng'        => $lastRequestParams['oNeLng'],
                'reids'         => '',
                'eids'          => '',
                '_'             => $lastRequestParams['_'] + 1, // last iteration number + 1
            );
        } else { // first request context
            $params += array(
                    'reids'         => '',
                    'eids'          => '',
                    '_'             => $now, // Initial timestamp for first iteration
            );
        }

        return $params;
    }

    /**
     * Update session params for current server, according to last cURL response.
     *
     * @param Zone $zone
     */
    protected function updateCurrentServerSessionParams() {
        if (!$this->isCurrentServerAvailable() || $this->_curl->error)
            return false;

        $lastSessionParams = $this->_currentServer->getLastSessionParams();
        $lastRequestParams = Tools::parseHttpRequest($this->_curl->url);

        // Refresh cookies
        if ($this->_curl->responseCookies)
            $lastSessionParams['cookies'] = array_merge($lastSessionParams['cookies'], $this->_curl->responseCookies);

        // Use response GPS coordinates, if available
        if (isset($this->_curl->response->oNeLat) && isset($this->_curl->response->oNeLng)
            && isset($this->_curl->response->oSwLat) && isset($this->_curl->response->oSwLng)) {
            $lastSessionParams['last_request'] = array(
                'oNeLat'    => $this->_curl->response->oNeLat,
                'oNeLng'    => $this->_curl->response->oNeLng,
                'oSwLat'    => $this->_curl->response->oSwLat,
                'oSwLng'    => $this->_curl->response->oSwLng,
            );
        } else { // Used request params otherwise
            $lastSessionParams['last_request'] = array(
                'oNeLat'    => $lastRequestParams['swLat'],
                'oNeLng'    => $lastRequestParams['neLng'],
                'oSwLat'    => $lastRequestParams['swLat'],
                'oSwLng'    => $lastRequestParams['swLng'],
            );
        }

        // Save last timestamp if available
        if (isset($this->_curl->response->timestamp))
            $lastSessionParams['last_request']['timestamp'] = $this->_curl->response->timestamp;
        elseif (isset($lastRequestParams['timestamp'])) // take the request timestamp otherwise
            $lastSessionParams['last_request']['timestamp'] = $lastRequestParams['timestamp'];

        // Refresh iteration number
        $lastSessionParams['last_request']['_'] = $lastRequestParams['_'];

        return $this->_currentServer->updateSessionParams($lastSessionParams);
    }
}
