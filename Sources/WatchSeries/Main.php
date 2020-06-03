<?php

namespace Sources\WatchSeries;

use Data\Data;
use Shared\API;
use Sources\WatchSeries\Search;
use Sources\WatchSeries\Shared;

class Main extends Shared {

    public $config,$logger;

    function __construct($config,$logger)
    {
        $this->config = $config;
        $this->logger = $logger;

        $this->api = new API($logger);
    }

    public function search($content_type){

        $this->logger->debug('----------- Start ------------');

        while(true){
            
            $path = $this->config->api->sources->list->$content_type;

            $details = $this->details($path);

            if($details->data){

                if($content_type == 'series'){
                    $this->logger->notice("Series Sources Found");
                    $this->process_series($details,$content_type);
                } else {
                    $this->logger->notice("Movies Sources Found");
                    $this->process_movies($details);
                }
                
            } else {
                $this->logger->notice("No More Data Found");
            }
            
        }

        $this->logger->debug('---------- Completed ---------');
    }

}

?>