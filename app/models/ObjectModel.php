<?php
abstract class ObjectModel
{
    /**
     * List of field types
     */
    const TYPE_INT = 1;
    const TYPE_BOOL = 2;
    const TYPE_STRING = 3;
    const TYPE_FLOAT = 4;
    const TYPE_DATE = 5;
    const TYPE_HTML = 6;
    const TYPE_NOTHING = 7;

    /**
     * List of association types
     */
    const HAS_ONE = 1;
    const HAS_MANY = 2;

    /** @var integer Object id */
    public $id;

    /**
     * @var array Contain object definition
     */
    public static $definition = array();

    /**
     * @var array Contain current object definition
     */
    protected $def;

    /**
     * @var Db An instance of the db in order to avoid calling Db::getInstance() thousands of time
     */
    protected static $db = false;

    /**
     * @var boolean, enable possibility to define an id before adding object
     */
    public $force_id = false;

    /**
     * Build object
     *
     * @param int $id Existing object id in order to load object (optional)
     */
    public function __construct($id = null)
    {
        if (!ObjectModel::$db)
            ObjectModel::$db = Db::getInstance();

        $this->def = ObjectModel::getDefinition($this);

        if ($id)
        {
            // Load object from database if object id is present
            $cache_id = 'objectmodel_'.$this->def['classname'].'_'.(int)$id;
            if (!Cache::isStored($cache_id))
            {
                $sql = new DbQuery();
                $sql->from($this->def['table'], 'a');
                $sql->where('a.'.$this->def['primary'].' = '.(int)$id);

                if ($object_datas = ObjectModel::$db->getRow($sql))
                {
                    Cache::store($cache_id, $object_datas);
                }
            }
            else
                $object_datas = Cache::retrieve($cache_id);

            if ($object_datas)
            {
                $this->id = (int)$id;
                foreach ($object_datas as $key => $value)
                    if (array_key_exists($key, $this))
                        $this->{$key} = $value;

                $this->formatObjectFields();
            }
        }
    }

    /**
     * Prepare fields for ObjectModel class (add, update)
     * All fields are verified (pSQL, intval...)
     *
     * @return array All object fields
     */
    public function getFields($allow_null_values = false)
    {
        $fields = $this->formatFields($allow_null_values);

        // Ensure that we get something to insert
        if (!$fields && isset($this->id))
            $fields[$this->def['primary']] = $this->id;
        return $fields;
    }

    protected function formatObjectFields($allow_null_values = false) {
        foreach ($this->def['fields'] as $field => $data)
            $this->$field = ObjectModel::formatValue($this->$field, $data['type'], false, $allow_null_values);
    }

    /**
     * @return array
     */
    protected function formatFields($allow_null_values = false)
    {
        $fields = array();

        // Set primary key in fields
        if (isset($this->id))
            $fields[$this->def['primary']] = $this->id;

        foreach ($this->def['fields'] as $field => $data)
        {
            // Get field value
            $value = $this->$field;

            // Format field value
            $fields[$field] = ObjectModel::formatValue($value, $data['type'], false, $allow_null_values);
        }

        return $fields;
    }

    /**
     * Format a data
     *
     * @param mixed $value
     * @param int $type
     */
    public static function formatValue($value, $type, $with_quotes = false, $allow_null_value = false)
    {
        if ($allow_null_value && is_null($value))
            return null;

        switch ($type)
        {
            case self::TYPE_INT:
                return (int)$value;

            case self::TYPE_BOOL:
                return (int)$value;

            case self::TYPE_FLOAT:
                return (float)str_replace(',', '.', $value);

            case self::TYPE_DATE:
                if (!$value)
                    return '0000-00-00';

                if ($with_quotes)
                    return '\''.pSQL($value).'\'';
                return pSQL($value);

            case self::TYPE_HTML:
                if ($with_quotes)
                    return '\''.pSQL($value, true).'\'';
                return pSQL($value, true);

            case self::TYPE_NOTHING:
                return $value;

            case self::TYPE_STRING:
            default :
                if ($with_quotes)
                    return '\''.pSQL($value).'\'';
                return pSQL($value);
        }
    }

    /**
     * Save current object to database (add or update)
     *
     * @param bool $null_values
     * @param bool $autodate
     * @return boolean Insertion result
     */
    public function save($null_values = false, $autodate = true)
    {
        return (int)$this->id > 0 ? $this->update($null_values) : $this->add($autodate, $null_values);
    }

    /**
     * Add current object to database
     *
     * @param bool $null_values
     * @param bool $autodate
     * @return boolean Insertion result
     */
    public function add($autodate = true, $null_values = false)
    {
        if (!ObjectModel::$db)
            ObjectModel::$db = Db::getInstance();

        if (isset($this->id) && !$this->force_id)
            unset($this->id);

        // Automatically fill dates
        if ($autodate && property_exists($this, 'date_add'))
            $this->date_add = date('Y-m-d H:i:s');
        if ($autodate && property_exists($this, 'date_upd'))
            $this->date_upd = date('Y-m-d H:i:s');

        if (!$result = ObjectModel::$db->insert($this->def['table'], $this->getFields($null_values), $null_values))
            return false;

        // Get object id in database
        $this->id = ObjectModel::$db->Insert_ID();

        return $result;
    }

