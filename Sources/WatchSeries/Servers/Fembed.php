<?php

namespace Sources\WatchSeries\Servers;

use Data\Data;
use Shared\Request;
use Exception;

class Fembed extends Request {

    public $endpoint,$logger,$config;

    function __construct($config,$logger){
        $this->logger = $logger;
        $this->config = $config;
        $this->endpoint = 'https://feurl.com/api/source/';
    }

    public function video_locations($id){
        $url = $this->endpoint.$id;
        $response = $this->request($url,'POST');
        $content = $this->parse_json($response);

        $sources = [];

        if ($content->success){
            foreach($content->data as $video){
                $source = new Data();
                $source->quality = $video->label;
                $source->url = $video->file;
                $source->server_name = 'fembed';

                $sources[] = $source;
            }
        }

        return $sources;
    }
}

?>