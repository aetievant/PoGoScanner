<?php
// https://browse-tutorials.com/tutorial/php-memory-management

use Curl\Curl;

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
    protected static $_servers;

    public static function scanAllZones() {
        $zones = Zone::getZonesToScan();

        /* @var $zone Zone */
        foreach ($zones as $zone) {

            self::doScan($zone);
            gc_collect_cycles(); // Use that only if needed to force garbage collector.
        }
    }

    /**
     * Do a scan and fill spawn points for the specified zone
     *
     * @param Zone $zone
     */
    public static function doScan(Zone $zone) {
        $server = Server::getFirstAvailable();

        $curl = new Curl();
        $curl->get($server->getRequestUri(), self::getRequestParams($zone));

        if (!$curl->error) {
            if (isset($curl->response->pokemons)) {
                foreach ($curl->response->pokemons as $pokemonEncounter) {
//                    var_dump($pokemonEncounter);
                    if (!self::isExpired($pokemonEncounter->disappear_time) && !SpawnPoint::getByEncounterId($pokemonEncounter->encounter_id)) {
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

            // Try to free memory
            $curl = null;
            unset($curl);
        } else {
            throw new PoGoScannerException('Error: ' . $curl->errorCode . ': ' . $curl->errorMessage);
        }
    }

    protected static function getRequestParams(Zone $zone) {
        return array(
            //    'timestamp'     => $now, // Timestamp
                'pokemon'       => 'true',
                'lastpokemon'   => 'true',
                'pokestops'     => 'false',
                'luredonly'     => 'false',
                'gyms'          => 'false',
                'scanned'       => 'false',
                'spawnpoints'   => 'false',
                'swLat'         => $zone->sw_latitude,
                'swLng'         => $zone->sw_longitude,
                'neLat'         => $zone->ne_latitude,
                'neLng'         => $zone->ne_longitude,

            //    'oSwLat'         => $zone->sw_latitude, // o probably means "original"
            //    'oSwLng'         => $zone->sw_longitude,
            //    'oNeLat'         => $zone->ne_latitude,
            //    'oNeLng'         => $zone->ne_longitude,

                'reids'         => '',
                'eids'          => '',
            //    '_'             => $now, // Timestamp + nombre d'itération
        );
    }

    protected static function isExpired($timestamp) {
        return Tools::getTimestamp() > $timestamp;
    }
}
