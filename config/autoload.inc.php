<?php

require _VENDOR_DIR_ . '/autoload.php';

require_once(_CLASSES_DIR_.'/Autoload.php');
spl_autoload_register(array(Autoload::getInstance(), 'load'));
