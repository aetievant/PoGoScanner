<?php

class SpawnPoint extends ObjectModel
{
    const CACHE_KEY_AVAILABLE = 'SpawnPoint::getAllCurrentlyAvailable';

    const CACHE_KEY_SPAWN_POINTS_BY_ENCOUNTER_IDS = 'SpawnPoint::spawnpoints_by_encounter_ids';

    /** @var int */
    public $pokemon_id;

    /** @var string */
    public $encounter_id;

    /** @var int */
    public $zone_id;

    /** @var float */
    public $latitude;

    /** @var float */
    public $longitude;

    /** @var int */
    public $iv_attack;

    /** @var int */
    public $iv_defense;

    /** @var int */
    public $iv_stamina;

    /** @var string Spawn date */
    public $spawn_date;

    /** @var string Spawn date */
    public $expiration_date;

    /** @var int */
    public $pokemon_move_id_1;

    /** @var int */
    public $pokemon_move_id_2;

    /** @var int */
    public $pokemon_gender_id;

    /** @var string Spawn date */
    public $spawnpoint_id;

    /** @var boolean */
    public $is_notification_sent = 0;

        /** @var string */
    public $zone_name;

    /** @var string */
    public $pokemon_french_name;

    /** @var string */
    public $pokemon_english_name;

    /** @var string */
    public $pokemon_move_1_french_name;

    /** @var string */
    public $pokemon_move_1_english_name;

    /** @var string */
    public $pokemon_move_2_french_name;

    /** @var string */
    public $pokemon_move_2_english_name;

    /** @var string */
    public $pokemon_gender_french_name;

    /** @var string */
    public $pokemon_gender_english_name;

    /** @var string Object creation date */
    public $date_add;

