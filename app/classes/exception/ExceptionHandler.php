<?php

class ExceptionHandler
{
    public static function initHandlers() {
        register_shutdown_function(array('ExceptionHandler', 'shutdownHandler'));
    }

    public static function shutdownHandler() {
        Tools::setIsRunning(false);

    }
}
