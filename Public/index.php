<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../Shared/Logger.php';

use Details\Information;
use Details\Recent;
use phpDocumentor\Reflection\DocBlock\Tags\Example;
use Sources\WatchSeries\Server;

use Shared\Config;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reponse_json = '{}';

    try {

        $request_json = file_get_contents("php://input");

        $request = (object)json_decode($request_json);
    
        $logger->debug('------- Incoming Request --------');
    
        $conf = new Config($logger);
        $data = $request->data;

        $Config = $conf->load_config($data->user_key);
    
        $search_type = strtolower($data->type);
        $content_type = strtolower($data->content_type);
    
        $logger->debug("------- Search Type: $search_type --------");
    
        if($search_type == 'search_sources'){
            $server = new Server($Config,$logger);
            $reponse_json = $server->sources($request);
        } elseif($search_type == 'search_recent'){
            
            if(property_exists($data,'return_location')){
                $return_location = $data->return_location;

                if (filter_var($return_location, FILTER_VALIDATE_URL) === FALSE) {
                    throw new Exception('Invalid Return Location Provided');
                }

                $Config->api->url = $return_location;
                $logger->notice('Data Return Location: '.$return_location);
            }

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