    /** @var string Object last modification date */
    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'spawn_point',
        'primary' => 'spawn_point_id',
        'fields' => array(
            'pokemon_id'            => array('type' => self::TYPE_INT),
            'encounter_id'          => array('type' => self::TYPE_STRING),
            'zone_id'               => array('type' => self::TYPE_INT),
            'latitude'              => array('type' => self::TYPE_FLOAT),
            'longitude'             => array('type' => self::TYPE_FLOAT),
            'iv_attack'             => array('type' => self::TYPE_INT),
            'iv_defense'            => array('type' => self::TYPE_INT),
            'iv_stamina'            => array('type' => self::TYPE_INT),
            'spawn_date'            => array('type' => self::TYPE_DATE),
            'expiration_date'       => array('type' => self::TYPE_DATE),
            'pokemon_move_id_1'     => array('type' => self::TYPE_INT),
            'pokemon_move_id_2'     => array('type' => self::TYPE_INT),
            'pokemon_gender_id'     => array('type' => self::TYPE_INT),
            'spawnpoint_id'         => array('type' => self::TYPE_STRING),
            'is_notification_sent'  => array('type' => self::TYPE_BOOL),
            'date_add'              => array('type' => self::TYPE_DATE),
            'date_upd'              => array('type' => self::TYPE_DATE),
        ),
        'associations' => array(
            'pokemon'           => array('type' => self::HAS_ONE, 'field' => 'pokemon_id', 'object' => 'Pokemon'),
            'zone'              => array('type' => self::HAS_ONE, 'field' => 'zone_id', 'object' => 'Zone'),
            'pokemon_move_1'    => array('type' => self::HAS_ONE, 'field' => 'pokemon_move_id_1', 'foreign_field' => 'pokemon_move_id', 'object' => 'PokemonMove'),
            'pokemon_move_2'    => array('type' => self::HAS_ONE, 'field' => 'pokemon_move_id_2', 'foreign_field' => 'pokemon_move_id', 'object' => 'PokemonMove'),
            'pokemon_gender'    => array('type' => self::HAS_ONE, 'field' => 'pokemon_gender_id', 'object' => 'PokemonGender'),
        ),
    );

    protected static $_unicodeIvMapper = array(
        0   => '\u24EA',
        1   => '\u2460',
        2   => '\u2461',
        3   => '\u2462',
        4   => '\u2463',
        5   => '\u2464',
        6   => '\u2465',
        7   => '\u2466',
        8   => '\u2467',
        9   => '\u2468',
        10  => '\u2469',
        11  => '\u246A',
        12  => '\u246B',
        13  => '\u246C',
        14  => '\u246D',
        15  => '\u246E',
    );

    protected static function loadAllAvailableEntries($refresh = false) {
        if ($refresh || !Cache::isStored(self::CACHE_KEY_AVAILABLE)) {
            self::cleanOldEntries();

            $collection = new Collection('SpawnPoint');
            $collection->where('expiration_date', '>', Tools::getMySQLDatetime());

            $spawnPointsByEncounterIds = array();

            /* @var $spawnPoint SpawnPoint */
            foreach ($collection->getAll() as $spawnPoint) {
                $spawnPointsByEncounterIds[$spawnPoint->encounter_id] = &$spawnPoint;
            }

            Cache::store(self::CACHE_KEY_SPAWN_POINTS_BY_ENCOUNTER_IDS, $spawnPointsByEncounterIds);
            Cache::store(self::CACHE_KEY_AVAILABLE, $collection->getAll());
        }
    }

    public static function cleanOldEntries() {
        Db::getInstance()->delete(self::$definition['table'], 'expiration_date < NOW()');
    }

    public static function getByEncounterId($encounterId) {
        self::loadAllAvailableEntries();

        $encounterIds = Cache::retrieve(self::CACHE_KEY_SPAWN_POINTS_BY_ENCOUNTER_IDS);

        return isset($encounterIds[$encounterId]) ? $encounterIds[$encounterId] : false;
    }

    public static function getAllCurrentlyAvailable($refresh = false) {
        self::loadAllAvailableEntries($refresh);

        return Cache::retrieve(self::CACHE_KEY_AVAILABLE);
    }

    public static function getAllCurrentlyAvailableByEncounterId($refresh = false) {
        self::loadAllAvailableEntries($refresh);

        return Cache::retrieve(self::CACHE_KEY_SPAWN_POINTS_BY_ENCOUNTER_IDS);
    }

    public function getIvRate() {
        return Tools::calculateIvRate($this->iv_attack, $this->iv_defense, $this->iv_stamina);
    }

    public function getIvFormattedString($useUnicode = false) {
        return $useUnicode ?
            utf8_chr(self::$_unicodeIvMapper[$this->iv_attack]).utf8_chr(self::$_unicodeIvMapper[$this->iv_defense]).utf8_chr(self::$_unicodeIvMapper[$this->iv_stamina]) :
            sprintf('%s/%s/%s', $this->iv_attack, $this->iv_defense, $this->iv_stamina);
    }

    public function add($autodate = true, $null_values = false)
    {
        parent::add($autodate, $null_values);

        // Update cache
        $cache = Cache::retrieve(self::CACHE_KEY_AVAILABLE);
        $cacheByEncounterId = Cache::retrieve(self::CACHE_KEY_SPAWN_POINTS_BY_ENCOUNTER_IDS);

        $cache[$this->id] = $this;
        $cacheByEncounterId[$this->encounter_id] = $this;

        Cache::store(self::CACHE_KEY_SPAWN_POINTS_BY_ENCOUNTER_IDS, $cacheByEncounterId);
        Cache::store(self::CACHE_KEY_AVAILABLE, $cache);
    }

    /**
     * Return spanw points to notify to users
     *
     * @return Collection
     */
    public static function getUnnotifiedSpawnPoints() {
        self::cleanOldEntries();

        $collection = new Collection('SpawnPoint');
        $collection
            ->where('expiration_date', '>', Tools::getMySQLDatetime())
            ->where('is_notification_sent', '=', '0');

        foreach (self::$definition['associations'] as $name => $association) {
            $collection->join($name);
        }

        return $collection->getAll();
    }
}
