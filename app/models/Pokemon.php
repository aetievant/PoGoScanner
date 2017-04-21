<?php

class Pokemon extends ObjectModel
{
    /** @var string */
    public $french_name;

    /** @var string */
    public $english_name;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'pokemon',
        'primary' => 'pokemon_id',
        'fields' => array(
            'french_name'   => array('type' => self::TYPE_STRING),
            'english_name'  => array('type' => self::TYPE_STRING),
        ),
    );

}
