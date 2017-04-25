<?php

use Curl\Curl;

/**
 * That class defines which Pokémon are meant to be notified to users.
 */
class Notifier extends ObjectModel
{
    const CACHE_KEY_NOTIFIERS = 'Notifier::notifiers';
    const CACHE_KEY_POKEMON_IDS = 'Notifier::pokemon_ids';

    /** @var int */
    public $pokemon_id;

    /** @var float */
    public $min_iv;

    /** @var bool */
    public $is_disallowed = false;

    /** @var bool */
    public $is_important = false;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'notifier',
        'primary' => 'pokemon_id',
        'fields' => array(
            'pokemon_id'    => array('type' => self::TYPE_INT),
            'min_iv'        => array('type' => self::TYPE_FLOAT),
            'is_disallowed' => array('type' => self::TYPE_BOOL),
            'is_important' => array('type' => self::TYPE_BOOL),
        ),
        'associations' => array(
            'pokemon' => array('type' => self::HAS_ONE, 'field' => 'pokemon_id', 'object' => 'Pokemon'),
        ),
    );

    public static function getAll() {
        if (!Cache::isStored(self::CACHE_KEY_NOTIFIERS)) {
            $collection = new Collection('Notifier');
            Cache::store(self::CACHE_KEY_NOTIFIERS, $collection->getAll());
        }

        return Cache::retrieve(self::CACHE_KEY_NOTIFIERS);
    }

    /**
     * Get minimal wanted IV for a particular pokemon. Fall-back to default
     * configuration if notifier does not exist.
     *
     * @deprecated since API no longer return IVs
     * @param int $pokemonId
     * @return float
     */
    public static function getMinimalIvForPokemon($pokemonId) {
        $notifiers = self::getAll();
        $defaultMinIv = Configuration::get('default_min_iv_rate');

        return (float) (isset($notifiers[$pokemonId]) ? $notifiers[$pokemonId]->min_iv : $defaultMinIv);
    }

    /**
     * Indicates wether a Pokémon should be notified or not.
     *
     * @param int $pokemonId
     * @return bool
     */
    public static function isPokemonNotified($pokemonId) {
        $notifiers = self::getAll();

        return isset($notifiers[$pokemonId]) && $notifiers[$pokemonId]->isAllowed();
    }

    /**
     * Indicates if a Pokémon is allowed to be notified.
     *
     * @deprecated since API no longer return IVs
     * @param int $pokemonId
     * @return bool
     */
    public static function isPokemonAllowed($pokemonId) {
        $notifiers = self::getAll();

        return (isset($notifiers[$pokemonId]) ? $notifiers[$pokemonId]->isAllowed() : true);
    }

    /**
     * Indicates if a Pokémon has been strictly disallowed or not.
     *
     * @return bool
     */
    public function isAllowed() {
        return ! ((bool) $this->is_disallowed);
    }

    public static function sendNotifications() {
        $treatedSpawnPointIds = array();
        $notifications = SpawnPoint::getUnnotifiedSpawnPoints();

        if (!$notifications->count())
            return;

        /* @var $notification SpawnPoint */
        foreach ($notifications as $notification) {
            $hasError = false;
            $hasError |= !self::sendEmailNotification($notification);

            if ($notification->isImportant())
                $hasError |= !self::sendFreeSmsNotification($notification);

            if (!$hasError)
                $treatedSpawnPointIds[] = $notification->id;
        }

        if ($treatedSpawnPointIds)
            Db::getInstance()->update(SpawnPoint::$definition['table'], array('is_notification_sent' => 1), SpawnPoint::$definition['primary'].' IN ('.implode(',', $treatedSpawnPointIds).')');
    }

    /**
     * Send email notifications
     *
     * @todo
     * @param SpawnPoint $notification
     * @return bool true if all is okay, false otherwise
     */
    protected static function sendEmailNotification(SpawnPoint $notification) {
        return true;
    }

    /**
     * Send SMS notifications via Free API.
     *
     * @param SpawnPoint $notification
     * @return bool true if all is okay, false otherwise
     */
    protected static function sendFreeSmsNotification(SpawnPoint $notification) {
        $hasErrors = false;

        $users = NotifiedPerson::getAll();
        $freeApiRequestUrl = Configuration::get('free_sms_api_request_url');

        $curl = new Curl();

        $vars = array(
            'zone_id'                       => $notification->zone_id,
            'zone_name'                     => $notification->zone_name,
            'pokemon_id'                    => $notification->pokemon_id,
            'pokemon_french_name'           => $notification->pokemon_french_name,
            'pokemon_english_name'          => $notification->pokemon_english_name,
            'pokemon_move_1_french_name'    => $notification->pokemon_move_1_french_name,
            'pokemon_move_2_french_name'    => $notification->pokemon_move_2_french_name,
            'latitude'                      => $notification->latitude,
            'longitude'                     => $notification->longitude,
            'iv_rate'                       => $notification->getIvRate(),
            'iv'                            => $notification->getIvFormattedString(),
            'expiration_date'               => Tools::getHumanReadableDate($notification->expiration_date)
        );

        $smsText = self::getFormattedSms($vars);

        /* @var $user NotifiedPerson */
        foreach ($users as $user) {
            if (!$user->free_sms_notify)
                continue;

            $freeApiParams = array(
                'user' => $user->free_user_id,
                'pass' => $user->free_api_key,
                'msg'  => $smsText
            );

            $curl->get($freeApiRequestUrl, $freeApiParams);

            $hasErrors |= $curl->error;
        }

        return !$hasErrors;
    }

    protected static function getFormattedSms($vars) {
        $template = Configuration::get('sms_template');

        $pattern = '/{%(\w+)%}/';
        $callback = function($matches) use($vars) {
            return isset($vars[$matches[1]]) ? $vars[$matches[1]] : $matches[0];
        };

        return preg_replace_callback($pattern, $callback, $template);
    }
}