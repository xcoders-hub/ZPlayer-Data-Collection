<?php

namespace sources\WatchSeries;

use Data\Data;
use Shared\API;
use Sources\WatchSeries\Shared;

class Update extends Shared {

    public $config,$logger;

    function __construct($config,$logger)
    {
        $this->config = $config;
        $this->logger = $logger;

        $this->api = new API($logger);
    }


    public function list(){
        $url = $this->config->api->url . $this->config->api->sources->update;
        $this->logger->debug('Fetching New Update List');
        $response = $this->request($url);
        $content = $this->parse_json($response);

        return $content;
    }

    public function sources(){

        while(true){

            $response = $this->list();
            $content_list = $response->data;
            
    
            foreach($content_list as $data){
    
                $content = $data->content;
                $content_type = $data->content_type;
                $content_id = $data->content_id;
                $name = $content->name;
                $content_age = $data->content_age;

                $this->logger->debug("---------- Updating $name Sources Start --------------");
                $this->logger->debug("Source Created:$content_age  Hours Ago");
    
                $new_source = new Data();
                $new_source->content_id = $content_id;
                $new_source->content_type = $content_type;
    
                $this->send_sources($new_source,true);
    
                $content_url = $content->url;
    
                if(!$content_url){
                    //No URL Found, Get New Sources Instead OF Updating OLD
                } else {

                    $sources = $this->fetch_sources( $content_url );
                
                    $this->logger->debug('Found '.count($sources).' Sources');
    
                    $new_source->sources = $sources;
    
                    $new_source->content_url = $content_url;
    
                    $this->send_sources($new_source);

                }

                $this->logger->debug("---------- Updating $name Sources Complete --------------");
            }

        }

    }

}

?>