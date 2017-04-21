<?php

class PokemonMove extends ObjectModel
{
    /** @var string */
    public $french_name;

    /** @var string */
    public $english_name;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'pokemon_move',
        'primary' => 'pokemon_move_id',
        'fields' => array(
            'french_name'   => array('type' => self::TYPE_STRING),
            'english_name'  => array('type' => self::TYPE_STRING),
        ),
    );

}

