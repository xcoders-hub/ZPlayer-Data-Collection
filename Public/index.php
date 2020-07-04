<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../Shared/Logger.php';

use Details\Information;
use Details\Recent;
use Sources\WatchSeries\Server;

use Shared\Config;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $request_json = file_get_contents("php://input");

        $request = (object)json_decode($request_json);
    
        $logger->debug('------- Incoming Request --------');
    
        $conf = new Config($logger);
        $Config = $conf->load_config($request->data->user_key);
    
        $search_type = strtolower($request->data->type);
        $content_type = strtolower($request->data->content_type);
    
        $logger->debug("------- Search Type: $search_type --------");
    
        if($search_type == 'search_sources'){
            $server = new Server($Config,$logger);
            $reponse_json = $server->sources($request);
        } elseif($search_type == 'search_released'){
            $released = new Recent($Config,$logger);
            $released->recent_content($content_type);
        } elseif($search_type == 'search_similar'){
            $content = new Information($Config,$logger);
            $content->similar();
        }   

    } catch(Exception $e){
        $reponse_json = '{"error": { "message": "'.$e->getMessage().'"} }';
    }

    header('Content-Type: application/json');

    echo $reponse_json;
}

?>