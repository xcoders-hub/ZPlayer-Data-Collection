<?php

namespace Sources\WatchSeries\Servers;

use Data\Data;
use Shared\Request;
use Exception;

class MovCloud extends Request {

    public $endpoint,$logger,$config;

    function __construct($config,$logger){
        $this->logger = $logger;
        $this->config = $config;
        $this->endpoint = 'https://api.movcloud.net/stream/';
    }

    public function video_locations($id){
        $url = $this->endpoint.$id;
        $response = $this->request($url);
        $content = $this->parse_json($response);

        $sources = [];

        foreach($content->data->sources as $video){

            $source = new Data();
            $source->quality = '';
            $source->url = $video->file;
            $source->server_name = 'movcloud';

            $sources[] = $source;
        }

        return $sources;
    }
}

?>