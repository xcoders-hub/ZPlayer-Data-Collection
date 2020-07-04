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

        $this->endpoint = $config->data_page->sources->movcloud;
    }

    public function video_locations($id){
        $url = $this->endpoint.$id;
        $response = $this->request($url);
        $content = $this->parse_json($response);

        $sources = [];

        foreach($content->data->sources as $video){

            $url = $video->file;

            $source = new Data();
            $source->quality = '';
            $source->url = $url;
            $source->server_name = 'Movcloud';

            $sources[] = $source;

        }

        return $sources;
    }
}

?>