<?php

class NotifiedPerson extends ObjectModel
{
    const CACHE_KEY = 'NotifiedPerson::getAll';

    /** @var string */
    public $name;

    /** @var string */
    public $email;

    /** @var string */
    public $free_user_id;

    /** @var string */
    public $free_api_key;

    /** @var boolean */
    public $email_notify;

    /** @var boolean */
    public $free_sms_notify;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'notified_person',
        'primary' => 'notified_person_id',
        'fields' => array(
            'name'              => array('type' => self::TYPE_STRING),
            'email'             => array('type' => self::TYPE_STRING),
            'free_user_id'      => array('type' => self::TYPE_STRING),
            'free_api_key'      => array('type' => self::TYPE_STRING),
            'email_notify'      => array('type' => self::TYPE_BOOL),
            'free_sms_notify'   => array('type' => self::TYPE_BOOL),
        ),
    );

    public static function getAll() {
        if (!Cache::isStored(self::CACHE_KEY)) {
            $collection = new Collection('NotifiedPerson');
            Cache::store(self::CACHE_KEY, $collection->getAll());
        }

        return Cache::retrieve(self::CACHE_KEY);
    }
}