    /**
     * Duplicate current object to database
     *
     * @return new object
     */
    public function duplicateObject()
    {
        $definition = ObjectModel::getDefinition($this);

        $res = Db::getInstance()->getRow('
                    SELECT *
                    FROM `'.bqSQL($definition['table']).'`
                    WHERE `'.bqSQL($definition['primary']).'` = '.(int)$this->id
                );
        if (!$res)
            return false;
        unset($res[$definition['primary']]);
        foreach ($res as $field => &$value)
            if (isset($definition['fields'][$field]))
                $value = ObjectModel::formatValue($value, $definition['fields'][$field]['type'], false, true);

        if (!Db::getInstance()->insert($definition['table'], $res))
            return false;

        $object_id = Db::getInstance()->Insert_ID();

        $object_duplicated = new $definition['classname']((int)$object_id);

        return $object_duplicated;
    }

    /**
     * Update current object to database
     *
     * @param bool $null_values
     * @return boolean Update result
     */
    public function update($null_values = false)
    {
        if (!ObjectModel::$db)
            ObjectModel::$db = Db::getInstance();

        $this->clearCache();

        // Automatically fill dates
        if (array_key_exists('date_upd', $this))
            $this->date_upd = date('Y-m-d H:i:s');

        // Database update
        return ObjectModel::$db->update($this->def['table'], $this->getFields($null_values), '`'.pSQL($this->def['primary']).'` = '.(int)$this->id, 0, $null_values);
    }

    /**
     * Delete current object from database
     *
     * @return boolean Deletion result
     */
    public function delete()
    {
        if (!ObjectModel::$db)
            ObjectModel::$db = Db::getInstance();

        $this->clearCache();

        return ObjectModel::$db->delete($this->def['table'], '`'.pSQL($this->def['primary']).'` = '.(int)$this->id);
    }

    /**
     * Delete several objects from database
     *
     * @param array $selection
     * @return bool Deletion result
     */
    public function deleteSelection($selection)
    {
        $result = true;
        foreach ($selection as $id)
        {
            $this->id = (int)$id;
            $result = $result && $this->delete();
        }
        return $result;
    }

    public function clearCache($all = false)
    {
        if ($all)
            Cache::clean('objectmodel_'.$this->def['classname'].'_*');
        elseif ($this->id)
            Cache::clean('objectmodel_'.$this->def['classname'].'_'.(int)$this->id.'_*');
    }

    /**
     * Checks wether this instance has been loaded from database or not.
     *
     * @return bool
     */
    public function isLoaded() {
        return (bool) $this->id;
    }

    /**
     * Fill an object with given data. Data must be an array with this syntax: array(objProperty => value, objProperty2 => value, etc.)
     *
     * @param array $data
     */
    public function hydrate(array $data)
    {
        if (isset($data[$this->def['primary']]))
            $this->id = $data[$this->def['primary']];
        foreach ($data as $key => $value)
            if (array_key_exists($key, $this))
                $this->$key = $value;

        $this->formatObjectFields();
    }

    /**
     * Fill (hydrate) a list of objects in order to get a collection of these objects
     *
     * @param string $class Class of objects to hydrate
     * @param array $datas List of data (multi-dimensional array)
     * @return array
     */
    public static function hydrateCollection($class, array $datas)
    {
        if (!class_exists($class))
            throw new PoGoScannerException("Class '$class' not found");

        $collection = array();
        $rows = array();
        if ($datas)
        {
            $definition = ObjectModel::getDefinition($class);
            if (!array_key_exists($definition['primary'], $datas[0]))
                throw new PoGoScannerException("Identifier '{$definition['primary']}' not found for class '$class'");

            foreach ($datas as $row)
            {
                // Get object common properties
                $id = $row[$definition['primary']];
                if (!isset($rows[$id]))
                    $rows[$id] = $row;
            }
        }

        // Hydrate objects
        foreach ($rows as $row)
        {
            $obj = new $class;
            $obj->hydrate($row);
            $collection[$obj->id] = $obj;
        }
        return $collection;
    }

    /**
     * Get object definition
     *
     * @param string $class Name of object
     * @param string $field Name of field if we want the definition of one field only
     * @return array
     */
    public static function getDefinition($class, $field = null)
    {
        if (is_object($class))
            $class = get_class($class);

        if ($field === null)
            $cache_id = 'objectmodel_def_'.$class;

        if ($field !== null || !Cache::isStored($cache_id))
        {
            $reflection = new ReflectionClass($class);
            $definition = $reflection->getStaticPropertyValue('definition');

            $definition['classname'] = $class;

            if ($field)
                return isset($definition['fields'][$field]) ? $definition['fields'][$field] : null;

            Cache::store($cache_id, $definition);
            return $definition;
        }

        return Cache::retrieve($cache_id);
    }

}