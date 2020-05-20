<?php

namespace Sources\WatchSeries\Servers;

use Data\Data;
use Shared\Request;
use Exception;

class Vidcloud extends Request {

    public $endpoint,$logger,$config;

    function __construct($config,$logger){
        $this->logger = $logger;
        $this->config = $config;
        $this->endpoint = 'https://vidcloud9.com/ajax.php?';
    }

    public function video_locations($id){
        $url = $this->endpoint.$id;

        $response = $this->request($url,'GET',array('x-requested-with' => 'XMLHttpRequest'));
        $content = $this->parse_json($response);

        $sources = [];

        foreach($content->source as $video){
            $source = new Data();
            $source->quality = '';
            $source->url = $video->file;
            $source->server_name = 'vidcloud';

            $sources[] = $source;
        }

        return array_unique($sources);
    }
}

?>