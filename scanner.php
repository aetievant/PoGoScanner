<?php

require(dirname(__FILE__).'/config/config.inc.php');

if (!_MODE_DEV_ && !Tools::isPHPCLI())
    exit('Cannot execute this script for security reasons.');

if (Tools::isCurrentlyRunning())
    exit('An other instance of the script is running');

ExceptionHandler::initHandlers();

try {
    Tools::setIsRunning(true);
    Tools::expandServerLimitations();

    Scanner::scanAllZones();
    Notifier::sendNotifications();
} catch (PoGoScannerException $ex) {
    echo $ex->displayMessage();
}