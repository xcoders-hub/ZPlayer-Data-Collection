<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../Shared/Logger.php';
require_once __DIR__.'/../Config/Config.php';

use Sources\WatchSeries\Server;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $server = new Server($Config,$logger);
    $request_json = file_get_contents("php://input");
    $reponse_json = $server->sources($request_json);
    header('Content-Type: application/json');
    echo $reponse_json;
}

?>