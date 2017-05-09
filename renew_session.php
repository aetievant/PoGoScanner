<?php

require(dirname(__FILE__).'/config/config.inc.php');
use Curl\Curl;

try {
    $server = Server::getFirstAvailable();

    /**
     * Handles force params in PHP CLI, e.g:
     * php renew_session.php "force=1"
     */
    if (Tools::isPHPCLI())
        Tools::argvToGET($argc, $argv);

    if (!Tools::getValue('force') && !$server->isSessionExpired())
        exit('not changing');

    $curl = new Curl();

    // Set user agent if available in configuration
    if ($userAgent = Configuration::get('request_user_agent'))
        $curl->setUserAgent($userAgent);

    // Set some traditional browser headers
    $requestHeaders = array(
        'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language'           => 'fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3',
        'Connection'                => 'keep-alive',
        'Upgrade-Insecure-Requests' => '1',
    );

    $curl->setHeaders($requestHeaders);
    $curl->setOpt(CURLOPT_ENCODING, 'gzip, deflate, br');

    $curl->get($server->getHttpServerAddress());

//    var_dump($curl->url, $curl->requestHeaders, $curl->responseHeaders, $curl->responseCookies, $curl->response);

    if (!$curl->error) {
        $server->updateSessionParams(array(
            'cookies' => $curl->responseCookies
        ));
    } else {
        throw new PoGoScannerException('Error: ' . $curl->errorCode . ': ' . $curl->errorMessage);
    }
} catch (PoGoScannerException $ex) {
    echo $ex->displayMessage();
}
