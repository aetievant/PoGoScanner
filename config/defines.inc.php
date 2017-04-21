<?php

/* Debug only */
if (!defined('_MODE_DEV_')) {
	define('_MODE_DEV_', false);
}
/* Compatibility warning */
if (_MODE_DEV_ === true) {
    @ini_set('display_errors', 'on');
    @error_reporting(E_ALL | E_STRICT);
    define('_DEBUG_SQL_', true);
} else {
    @ini_set('display_errors', 'off');
    define('_DEBUG_SQL_', false);
}

/* Directories */
if (!defined('_ROOT_DIR_')) {
    define('_ROOT_DIR_', realpath(__DIR__.'/..'));
}

if (!defined('_APP_DIR_')) {
    define('_APP_DIR_', _ROOT_DIR_.'/app');
}

if (!defined('_LIB_DIR_')) {
    define('_LIB_DIR_', _ROOT_DIR_.'/lib');
}

if (!defined('_VENDOR_DIR_')) {
    define('_VENDOR_DIR_', _ROOT_DIR_.'/vendor');
}

if (!defined('_CLASSES_DIR_')) {
    define('_CLASSES_DIR_', _APP_DIR_.'/classes');
}

if (!defined('_MODELS_DIR_')) {
    define('_MODELS_DIR_', _LIB_DIR_.'/models');
}