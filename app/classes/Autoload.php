<?php
class Autoload
{

    /**
     * @var Autoload
     */
    protected static $instance;

    /**
     * @var string Root directory
     */
    protected $root_dir;

    /**
     *  @var array array('classname' => 'path/to/class')
     */
    public $index = array();


    protected function __construct()
    {
        $this->root_dir = _APP_DIR_.'/';

        if (@filemtime($file) && is_readable($file)) {
            $this->index = include($file);
        } else {
            $this->generateIndex();
        }
    }

    /**
     * Get instance of autoload (singleton)
     *
     * @return Autoload
     */
    public static function getInstance()
    {
        if (!Autoload::$instance) {
            Autoload::$instance = new Autoload();
        }

        return Autoload::$instance;
    }

    /**
     * Retrieve informations about a class in classes index and load it
     *
     * @param string $classname
     */
    public function load($classname)
    {
        // load index if not loaded yet
        if (!$this->index)
            $this->generateIndex();

        // Call directly ProductCore, ShopCore class
        if (isset($this->index[$classname])) {
            require_once($this->root_dir.$this->index[$classname]);
        }
    }

    /**
     * Generate classes index
     */
    public function generateIndex()
    {
        $this->index = array_merge(
            $this->getClassesFromDir('classes'),
            $this->getClassesFromDir('models')
        );
    }

    /**
     * Retrieve recursively all classes in a directory and its subdirectories
     *
     * @param string $path Relativ path from root to the directory
     * @return array
     */
    protected function getClassesFromDir($path)
    {
        $classes = array();
        $path = rtrim($path, '/').'/';
        $scanDir = $this->root_dir.$path;

        foreach (scandir($scanDir) as $file) {
            if ($file[0] != '.') {
                if (is_dir($scanDir.$file)) {
                    $classes = array_merge($classes, $this->getClassesFromDir($path.$file));
                } elseif (substr($file, -4) == '.php') {

                    $className = basename($file, '.php');
                    $classes[$className] = $path.$file;
                }
            }
        }

        return $classes;
    }

    public function getClassPath($classname)
    {
        return (isset($this->index[$classname]) && isset($this->index[$classname]['path'])) ? $this->index[$classname]['path'] : null;
    }

    private function normalizeDirectory($directory)
    {
        return rtrim($directory, '/\\').DIRECTORY_SEPARATOR;
    }
}

