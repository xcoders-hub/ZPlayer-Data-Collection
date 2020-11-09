<?php

namespace Sources\WatchSeries\Servers;

use Data\Data;
use Shared\Request;

class EasyLoad extends Request {

    public $endpoint,$logger,$config;

    function __construct($config,$logger){
        $this->logger = $logger;
        $this->config = $config;

        $this->endpoint = $config->data_page->sources->fembed;
    }

    public function video_locations($url){

        $response = $this->request($url);
        preg_match('/\("videolink"\)\.innerHTML\s*=\s*\"(.+)\"\s*;?/im',$response, $matches);

        $sources = [];

        if($matches){
            $video_url = $matches[1];

            $source = new Data();
            $source->quality = '';
            $source->url = "https:$video_url";
            $source->server_name = 'EasyLoad';

            $sources[] = $source;
        }

        return $sources;
    }
}

?>