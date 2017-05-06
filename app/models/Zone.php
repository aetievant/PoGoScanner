<?php

class Zone extends ObjectModel
{
    /** @var string */
    public $name;

    /** @var float */
    public $sw_latitude;

    /** @var float */
    public $sw_longitude;

    /** @var float */
    public $ne_latitude;

    /** @var float */
    public $ne_longitude;

    /** @var bool */
    public $is_enabled;

    /** @var string */
    public $lastscan_date;

    /** @var bool */
    public $has_returned_data;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'zone',
        'primary' => 'zone_id',
        'fields' => array(
            'name'                  => array('type' => self::TYPE_STRING),
            'sw_latitude'           => array('type' => self::TYPE_FLOAT),
            'sw_longitude'          => array('type' => self::TYPE_FLOAT),
            'ne_latitude'           => array('type' => self::TYPE_FLOAT),
            'ne_longitude'          => array('type' => self::TYPE_FLOAT),
            'is_enabled'            => array('type' => self::TYPE_BOOL),
            'lastscan_date'     => array('type' => self::TYPE_DATE),
            'has_returned_data'     => array('type' => self::TYPE_BOOL),
        ),
    );


    public static function getZonesToScan() {
        $zones = new Collection('Zone');
        $zones
            ->where('is_enabled', '=', 1)
            ->orderBy('lastscan_date')
            ->setPageSize(Configuration::get('maximum_zones_to_scan_by_execution'));

        return $zones->getAll();
    }

    public function updateScanDate($hasReturnedData = false) {
        $this->lastscan_date = Tools::getMySQLDatetime();
        $this->lastscan_timestamp = Tools::getTimestamp();
        $this->has_returned_data = $hasReturnedData;
        $this->update();
    }
}
